<?php

namespace App\Services\Register;

use PDO;
use Exception;
use DateTime;

// models/PatientRegistration.php
















class PatientRegistration
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
    }

    /**
     * Process full multi-step Patient Registration & Pre-Consultation Intake atomically
     */
    public function registerCompletePatient(array $data, ?int $userId = 1): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $firstName = trim($data['personal']['firstName'] ?? $data['firstName'] ?? '');
            $lastName  = trim($data['personal']['lastName'] ?? $data['lastName'] ?? '');
            $dob       = trim($data['personal']['dob'] ?? $data['dob'] ?? date('Y-m-d'));

            $existingPatient = $this->identifyExistingPatient($data, $firstName, $lastName, $dob);

            $patientModel = new Patient($this->db);
            $emgModel = new EmergencyContact($this->db);
            $medModel = new MedicalHistory($this->db);
            $complaintModel = new Complaint($this->db);
            $symptomModel = new Symptom($this->db);
            $bodyLocationModel = new BodyLocation($this->db);
            $bodySystemModel = new BodySystem($this->db);
            $analysisModel = new ComplaintAnalysis($this->db);
            $conditionModel = new PossibleCondition($this->db);
            $appointmentModel = new Appointment($this->db);
            $queueModel = new Queue($this->db);
            $historyModel = new PatientHistory($this->db);

            // 1. Patient identity. A returning patient keeps the same PatientID;
            // the appointment and clinical records below represent the new visit.
            $isReturningPatient = $existingPatient !== null;
            if ($isReturningPatient) {
                $patientId = (int)$existingPatient['PatientID'];
                $patientCode = $existingPatient['PatientCode'];
            } else {
                $patientCode = $patientModel->generatePatientCode();
                $patientId = $patientModel->create([
                    'PatientCode'     => $patientCode,
                    'FirstName'       => $data['personal']['firstName'] ?? $data['firstName'] ?? '',
                    'MiddleName'      => $data['personal']['middleName'] ?? $data['middleName'] ?? null,
                    'LastName'        => $data['personal']['lastName'] ?? $data['lastName'] ?? '',
                    'DateOfBirth'     => $data['personal']['dob'] ?? $data['dob'] ?? date('Y-m-d'),
                    'Age'             => $data['personal']['age'] ?? $data['age'] ?? 0,
                    'Gender'          => $data['personal']['gender'] ?? $data['gender'] ?? 'Male',
                    'CivilStatus'     => $data['personal']['civilStatus'] ?? $data['civilStatus'] ?? 'Single',
                    'ContactNumber'   => $data['personal']['phone'] ?? $data['phone'] ?? '',
                    'Email'           => $data['personal']['email'] ?? $data['email'] ?? '',
                    'Address'         => $data['personal']['address'] ?? $data['address'] ?? '',
                    'BloodType'       => $data['medical']['bloodType'] ?? $data['bloodType'] ?? 'Unknown',
                    'PatientCategory' => $data['patientCategory'] ?? 'Outpatient',
                    'Status'          => 'Active',
                    'RegisteredBy'    => $userId
                ]);

                // Demographic and emergency information belongs to the patient identity,
                // so it is only created during the first registration.
                $emgModel->create([
                    'PatientID'     => $patientId,
                    'ContactName'   => $data['emergency']['name'] ?? $data['emgName'] ?? '',
                    'Relationship'  => $data['emergency']['relationship'] ?? $data['emgRelationship'] ?? 'Parent',
                    'ContactNumber' => $data['emergency']['phone'] ?? $data['emgPhone'] ?? ''
                ]);

                $medModel->create([
                    'PatientID'               => $patientId,
                    'Allergies'               => $data['medical']['allergies'] ?? $data['allergies'] ?? 'None',
                    'ExistingConditions'      => $data['medical']['conditions'] ?? $data['conditions'] ?? 'None',
                    'CurrentMedications'      => $data['medical']['medications'] ?? $data['medications'] ?? 'None',
                    'PreviousHospitalization' => $data['medical']['hospitalization'] ?? $data['hospitalization'] ?? 'None'
                ]);
            }

            // 4. Chief Complaint
            $complaintText = $data['complaint'] ?? 'General Consultation';
            $complaintId = $complaintModel->create([
                'PatientID'            => $patientId,
                'ComplaintDescription' => $complaintText,
                'Severity'             => (int)($data['severity'] ?? 3),
                'Duration'             => $data['duration'] ?? '1–3 days ago',
                'AggravatingFactors'   => $data['aggravating'] ?? null,
                'RelievingFactors'     => $data['relieving'] ?? null
            ]);

            // 5. Symptoms Junction
            $symptoms = $data['symptoms'] ?? [];
            if (!empty($symptoms)) {
                $symptomModel->saveComplaintSymptoms($complaintId, $symptoms);
            }

            // 6. Body System & Anatomical Location Resolution
            $bodySystemId = (int)($data['bodySystemId'] ?? 0);
            if ($bodySystemId > 0) {
                $stmtSys = $this->db->prepare("SELECT BodySystemID FROM body_systems WHERE BodySystemID = :id LIMIT 1");
                $stmtSys->execute([':id' => $bodySystemId]);
                if (!$stmtSys->fetch()) {
                    $bodySystemId = 0;
                }
            }
            if ($bodySystemId <= 0) {
                $defSys = $this->db->query("SELECT BodySystemID FROM body_systems ORDER BY BodySystemID ASC LIMIT 1")->fetchColumn();
                $bodySystemId = $defSys ? (int)$defSys : 1;
            }

            $bodyLocationCode = $data['bodyLocation'] ?? 'abdomen';
            $locRecord = $bodyLocationModel->getByCode($bodyLocationCode);
            if ($locRecord && !empty($locRecord['BodyLocationID'])) {
                $bodyLocationId = (int)$locRecord['BodyLocationID'];
            } else {
                $defLoc = $this->db->query("SELECT BodyLocationID FROM body_locations ORDER BY BodyLocationID ASC LIMIT 1")->fetchColumn();
                $bodyLocationId = $defLoc ? (int)$defLoc : 4;
            }

            // 7. Complaint Analysis (Classification Record)
            $relevanceLevel = (int)($data['systemRelevanceScore'] ?? 95);
            $confidenceLevel = "High ({$relevanceLevel}%)";
            $clinicalNotes = "Automated pre-consultation symptom classification matched to " . ($locRecord['LocationName'] ?? 'Anatomical region');

            $analysisModel->create([
                'ComplaintID'    => $complaintId,
                'BodySystemID'   => $bodySystemId,
                'BodyLocationID' => $bodyLocationId,
                'RelevanceLevel' => $relevanceLevel,
                'ConfidenceLevel'=> $confidenceLevel,
                'ClinicalNotes'  => $clinicalNotes
            ]);

            // 8. Possible Conditions Junction
            $conditions = $data['possibleConditions'] ?? [];
            if (!empty($conditions)) {
                $conditionModel->saveComplaintConditions($complaintId, $conditions);
            } else {
                // Attach default conditions for system
                $dbConds = $conditionModel->getByBodySystemId($bodySystemId);
                if (!empty($dbConds)) {
                    $condIds = array_column($dbConds, 'ConditionID');
                    $conditionModel->saveComplaintConditions($complaintId, $condIds);
                }
            }

            // 9. Doctor Selection & Appointment Scheduling
            $requestedDoctorId = (int)($data['doctorId'] ?? ($data['selectedDoctor']['DoctorID'] ?? $data['selectedDoctor']['id'] ?? 0));
            $doctorId = $this->resolveDoctorId($requestedDoctorId);
            if ($doctorId <= 0) {
                throw new Exception('No active doctor is available for this consultation.');
            }

            $appDate = $data['appointmentDate'] ?? date('Y-m-d');
            $appTime = $data['selectedSlot'] ?? '02:30 PM';
            $allowedConsultationTypes = [
                'In-Person Consultation',
                'Secure Telehealth Video',
                'Follow-up Review',
                'Emergency Triage'
            ];
            $consType = in_array($data['consultationType'] ?? '', $allowedConsultationTypes)
                ? $data['consultationType']
                : 'In-Person Consultation';

            $appId = $appointmentModel->create([
                'PatientID'        => $patientId,
                'DoctorID'         => $doctorId,
                'AppointmentDate'  => $appDate,
                'AppointmentTime'  => $appTime,
                'ConsultationType' => $consType,
                'Reason'           => substr($complaintText, 0, 200),
                'Priority'         => ($data['severity'] ?? 3) >= 4 ? 'Urgent' : 'Normal',
                'Notes'            => 'Pre-consultation digital intake registration.',
                'Status'           => 'Scheduled',
                'CreatedBy'        => $userId
            ]);

            // 10. Patient Queue Ticket
            $queueNum = $queueModel->generateQueueNumber();
            $queueId = $queueModel->create([
                'AppointmentID' => $appId,
                'PatientID'     => $patientId,
                'DoctorID'      => $doctorId,
                'QueueNumber'   => $queueNum,
                'QueueStatus'   => 'Waiting',
                'Priority'      => ($data['severity'] ?? 3) >= 4 ? 'Priority' : 'Normal'
            ]);

            // 11. Comprehensive Audit Trail (Registration History)
            $fullName = trim(($data['personal']['firstName'] ?? '') . ' ' . ($data['personal']['lastName'] ?? ''));
            $historyModel->logMultiple([
                ['PatientID' => $patientId, 'Action' => $isReturningPatient ? 'Returning Patient Visit' : 'Patient Registered', 'Description' => $isReturningPatient ? "Returning patient {$fullName} identified as {$patientCode}; new encounter created." : "Patient {$fullName} registered with Digital Code {$patientCode}.", 'UserID' => $userId],
                ['PatientID' => $patientId, 'Action' => 'Complaint Recorded', 'Description' => "Chief complaint recorded: " . substr($complaintText, 0, 100), 'UserID' => $userId],
                ['PatientID' => $patientId, 'Action' => 'Symptom Classification', 'Description' => "Pre-consultation analysis matched to {$relevanceLevel}% relevance.", 'UserID' => $userId],
                ['PatientID' => $patientId, 'Action' => 'Doctor Assigned', 'Description' => "Consultation booked with Doctor ID #{$doctorId} for {$appDate} {$appTime}.", 'UserID' => $userId],
                ['PatientID' => $patientId, 'Action' => 'Queue Generated', 'Description' => "Assigned Daily Queue Ticket #{$queueNum}.", 'UserID' => $userId],
            ]);

            // Fetch Doctor details for response
            $doctorModel = new Doctor($this->db);
            $docInfo = $doctorModel->getById($doctorId);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'success'        => true,
                'patient_id'     => $patientId,
                'patient_code'   => $patientCode,
                'appointment_id' => $appId,
                'queue_id'       => $queueId,
                'queue_number'   => $queueNum,
                'doctor'         => $docInfo,
                'message'        => 'Patient successfully registered and queued for consultation.'
            ];

        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }

    public function checkDuplicate(string $firstName, string $lastName, string $dob): ?array
    {
        $stmt = $this->db->prepare("
            SELECT PatientID, PatientCode, FirstName, LastName, DateOfBirth, ContactNumber, CreatedAt
            FROM patients
            WHERE LOWER(TRIM(FirstName)) = LOWER(TRIM(:fn))
              AND LOWER(TRIM(LastName)) = LOWER(TRIM(:ln))
              AND DateOfBirth = :dob
            LIMIT 1
        ");
        $stmt->execute([
            ':fn' => $firstName,
            ':ln' => $lastName,
            ':dob' => $dob
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function identifyExistingPatient(array $data, string $firstName, string $lastName, string $dob): ?array
    {
        $patientId = (int)($data['patient_id'] ?? $data['PatientID'] ?? 0);
        if ($patientId > 0) {
            $stmt = $this->db->prepare("SELECT PatientID, PatientCode, FirstName, LastName, DateOfBirth, ContactNumber, CreatedAt FROM patients WHERE PatientID = :id LIMIT 1");
            $stmt->execute([':id' => $patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient && $this->sameIdentity($patient, $firstName, $lastName, $dob)) {
                return $patient;
            }
        }

        $patientCode = trim((string)($data['patient_code'] ?? $data['PatientCode'] ?? ''));
        if ($patientCode !== '') {
            $stmt = $this->db->prepare("SELECT PatientID, PatientCode, FirstName, LastName, DateOfBirth, ContactNumber, CreatedAt FROM patients WHERE PatientCode = :code LIMIT 1");
            $stmt->execute([':code' => $patientCode]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient && $this->sameIdentity($patient, $firstName, $lastName, $dob)) {
                return $patient;
            }
        }

        return $this->checkDuplicate($firstName, $lastName, $dob);
    }

    private function sameIdentity(array $patient, string $firstName, string $lastName, string $dob): bool
    {
        return strcasecmp(trim((string)$patient['FirstName']), trim($firstName)) === 0
            && strcasecmp(trim((string)$patient['LastName']), trim($lastName)) === 0
            && (string)$patient['DateOfBirth'] === $dob;
    }

    private function resolveDoctorId(int $requestedDoctorId): int
    {
        if ($requestedDoctorId > 0) {
            $stmt = $this->db->prepare("SELECT DoctorID FROM doctors WHERE DoctorID = :id AND Status <> 'Inactive' LIMIT 1");
            $stmt->execute([':id' => $requestedDoctorId]);
            $doctorId = $stmt->fetchColumn();
            if ($doctorId) {
                return (int)$doctorId;
            }
        }

        $doctorId = $this->db->query("SELECT DoctorID FROM doctors WHERE Status IN ('Available', 'Busy', 'On Leave') ORDER BY DoctorID ASC LIMIT 1")->fetchColumn();
        return $doctorId ? (int)$doctorId : 0;
    }
}
