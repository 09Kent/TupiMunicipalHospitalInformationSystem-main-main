<?php

namespace App\Services\Register;

use PDO;
use Exception;
use DateTime;

// models/Symptom.php



class Symptom
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM symptoms WHERE Status = 'Active' ORDER BY SymptomName ASC");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM symptoms WHERE SymptomID = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByName(string $name): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM symptoms WHERE SymptomName = :name LIMIT 1");
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Attach symptoms to a complaint (Many-to-Many)
     */
    public function saveComplaintSymptoms(int $complaintId, array $symptomIdsOrNames): void
    {
        if (empty($symptomIdsOrNames)) return;

        // Clear existing
        $stmtDel = $this->db->prepare("DELETE FROM patient_symptoms WHERE ComplaintID = :cid");
        $stmtDel->execute(['cid' => $complaintId]);

        $ids = [];
        $names = [];
        foreach ($symptomIdsOrNames as $item) {
            if (is_numeric($item)) {
                $ids[] = (int)$item;
            } else {
                $trimmed = trim((string)$item);
                if ($trimmed !== '') {
                    $names[] = $trimmed;
                }
            }
        }

        if (!empty($names)) {
            $inClause = implode(',', array_fill(0, count($names), '?'));
            $stmtFind = $this->db->prepare("SELECT SymptomID FROM symptoms WHERE LOWER(TRIM(SymptomName)) IN ($inClause)");
            $stmtFind->execute(array_map('strtolower', $names));
            $foundIds = $stmtFind->fetchAll(PDO::FETCH_COLUMN);
            foreach ($foundIds as $fId) {
                $ids[] = (int)$fId;
            }
        }

        $ids = array_unique($ids);
        if (!empty($ids)) {
            $values = [];
            $params = [];
            foreach (array_values($ids) as $i => $sId) {
                $values[] = "(:cid{$i}, :sid{$i})";
                $params["cid{$i}"] = $complaintId;
                $params["sid{$i}"] = $sId;
            }
            $stmtIns = $this->db->prepare("INSERT INTO patient_symptoms (ComplaintID, SymptomID) VALUES " . implode(', ', $values));
            $stmtIns->execute($params);
        }
    }

    public function getByComplaintId(int $complaintId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.* 
            FROM patient_symptoms ps
            JOIN symptoms s ON s.SymptomID = ps.SymptomID
            WHERE ps.ComplaintID = :cid
            ORDER BY s.SymptomName ASC
        ");
        $stmt->execute(['cid' => $complaintId]);
        return $stmt->fetchAll();
    }
}
