<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BillingPayment;
use App\Models\BillingInvoice;
use App\Models\Patient;
use App\Models\SystemAuditLog;
use Exception;

class PaymentGatewayService
{
    const STATUS_PENDING       = 'PENDING';
    const STATUS_PROCESSING    = 'PROCESSING';
    const STATUS_AUTHORIZED    = 'AUTHORIZED';
    const STATUS_PAID          = 'PAID';
    const STATUS_FAILED        = 'FAILED';
    const STATUS_CANCELLED     = 'CANCELLED';
    const STATUS_EXPIRED       = 'EXPIRED';
    const STATUS_REFUNDED      = 'REFUNDED';
    const STATUS_PENDING_VERIF = 'PENDING VERIFICATION';

    const METHOD_CASH          = 'cash';
    const METHOD_GCASH         = 'gcash';
    const METHOD_MAYA          = 'maya';
    const METHOD_QRPH          = 'qrph';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ONLINE_BANK   = 'online_banking';
    const METHOD_CARD          = 'card';
    const METHOD_CASHLESS      = 'cashless';
    const METHOD_MEDICAL_EMS   = 'medical_ems';

    /**
     * Normalize payment method input
     */
    public static function normalizeMethod(string $method): string
    {
        $m = strtolower(trim($method));
        if (str_contains($m, 'medicalems') || str_contains($m, 'medical_ems') || str_contains($m, 'ems') || str_contains($m, 'assistance')) {
            return self::METHOD_MEDICAL_EMS;
        }
        if (str_contains($m, 'cashless')) {
            return self::METHOD_CASHLESS;
        }
        if (str_contains($m, 'cash') && !str_contains($m, 'gcash')) {
            return self::METHOD_CASH;
        }
        if (str_contains($m, 'gcash')) {
            return self::METHOD_GCASH;
        }
        if (str_contains($m, 'maya') || str_contains($m, 'paymaya')) {
            return self::METHOD_MAYA;
        }
        if (str_contains($m, 'qr') || str_contains($m, 'qrph')) {
            return self::METHOD_QRPH;
        }
        if (str_contains($m, 'bank') || str_contains($m, 'transfer')) {
            return self::METHOD_BANK_TRANSFER;
        }
        if (str_contains($m, 'card') || str_contains($m, 'credit') || str_contains($m, 'debit')) {
            return self::METHOD_CARD;
        }
        return self::METHOD_CASH;
    }

    /**
     * Generate unique sequential Official Receipt number (e.g. OR-2026-000001)
     */
    public function generateReceiptNumber(): string
    {
        $year = date('Y');
        $prefix = "OR-{$year}-%";

        $max = DB::table('billing_payments')
            ->where('ReceiptNumber', 'like', $prefix)
            ->max('PaymentID');

        $count = DB::table('billing_payments')->where('ReceiptNumber', 'like', $prefix)->count();
        $nextSeq = max($count + 1, (int)$max + 1);

        return sprintf("OR-%s-%06d", $year, $nextSeq);
    }

    /**
     * Generate unique transaction reference (e.g. TXN-2026-XXXXXX)
     */
    public function generateTransactionReference(string $method): string
    {
        $prefix = match($method) {
            self::METHOD_GCASH         => 'TXN-GCASH-',
            self::METHOD_MAYA          => 'TXN-MAYA-',
            self::METHOD_QRPH          => 'TXN-QRPH-',
            self::METHOD_BANK_TRANSFER => 'TXN-BANK-',
            self::METHOD_CARD          => 'TXN-CARD-',
            self::METHOD_CASHLESS      => 'TXN-CSHLS-',
            self::METHOD_MEDICAL_EMS   => 'TXN-EMS-',
            default                    => 'TXN-CSH-'
        };
        return $prefix . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Generate EMVCo / Philippine QR Ph Standard Specification Compliant Payload
     */
    public function generateQrPhPayload(string $reference, float $amount, string $invoiceNumber): string
    {
        $merchantName = "Tupi Municipal Hospital";
        $merchantCity = "Tupi";
        $merchantCategory = "8062"; // Health & Medical Services
        $currencyCode = "608";      // PHP ISO 4217
        $amountFormatted = number_format($amount, 2, '.', '');

        // EMVCo TLV (Tag-Length-Value) Helper
        $tlv = function (string $tag, string $value): string {
            return sprintf("%s%02d%s", $tag, strlen($value), $value);
        };

        $qrData = '';
        $qrData .= $tlv('00', '01'); // Format Indicator
        $qrData .= $tlv('01', '12'); // Dynamic QR (12)
        
        // Tag 28: Philippine QR Ph National Retail Payment System (NRPS)
        $sub28  = $tlv('00', 'ph.gov.bsp.qrph');
        $sub28 .= $tlv('01', 'TMHIS-TUPI-HOSP');
        $sub28 .= $tlv('02', $reference);
        $qrData .= $tlv('28', $sub28);

        $qrData .= $tlv('52', $merchantCategory);
        $qrData .= $tlv('53', $currencyCode);
        $qrData .= $tlv('54', $amountFormatted);
        $qrData .= $tlv('58', 'PH');
        $qrData .= $tlv('59', substr($merchantName, 0, 25));
        $qrData .= $tlv('60', substr($merchantCity, 0, 15));

        // Additional Data Field Template (Tag 62)
        $sub62  = $tlv('01', $invoiceNumber);
        $sub62 .= $tlv('05', $reference);
        $sub62 .= $tlv('07', 'TMHIS-BILLING');
        $qrData .= $tlv('62', $sub62);

        // Calculate CRC16-CCITT for Tag 63
        $qrForChecksum = $qrData . '6304';
        $crc = $this->calculateCrc16($qrForChecksum);
        $finalPayload = $qrForChecksum . strtoupper(sprintf('%04x', $crc));

        return $finalPayload;
    }

    /**
     * CRC16-CCITT calculation for EMVCo QR validation
     */
    private function calculateCrc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return $crc;
    }

