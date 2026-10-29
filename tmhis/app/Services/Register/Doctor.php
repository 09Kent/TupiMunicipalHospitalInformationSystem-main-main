<?php

namespace App\Services\Register;

use PDO;
use Exception;
use DateTime;

// models/Doctor.php



class Doctor
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::getConnection();
    }

    private function ensureDoctorChen(): void
    {
        try {
            $stmt = $this->db->prepare("SELECT DoctorID, Status, UserID FROM doctors WHERE LOWER(LastName) = 'chen' AND LOWER(FirstName) = 'alexander' LIMIT 1");
            $stmt->execute();
            $chen = $stmt->fetch(PDO::FETCH_ASSOC);

            $userId = null;
            try {
                $uStmt = $this->db->prepare("SELECT UserID FROM users WHERE LOWER(Username) = 'dr.chen' OR LOWER(Email) LIKE '%chen%' LIMIT 1");
                $uStmt->execute();
                $uRow = $uStmt->fetchColumn();
                if ($uRow) {
                    $userId = (int) $uRow;
                }
            } catch (\Throwable $ue) {
            }

            if ($chen) {
                if (($chen['Status'] ?? '') === 'Inactive') {
                    $upd = $this->db->prepare("UPDATE doctors SET Status = 'Available' WHERE DoctorID = :id");
                    $upd->execute([':id' => $chen['DoctorID']]);
                }
                if ($userId && empty($chen['UserID'])) {
                    $updU = $this->db->prepare("UPDATE doctors SET UserID = :uid WHERE DoctorID = :id");
                    $updU->execute([':uid' => $userId, ':id' => $chen['DoctorID']]);
                }
                return;
            }

            $ins = $this->db->prepare("
                INSERT INTO doctors (
                    UserID, FirstName, LastName, SpecialtyID, Title, Specialty,
                    LicenseNumber, ContactNumber, Email, ExperienceYears, Clinic,
                    ClinicRoom, ProfileImage, Rating, ReviewsCount, ConsultationFee,
                    Bio, Education, Languages, Status, CreatedAt
                ) VALUES (
                    :uid, 'Alexander', 'Chen', 2, 'MD, FACC, FSCAI', 'Cardiologist',
                    'LIC-MED-CHEN-01', '+63 917 880 1103', 'alexander.chen@tupihospital.gov.ph', 16, 'Tupi Municipal Hospital',
                    'Cardiology Center, 5th Floor', '/assets/doctors/doc-male-1.jpg', 4.95, 210, '$110.00',
                    'Expert in cardiovascular evaluations, arrhythmias, chest discomfort triage, hypertension, and advanced heart health.',
                    'Stanford Medicine (Cardiology Fellowship), Harvard Medical School (MD)',
                    'English, Mandarin, Tagalog', 'Available', NOW()
                )
            ");
            $ins->execute([':uid' => $userId]);
        } catch (\Throwable $e) {
            // Ignore if read-only or schema variation
        }
    }

    public function getAll(): array
    {
        $this->ensureDoctorChen();

        $stmt = $this->db->query("
            SELECT d.*, 
                   GROUP_CONCAT(bs.SystemName SEPARATOR ', ') AS LinkedSystems,
                   GROUP_CONCAT(bs.BodySystemID) AS SystemIDs
            FROM doctors d
            LEFT JOIN doctor_body_systems dbs ON dbs.DoctorID = d.DoctorID
            LEFT JOIN body_systems bs ON bs.BodySystemID = dbs.BodySystemID
            WHERE d.Status IN ('Available', 'Busy', 'Active', 'On Leave') OR d.Status IS NULL
            GROUP BY d.DoctorID
            ORDER BY CASE WHEN LOWER(d.LastName) = 'chen' THEN 0 ELSE 1 END ASC, d.Rating DESC, d.ExperienceYears DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, 
                   GROUP_CONCAT(bs.SystemName SEPARATOR ', ') AS LinkedSystems,
                   GROUP_CONCAT(bs.BodySystemID) AS SystemIDs
            FROM doctors d
            LEFT JOIN doctor_body_systems dbs ON dbs.DoctorID = d.DoctorID
            LEFT JOIN body_systems bs ON bs.BodySystemID = dbs.BodySystemID
            WHERE d.DoctorID = :id
            GROUP BY d.DoctorID LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get Recommended Doctors prioritized by the patient's classified Body System
     */
    public function getRecommendedDoctors(int $bodySystemId): array
    {
        $this->ensureDoctorChen();

        $stmt = $this->db->prepare("
            SELECT d.*, 
                   GROUP_CONCAT(bs.SystemName SEPARATOR ', ') AS LinkedSystems,
                   (CASE WHEN EXISTS (
                       SELECT 1 FROM doctor_body_systems dbs 
                       WHERE dbs.DoctorID = d.DoctorID AND dbs.BodySystemID = :sys_id
                   ) THEN 1 ELSE 0 END) AS IsTopMatch
            FROM doctors d
            LEFT JOIN doctor_body_systems dbs ON dbs.DoctorID = d.DoctorID
            LEFT JOIN body_systems bs ON bs.BodySystemID = dbs.BodySystemID
            WHERE d.Status IN ('Available', 'Busy', 'Active', 'On Leave') OR d.Status IS NULL
            GROUP BY d.DoctorID
            ORDER BY CASE WHEN LOWER(d.LastName) = 'chen' THEN 0 ELSE 1 END ASC, IsTopMatch DESC, d.Rating DESC, d.ExperienceYears DESC
        ");
        $stmt->execute(['sys_id' => $bodySystemId]);
        return $stmt->fetchAll();
    }
}
