<?php

namespace App\Services\Register;

use PDO;
use Exception;
use DateTime;

// models/PatientHistory.php



class PatientHistory
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
    }

    public function log(int $patientId, string $action, string $description, ?int $userId = 1): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO patient_registration_history (PatientID, UserID, Action, Description, CreatedAt)
            VALUES (:pid, :uid, :action, :desc, NOW())
        ");

        $stmt->execute([
            'pid'    => $patientId,
            'uid'    => $userId,
            'action' => trim($action),
            'desc'   => trim($description)
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function logMultiple(array $records): void
    {
        if (empty($records)) return;
        $values = [];
        $params = [];
        foreach ($records as $i => $rec) {
            $values[] = "(:pid{$i}, :uid{$i}, :action{$i}, :desc{$i}, NOW())";
            $params["pid{$i}"] = $rec['PatientID'];
            $params["uid{$i}"] = $rec['UserID'] ?? 1;
            $params["action{$i}"] = trim($rec['Action']);
            $params["desc{$i}"] = trim($rec['Description']);
        }
        $sql = "INSERT INTO patient_registration_history (PatientID, UserID, Action, Description, CreatedAt) VALUES " . implode(', ', $values);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function getByPatientId(int $patientId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.FirstName AS UserFirst, u.LastName AS UserLast, u.Role
            FROM patient_registration_history h
            LEFT JOIN users u ON u.UserID = h.UserID
            WHERE h.PatientID = :pid
            ORDER BY h.CreatedAt DESC
        ");
        $stmt->execute(['pid' => $patientId]);
        return $stmt->fetchAll();
    }
}
