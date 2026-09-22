<?php

namespace App\Services\Nurse;

use PDO;
use Exception;
use TMHIS\Database;

require_once __DIR__ . '/../config/Database.php';

class NurseTaskManager
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
    }

    /**
     * Get active nursing operational tasks with patient and doctor context
     */
    public function getTasks(?int $nurseId = null, int $limit = 50): array
    {
        $sql = "SELECT nt.*, p.FirstName, p.LastName, p.PatientCode, p.Age, p.Gender,
                       d.FirstName as DoctorFirstName, d.LastName as DoctorLastName, d.Specialty
                FROM nurse_tasks nt
                JOIN patients p ON nt.PatientID = p.PatientID
                LEFT JOIN doctors d ON nt.DoctorID = d.DoctorID";
        $params = [];
        if ($nurseId !== null) {
            $sql .= " WHERE nt.NurseID = :nurse_id";
            $params[':nurse_id'] = $nurseId;
        }
        $sql .= " ORDER BY nt.TaskID DESC LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate state machine transition for nursing task
     */
    public function validateTransition(string $current, string $target): bool
    {
        $allowedTransitions = [
            'Pending'     => ['In Progress', 'Cancelled'],
            'Assigned'    => ['In Progress', 'Cancelled'],
            'In Progress' => ['Completed', 'Cancelled'],
            'Completed'   => [], // Terminal state
            'Cancelled'   => []  // Terminal state
        ];

        $validTargets = $allowedTransitions[$current] ?? [];
        return in_array($target, $validTargets, true);
    }

    /**
     * Update task status with strict state machine validation
     * Allowed: (Pending|Assigned) -> In Progress -> Completed
     */
    public function updateTaskStatus(int $taskId, string $newStatus, ?string $remarks = null): bool
    {
        $stmt = $this->db->prepare("SELECT Status FROM nurse_tasks WHERE TaskID = :id");
        $stmt->execute([':id' => $taskId]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus === false) {
            throw new Exception("Nurse task #{$taskId} not found.");
        }

        // Normalize
        $current = trim((string)$currentStatus);
        $target = trim($newStatus);

        if (!$this->validateTransition($current, $target)) {
            throw new Exception("Invalid nurse task state transition from '{$current}' to '{$target}'. Allowed transition: Assigned/Pending -> In Progress -> Completed.");
        }

        $sql = "UPDATE nurse_tasks SET Status = :status";
        $params = [
            ':status' => $target,
            ':id'     => $taskId
        ];

        if ($target === 'Completed') {
            $sql .= ", CompletedAt = NOW()";
        }
        if ($remarks !== null) {
            $sql .= ", Remarks = :remarks";
            $params[':remarks'] = $remarks;
        }
        $sql .= " WHERE TaskID = :id";

        $updateStmt = $this->db->prepare($sql);
        return $updateStmt->execute($params);
    }

    /**
     * Create a new nursing task
     */
    public function createTask(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO nurse_tasks (
                PatientID, NurseID, DoctorID, TaskTitle, Category, 
                DueTime, Priority, Status, Remarks, CreatedAt
            ) VALUES (
                :PatientID, :NurseID, :DoctorID, :TaskTitle, :Category, 
                :DueTime, :Priority, 'Pending', :Remarks, NOW()
            )
        ");

        $stmt->execute([
            ':PatientID' => (int)$data['PatientID'],
            ':NurseID'   => !empty($data['NurseID']) ? (int)$data['NurseID'] : null,
            ':DoctorID'  => !empty($data['DoctorID']) ? (int)$data['DoctorID'] : null,
            ':TaskTitle' => $data['TaskTitle'],
            ':Category'  => $data['Category'] ?? 'General Care',
            ':DueTime'   => $data['DueTime'] ?? '12:00 PM',
            ':Priority'  => $data['Priority'] ?? 'Normal',
            ':Remarks'   => $data['Remarks'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }
}
