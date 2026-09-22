<?php
// Section/Pharmacy/models/PharmacyManager.php

require_once __DIR__ . '/../config/Database.php';

class PharmacyManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getActivePrescriptions(): array
    {
        $sql = "SELECT rx.*, p.FirstName, p.LastName, p.PatientCode, p.DateOfBirth, p.Age, p.Gender,
                       d.FirstName as DoctorFirstName, d.LastName as DoctorLastName, d.Specialty, d.LicenseNumber
                FROM prescriptions rx
                JOIN patients p ON rx.PatientID = p.PatientID
                JOIN doctors d ON rx.DoctorID = d.DoctorID
                ORDER BY rx.PrescriptionID DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function dispense(array $data): int
    {
        $rxId = (int)$data['PrescriptionID'];
        $patientId = (int)$data['PatientID'];

        // Get prescription info
        $rxStmt = $this->db->prepare("SELECT * FROM prescriptions WHERE PrescriptionID = :id");
        $rxStmt->execute([':id' => $rxId]);
        $rx = $rxStmt->fetch(PDO::FETCH_ASSOC);

        $medicineName = $rx['MedicineName'] ?? ($data['MedicineName'] ?? '');
        $qtyStr = $data['QuantityDispensed'] ?? ($rx['Quantity'] ?? '1');
        preg_match('/\d+/', $qtyStr, $matches);
        $qtyToDeduct = !empty($matches[0]) ? (int)$matches[0] : 1;

        // Find earliest-expiring available batch from pharmacy_inventory
        $invItem = null;
        if (!empty($data['InventoryID'])) {
            $invStmt = $this->db->prepare("SELECT * FROM pharmacy_inventory WHERE InventoryID = :id");
            $invStmt->execute([':id' => (int)$data['InventoryID']]);
            $invItem = $invStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$invItem && !empty($medicineName)) {
            $search = '%' . strtolower(trim(explode(' ', $medicineName)[0])) . '%';
            $invStmt = $this->db->prepare("
                SELECT * FROM pharmacy_inventory 
                WHERE (LOWER(GenericName) LIKE :s1 OR LOWER(BrandName) LIKE :s2)
                  AND CurrentStock > 0
                ORDER BY ExpiryDate ASC 
                LIMIT 1
            ");
            $invStmt->execute([':s1' => $search, ':s2' => $search]);
            $invItem = $invStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$invItem) {
            // Pick first available inventory item with stock
            $invStmt = $this->db->query("SELECT * FROM pharmacy_inventory WHERE CurrentStock > 0 ORDER BY ExpiryDate ASC LIMIT 1");
            $invItem = $invStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$invItem) {
            throw new Exception("Medication out of stock in pharmacy inventory.");
        }

        if ($invItem['CurrentStock'] < $qtyToDeduct) {
            throw new Exception("Insufficient pharmacy stock for '{$invItem['GenericName']}': Available {$invItem['CurrentStock']}, requested {$qtyToDeduct}.");
        }

        $this->db->beginTransaction();
        try {
            // 1. Generate sequential dispense code
            $stmtMax = $this->db->query("SELECT MAX(DispenseID) FROM dispensing_records");
            $nextDisp = ((int)($stmtMax->fetchColumn() ?: 0)) + 1;
            $dispenseCode = sprintf("DISP-%s-%04d", date('Ymd'), $nextDisp);

            $batchNo = $invItem['BatchNumber'] ?? ($data['BatchNumber'] ?? 'B-2026-001');
            $dispenserName = $data['DispenserName'] ?? 'Kareen Joy Ramos, RPh';

            // 2. Deduct inventory stock
            $newStock = $invItem['CurrentStock'] - $qtyToDeduct;
            $newStatus = ($newStock <= 0) ? 'Out of Stock' : (($newStock <= $invItem['ReorderLevel']) ? 'Low Stock' : 'In Stock');
            
            $upInv = $this->db->prepare("
                UPDATE pharmacy_inventory 
                SET CurrentStock = :stock, Status = :status, UpdatedAt = NOW() 
                WHERE InventoryID = :id
            ");
            $upInv->execute([
                ':stock'  => $newStock,
                ':status' => $newStatus,
                ':id'     => $invItem['InventoryID']
            ]);

            // 3. Create dispensing record
            $sql = "INSERT INTO dispensing_records (
                        DispenseCode, PrescriptionID, PatientID, DispensedBy, DispenserName, 
                        QuantityDispensed, DosageInstructions, BatchNumber, DispenseDate, Status, Notes
                    ) VALUES (
                        :DispenseCode, :PrescriptionID, :PatientID, :DispensedBy, :DispenserName, 
                        :QuantityDispensed, :DosageInstructions, :BatchNumber, NOW(), 'Dispensed', :Notes
                    )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':DispenseCode'        => $dispenseCode,
                ':PrescriptionID'      => $rxId,
                ':PatientID'           => $patientId,
                ':DispensedBy'         => session('user_id') ?: 8,
                ':DispenserName'       => $dispenserName,
                ':QuantityDispensed'   => $qtyStr,
                ':DosageInstructions'  => $data['DosageInstructions'] ?? ($rx['Instructions'] ?? 'Take as prescribed.'),
                ':BatchNumber'         => $batchNo,
                ':Notes'               => $data['Notes'] ?? 'Medication dispensed and patient counseled.'
            ]);

            $dispenseId = (int)$this->db->lastInsertId();

            // 4. Log stock movement
            $mvStmt = $this->db->prepare("
                INSERT INTO pharmacy_stock_movements (InventoryID, MovementType, Quantity, ReferenceCode, RecordedBy, CreatedAt)
                VALUES (:invId, 'Dispensed', :qty, :ref, :dispenser, NOW())
            ");
            $mvStmt->execute([
                ':invId'     => $invItem['InventoryID'],
                ':qty'       => -$qtyToDeduct,
                ':ref'       => $dispenseCode,
                ':dispenser' => $dispenserName
            ]);

            // 5. Update prescription status to Completed
            $upStmt = $this->db->prepare("UPDATE prescriptions SET Status = 'Completed' WHERE PrescriptionID = :rxId");
            $upStmt->execute([':rxId' => $rxId]);

            $this->db->commit();

            return $dispenseId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getInventory(): array
    {
        $stmt = $this->db->query("SELECT * FROM pharmacy_inventory ORDER BY InventoryID ASC");
        return $stmt->fetchAll();
    }
}
