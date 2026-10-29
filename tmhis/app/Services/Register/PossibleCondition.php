<?php

namespace App\Services\Register;

use PDO;
use Exception;
use DateTime;

// models/PossibleCondition.php



class PossibleCondition
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT pc.*, bs.SystemName, bs.Emoji AS SystemEmoji
            FROM possible_conditions pc
            JOIN body_systems bs ON bs.BodySystemID = pc.BodySystemID
            WHERE pc.Status = 'Active'
            ORDER BY pc.ConditionID ASC
        ");
        return $stmt->fetchAll();
    }

    public function getByBodySystemId(int $systemId): array
    {
        $stmt = $this->db->prepare("
            SELECT pc.*, bs.SystemName
            FROM possible_conditions pc
            JOIN body_systems bs ON bs.BodySystemID = pc.BodySystemID
            WHERE pc.BodySystemID = :sid AND pc.Status = 'Active'
            ORDER BY pc.ConditionID ASC
        ");
        $stmt->execute(['sid' => $systemId]);
        return $stmt->fetchAll();
    }

    public function saveComplaintConditions(int $complaintId, array $conditionIdsOrNames): void
    {
        if (empty($conditionIdsOrNames))
            return;

        // Clear existing
        $stmtDel = $this->db->prepare("DELETE FROM complaint_conditions WHERE ComplaintID = :cid");
        $stmtDel->execute(['cid' => $complaintId]);

        $ids = [];
        $names = [];
        foreach ($conditionIdsOrNames as $item) {
            if (is_array($item)) {
                $item = $item['ConditionID'] ?? $item['id'] ?? $item['ConditionName'] ?? $item['name'] ?? reset($item);
            }
            if (is_numeric($item)) {
                $ids[] = (int) $item;
            } elseif (is_scalar($item)) {
                $trimmed = trim((string) $item);
                if ($trimmed !== '') {
                    $names[] = $trimmed;
                }
            }
        }

        if (!empty($names)) {
            $inClause = implode(',', array_fill(0, count($names), '?'));
            $stmtFind = $this->db->prepare("SELECT ConditionID FROM possible_conditions WHERE ConditionName IN ($inClause)");
            $stmtFind->execute($names);
            $foundIds = $stmtFind->fetchAll(PDO::FETCH_COLUMN);
            foreach ($foundIds as $fId) {
                $ids[] = (int) $fId;
            }
        }

        $ids = array_unique($ids);
        if (!empty($ids)) {
            $values = [];
            $params = [];
            foreach (array_values($ids) as $i => $condId) {
                $values[] = "(:cid{$i}, :cond_id{$i})";
                $params["cid{$i}"] = $complaintId;
                $params["cond_id{$i}"] = $condId;
            }
            $stmtIns = $this->db->prepare("INSERT INTO complaint_conditions (ComplaintID, ConditionID) VALUES " . implode(', ', $values));
            $stmtIns->execute($params);
        }
    }

    public function getByComplaintId(int $complaintId): array
    {
        $stmt = $this->db->prepare("
            SELECT pc.* 
            FROM complaint_conditions cc
            JOIN possible_conditions pc ON pc.ConditionID = cc.ConditionID
            WHERE cc.ComplaintID = :cid
            ORDER BY pc.ConditionName ASC
        ");
        $stmt->execute(['cid' => $complaintId]);
        return $stmt->fetchAll();
    }
}
