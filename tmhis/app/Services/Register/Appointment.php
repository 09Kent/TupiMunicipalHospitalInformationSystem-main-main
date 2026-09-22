<?php

namespace App\Services\Register;

use PDO;
use Exception;
use DateTime;

// models/Appointment.php




class Appointment
{
    private PDO $db;
    private PatientHistory $historyModel;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
        $this->historyModel = new PatientHistory($this->db);
    }

    public function checkConflict(int $doctorId, string $date, string $time, ?int $excludeAppId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM appointments 
                WHERE DoctorID = :doc_id 
                  AND AppointmentDate = :app_date 
                  AND AppointmentTime = :app_time 
                  AND Status NOT IN ('Cancelled', 'Completed', 'No Show')";
        if ($excludeAppId) {
            $sql .= " AND AppointmentID != :exclude_id";
        }
        $stmt = $this->db->prepare($sql);
        $params = [
            ':doc_id'   => $doctorId,
            ':app_date' => $date,
            ':app_time' => $time
        ];
        if ($excludeAppId) {
            $params[':exclude_id'] = $excludeAppId;
        }
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $doctorId = (int)($data['DoctorID'] ?? 0);
        $appDate  = $data['AppointmentDate'] ?? date('Y-m-d');
        $appTime  = $data['AppointmentTime'] ?? '02:30 PM';

        if ($doctorId && $this->checkConflict($doctorId, $appDate, $appTime)) {
            // Auto-advance slot to next available 15-minute interval
            $dt = DateTime::createFromFormat('h:i A', $appTime) ?: new DateTime('14:30');
            $attempts = 0;
            while ($attempts < 24 && $this->checkConflict($doctorId, $appDate, $dt->format('h:i A'))) {
                $dt->modify('+15 minutes');
                $attempts++;
            }
            if ($this->checkConflict($doctorId, $appDate, $dt->format('h:i A'))) {
                throw new Exception("Physician scheduling conflict: Doctor #{$doctorId} has no available slots remaining on {$appDate}.");
            }
            $appTime = $dt->format('h:i A');
        }

        $stmt = $this->db->prepare("
            INSERT INTO appointments (
                PatientID, DoctorID, AppointmentDate, AppointmentTime, 
                ConsultationType, Reason, Priority, Notes, Status, CreatedBy, CreatedAt
            ) VALUES (
                :pid, :doc_id, :app_date, :app_time, 
                :type, :reason, :prio, :notes, :status, :created_by, NOW()
            )
        ");

        $stmt->execute([
            'pid'        => $data['PatientID'],
            'doc_id'     => $data['DoctorID'],
            'app_date'   => $data['AppointmentDate'] ?? date('Y-m-d'),
            'app_time'   => $data['AppointmentTime'] ?? '02:30 PM',
            'type'       => $data['ConsultationType'] ?? 'In-Person Consultation',
            'reason'     => trim($data['Reason'] ?? 'General Consultation'),
            'prio'       => $data['Priority'] ?? 'Normal',
            'notes'      => !empty($data['Notes']) ? trim($data['Notes']) : null,
            'status'     => $data['Status'] ?? 'Scheduled',
            'created_by' => $data['CreatedBy'] ?? 1
        ]);

        $appId = (int)$this->db->lastInsertId();

        // Log to Patient History
        $this->historyModel->log(
            (int)$data['PatientID'],
            'Consultation Scheduled',
            "Consultation appointment #{$appId} booked for {$data['AppointmentDate']} at {$data['AppointmentTime']}.",
            $data['CreatedBy'] ?? 1
        );

        return $appId;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                   p.PatientCode, p.FirstName AS PatFirst, p.LastName AS PatLast, p.ContactNumber AS PatPhone, p.Age, p.Gender,
                   d.FirstName AS DocFirst, d.LastName AS DocLast, d.Specialty, d.Clinic, d.ClinicRoom, d.ProfileImage
            FROM appointments a
            JOIN patients p ON p.PatientID = a.PatientID
            JOIN doctors d ON d.DoctorID = a.DoctorID
            WHERE a.AppointmentID = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getTodayAppointments(): array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                   p.PatientCode, p.FirstName AS PatFirst, p.LastName AS PatLast, p.Age, p.Gender,
                   d.FirstName AS DocFirst, d.LastName AS DocLast, d.Specialty, d.ClinicRoom,
                   q.QueueNumber, q.QueueStatus
            FROM appointments a
            JOIN patients p ON p.PatientID = a.PatientID
            JOIN doctors d ON d.DoctorID = a.DoctorID
            LEFT JOIN patient_queue q ON q.AppointmentID = a.AppointmentID
            WHERE a.AppointmentDate = CURDATE()
            ORDER BY a.AppointmentTime ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPaginated(int $limit = 10, int $offset = 0, string $date = '', string $doctor = '', string $status = ''): array
    {
        $sql = "
            SELECT a.*, 
                   p.PatientCode, p.FirstName AS PatFirst, p.LastName AS PatLast, p.Age, p.Gender, p.ContactNumber AS PatPhone,
                   d.FirstName AS DocFirst, d.LastName AS DocLast, d.Specialty, d.ClinicRoom,
                   q.QueueNumber, q.QueueStatus
            FROM appointments a
            JOIN patients p ON p.PatientID = a.PatientID
            JOIN doctors d ON d.DoctorID = a.DoctorID
            LEFT JOIN patient_queue q ON q.AppointmentID = a.AppointmentID
            WHERE 1=1
        ";

        $params = [];

        if (!empty($date)) {
            $sql .= " AND a.AppointmentDate = :date";
            $params['date'] = $date;
        }

        if (!empty($doctor)) {
            $sql .= " AND a.DoctorID = :doc";
            $params['doc'] = $doctor;
        }

        if (!empty($status)) {
            $sql .= " AND a.Status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY a.AppointmentDate DESC, a.AppointmentTime ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countTotal(string $date = '', string $doctor = '', string $status = ''): int
    {
        $sql = "SELECT COUNT(*) FROM appointments a WHERE 1=1";
        $params = [];

        if (!empty($date)) {
            $sql .= " AND a.AppointmentDate = :date";
            $params['date'] = $date;
        }

        if (!empty($doctor)) {
            $sql .= " AND a.DoctorID = :doc";
            $params['doc'] = $doctor;
        }

        if (!empty($status)) {
            $sql .= " AND a.Status = :status";
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function reschedule(int $id, string $newDate, string $newTime, ?int $newDoctorId = null, string $reason = '', ?int $userId = 1): bool
    {
        $app = $this->getById($id);
        if (!$app) return false;

        $docId = $newDoctorId ?: $app['DoctorID'];

        $stmt = $this->db->prepare("
            UPDATE appointments SET
                AppointmentDate = :date,
                AppointmentTime = :time,
                DoctorID        = :doc,
                Notes           = CONCAT(COALESCE(Notes, ''), '\nRescheduled: ', :reason),
                UpdatedAt       = NOW()
            WHERE AppointmentID = :id
        ");

        $success = $stmt->execute([
            'id'     => $id,
            'date'   => $newDate,
            'time'   => $newTime,
            'doc'    => $docId,
            'reason' => $reason
        ]);

        if ($success) {
            $this->historyModel->log(
                (int)$app['PatientID'],
                'Consultation Rescheduled',
                "Appointment #{$id} rescheduled to {$newDate} at {$newTime}. Reason: {$reason}",
                $userId
            );
        }

        return $success;
    }

    public function updateStatus(int $id, string $status, ?int $userId = 1): bool
    {
        $app = $this->getById($id);
        if (!$app) return false;

        $stmt = $this->db->prepare("UPDATE appointments SET Status = :status, UpdatedAt = NOW() WHERE AppointmentID = :id");
        $success = $stmt->execute(['id' => $id, 'status' => $status]);

        if ($success) {
            $this->historyModel->log(
                (int)$app['PatientID'],
                'Appointment Status Updated',
                "Consultation appointment #{$id} status changed to '{$status}'.",
                $userId
            );
        }

        return $success;
    }

    public function cancel(int $id, string $reason = '', ?int $userId = 1): bool
    {
        $app = $this->getById($id);
        if (!$app) return false;

        $stmt = $this->db->prepare("
            UPDATE appointments SET 
                Status = 'Cancelled', 
                Notes = CONCAT(COALESCE(Notes, ''), '\nCancelled: ', :reason), 
                UpdatedAt = NOW() 
            WHERE AppointmentID = :id
        ");
        $success = $stmt->execute(['id' => $id, 'reason' => $reason]);

        if ($success) {
            $this->historyModel->log(
                (int)$app['PatientID'],
                'Consultation Cancelled',
                "Appointment #{$id} was cancelled. Reason: {$reason}",
                $userId
            );
        }

        return $success;
    }

    public function getAppointments(string $date = '', string $status = '', int $doctorId = 0): array
    {
        $docParam = $doctorId > 0 ? (string)$doctorId : '';
        return $this->getPaginated(100, 0, $date, $docParam, $status);
    }

    public function getDailySummary(string $date = ''): array
    {
        $d = !empty($date) ? $date : date('Y-m-d');
        $stmt = $this->db->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN Status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN Status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN Status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM appointments WHERE AppointmentDate = :d");
        $stmt->execute([':d' => $d]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total' => (int)($row['total'] ?? 0),
            'scheduled' => (int)($row['scheduled'] ?? 0),
            'completed' => (int)($row['completed'] ?? 0),
            'cancelled' => (int)($row['cancelled'] ?? 0),
        ];
    }
}
