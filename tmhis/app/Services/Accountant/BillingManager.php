<?php
// Section/Accountant/models/BillingManager.php

require_once __DIR__ . '/../config/Database.php';

class BillingManager
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getCharges(?int $patientId = null): array
    {
        $sql = "SELECT bc.*, p.FirstName, p.LastName, p.PatientCode, p.ContactNumber
                FROM billing_charges bc
                JOIN patients p ON bc.PatientID = p.PatientID";
        $params = [];
        if ($patientId !== null) {
            $sql .= " WHERE bc.PatientID = :pid";
            $params[':pid'] = $patientId;
        }
        $sql .= " ORDER BY bc.ChargeID DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInvoices(?int $patientId = null): array
    {
        $sql = "SELECT bi.*, p.FirstName, p.LastName, p.PatientCode
                FROM billing_invoices bi
                JOIN patients p ON bi.PatientID = p.PatientID";
        $params = [];
        if ($patientId !== null) {
            $sql .= " WHERE bi.PatientID = :pid";
            $params[':pid'] = $patientId;
        }
        $sql .= " ORDER BY bi.InvoiceID DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInvoiceById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_invoices WHERE InvoiceID = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        return $inv ?: null;
    }

    public function getPayments(?int $patientId = null): array
    {
        $sql = "SELECT bp.*, p.FirstName, p.LastName, p.PatientCode, bi.InvoiceNumber
                FROM billing_payments bp
                JOIN patients p ON bp.PatientID = p.PatientID
                JOIN billing_invoices bi ON bp.InvoiceID = bi.InvoiceID";
        $params = [];
        if ($patientId !== null) {
            $sql .= " WHERE bp.PatientID = :pid";
            $params[':pid'] = $patientId;
        }
        $sql .= " ORDER BY bp.PaymentID DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCharge(array $data): int
    {
        $unitPrice = (float)($data['UnitPrice'] ?? 0.00);
        $qty = (int)($data['Quantity'] ?? 1);
        $subtotal = round($unitPrice * $qty, 2);
        $discount = (float)($data['DiscountAmount'] ?? 0.00);
        $net = round(max(0, $subtotal - $discount), 2);

        $sql = "INSERT INTO billing_charges (
                    PatientID, AppointmentID, FeeID, ChargeCategory, ItemDescription, 
                    Quantity, UnitPrice, SubTotal, DiscountAmount, NetAmount, 
                    BillingStatus, source_type, source_id, CreatedBy, CreatedAt
                ) VALUES (
                    :PatientID, :AppointmentID, :FeeID, :ChargeCategory, :ItemDescription, 
                    :Quantity, :UnitPrice, :SubTotal, :DiscountAmount, :NetAmount, 
                    'Unbilled', :source_type, :source_id, :CreatedBy, NOW()
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':PatientID'        => (int)$data['PatientID'],
            ':AppointmentID'    => !empty($data['AppointmentID']) ? (int)$data['AppointmentID'] : null,
            ':FeeID'            => !empty($data['FeeID']) ? (int)$data['FeeID'] : null,
            ':ChargeCategory'   => $data['ChargeCategory'] ?? 'Consultation',
            ':ItemDescription'  => $data['ItemDescription'],
            ':Quantity'         => $qty,
            ':UnitPrice'        => $unitPrice,
            ':SubTotal'         => $subtotal,
            ':DiscountAmount'   => $discount,
            ':NetAmount'        => $net,
            ':source_type'      => $data['source_type'] ?? null,
            ':source_id'        => !empty($data['source_id']) ? (int)$data['source_id'] : null,
            ':CreatedBy'        => $data['CreatedBy'] ?? 'Billing Staff'
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Compute statutory discount (Senior Citizen / PWD 20%) and PhilHealth deduction
     * Strictly applies 20% discount once even if patient qualifies for both.
     */
    public function computeDiscounts(float $grossAmount, array $qualifications = [], float $philHealthDeduction = 0.0): array
    {
        $hasSenior = in_array('Senior Citizen', $qualifications, true) || in_array('senior', $qualifications, true);
        $hasPWD    = in_array('PWD', $qualifications, true) || in_array('pwd', $qualifications, true);

        $discountType = 'None';
        $discountAmount = 0.0;

        if ($hasSenior && $hasPWD) {
            $discountType = 'Senior Citizen + PWD (20% Max Statutory)';
            $discountAmount = round($grossAmount * 0.20, 2);
        } elseif ($hasSenior) {
            $discountType = 'Senior Citizen (20%)';
            $discountAmount = round($grossAmount * 0.20, 2);
        } elseif ($hasPWD) {
            $discountType = 'PWD (20%)';
            $discountAmount = round($grossAmount * 0.20, 2);
        }

        $afterDiscount = max(0, $grossAmount - $discountAmount);
        $finalPhilHealth = min($afterDiscount, round($philHealthDeduction, 2));
        $totalPayable = round(max(0, $afterDiscount - $finalPhilHealth), 2);

        return [
            'gross_amount'         => round($grossAmount, 2),
            'discount_type'        => $discountType,
            'discount_amount'      => $discountAmount,
            'philhealth_deduction' => $finalPhilHealth,
            'total_payable'        => $totalPayable
        ];
    }

    /**
     * Generate sequential Official Receipt numbers (e.g. OR-2026-000001)
     */
    public function generateReceiptNumber(): string
    {
        $year = date('Y');
        $prefix = "OR-{$year}-%";

        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(ReceiptNumber, 9) AS UNSIGNED)) 
            FROM billing_payments 
            WHERE ReceiptNumber LIKE :prefix
        ");
        $stmt->execute([':prefix' => $prefix]);
        $maxSeq = (int)$stmt->fetchColumn();
        $nextSeq = $maxSeq + 1;

        return sprintf("OR-%s-%06d", $year, $nextSeq);
    }

    /**
     * Process payment and accurately track remaining balance
     */
    public function processPayment(array $data): array
    {
        $invoiceId = (int)$data['InvoiceID'];
        $patientId = (int)$data['PatientID'];
        $amountPaid = round((float)$data['AmountPaid'], 2);

        if ($amountPaid <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }

        $inv = $this->getInvoiceById($invoiceId);
        if (!$inv) {
            throw new Exception("Billing Invoice #{$invoiceId} not found.");
        }

        $receiptNo = $data['ReceiptNumber'] ?? $this->generateReceiptNumber();
        $words = $data['AmountInWords'] ?? 'Pesos Only';

        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO billing_payments (
                        ReceiptNumber, InvoiceID, PatientID, AmountPaid, 
                        PaymentMethod, ReferenceNumber, AmountInWords, CashierName, PaymentDate
                    ) VALUES (
                        :ReceiptNumber, :InvoiceID, :PatientID, :AmountPaid, 
                        :PaymentMethod, :ReferenceNumber, :AmountInWords, :CashierName, NOW()
                    )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':ReceiptNumber'    => $receiptNo,
                ':InvoiceID'        => $invoiceId,
                ':PatientID'        => $patientId,
                ':AmountPaid'       => $amountPaid,
                ':PaymentMethod'    => $data['PaymentMethod'] ?? 'Cash',
                ':ReferenceNumber'  => $data['ReferenceNumber'] ?? null,
                ':AmountInWords'    => $words,
                ':CashierName'      => $data['CashierName'] ?? (session('full_name') ?: 'Maria Santos')
            ]);

            // Calculate updated balance
            $newTotalPaid = round((float)$inv['AmountPaid'] + $amountPaid, 2);
            $totalPayable = (float)$inv['TotalPayable'];
            $newBalance = round(max(0, $totalPayable - $newTotalPaid), 2);
            $paymentStatus = ($newBalance <= 0.001) ? 'Paid In Full' : 'Partially Paid';

            $up = $this->db->prepare("
                UPDATE billing_invoices 
                SET AmountPaid = :paid, BalanceDue = :bal, PaymentStatus = :status 
                WHERE InvoiceID = :invId
            ");
            $up->execute([
                ':paid'   => $newTotalPaid,
                ':bal'    => $newBalance,
                ':status' => $paymentStatus,
                ':invId'  => $invoiceId
            ]);

            $this->db->commit();

            return [
                'success'          => true,
                'receipt_number'   => $receiptNo,
                'amount_paid'      => $amountPaid,
                'new_total_paid'   => $newTotalPaid,
                'balance_due'      => $newBalance,
                'payment_status'   => $paymentStatus
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Cross-module unbilled charge aggregation (Consultations, Laboratory, Dispensing)
     * Enforces uniqueness to prevent duplicate billing
     */
    public function aggregateUnbilledCharges(int $patientId): array
    {
        $added = [];

        // 1. Unbilled completed consultations
        $sqlApp = "SELECT a.AppointmentID, a.AppointmentDate, a.DoctorID, d.LastName as DoctorName
                   FROM appointments a
                   JOIN doctors d ON a.DoctorID = d.DoctorID
                   WHERE a.PatientID = :pid 
                     AND a.Status = 'Completed'
                     AND NOT EXISTS (
                         SELECT 1 FROM billing_charges bc 
                         WHERE bc.source_type = 'consultation' AND bc.source_id = a.AppointmentID
                     )";
        $stmtApp = $this->db->prepare($sqlApp);
        $stmtApp->execute([':pid' => $patientId]);
        $apps = $stmtApp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($apps as $app) {
            $cid = $this->createCharge([
                'PatientID'       => $patientId,
                'AppointmentID'   => $app['AppointmentID'],
                'ChargeCategory'  => 'Consultation',
                'ItemDescription' => "Outpatient Clinical Consultation — Dr. {$app['DoctorName']}",
                'Quantity'        => 1,
                'UnitPrice'       => 350.00,
                'DiscountAmount'  => 0.00,
                'source_type'     => 'consultation',
                'source_id'       => $app['AppointmentID'],
                'CreatedBy'       => 'Automated Charge Aggregator'
            ]);
            $added[] = ['type' => 'consultation', 'charge_id' => $cid, 'ref' => $app['AppointmentID']];
        }

        // 2. Unbilled completed laboratory results
        $sqlLab = "SELECT lr.ResultID, lr.TestName, lr.RequestID
                   FROM laboratory_results lr
                   WHERE lr.PatientID = :pid
                     AND NOT EXISTS (
                         SELECT 1 FROM billing_charges bc 
                         WHERE bc.source_type = 'laboratory' AND bc.source_id = lr.ResultID
                     )";
        $stmtLab = $this->db->prepare($sqlLab);
        $stmtLab->execute([':pid' => $patientId]);
        $labs = $stmtLab->fetchAll(PDO::FETCH_ASSOC);

        foreach ($labs as $lab) {
            $cid = $this->createCharge([
                'PatientID'       => $patientId,
                'ChargeCategory'  => 'Laboratory',
                'ItemDescription' => "Laboratory Diagnostic Test: {$lab['TestName']}",
                'Quantity'        => 1,
                'UnitPrice'       => 250.00,
                'DiscountAmount'  => 0.00,
                'source_type'     => 'laboratory',
                'source_id'       => $lab['ResultID'],
                'CreatedBy'       => 'Automated Charge Aggregator'
            ]);
            $added[] = ['type' => 'laboratory', 'charge_id' => $cid, 'ref' => $lab['ResultID']];
        }

        // 3. Unbilled completed pharmacy dispensing records
        $sqlDisp = "SELECT dr.DispenseID, dr.QuantityDispensed, rx.MedicineName
                    FROM dispensing_records dr
                    JOIN prescriptions rx ON dr.PrescriptionID = rx.PrescriptionID
                    WHERE dr.PatientID = :pid 
                      AND dr.Status = 'Dispensed'
                      AND NOT EXISTS (
                          SELECT 1 FROM billing_charges bc 
                          WHERE bc.source_type = 'pharmacy' AND bc.source_id = dr.DispenseID
                      )";
        $stmtDisp = $this->db->prepare($sqlDisp);
        $stmtDisp->execute([':pid' => $patientId]);
        $disps = $stmtDisp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($disps as $disp) {
            $cid = $this->createCharge([
                'PatientID'       => $patientId,
                'ChargeCategory'  => 'Pharmacy',
                'ItemDescription' => "Prescription Dispense: {$disp['MedicineName']} ({$disp['QuantityDispensed']})",
                'Quantity'        => 1,
                'UnitPrice'       => 150.00,
                'DiscountAmount'  => 0.00,
                'source_type'     => 'pharmacy',
                'source_id'       => $disp['DispenseID'],
                'CreatedBy'       => 'Automated Charge Aggregator'
            ]);
            $added[] = ['type' => 'pharmacy', 'charge_id' => $cid, 'ref' => $disp['DispenseID']];
        }

        return $added;
    }
}
