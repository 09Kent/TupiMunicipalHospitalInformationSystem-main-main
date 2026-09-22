<?php
// Section/Med_Tech/models/LabTechnologist.php

require_once __DIR__ . '/../config/Database.php';

class LabTechnologist
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getPendingRequests(): array
    {
        $sql = "SELECT lr.*, p.FirstName, p.LastName, p.PatientCode, p.Age, p.Gender,
                       d.FirstName as DoctorFirstName, d.LastName as DoctorLastName, d.Specialty
                FROM laboratory_requests lr
                JOIN patients p ON lr.PatientID = p.PatientID
                JOIN doctors d ON lr.DoctorID = d.DoctorID
                ORDER BY lr.RequestID DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function recordResult(array $data): int
    {
        $doctorId = !empty($data['DoctorID']) ? (int)$data['DoctorID'] : null;
        if (!$doctorId && !empty($data['RequestID'])) {
            $reqStmt = $this->db->prepare("SELECT DoctorID FROM laboratory_requests WHERE RequestID = :rid LIMIT 1");
            $reqStmt->execute([':rid' => (int)$data['RequestID']]);
            $doctorId = (int)$reqStmt->fetchColumn();
        }
        if (!$doctorId) {
            $docStmt = $this->db->query("SELECT DoctorID FROM doctors WHERE Status = 'Active' LIMIT 1");
            $doctorId = (int)($docStmt ? ($docStmt->fetchColumn() ?: 1) : 1);
        }

        $sql = "INSERT INTO laboratory_results (RequestID, PatientID, DoctorID, TestName, ResultValue, NormalRange, Units, Interpretation, Notes)
                VALUES (:RequestID, :PatientID, :DoctorID, :TestName, :ResultValue, :NormalRange, :Units, :Interpretation, :Notes)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':RequestID' => (int)$data['RequestID'],
            ':PatientID' => (int)$data['PatientID'],
            ':DoctorID' => $doctorId,
            ':TestName' => $data['TestName'],
            ':ResultValue' => $data['ResultValue'],
            ':NormalRange' => $data['NormalRange'] ?? 'Normal',
            ':Units' => $data['Units'] ?? null,
            ':Interpretation' => $data['Interpretation'] ?? 'Normal',
            ':Notes' => $data['Notes'] ?? 'Test completed and verified by Medical Technologist.'
        ]);

        $resultId = (int)$this->db->lastInsertId();

        // Update request status to Completed
        $upStmt = $this->db->prepare("UPDATE laboratory_requests SET Status = 'Completed' WHERE RequestID = :reqId");
        $upStmt->execute([':reqId' => (int)$data['RequestID']]);

        return $resultId;
    }

    public function getCatalog(): array
    {
        $stmt = $this->db->query("SELECT * FROM test_catalog WHERE Status = 'Active' ORDER BY CatalogID ASC");
        return $stmt->fetchAll();
    }
}
