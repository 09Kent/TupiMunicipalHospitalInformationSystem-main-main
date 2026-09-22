<?php
// Doctor/models/Prescription.php

class Prescription
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function generateCode(): string
    {
        $year = date('Y');
        $stmt = $this->db->query("SELECT MAX(PrescriptionID) AS max_id FROM prescriptions");
        $next = ((int)($stmt->fetchColumn() ?: 0)) + 1;
        return sprintf("RX-%s-%04d", $year, $next);
    }

    public function checkAllergyConflict(int $patientId, string $medicineName): ?array
    {
        $stmt = $this->db->prepare("
            SELECT AllergyID, Allergen, AllergyType, Severity, Reaction 
            FROM allergy_records 
            WHERE PatientID = :pid AND Status = 'Active'
        ");
        $stmt->execute([':pid' => $patientId]);
        $allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $medLower = strtolower(trim($medicineName));
        foreach ($allergies as $alg) {
            $allergenLower = strtolower(trim($alg['Allergen']));
            if (empty($allergenLower)) {
                continue;
            }
            if (str_contains($medLower, $allergenLower) || str_contains($allergenLower, $medLower)) {
                return $alg;
            }
            $tokens = preg_split('/[\/,\+;]/', $allergenLower);
            foreach ($tokens as $token) {
                $trimmed = trim($token);
                if (strlen($trimmed) >= 3 && str_contains($medLower, $trimmed)) {
                    return $alg;
                }
            }
        }
        return null;
    }

    public function create(array $data): int
    {
        $patientId = (int)($data['patient_id'] ?? $data['PatientID'] ?? 0);
        $medicineName = (string)($data['medicine_name'] ?? $data['MedicineName'] ?? ($data['Medications'][0]['MedicineName'] ?? ''));
        $conflict = $this->checkAllergyConflict($patientId, $medicineName);
        if ($conflict) {
            throw new Exception("Allergy Safety Violation: Patient is allergic to '{$conflict['Allergen']}' (Severity: {$conflict['Severity']}, Reaction: {$conflict['Reaction']}). Prescription creation blocked for patient safety.");
        }

        $code = $data['prescription_code'] ?? $this->generateCode();

        $stmt = $this->db->prepare("
            INSERT INTO prescriptions (
                PrescriptionCode, PatientID, DoctorID, AppointmentID,
                MedicineName, Dosage, Frequency, Duration, Instructions,
                Quantity, Refills, Status, IssuedDate
            ) VALUES (
                :code, :patient_id, :doctor_id, :appointment_id,
                :medicine, :dosage, :frequency, :duration, :instructions,
                :quantity, :refills, :status, :issued_date
            )
        ");

        $stmt->execute([
            ':code'          => $code,
            ':patient_id'    => $data['patient_id'] ?? $data['PatientID'],
            ':doctor_id'     => $data['doctor_id'] ?? $data['DoctorID'],
            ':appointment_id'=> $data['appointment_id'] ?? $data['AppointmentID'] ?? null,
            ':medicine'      => $data['medicine_name'] ?? $data['MedicineName'] ?? ($data['Medications'][0]['MedicineName'] ?? 'Medication'),
            ':dosage'        => $data['dosage'] ?? $data['Dosage'] ?? ($data['Medications'][0]['Dosage'] ?? 'Standard'),
            ':frequency'     => $data['frequency'] ?? $data['Frequency'] ?? ($data['Medications'][0]['Frequency'] ?? 'Daily'),
            ':duration'      => $data['duration'] ?? $data['Duration'] ?? ($data['Medications'][0]['Duration'] ?? '7 days'),
            ':instructions'  => $data['instructions'] ?? $data['Instructions'] ?? 'Take as directed.',
            ':quantity'      => $data['quantity'] ?? $data['Quantity'] ?? '1 Box',
            ':refills'       => $data['refills'] ?? $data['Refills'] ?? 0,
            ':status'        => $data['status'] ?? $data['Status'] ?? 'Active',
            ':issued_date'   => $data['issued_date'] ?? $data['IssuedDate'] ?? date('Y-m-d')
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $prescriptionId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE prescriptions
            SET MedicineName = COALESCE(:medicine, MedicineName),
                Dosage = COALESCE(:dosage, Dosage),
                Frequency = COALESCE(:frequency, Frequency),
                Duration = COALESCE(:duration, Duration),
                Instructions = COALESCE(:instructions, Instructions),
                Quantity = COALESCE(:quantity, Quantity),
                Refills = COALESCE(:refills, Refills),
                Status = COALESCE(:status, Status)
            WHERE PrescriptionID = :id
        ");

        return $stmt->execute([
            ':medicine'     => $data['medicine_name'] ?? null,
            ':dosage'       => $data['dosage'] ?? null,
            ':frequency'    => $data['frequency'] ?? null,
            ':duration'     => $data['duration'] ?? null,
            ':instructions' => $data['instructions'] ?? null,
            ':quantity'     => $data['quantity'] ?? null,
            ':refills'      => $data['refills'] ?? null,
            ':status'       => $data['status'] ?? null,
            ':id'           => $prescriptionId
        ]);
    }

    public function cancel(int $prescriptionId): bool
    {
        $stmt = $this->db->prepare("UPDATE prescriptions SET Status = 'Cancelled' WHERE PrescriptionID = :id");
        return $stmt->execute([':id' => $prescriptionId]);
    }

    public function findById(int $prescriptionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, 
                   doc.FirstName AS DoctorFirstName, doc.LastName AS DoctorLastName, doc.Title AS DoctorTitle,
                   doc.Specialty, doc.LicenseNumber, doc.ContactNumber AS DoctorContact, doc.Clinic, doc.ClinicRoom,
                   p.FirstName AS PatientFirstName, p.MiddleName, p.LastName AS PatientLastName, p.PatientCode,
                   p.DateOfBirth, p.Age, p.Gender, p.Address, p.ContactNumber AS PatientContact, p.BloodType
            FROM prescriptions pr
            JOIN doctors doc ON pr.DoctorID = doc.DoctorID
            JOIN patients p ON pr.PatientID = p.PatientID
            WHERE pr.PrescriptionID = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $prescriptionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPatient(int $patientId): array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, doc.FirstName AS DoctorFirstName, doc.LastName AS DoctorLastName, doc.Specialty
            FROM prescriptions pr
            JOIN doctors doc ON pr.DoctorID = doc.DoctorID
            WHERE pr.PatientID = :patient_id
            ORDER BY pr.IssuedDate DESC, pr.CreatedAt DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    public function getAll(int $doctorId, int $limit = 50, string $status = ''): array
    {
        $sql = "
            SELECT pr.*, p.PatientCode, p.FirstName AS PatientFirstName, p.LastName AS PatientLastName,
                   p.Age, p.Gender
            FROM prescriptions pr
            JOIN patients p ON pr.PatientID = p.PatientID
            WHERE pr.DoctorID = :docId
        ";
        $params = [':docId' => $doctorId];

        if (!empty($status)) {
            $sql .= " AND pr.Status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY pr.IssuedDate DESC, pr.CreatedAt DESC LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
