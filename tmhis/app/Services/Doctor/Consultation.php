<?php
// Doctor/models/Consultation.php

class Consultation
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function startConsultation(int $appointmentId, int $doctorId): bool
    {
        try {
            // Update appointment status
            $stmtApp = $this->db->prepare("
                UPDATE appointments 
                SET Status = 'In Consultation', UpdatedAt = NOW() 
                WHERE AppointmentID = :appId
            ");
            $stmtApp->execute([':appId' => $appointmentId]);

            // Update queue status
            try {
                $stmtQueue = $this->db->prepare("
                    UPDATE patient_queue 
                    SET QueueStatus = 'In Consultation', 
                        CalledAt = COALESCE(CalledAt, NOW()), 
                        ConsultationStartedAt = NOW()
                    WHERE AppointmentID = :appId
                ");
                $stmtQueue->execute([':appId' => $appointmentId]);
            } catch (\Throwable $qe) {
            }

            // Update doctor status to Busy
            try {
                $stmtDoc = $this->db->prepare("UPDATE doctors SET Status = 'Busy' WHERE DoctorID = :docId");
                $stmtDoc->execute([':docId' => $doctorId]);
            } catch (\Throwable $de) {
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function completeConsultation(int $appointmentId, int $doctorId, ?string $summaryNotes = null, string $disposition = 'OUTPATIENT', array $admissionData = []): bool
    {
        try {
            $disposition = strtoupper(trim($disposition));
            if (!in_array($disposition, ['OUTPATIENT', 'ADMIT', 'REFER', 'FOLLOW-UP', 'DISCHARGE'])) {
                $disposition = 'OUTPATIENT';
            }

            // Update appointment status to Completed
            $stmtApp = $this->db->prepare("
                UPDATE appointments 
                SET Status = 'Completed', Notes = COALESCE(:notes, Notes), UpdatedAt = NOW() 
                WHERE AppointmentID = :appId
            ");
            $stmtApp->execute([
                ':notes' => $summaryNotes,
                ':appId' => $appointmentId
            ]);

            // Fetch PatientID for clinical routing
            $stmtPat = $this->db->prepare("SELECT PatientID FROM appointments WHERE AppointmentID = :appId LIMIT 1");
            $stmtPat->execute([':appId' => $appointmentId]);
            $patientId = (int) $stmtPat->fetchColumn();

            $admissionId = null;

            if ($disposition === 'ADMIT') {
                // Requirement 17: If doctor explicitly selects ADMIT PATIENT, create inpatient admission record
                $admNum = 'ADM-' . date('Ymd') . '-' . str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT);
                $ward = $admissionData['ward'] ?? 'Medical Ward';
                $room = $admissionData['room_number'] ?? $admissionData['room'] ?? 'Room 201';
                $bed = $admissionData['bed_number'] ?? $admissionData['bed'] ?? 'Bed-01';
                $reason = $admissionData['admission_reason'] ?? $admissionData['reason'] ?? ($summaryNotes ?: 'Admitted for inpatient medical care and monitoring');

                try {
                    $stmtAdm = $this->db->prepare("
                        INSERT INTO admissions (
                            AdmissionNumber, PatientID, DoctorID, AppointmentID, Ward, RoomNumber, BedNumber,
                            AdmissionReason, AdmissionDate, Status, AdmittedBy, AdmittedByName, created_at, updated_at
                        ) VALUES (
                            :adm_num, :pid, :doc_id, :aid, :ward, :room, :bed,
                            :reason, NOW(), 'Admitted', :admitted_by, :admitted_by_name, NOW(), NOW()
                        )
                    ");
                    $stmtAdm->execute([
                        ':adm_num' => $admNum,
                        ':pid' => $patientId,
                        ':doc_id' => $doctorId,
                        ':aid' => $appointmentId,
                        ':ward' => $ward,
                        ':room' => $room,
                        ':bed' => $bed,
                        ':reason' => $reason,
                        ':admitted_by' => auth()->id() ?? $doctorId,
                        ':admitted_by_name' => auth()->user() ? auth()->user()->FullName : 'Attending Physician'
                    ]);
                    $admissionId = (int) $this->db->lastInsertId();
                } catch (\Throwable $admErr) {
                }

                // Update patient category to Inpatient
                try {
                    $stmtCat = $this->db->prepare("UPDATE patients SET PatientCategory = 'Inpatient', UpdatedAt = NOW() WHERE PatientID = :pid");
                    $stmtCat->execute([':pid' => $patientId]);
                } catch (\Throwable $catErr) {
                }

                // Next queue status routes patient directly into Inpatient Nurse Station
                $nextQueueStatus = 'Admitted - Inpatient';

                try {
                    $stmtAudit = $this->db->prepare("
                        INSERT INTO system_audit_logs (UserID, UserName, UserRole, Action, Module, RecordID, Details, IPAddress, CreatedAt)
                        VALUES (:uid, 'doctor', 'Doctor', 'ADMISSION_ORDERED', 'Doctor Consultation', :rec, :det, '127.0.0.1', NOW())
                    ");
                    $stmtAudit->execute([
                        ':uid' => auth()->id() ?? $doctorId,
                        ':rec' => $admissionId ?: $patientId,
                        ':det' => "Patient #$patientId admitted by Doctor #$doctorId to $ward, $room, $bed. Admission No: $admNum"
                    ]);
                } catch (\Throwable $ae) {
                }
            } elseif ($disposition === 'REFER') {
                // Doctor ordered patient referral (Internal specialist endorsement or External hospital transfer)
                $nextQueueStatus = 'Referred / External Transfer';
                try {
                    $stmtAudit = $this->db->prepare("
                        INSERT INTO system_audit_logs (UserID, UserName, UserRole, Action, Module, RecordID, Details, IPAddress, CreatedAt)
                        VALUES (:uid, 'doctor', 'Doctor', 'REFERRAL_ORDERED', 'Doctor Consultation', :rec, :det, '127.0.0.1', NOW())
                    ");
                    $stmtAudit->execute([
                        ':uid' => auth()->id() ?? $doctorId,
                        ':rec' => $patientId,
                        ':det' => "Doctor #$doctorId completed consultation with referral disposition. Routed to $nextQueueStatus."
                    ]);
                } catch (\Throwable $ae) {
                }
            } else {
                // Outpatient or Discharge - route through lab/pharmacy/billing
                $nextQueueStatus = 'For Billing';
                if ($patientId > 0) {
                    $hasLab = false;
                    $hasRx = false;
                    try {
                        $stmtLab = $this->db->prepare("
                            SELECT COUNT(*) FROM laboratory_requests 
                            WHERE PatientID = :pid AND Status IN ('Pending', 'Ordered', 'Received')
                        ");
                        $stmtLab->execute([':pid' => $patientId]);
                        $hasLab = (int) $stmtLab->fetchColumn() > 0;
                    } catch (\Throwable $le) {
                    }

                    try {
                        $stmtRx = $this->db->prepare("
                            SELECT COUNT(*) FROM prescriptions 
                            WHERE PatientID = :pid AND Status = 'Active'
                        ");
                        $stmtRx->execute([':pid' => $patientId]);
                        $hasRx = (int) $stmtRx->fetchColumn() > 0;
                    } catch (\Throwable $re) {
                    }

                    if ($hasLab) {
                        $nextQueueStatus = 'Laboratory';
                    } elseif ($hasRx) {
                        $nextQueueStatus = 'Pharmacy';
                    } else {
                        $nextQueueStatus = 'For Billing';
                    }
                }

                try {
                    $stmtAudit = $this->db->prepare("
                        INSERT INTO system_audit_logs (UserID, UserName, UserRole, Action, Module, RecordID, Details, IPAddress, CreatedAt)
                        VALUES (:uid, 'doctor', 'Doctor', 'CONSULTATION_OUTPATIENT', 'Doctor Consultation', :rec, :det, '127.0.0.1', NOW())
                    ");
                    $stmtAudit->execute([
                        ':uid' => auth()->id() ?? $doctorId,
                        ':rec' => $patientId,
                        ':det' => "Doctor #$doctorId completed consultation with disposition: $disposition. Routed to $nextQueueStatus."
                    ]);
                } catch (\Throwable $ae) {
                }
            }

            // Update queue status according to clinical routing
            try {
                $stmtQueue = $this->db->prepare("
                    UPDATE patient_queue 
                    SET QueueStatus = :nextStatus, CompletedAt = NOW()
                    WHERE AppointmentID = :appId
                ");
                $stmtQueue->execute([
                    ':nextStatus' => $nextQueueStatus,
                    ':appId' => $appointmentId
                ]);
            } catch (\Throwable $qe) {
            }

            // Set doctor back to Available
            try {
                $stmtDoc = $this->db->prepare("UPDATE doctors SET Status = 'Available' WHERE DoctorID = :docId");
                $stmtDoc->execute([':docId' => $doctorId]);
            } catch (\Throwable $de) {
            }

            // Ensure consultation note exists in central database and record Disposition and AdmissionID
            try {
                $stmtCheckNote = $this->db->prepare("SELECT NoteID FROM consultation_notes WHERE AppointmentID = :appId LIMIT 1");
                $stmtCheckNote->execute([':appId' => $appointmentId]);
                $existingNoteId = $stmtCheckNote->fetchColumn();

                if ($existingNoteId) {
                    try {
                        $stmtUpdNote = $this->db->prepare("
                            UPDATE consultation_notes 
                            SET Disposition = :disp, AdmissionID = :adm_id, UpdatedAt = NOW() 
                            WHERE NoteID = :nid
                        ");
                        $stmtUpdNote->execute([
                            ':disp' => $disposition,
                            ':adm_id' => $admissionId,
                            ':nid' => $existingNoteId
                        ]);
                    } catch (\Throwable $une) {
                        $stmtUpdFallback = $this->db->prepare("UPDATE consultation_notes SET UpdatedAt = NOW() WHERE NoteID = :nid");
                        $stmtUpdFallback->execute([':nid' => $existingNoteId]);
                    }
                } else if ($patientId > 0) {
                    $noteText = $summaryNotes ?: 'Clinical consultation completed and archived.';
                    try {
                        $stmtInsNote = $this->db->prepare("
                            INSERT INTO consultation_notes (
                                PatientID, DoctorID, AppointmentID, Subjective, Objective,
                                Assessment, Plan, ClinicalNotes, Disposition, AdmissionID, CreatedAt
                            ) VALUES (
                                :pid, :doc_id, :aid, 'General Consultation', 'Clinical examination conducted',
                                'Consultation completed', :plan, :notes, :disp, :adm_id, NOW()
                            )
                        ");
                        $stmtInsNote->execute([
                            ':pid' => $patientId,
                            ':doc_id' => $doctorId,
                            ':aid' => $appointmentId,
                            ':plan' => $noteText,
                            ':notes' => $noteText,
                            ':disp' => $disposition,
                            ':adm_id' => $admissionId
                        ]);
                    } catch (\Throwable $ine) {
                        $stmtInsFallback = $this->db->prepare("
                            INSERT INTO consultation_notes (
                                PatientID, DoctorID, AppointmentID, Subjective, Objective,
                                Assessment, Plan, ClinicalNotes, CreatedAt
                            ) VALUES (
                                :pid, :doc_id, :aid, 'General Consultation', 'Clinical examination conducted',
                                'Consultation completed', :plan, :notes, NOW()
                            )
                        ");
                        $stmtInsFallback->execute([
                            ':pid' => $patientId,
                            ':doc_id' => $doctorId,
                            ':aid' => $appointmentId,
                            ':plan' => $noteText,
                            ':notes' => $noteText
                        ]);
                    }
                }
            } catch (\Throwable $ne) {
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getActiveConsultation(int $doctorId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, p.PatientCode, p.FirstName, p.LastName, p.Age, p.Gender, p.ContactNumber, p.BloodType,
                   pq.QueueNumber, pq.QueueStatus, pq.ConsultationStartedAt,
                   ca.BodySystemID, bs.SystemName, c.ComplaintDescription
            FROM appointments a
            JOIN patients p ON a.PatientID = p.PatientID
            LEFT JOIN patient_queue pq ON a.AppointmentID = pq.AppointmentID
            LEFT JOIN complaints c ON p.PatientID = c.PatientID
            LEFT JOIN complaint_analysis ca ON c.ComplaintID = ca.ComplaintID
            LEFT JOIN body_systems bs ON ca.BodySystemID = bs.BodySystemID
            WHERE a.DoctorID = :docId 
            AND (a.Status = 'In Consultation' OR pq.QueueStatus = 'In Consultation')
            ORDER BY a.UpdatedAt DESC
            LIMIT 1
        ");
        $stmt->execute([':docId' => $doctorId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
