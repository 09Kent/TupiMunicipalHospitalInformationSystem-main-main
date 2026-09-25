<?php
// Doctor/models/LaboratoryRequest.php

class LaboratoryRequest
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function generateCode(): string
    {
        $year = date('Y');
        $stmt = $this->db->query("SELECT MAX(RequestID) AS max_id FROM laboratory_requests");
        $next = ((int)($stmt->fetchColumn() ?: 0)) + 1;
        return sprintf("LAB-%s-%04d", $year, $next);
    }

    public function create(array $data): int
    {
        $code = $data['request_code'] ?? $this->generateCode();
        $patientId = (int)($data['patient_id'] ?? $data['PatientID']);
        $doctorId = (int)($data['doctor_id'] ?? $data['DoctorID']);
        $testType = trim((string)($data['test_type'] ?? $data['TestType']));
        $requestedDate = $data['requested_date'] ?? $data['RequestedDate'] ?? date('Y-m-d');

                $existing = $this->db->prepare("
                        SELECT RequestID FROM laboratory_requests
                        WHERE PatientID = :patient_id
                            AND DoctorID = :doctor_id
                            AND TestType = :test_type
                            AND RequestedDate = :requested_date
                            AND Status NOT IN ('Cancelled', 'Completed')
                        ORDER BY RequestID DESC LIMIT 1
                ");
        $existing->execute([
            ':patient_id' => $patientId,
            ':doctor_id' => $doctorId,
            ':test_type' => $testType,
            ':requested_date' => $requestedDate,
        ]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            return (int)$existingId;
        }

        $stmt = $this->db->prepare("
            INSERT INTO laboratory_requests (
                RequestCode, PatientID, DoctorID, AppointmentID,
                TestType, Priority, ClinicalNotes, Status, RequestedDate
            ) VALUES (
                :code, :patient_id, :doctor_id, :appointment_id,
                :test_type, :priority, :notes, :status, :requested_date
            )
        ");

        $stmt->execute([
            ':code'          => $code,
            ':patient_id'    => $patientId,
            ':doctor_id'     => $doctorId,
            ':appointment_id'=> $data['appointment_id'] ?? $data['AppointmentID'] ?? null,
            ':test_type'     => $testType,
            ':priority'      => $data['priority'] ?? $data['Priority'] ?? 'Routine',
            ':notes'         => $data['clinical_notes'] ?? $data['ClinicalNotes'] ?? null,
            ':status'        => $data['status'] ?? $data['Status'] ?? 'Pending',
            ':requested_date'=> $requestedDate
        ]);

        $requestId = (int)$this->db->lastInsertId();
        $this->createBillingCharge($requestId, $patientId, $data);

        return $requestId;
    }

    public function updateStatus(int $requestId, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE laboratory_requests SET Status = :status WHERE RequestID = :id");
        return $stmt->execute([':status' => $status, ':id' => $requestId]);
    }

    public function findById(int $requestId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT lr.*, 
                   doc.FirstName AS DoctorFirstName, doc.LastName AS DoctorLastName, doc.Specialty,
                   p.FirstName AS PatientFirstName, p.MiddleName, p.LastName AS PatientLastName, p.PatientCode,
                   p.Age, p.Gender, p.ContactNumber
            FROM laboratory_requests lr
            JOIN doctors doc ON lr.DoctorID = doc.DoctorID
            JOIN patients p ON lr.PatientID = p.PatientID
            WHERE lr.RequestID = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $requestId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPatient(int $patientId): array
    {
        $stmt = $this->db->prepare("
            SELECT lr.*, doc.FirstName AS DoctorFirstName, doc.LastName AS DoctorLastName, doc.Specialty,
                   (SELECT COUNT(*) FROM laboratory_results WHERE RequestID = lr.RequestID) AS ResultsCount
            FROM laboratory_requests lr
            JOIN doctors doc ON lr.DoctorID = doc.DoctorID
            WHERE lr.PatientID = :patient_id
            ORDER BY lr.RequestedDate DESC, lr.CreatedAt DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    public function getAll(int $doctorId, int $limit = 50, string $status = ''): array
    {
        $sql = "
            SELECT lr.*, p.PatientCode, p.FirstName AS PatientFirstName, p.LastName AS PatientLastName,
                   p.Age, p.Gender,
                   (SELECT COUNT(*) FROM laboratory_results WHERE RequestID = lr.RequestID) AS ResultsCount
            FROM laboratory_requests lr
            JOIN patients p ON lr.PatientID = p.PatientID
            WHERE lr.DoctorID = :docId
        ";
        $params = [':docId' => $doctorId];

        if (!empty($status)) {
            $sql .= " AND lr.Status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY lr.RequestedDate DESC, lr.CreatedAt DESC LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPendingCount(int $doctorId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM laboratory_requests 
            WHERE DoctorID = :docId AND Status IN ('Pending', 'In Progress', 'Sample Collected')
        ");
        $stmt->execute([':docId' => $doctorId]);
        return (int)$stmt->fetchColumn();
    }

    private function createBillingCharge(int $requestId, int $patientId, array $data): void
    {
        $duplicate = $this->db->prepare("SELECT ChargeID FROM billing_charges WHERE source_type = 'laboratory_request' AND source_id = :source_id LIMIT 1");
        $duplicate->execute([':source_id' => $requestId]);
        if ($duplicate->fetchColumn()) {
            return;
        }

        $testType = trim((string)($data['test_type'] ?? $data['TestType']));
        $catalog = $this->db->prepare("SELECT CatalogID, TestName, StandardPrice FROM test_catalog WHERE Status = 'Active' AND (TestName = :exact OR TestName LIKE :partial) ORDER BY TestName = :exact_order DESC LIMIT 1");
        $catalog->execute([':exact' => $testType, ':partial' => "%{$testType}%", ':exact_order' => $testType]);
        $test = $catalog->fetch(PDO::FETCH_ASSOC) ?: null;
        $unitPrice = (float)($test['StandardPrice'] ?? 250.00);
        $feeId = null;

        $fee = $this->db->query("SELECT FeeID FROM service_fees WHERE Category = 'Laboratory' AND Status = 'Active' ORDER BY FeeID ASC LIMIT 1")->fetchColumn();
        if ($fee) {
            $feeId = (int)$fee;
        }

        $stmt = $this->db->prepare("
            INSERT INTO billing_charges (PatientID, FeeID, ChargeCategory, ItemDescription, Quantity, UnitPrice, SubTotal, DiscountAmount, NetAmount, BillingStatus, source_type, source_id, CreatedBy, CreatedAt)
            VALUES (:patient_id, :fee_id, 'Laboratory', :description, 1, :unit_price, :subtotal, 0, :net_amount, 'Unbilled', 'laboratory_request', :source_id, 'Doctor Portal', NOW())
        ");
        $stmt->execute([
            ':patient_id' => $patientId,
            ':fee_id' => $feeId,
            ':description' => 'Laboratory Diagnostic Test: ' . $testType,
            ':unit_price' => $unitPrice,
            ':subtotal' => $unitPrice,
            ':net_amount' => $unitPrice,
            ':source_id' => $requestId,
        ]);
    }
}
