<?php
// Doctor/models/Referral.php

class Referral
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function generateCode(): string
    {
        $year = date('Y');
        $stmt = $this->db->query("SELECT MAX(ReferralID) AS max_id FROM referrals");
        $next = ((int)($stmt->fetchColumn() ?: 0)) + 1;
        return sprintf("REF-%s-%04d", $year, $next);
    }

    public function create(array $data): int
    {
        $code = $data['referral_code'] ?? $this->generateCode();
        $type = $data['referral_type'] ?? 'Internal';

        $stmt = $this->db->prepare("
            INSERT INTO referrals (
                ReferralCode, ReferralType, PatientID, ReferringDoctorID, ReferringDepartment,
                TargetSpecialtyID, TargetDoctorID, ReceivingHospital, HospitalAddress,
                RequestedService, Reason, ClinicalSummary, Priority, Urgency, Status, TrackingStatus, ResponseNotes
            ) VALUES (
                :code, :ref_type, :patient_id, :ref_doc_id, :ref_dept,
                :target_spec_id, :target_doc_id, :receiving_hospital, :hospital_address,
                :requested_service, :reason, :summary, :priority, :urgency, :status, :tracking_status, :notes
            )
        ");

        $stmt->execute([
            ':code'               => $code,
            ':ref_type'           => $type,
            ':patient_id'         => $data['patient_id'],
            ':ref_doc_id'         => $data['referring_doctor_id'],
            ':ref_dept'           => $data['referring_department'] ?? 'General Medicine',
            ':target_spec_id'     => !empty($data['target_specialty_id']) ? $data['target_specialty_id'] : null,
            ':target_doc_id'      => !empty($data['target_doctor_id']) ? $data['target_doctor_id'] : null,
            ':receiving_hospital' => $data['receiving_hospital'] ?? null,
            ':hospital_address'   => $data['hospital_address'] ?? null,
            ':requested_service'  => $data['requested_service'] ?? null,
            ':reason'             => $data['reason'],
            ':summary'            => $data['clinical_summary'] ?? null,
            ':priority'           => $data['priority'] ?? 'Routine',
            ':urgency'            => $data['urgency'] ?? ($data['priority'] ?? 'Routine'),
            ':status'             => $data['status'] ?? 'Pending',
            ':tracking_status'    => $data['tracking_status'] ?? ($type === 'External' ? 'Sent' : 'Created'),
            ':notes'              => $data['response_notes'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $referralId, string $status, ?string $responseNotes = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE referrals 
            SET Status = :status, 
                TrackingStatus = CASE 
                    WHEN :status = 'Accepted' THEN 'Accepted'
                    WHEN :status = 'Completed' THEN 'Transferred'
                    WHEN :status = 'Rejected' THEN 'Rejected'
                    ELSE TrackingStatus 
                END,
                ResponseNotes = COALESCE(:notes, ResponseNotes), 
                UpdatedAt = NOW() 
            WHERE ReferralID = :id
        ");
        return $stmt->execute([
            ':status' => $status,
            ':notes'  => $responseNotes,
            ':id'     => $referralId
        ]);
    }

    public function updateTracking(int $referralId, string $trackingStatus): bool
    {
        $stmt = $this->db->prepare("
            UPDATE referrals 
            SET TrackingStatus = :tracking,
                Status = CASE
                    WHEN :tracking IN ('Accepted', 'Received') THEN 'Accepted'
                    WHEN :tracking = 'Transferred' THEN 'Completed'
                    WHEN :tracking = 'Rejected' THEN 'Rejected'
                    ELSE Status
                END,
                UpdatedAt = NOW()
            WHERE ReferralID = :id
        ");
        return $stmt->execute([
            ':tracking' => $trackingStatus,
            ':id'       => $referralId
        ]);
    }

    public function findById(int $referralId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   rd.FirstName AS RefDocFirstName, rd.LastName AS RefDocLastName, rd.Specialty AS RefDocSpecialty,
                   ts.SpecialtyName AS TargetSpecialtyName,
                   td.FirstName AS TargetDocFirstName, td.LastName AS TargetDocLastName,
                   p.FirstName AS PatientFirstName, p.LastName AS PatientLastName, p.PatientCode,
                   p.Age, p.Gender, p.ContactNumber
            FROM referrals r
            JOIN doctors rd ON r.ReferringDoctorID = rd.DoctorID
            LEFT JOIN specialties ts ON r.TargetSpecialtyID = ts.SpecialtyID
            LEFT JOIN doctors td ON r.TargetDoctorID = td.DoctorID
            JOIN patients p ON r.PatientID = p.PatientID
            WHERE r.ReferralID = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $referralId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getOutgoing(int $doctorId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   ts.SpecialtyName AS TargetSpecialtyName,
                   td.FirstName AS TargetDocFirstName, td.LastName AS TargetDocLastName,
                   p.FirstName AS PatientFirstName, p.LastName AS PatientLastName, p.PatientCode
            FROM referrals r
            LEFT JOIN specialties ts ON r.TargetSpecialtyID = ts.SpecialtyID
            LEFT JOIN doctors td ON r.TargetDoctorID = td.DoctorID
            JOIN patients p ON r.PatientID = p.PatientID
            WHERE r.ReferringDoctorID = :docId
            ORDER BY r.CreatedAt DESC
        ");
        $stmt->execute([':docId' => $doctorId]);
        return $stmt->fetchAll();
    }

    public function getIncoming(int $doctorId, int $specialtyId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   rd.FirstName AS RefDocFirstName, rd.LastName AS RefDocLastName, rd.Specialty AS RefDocSpecialty,
                   ts.SpecialtyName AS TargetSpecialtyName,
                   p.FirstName AS PatientFirstName, p.LastName AS PatientLastName, p.PatientCode
            FROM referrals r
            JOIN doctors rd ON r.ReferringDoctorID = rd.DoctorID
            LEFT JOIN specialties ts ON r.TargetSpecialtyID = ts.SpecialtyID
            JOIN patients p ON r.PatientID = p.PatientID
            WHERE (r.TargetDoctorID = :docId OR (r.TargetSpecialtyID = :specId AND r.TargetDoctorID IS NULL))
            ORDER BY r.CreatedAt DESC
        ");
        $stmt->execute([':docId' => $doctorId, ':specId' => $specialtyId]);
        return $stmt->fetchAll();
    }
}