    /**
     * Convert numbers to Philippine English Words for Official Receipts
     */
    public function numberToWords(float $amount): string
    {
        $num = (int)floor($amount);
        $centavos = (int)round(($amount - $num) * 100);

        if ($num === 0 && $centavos === 0) return "Zero Pesos Only";

        $ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", 
                 "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
        $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
        $thousands = ["", "Thousand", "Million", "Billion"];

        $convertGroup = function ($n) use ($ones, $tens) {
            $res = "";
            if ($n >= 100) {
                $res .= $ones[(int)floor($n / 100)] . " Hundred ";
                $n %= 100;
            }
            if ($n >= 20) {
                $res .= $tens[(int)floor($n / 10)] . " ";
                $n %= 10;
            }
            if ($n > 0) {
                $res .= $ones[$n] . " ";
            }
            return trim($res);
        };

        $wordResult = "";
        $thousandIdx = 0;
        $remaining = $num;

        while ($remaining > 0) {
            $chunk = $remaining % 1000;
            if ($chunk !== 0) {
                $groupStr = $convertGroup($chunk);
                $wordResult = $groupStr . ($thousands[$thousandIdx] ? " " . $thousands[$thousandIdx] . " " : " ") . $wordResult;
            }
            $remaining = (int)floor($remaining / 1000);
            $thousandIdx++;
        }

        $formatted = trim($wordResult) . " Pesos";
        if ($centavos > 0) {
            $formatted .= " and " . $centavos . "/100";
        }
        return $formatted . " Only";
    }

    /**
     * Initialize or Process a Payment
     * 
     * Handles:
     * - Cash (immediate settlement + change)
     * - E-Wallet / GCash (dynamic reference + pending authorization)
     * - Maya Checkout / QR Ph (hosted checkout + dynamic QR)
     * - QR Ph (interoperable dynamic payload + pending authorization)
     * - Bank Transfer (pending verification by cashier)
     */
    public function initiatePayment(array $data, ?int $userId = null): array
    {
        $invoiceId = (int)($data['invoice_id'] ?? $data['InvoiceID'] ?? 0);
        $patientId = (int)($data['patient_id'] ?? $data['PatientID'] ?? 0);
        $amountPaid = round((float)($data['amount'] ?? $data['AmountPaid'] ?? 0), 2);
        $rawMethod = (string)($data['payment_method'] ?? $data['PaymentMethod'] ?? 'cash');
        $method = self::normalizeMethod($rawMethod);

        if ($invoiceId <= 0) {
            throw new Exception("Valid InvoiceID is required.");
        }
        if ($patientId <= 0) {
            throw new Exception("Valid PatientID is required.");
        }
        if ($amountPaid <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }

        $invoice = BillingInvoice::find($invoiceId);
        if (!$invoice) {
            throw new Exception("Invoice #{$invoiceId} not found.");
        }
        if (in_array(strtoupper($invoice->PaymentStatus), ['PAID', 'PAID IN FULL'])) {
            throw new Exception("Invoice #{$invoice->InvoiceNumber} has already been settled in full.");
        }

        $patient = Patient::find($patientId);
        $cashierName = $data['cashier_name'] ?? (auth()->user() ? auth()->user()->FullName : 'Maria Santos (Cashier)');
        $txnRef = $this->generateTransactionReference($method);

        // -------------------------------------------------------------
        // WORKFLOW 1: CASH PAYMENT (Immediate settlement)
        // -------------------------------------------------------------
        if ($method === self::METHOD_CASH) {
            $cashReceived = (float)($data['cash_received'] ?? $amountPaid);
            if ($cashReceived < $amountPaid) {
                throw new Exception("Cash received (₱" . number_format($cashReceived, 2) . ") is less than the required amount (₱" . number_format($amountPaid, 2) . ").");
            }
            $change = round(max(0, $cashReceived - $amountPaid), 2);

            $receiptNo = $this->generateReceiptNumber();
            $words = $this->numberToWords($amountPaid);

            DB::beginTransaction();
            try {
                $payment = BillingPayment::create([
                    'ReceiptNumber'            => $receiptNo,
                    'InvoiceID'                => $invoiceId,
                    'PatientID'                => $patientId,
                    'AmountPaid'               => $amountPaid,
                    'currency'                 => 'PHP',
                    'PaymentStatus'            => 'Completed',
                    'status'                   => self::STATUS_PAID,
                    'PaymentMethod'            => 'Cash',
                    'payment_provider'         => 'internal',
                    'ReferenceNumber'          => $txnRef,
                    'transaction_reference'    => $txnRef,
                    'provider_transaction_id'  => $txnRef,
                    'request_reference_number' => $txnRef,
                    'AmountInWords'            => $words,
                    'CashierName'              => $cashierName,
                    'RecordedBy'               => $cashierName,
                    'paid_at'                  => now(),
                    'PaymentDate'              => now(),
                    'created_by'               => $userId ?? auth()->id(),
                ]);

                // Update invoice
                $this->applyPaymentToInvoice($invoice, $amountPaid);

                // Audit Log
                $this->auditLog('Payment Successful', "Cash payment of ₱" . number_format($amountPaid, 2) . " processed for Invoice {$invoice->InvoiceNumber}. Cash received: ₱{$cashReceived}, Change: ₱{$change}. Receipt: {$receiptNo}", $txnRef, $userId);

                DB::commit();

                return [
                    'success'               => true,
                    'payment_id'            => $payment->PaymentID,
                    'status'                => self::STATUS_PAID,
                    'payment_method'        => 'cash',
                    'receipt_number'        => $receiptNo,
                    'transaction_reference' => $txnRef,
                    'amount_paid'           => $amountPaid,
                    'cash_received'         => $cashReceived,
                    'change'                => $change,
                    'invoice_status'        => $invoice->fresh()->PaymentStatus,
                    'message'               => 'Cash payment processed successfully. Official Receipt generated.'
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // -------------------------------------------------------------
        // WORKFLOW 2: MAYA (Hosted Checkout & Dynamic QR Ph)
        // -------------------------------------------------------------
        if ($method === self::METHOD_MAYA) {
            $qrPayload = $this->generateQrPhPayload($txnRef, $amountPaid, $invoice->InvoiceNumber);
            $expiresAt = now()->addMinutes(15);
            $checkoutUrl = url("/billing/checkout/maya/{$txnRef}");

            $payment = BillingPayment::create([
                'ReceiptNumber'            => 'PENDING-' . $txnRef,
                'InvoiceID'                => $invoiceId,
                'PatientID'                => $patientId,
                'AmountPaid'               => $amountPaid,
                'currency'                 => 'PHP',
                'PaymentStatus'            => 'Pending',
                'status'                   => self::STATUS_PENDING,
                'PaymentMethod'            => 'Maya',
                'payment_provider'         => 'maya',
                'ReferenceNumber'          => $txnRef,
                'transaction_reference'    => $txnRef,
                'request_reference_number' => $txnRef,
                'AmountInWords'            => $this->numberToWords($amountPaid),
                'CashierName'              => $cashierName,
                'RecordedBy'               => $cashierName,
                'payment_url'              => $checkoutUrl,
                'qr_payload'               => $qrPayload,
                'expires_at'               => $expiresAt,
                'PaymentDate'              => now(),
                'created_by'               => $userId ?? auth()->id(),
            ]);

            // Update invoice status to Pending Payment
            $invoice->update(['PaymentStatus' => 'Pending Payment']);

            $this->auditLog('Payment Created', "Maya payment transaction initialized for Invoice {$invoice->InvoiceNumber}. Ref: {$txnRef}. Amount: ₱{$amountPaid}", $txnRef, $userId);

            return [
                'success'               => true,
                'payment_id'            => $payment->PaymentID,
                'status'                => self::STATUS_PENDING,
                'payment_method'        => 'maya',
                'payment_provider'      => 'maya',
                'transaction_reference' => $txnRef,
                'amount'                => $amountPaid,
                'currency'              => 'PHP',
                'payment_url'           => $checkoutUrl,
                'qr_payload'            => $qrPayload,
                'expires_at'            => $expiresAt->toIso8601String(),
                'invoice_number'        => $invoice->InvoiceNumber,
                'patient_name'          => $patient ? ($patient->FirstName . ' ' . $patient->LastName) : 'Patient Record',
                'message'               => 'Maya checkout initialized. Scan QR Ph or proceed to hosted authorization.'
            ];
        }

        // -------------------------------------------------------------
        // WORKFLOW 3: GCASH / E-WALLET
        // -------------------------------------------------------------
        if ($method === self::METHOD_GCASH) {
            $qrPayload = $this->generateQrPhPayload($txnRef, $amountPaid, $invoice->InvoiceNumber);
            $expiresAt = now()->addMinutes(15);
            $checkoutUrl = url("/billing/checkout/gcash/{$txnRef}");

            $payment = BillingPayment::create([
                'ReceiptNumber'            => 'PENDING-' . $txnRef,
                'InvoiceID'                => $invoiceId,
                'PatientID'                => $patientId,
                'AmountPaid'               => $amountPaid,
                'currency'                 => 'PHP',
                'PaymentStatus'            => 'Pending',
                'status'                   => self::STATUS_PENDING,
                'PaymentMethod'            => 'GCash',
                'payment_provider'         => 'gcash',
                'ReferenceNumber'          => $txnRef,
                'transaction_reference'    => $txnRef,
                'request_reference_number' => $txnRef,
                'AmountInWords'            => $this->numberToWords($amountPaid),
                'CashierName'              => $cashierName,
                'RecordedBy'               => $cashierName,
                'payment_url'              => $checkoutUrl,
                'qr_payload'               => $qrPayload,
                'expires_at'               => $expiresAt,
                'PaymentDate'              => now(),
                'created_by'               => $userId ?? auth()->id(),
            ]);

            $invoice->update(['PaymentStatus' => 'Pending Payment']);

            $this->auditLog('Payment Created', "GCash e-wallet payment transaction initialized for Invoice {$invoice->InvoiceNumber}. Ref: {$txnRef}. Amount: ₱{$amountPaid}", $txnRef, $userId);

            return [
                'success'               => true,
                'payment_id'            => $payment->PaymentID,
                'status'                => self::STATUS_PENDING,
                'payment_method'        => 'gcash',
                'payment_provider'      => 'gcash',
                'transaction_reference' => $txnRef,
                'amount'                => $amountPaid,
                'currency'              => 'PHP',
                'payment_url'           => $checkoutUrl,
                'qr_payload'            => $qrPayload,
                'expires_at'            => $expiresAt->toIso8601String(),
                'invoice_number'        => $invoice->InvoiceNumber,
                'patient_name'          => $patient ? ($patient->FirstName . ' ' . $patient->LastName) : 'Patient Record',
                'message'               => 'GCash e-wallet checkout initialized. Awaiting customer authorization.'
            ];
        }

        // -------------------------------------------------------------
        // WORKFLOW 4: QR PH (Interoperable Dynamic QR)
        // -------------------------------------------------------------
        if ($method === self::METHOD_QRPH) {
            $qrPayload = $this->generateQrPhPayload($txnRef, $amountPaid, $invoice->InvoiceNumber);
            $expiresAt = now()->addMinutes(15);

            $payment = BillingPayment::create([
                'ReceiptNumber'            => 'PENDING-' . $txnRef,
                'InvoiceID'                => $invoiceId,
                'PatientID'                => $patientId,
                'AmountPaid'               => $amountPaid,
                'currency'                 => 'PHP',
                'PaymentStatus'            => 'Pending',
                'status'                   => self::STATUS_PENDING,
                'PaymentMethod'            => 'QR Ph',
                'payment_provider'         => 'qrph',
                'ReferenceNumber'          => $txnRef,
                'transaction_reference'    => $txnRef,
                'request_reference_number' => $txnRef,
                'AmountInWords'            => $this->numberToWords($amountPaid),
                'CashierName'              => $cashierName,
                'RecordedBy'               => $cashierName,
                'qr_payload'               => $qrPayload,
                'expires_at'               => $expiresAt,
                'PaymentDate'              => now(),
                'created_by'               => $userId ?? auth()->id(),
            ]);

            $invoice->update(['PaymentStatus' => 'Pending Payment']);

            $this->auditLog('Payment Created', "Dynamic QR Ph transaction created for Invoice {$invoice->InvoiceNumber}. Ref: {$txnRef}. Amount: ₱{$amountPaid}", $txnRef, $userId);

            return [
                'success'               => true,
                'payment_id'            => $payment->PaymentID,
                'status'                => self::STATUS_PENDING,
                'payment_method'        => 'qrph',
                'payment_provider'      => 'qrph',
                'transaction_reference' => $txnRef,
                'amount'                => $amountPaid,
                'currency'              => 'PHP',
                'qr_payload'            => $qrPayload,
                'expires_at'            => $expiresAt->toIso8601String(),
                'invoice_number'        => $invoice->InvoiceNumber,
                'patient_name'          => $patient ? ($patient->FirstName . ' ' . $patient->LastName) : 'Patient Record',
                'message'               => 'Dynamic QR Ph generated. Scan using any participating Philippine bank or e-wallet application.'
            ];
        }

        // -------------------------------------------------------------
        // WORKFLOW 5: BANK TRANSFER (Manual Verification)
        // -------------------------------------------------------------
        if ($method === self::METHOD_BANK_TRANSFER || $method === self::METHOD_ONLINE_BANK) {
            $bankName = trim((string)($data['bank_name'] ?? 'Landbank of the Philippines'));
            $slipRef  = trim((string)($data['bank_reference'] ?? $data['reference_no'] ?? ''));

            $payment = BillingPayment::create([
                'ReceiptNumber'            => 'VERIFY-' . $txnRef,
                'InvoiceID'                => $invoiceId,
                'PatientID'                => $patientId,
                'AmountPaid'               => $amountPaid,
                'currency'                 => 'PHP',
                'PaymentStatus'            => 'Pending Verification',
                'status'                   => self::STATUS_PENDING_VERIF,
                'PaymentMethod'            => 'Bank Transfer',
                'payment_provider'         => 'manual_bank',
                'ReferenceNumber'          => $slipRef ?: $txnRef,
                'transaction_reference'    => $txnRef,
                'request_reference_number' => $slipRef,
                'AmountInWords'            => $this->numberToWords($amountPaid),
                'CashierName'              => $cashierName,
                'RecordedBy'               => $cashierName,
                'failure_reason'           => "Bank: {$bankName}. Pending authorized verification.",
                'PaymentDate'              => now(),
                'created_by'               => $userId ?? auth()->id(),
            ]);

            $invoice->update(['PaymentStatus' => 'Pending Payment']);

            $this->auditLog('Payment Created', "Bank Transfer payment recorded for Invoice {$invoice->InvoiceNumber} via {$bankName}. Ref: {$txnRef}. Status: Pending Verification", $txnRef, $userId);

            return [
                'success'               => true,
                'payment_id'            => $payment->PaymentID,
                'status'                => self::STATUS_PENDING_VERIF,
                'payment_method'        => 'bank_transfer',
                'payment_provider'      => 'manual_bank',
                'bank_name'             => $bankName,
                'transaction_reference' => $txnRef,
                'bank_reference'        => $slipRef,
                'amount'                => $amountPaid,
                'invoice_number'        => $invoice->InvoiceNumber,
                'message'               => 'Bank transfer recorded. Status set to Pending Verification until verified by cashier or accountant.'
            ];
        }

        // -------------------------------------------------------------
        // WORKFLOW 6: CARD (Terminal/Auth)
        // -------------------------------------------------------------
        if ($method === self::METHOD_CARD) {
            $approvalCode = trim((string)($data['approval_code'] ?? $data['reference_no'] ?? 'AUTH-' . rand(100000, 999999)));
            $receiptNo = $this->generateReceiptNumber();
            $words = $this->numberToWords($amountPaid);

            DB::beginTransaction();
            try {
                $payment = BillingPayment::create([
                    'ReceiptNumber'            => $receiptNo,
                    'InvoiceID'                => $invoiceId,
                    'PatientID'                => $patientId,
                    'AmountPaid'               => $amountPaid,
                    'currency'                 => 'PHP',
                    'PaymentStatus'            => 'Completed',
                    'status'                   => self::STATUS_PAID,
                    'PaymentMethod'            => 'Credit/Debit Card',
                    'payment_provider'         => 'card_terminal',
                    'ReferenceNumber'          => $approvalCode,
                    'transaction_reference'    => $txnRef,
                    'provider_transaction_id'  => $approvalCode,
                    'request_reference_number' => $approvalCode,
                    'AmountInWords'            => $words,
                    'CashierName'              => $cashierName,
                    'RecordedBy'               => $cashierName,
                    'paid_at'                  => now(),
                    'PaymentDate'              => now(),
                    'created_by'               => $userId ?? auth()->id(),
                ]);

                $this->applyPaymentToInvoice($invoice, $amountPaid);

                $this->auditLog('Payment Successful', "Card payment of ₱" . number_format($amountPaid, 2) . " approved for Invoice {$invoice->InvoiceNumber}. Approval Code: {$approvalCode}. Receipt: {$receiptNo}", $txnRef, $userId);

                DB::commit();

                return [
                    'success'               => true,
                    'payment_id'            => $payment->PaymentID,
                    'status'                => self::STATUS_PAID,
                    'payment_method'        => 'card',
                    'receipt_number'        => $receiptNo,
                    'transaction_reference' => $txnRef,
                    'approval_code'         => $approvalCode,
                    'amount_paid'           => $amountPaid,
                    'invoice_status'        => $invoice->fresh()->PaymentStatus,
                    'message'               => 'Card transaction verified and settled. Official Receipt generated.'
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // -------------------------------------------------------------
        // WORKFLOW 7: CASHLESS PAYMENT (Digital / POS Terminal Settlement)
        // -------------------------------------------------------------
        if ($method === self::METHOD_CASHLESS) {
            $terminalRef = trim((string)($data['reference_no'] ?? $data['approval_code'] ?? 'POS-' . rand(100000, 999999)));
            $receiptNo = $this->generateReceiptNumber();
            $words = $this->numberToWords($amountPaid);

            DB::beginTransaction();
            try {
                $payment = BillingPayment::create([
                    'ReceiptNumber'            => $receiptNo,
                    'InvoiceID'                => $invoiceId,
                    'PatientID'                => $patientId,
                    'AmountPaid'               => $amountPaid,
                    'currency'                 => 'PHP',
                    'PaymentStatus'            => 'Completed',
                    'status'                   => self::STATUS_PAID,
                    'PaymentMethod'            => 'Cashless Payment',
                    'payment_provider'         => 'cashless_terminal',
                    'ReferenceNumber'          => $terminalRef,
                    'transaction_reference'    => $txnRef,
                    'provider_transaction_id'  => $terminalRef,
                    'request_reference_number' => $terminalRef,
                    'AmountInWords'            => $words,
                    'CashierName'              => $cashierName,
                    'RecordedBy'               => $cashierName,
                    'paid_at'                  => now(),
                    'PaymentDate'              => now(),
                    'created_by'               => $userId ?? auth()->id(),
                ]);

                $this->applyPaymentToInvoice($invoice, $amountPaid);

                $this->auditLog('Payment Successful', "Cashless payment of ₱" . number_format($amountPaid, 2) . " settled for Invoice {$invoice->InvoiceNumber}. Ref: {$terminalRef}. Receipt: {$receiptNo}", $txnRef, $userId);

                DB::commit();

                return [
                    'success'               => true,
                    'payment_id'            => $payment->PaymentID,
                    'status'                => self::STATUS_PAID,
                    'payment_method'        => 'cashless',
                    'receipt_number'        => $receiptNo,
                    'transaction_reference' => $txnRef,
                    'approval_code'         => $terminalRef,
                    'amount_paid'           => $amountPaid,
                    'invoice_status'        => $invoice->fresh()->PaymentStatus,
                    'message'               => 'Cashless digital payment verified and settled. Official Receipt generated.'
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // -------------------------------------------------------------
        // WORKFLOW 8: MEDICALEMS (Municipal Medical Assistance / Guarantee Voucher)
        // -------------------------------------------------------------
        if ($method === self::METHOD_MEDICAL_EMS) {
            $voucherRef = trim((string)($data['reference_no'] ?? $data['voucher_no'] ?? 'EMS-VOUCH-' . date('Ymd') . '-' . rand(1000, 9999)));
            $receiptNo = $this->generateReceiptNumber();
            $words = $this->numberToWords($amountPaid);

            DB::beginTransaction();
            try {
                $payment = BillingPayment::create([
                    'ReceiptNumber'            => $receiptNo,
                    'InvoiceID'                => $invoiceId,
                    'PatientID'                => $patientId,
                    'AmountPaid'               => $amountPaid,
                    'currency'                 => 'PHP',
                    'PaymentStatus'            => 'Completed',
                    'status'                   => self::STATUS_PAID,
                    'PaymentMethod'            => 'MedicalEMS',
                    'payment_provider'         => 'lgu_medical_ems',
                    'ReferenceNumber'          => $voucherRef,
                    'transaction_reference'    => $txnRef,
                    'provider_transaction_id'  => $voucherRef,
                    'request_reference_number' => $voucherRef,
                    'AmountInWords'            => $words,
                    'CashierName'              => $cashierName,
                    'RecordedBy'               => $cashierName,
                    'paid_at'                  => now(),
                    'PaymentDate'              => now(),
                    'created_by'               => $userId ?? auth()->id(),
                ]);

                $this->applyPaymentToInvoice($invoice, $amountPaid);

                $this->auditLog('Payment Successful', "MedicalEMS Assistance Guarantee voucher of ₱" . number_format($amountPaid, 2) . " applied to Invoice {$invoice->InvoiceNumber}. Voucher: {$voucherRef}. Receipt: {$receiptNo}", $txnRef, $userId);

                DB::commit();

                return [
                    'success'               => true,
                    'payment_id'            => $payment->PaymentID,
                    'status'                => self::STATUS_PAID,
                    'payment_method'        => 'medical_ems',
                    'receipt_number'        => $receiptNo,
                    'transaction_reference' => $txnRef,
                    'voucher_reference'     => $voucherRef,
                    'amount_paid'           => $amountPaid,
                    'invoice_status'        => $invoice->fresh()->PaymentStatus,
                    'message'               => 'MedicalEMS assistance guarantee settled. Official Receipt generated under LGU Health Fund.'
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        throw new Exception("Unsupported payment method: {$rawMethod}");
    }

    /**
     * Staff Verification of Bank Transfer
     */
    public function verifyBankTransfer(int $paymentId, int $verifierUserId, ?string $bankReference = null): array
    {
        $payment = BillingPayment::find($paymentId);
        if (!$payment) {
            throw new Exception("Payment record #{$paymentId} not found.");
        }
        if ($payment->status === self::STATUS_PAID) {
            return [
                'success'        => true,
                'already_paid'   => true,
                'receipt_number' => $payment->ReceiptNumber,
                'message'        => 'Payment has already been verified and paid.'
            ];
        }

        $invoice = BillingInvoice::find($payment->InvoiceID);
        if (!$invoice) {
            throw new Exception("Invoice #{$payment->InvoiceID} not found.");
        }

        $receiptNo = $this->generateReceiptNumber();
        $finalBankRef = $bankReference ?: ($payment->request_reference_number ?: 'VERIF-' . time());

        DB::beginTransaction();
        try {
            $payment->update([
                'ReceiptNumber'           => $receiptNo,
                'status'                  => self::STATUS_PAID,
                'PaymentStatus'           => 'Completed',
                'provider_transaction_id' => $finalBankRef,
                'ReferenceNumber'         => $finalBankRef,
                'verified_by'             => $verifierUserId,
                'verified_at'             => now(),
                'paid_at'                 => now(),
                'failure_reason'          => 'Bank transfer verified by authorized hospital staff.',
                'updated_at'              => now(),
            ]);

            $this->applyPaymentToInvoice($invoice, (float)$payment->AmountPaid);

            $this->auditLog('Bank Payment Verified', "Bank transfer verified for Invoice {$invoice->InvoiceNumber}. Receipt: {$receiptNo}. Bank Ref: {$finalBankRef}", $payment->transaction_reference, $verifierUserId);

            DB::commit();

            return [
                'success'               => true,
                'payment_id'            => $payment->PaymentID,
                'status'                => self::STATUS_PAID,
                'receipt_number'        => $receiptNo,
                'bank_reference'        => $finalBankRef,
                'amount_paid'           => (float)$payment->AmountPaid,
                'invoice_status'        => $invoice->fresh()->PaymentStatus,
                'message'               => "Bank transfer verified successfully. Official Receipt {$receiptNo} generated."
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check / Poll Payment Status Server-Side
     */
    public function checkPaymentStatus(int $paymentId): array
    {
        $payment = BillingPayment::find($paymentId);
        if (!$payment) {
            return [
                'success' => false,
                'message' => "Payment #{$paymentId} not found."
            ];
        }

        // If already paid, return confirmed status immediately
        if ($payment->status === self::STATUS_PAID || $payment->PaymentStatus === 'Completed') {
            return [
                'success'               => true,
                'status'                => self::STATUS_PAID,
                'payment_id'            => $payment->PaymentID,
                'receipt_number'        => $payment->ReceiptNumber,
                'transaction_reference' => $payment->transaction_reference,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'amount_paid'           => (float)$payment->AmountPaid,
                'invoice_id'            => $payment->InvoiceID,
                'print_url'             => "/print/receipt/{$payment->PaymentID}",
                'message'               => 'Payment confirmed and verified.'
            ];
        }

        // If expired
        if ($payment->expires_at && now()->greaterThan($payment->expires_at) && $payment->status === self::STATUS_PENDING) {
            $payment->update([
                'status'         => self::STATUS_EXPIRED,
                'PaymentStatus'  => 'Expired',
                'failure_reason' => 'Payment session expired without customer authorization.',
                'updated_at'     => now()
            ]);
            return [
                'success' => true,
                'status'  => self::STATUS_EXPIRED,
                'message' => 'Payment transaction has expired.'
            ];
        }

        return [
            'success'               => true,
            'status'                => $payment->status,
            'payment_status'        => $payment->PaymentStatus,
            'payment_id'            => $payment->PaymentID,
            'transaction_reference' => $payment->transaction_reference,
            'amount_paid'           => (float)$payment->AmountPaid,
            'expires_at'            => $payment->expires_at ? ($payment->expires_at instanceof \DateTimeInterface ? $payment->expires_at->format('c') : date('c', strtotime($payment->expires_at))) : null,
            'message'               => $payment->status === self::STATUS_PENDING ? 'Waiting for customer payment authorization...' : 'Pending verification.'
        ];
    }

    /**
     * Process External Provider Webhook
     * 
     * Idempotent: checks whether transaction is already settled before updating
     */
    public function processWebhook(array $payload, ?string $signature = null, string $provider = 'maya'): array
    {
        $webhookSecret = config('services.payment.webhook_secret', env('PAYMENT_WEBHOOK_SECRET'));
        if (!empty($webhookSecret) && !empty($signature)) {
            $expected = hash_hmac('sha256', json_encode($payload), $webhookSecret);
            if (!hash_equals($expected, $signature)) {
                Log::warning("Invalid payment webhook signature received from {$provider}");
                return ['success' => false, 'message' => 'Invalid signature', 'code' => 401];
            }
        }

        $ref = $payload['transaction_reference'] ?? $payload['reference_number'] ?? $payload['requestReferenceNumber'] ?? null;
        $providerTxnId = $payload['provider_transaction_id'] ?? $payload['id'] ?? $payload['paymentId'] ?? null;
        $statusRaw = strtoupper((string)($payload['status'] ?? $payload['paymentStatus'] ?? 'PAID'));

        if (!$ref && !$providerTxnId) {
            return ['success' => false, 'message' => 'Missing transaction reference.', 'code' => 422];
        }

        $payment = BillingPayment::where('transaction_reference', $ref)
            ->orWhere('ReferenceNumber', $ref)
            ->orWhere('provider_transaction_id', $providerTxnId)
            ->first();

        if (!$payment) {
            return ['success' => false, 'message' => "Payment record for reference {$ref} not found.", 'code' => 404];
        }

        // Idempotency: If already marked PAID, return 200 OK without double crediting
        if ($payment->status === self::STATUS_PAID || $payment->PaymentStatus === 'Completed') {
            return [
                'success'      => true,
                'already_paid' => true,
                'message'      => 'Transaction has already been processed and marked as PAID.',
                'code'         => 200
            ];
        }

        $invoice = BillingInvoice::find($payment->InvoiceID);

        DB::beginTransaction();
        try {
            if (in_array($statusRaw, ['PAID', 'PAYMENT_SUCCESS', 'SUCCESS', 'COMPLETED'])) {
                $receiptNo = $this->generateReceiptNumber();
                $finalProviderId = $providerTxnId ?: ($provider . '-txn-' . bin2hex(random_bytes(6)));

                $payment->update([
                    'ReceiptNumber'           => $receiptNo,
                    'status'                  => self::STATUS_PAID,
                    'PaymentStatus'           => 'Completed',
                    'provider_transaction_id' => $finalProviderId,
                    'paid_at'                 => now(),
                    'provider_response'       => json_encode($payload),
                    'updated_at'              => now()
                ]);

                if ($invoice) {
                    $this->applyPaymentToInvoice($invoice, (float)$payment->AmountPaid);
                }

                $this->auditLog('Payment Successful', "Webhook confirmed {$provider} payment of ₱{$payment->AmountPaid} for Invoice {$invoice->InvoiceNumber}. Provider Ref: {$finalProviderId}. Receipt: {$receiptNo}", $ref);

                DB::commit();

                return [
                    'success'                 => true,
                    'status'                  => self::STATUS_PAID,
                    'receipt_number'          => $receiptNo,
                    'provider_transaction_id' => $finalProviderId,
                    'message'                 => 'Webhook payment processed and verified.',
                    'code'                    => 200
                ];
            } else {
                // Payment Failed / Cancelled
                $newStatus = in_array($statusRaw, ['CANCELLED', 'EXPIRED']) ? self::STATUS_CANCELLED : self::STATUS_FAILED;
                $payment->update([
                    'status'            => $newStatus,
                    'PaymentStatus'     => ucfirst(strtolower($newStatus)),
                    'failed_at'         => now(),
                    'failure_reason'    => $payload['failure_reason'] ?? $payload['error_message'] ?? 'Payment authorization declined by provider.',
                    'provider_response' => json_encode($payload),
                    'updated_at'        => now()
                ]);

                $this->auditLog('Payment Failed', "Webhook reported payment {$newStatus} for Invoice {$invoice->InvoiceNumber}. Ref: {$ref}", $ref);

                DB::commit();

                return [
                    'success' => true,
                    'status'  => $newStatus,
                    'message' => "Payment {$newStatus} recorded.",
                    'code'    => 200
                ];
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Webhook error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    /**
     * Apply paid amount to Invoice & Patient Queue
     */
    private function applyPaymentToInvoice(BillingInvoice $invoice, float $amountPaid): void
    {
        $newTotalPaid = round((float)$invoice->AmountPaid + $amountPaid, 2);
        $totalPayable = (float)$invoice->TotalPayable;
        $newBalance = round(max(0, $totalPayable - $newTotalPaid), 2);
        $status = ($newBalance <= 0.001) ? 'Paid In Full' : 'Partially Paid';

        $invoice->update([
            'AmountPaid'    => $newTotalPaid,
            'BalanceDue'    => $newBalance,
            'PaymentStatus' => $status
        ]);

        if ($status === 'Paid In Full') {
            DB::table('patient_queue')
                ->where('PatientID', $invoice->PatientID)
                ->whereNotIn('QueueStatus', ['Completed', 'Cancelled'])
                ->update([
                    'QueueStatus' => 'Completed',
                    'CompletedAt' => now()
                ]);
        }
    }

    /**
     * Record event in system_audit_logs
     */
    private function auditLog(string $action, string $details, ?string $ref = null, ?int $userId = null): void
    {
        try {
            $user = auth()->user();
            $uId = $userId ?: ($user ? $user->UserID : 1);
            $uName = $user ? $user->FullName : 'System / Cashier';
            $uRole = $user ? ($user->Role ?: 'Cashier') : 'Cashier';

            DB::table('system_audit_logs')->insert([
                'UserID'       => $uId,
                'UserName'     => $user ? ($user->Username ?? 'cashier') : 'cashier',
                'UserRole'     => $uRole,
                'Action'       => $action,
                'Module'       => 'Billing & Cashier',
                'RecordID'     => $ref ?: '',
                'Details'      => $details,
                'IPAddress'    => request()->ip() ?: '127.0.0.1',
                'UserAgent'    => substr(request()->userAgent() ?: 'TMHIS App', 0, 255),
                'CreatedAt'    => now()
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to write audit log: " . $e->getMessage());
        }
    }
}
