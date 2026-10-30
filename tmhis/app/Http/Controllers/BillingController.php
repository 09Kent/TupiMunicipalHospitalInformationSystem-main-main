<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Helpers\LegacyBridge;
use PDO;

require_once app_path('Services/Accountant/BillingManager.php');
require_once app_path('Helpers/legacy_bridge.php');

class BillingController extends Controller
{
    private \BillingManager $billingManager;
    private \App\Services\Billing\PaymentGatewayService $paymentService;

    public function __construct()
    {
        $this->billingManager = new \BillingManager();
        $this->paymentService = new \App\Services\Billing\PaymentGatewayService();
    }

    public function dashboard(Request $request)
    {
        $billingData = $this->buildLiveBillingData();
        return view('accountant.dashboard', compact('billingData'));
    }

    public function apiData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->buildLiveBillingData()
        ]);
    }

    public function createCharge(Request $request): JsonResponse
    {
        $request->validate([
            'PatientID'       => 'required|integer',
            'ItemDescription' => 'required|string',
            'UnitPrice'       => 'required|numeric|min:0',
            'Quantity'        => 'required|integer|min:1'
        ]);

        try {
            $chargeId = $this->billingManager->createCharge($request->all());
            return response()->json([
                'success'   => true,
                'charge_id' => $chargeId,
                'message'   => 'Service charge created successfully.'
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to create service charge.')
            ], 500);
        }
    }

    public function computeDiscounts(Request $request): JsonResponse
    {
        $gross = (float)($request->input('gross_amount') ?? $request->input('subtotal') ?? $request->input('amount') ?? 0.0);
        $qualifications = (array)$request->input('qualifications', []);
        $discountType = $request->input('discount_type');
        if ($discountType && empty($qualifications)) {
            $qualifications[] = $discountType;
        }
        $philhealth = (float)($request->input('philhealth_deduction') ?? $request->input('philhealth') ?? 0.0);
        $otherDeductions = (float)($request->input('other_deductions') ?? $request->input('other_deduction') ?? 0.0);

        $result = $this->billingManager->computeDiscounts($gross, $qualifications, $philhealth, $otherDeductions);

        return response()->json([
            'success' => true,
            'data'    => $result
        ]);
    }

    public function processPayment(Request $request): JsonResponse
    {
        $patientId = (int)($request->input('PatientID') ?? $request->input('patient_id') ?? 0);
        $amountPaid = (float)($request->input('AmountPaid') ?? $request->input('amount_paid') ?? $request->input('amount') ?? 0);
        $invoiceId = (int)($request->input('InvoiceID') ?? $request->input('invoice_id') ?? 0);

        if ($patientId <= 0 && ($request->filled('patient_code') || $request->filled('patient_id_str'))) {
            $code = $request->input('patient_code') ?? $request->input('patient_id_str');
            $pRow = DB::table('patients')->where('PatientCode', $code)->first();
            if ($pRow) $patientId = (int)$pRow->PatientID;
        }

        if ($invoiceId <= 0 && $request->filled('invoice_number')) {
            $invNum = $request->input('invoice_number');
            $iRow = DB::table('billing_invoices')->where('InvoiceNumber', $invNum)->first();
            if ($iRow) $invoiceId = (int)$iRow->InvoiceID;
        }

        if ($patientId <= 0 || $amountPaid <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Valid PatientID and AmountPaid greater than 0 are required.'
            ], 422);
        }

        // If no invoice ID provided, generate invoice dynamically from patient charges
        if ($invoiceId <= 0) {
            $gross = (float)($request->input('gross_amount') ?? $amountPaid);
            $totalPayable = (float)($request->input('total_payable') ?? $amountPaid);
            $rawDiscount = (string)($request->input('discount_type') ?? 'None');
            if (stripos($rawDiscount, 'senior') !== false) {
                $discountType = 'Senior Citizen (20%)';
            } elseif (stripos($rawDiscount, 'pwd') !== false) {
                $discountType = 'PWD (20%)';
            } elseif (stripos($rawDiscount, 'indigent') !== false || stripos($rawDiscount, 'barangay') !== false) {
                $discountType = 'Indigent / Barangay (100%)';
            } elseif (stripos($rawDiscount, 'employee') !== false) {
                $discountType = 'Hospital Employee (50%)';
            } else {
                $discountType = 'None';
            }
            $discountAmt = (float)($request->input('discount_amt') ?? $request->input('discount_amount') ?? 0.0);

            $inv = \App\Models\BillingInvoice::create([
                'InvoiceNumber'   => 'INV-' . time() . '-' . rand(100, 999),
                'PatientID'       => $patientId,
                'GrossAmount'     => $gross,
                'DiscountType'    => $discountType,
                'DiscountAmount'  => $discountAmt,
                'TotalPayable'    => $totalPayable,
                'AmountPaid'      => 0.00,
                'BalanceDue'      => $totalPayable,
                'PaymentStatus'   => 'Unpaid',
                'DueDate'         => date('Y-m-d', strtotime('+30 days')),
                'BilledBy'        => auth()->user() ? auth()->user()->FullName : 'Maria Santos (Cashier)',
            ]);
            $invoiceId = $inv->InvoiceID;
        }

        // Mark attached charge IDs as Billed
        if ($request->has('charge_ids')) {
            $cIds = (array)$request->input('charge_ids');
            \App\Models\BillingCharge::whereIn('ChargeID', $cIds)->update(['BillingStatus' => 'Billed']);
        }

        $params = $request->all();
        $params['InvoiceID'] = $invoiceId;
        $params['PatientID'] = $patientId;
        $params['AmountPaid'] = $amountPaid;

        try {
            $result = $this->paymentService->initiatePayment($params, auth()->id());
            $result['invoice_id'] = $invoiceId;
            $invObj = \App\Models\BillingInvoice::find($invoiceId);
            $result['invoice_number'] = $invObj ? $invObj->InvoiceNumber : null;

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to process payment transaction.')
            ], 422);
        }
    }

    public function getPaymentStatus(int $id): JsonResponse
    {
        try {
            $status = $this->paymentService->checkPaymentStatus($id);
            return response()->json($status);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to retrieve payment status.')
            ], 500);
        }
    }

    public function verifyBankPayment(Request $request, int $id): JsonResponse
    {
        $verifierId = auth()->id() ?? 1;
        $bankRef = $request->input('bank_reference') ?? $request->input('reference_no');

        try {
            $result = $this->paymentService->verifyBankTransfer($id, $verifierId, $bankRef);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to verify bank transfer.')
            ], 422);
        }
    }

    public function simulateProviderCallback(Request $request, int $id): JsonResponse
    {
        // Sandbox feature: simulates customer authorizing Maya / GCash / QR Ph in mobile app
        $payment = \App\Models\BillingPayment::find($id);
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        $outcome = $request->input('outcome', 'PAID');
        $mockProviderId = strtoupper($payment->payment_provider) . '-TXN-' . strtoupper(bin2hex(random_bytes(6)));

        $payload = [
            'transaction_reference'   => $payment->transaction_reference,
            'provider_transaction_id' => $mockProviderId,
            'status'                  => $outcome,
            'failure_reason'          => $outcome !== 'PAID' ? 'Insufficient customer balance in e-wallet.' : null,
            'simulated_at'            => now()->toIso8601String()
        ];

        $res = $this->paymentService->processWebhook($payload, null, $payment->payment_provider);
        return response()->json($res);
    }

    public function handleWebhook(Request $request, string $provider = 'maya'): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Signature') ?? $request->header('X-PayMaya-Signature') ?? $request->header('Signature');

        $result = $this->paymentService->processWebhook($payload, $signature, $provider);
        $code = $result['code'] ?? ($result['success'] ? 200 : 400);
        return response()->json($result, $code);
    }

    public function aggregateUnbilled(int $patientId): JsonResponse
    {
        try {
            $added = $this->billingManager->aggregateUnbilledCharges($patientId);
            return response()->json([
                'success' => true,
                'added'   => $added,
                'count'   => count($added),
                'message' => count($added) . ' unbilled cross-module charges aggregated.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to aggregate unbilled charges.')
            ], 500);
        }
    }

    private function buildLiveBillingData(): array
    {
        $db = \Database::getConnection();

        // 1. Live Patients
        $stmtPat = $db->query("
            SELECT PatientID, PatientCode, FirstName, LastName, Age, Gender, ContactNumber, PatientCategory 
            FROM patients 
            ORDER BY PatientID ASC
        ");
        $patientsList = [];
        while ($p = $stmtPat->fetch(PDO::FETCH_ASSOC)) {
            $patientsList[] = [
                'id'        => $p['PatientCode'] ?: ('P-2026-' . str_pad((string)$p['PatientID'], 3, '0', STR_PAD_LEFT)),
                'name'      => $p['FirstName'] . ' ' . $p['LastName'],
                'age'       => (int)$p['Age'],
                'gender'    => $p['Gender'],
                'room'      => ($p['PatientCategory'] === 'Inpatient' ? 'Room ' . (300 + $p['PatientID'] % 20) : 'OPD Triage'),
                'physician' => 'Dr. Roberto Mendoza, MD',
                'contact'   => $p['ContactNumber'] ?: '0917-555-0101',
                'address'   => 'Tupi, South Cotabato'
            ];
        }

        // 2. Live Charges
        $chargesList = [];
        $rawCharges = $this->billingManager->getCharges();
        foreach ($rawCharges as $c) {
            $chargesList[] = [
                'id'          => 'CHG-2026-' . str_pad((string)$c['ChargeID'], 3, '0', STR_PAD_LEFT),
                'patientId'   => $c['PatientCode'] ?: ('P-2026-' . str_pad((string)$c['PatientID'], 3, '0', STR_PAD_LEFT)),
                'patientName' => $c['FirstName'] . ' ' . $c['LastName'],
                'category'    => $c['ChargeCategory'],
                'serviceName' => $c['ItemDescription'],
                'department'  => $c['ChargeCategory'],
                'quantity'    => (int)$c['Quantity'],
                'unitPrice'   => (float)$c['UnitPrice'],
                'total'       => (float)$c['NetAmount'],
                'date'        => date('M d, Y', strtotime($c['CreatedAt'] ?: 'now')),
                'status'      => $c['BillingStatus'],
                'notes'       => $c['ItemDescription']
            ];
        }

        // 3. Live Invoices / Bills
        $billsList = [];
        $invoicesList = [];
        $rawInvoices = $this->billingManager->getInvoices();
        foreach ($rawInvoices as $inv) {
            $billObj = [
                'billId'              => 'BILL-2026-' . str_pad((string)$inv['InvoiceID'], 3, '0', STR_PAD_LEFT),
                'id'                  => $inv['InvoiceNumber'] ?: ('INV-2026-' . str_pad((string)$inv['InvoiceID'], 4, '0', STR_PAD_LEFT)),
                'invoiceId'           => $inv['InvoiceNumber'] ?: ('INV-2026-' . str_pad((string)$inv['InvoiceID'], 4, '0', STR_PAD_LEFT)),
                'patientId'           => $inv['PatientCode'] ?: ('P-2026-' . str_pad((string)$inv['PatientID'], 3, '0', STR_PAD_LEFT)),
                'patientName'         => $inv['FirstName'] . ' ' . $inv['LastName'],
                'date'                => date('M d, Y', strtotime($inv['CreatedAt'] ?? 'now')),
                'subtotal'            => (float)$inv['GrossAmount'],
                'discountType'        => $inv['DiscountType'] ?: 'None',
                'discountRate'        => ($inv['GrossAmount'] > 0) ? round(((float)$inv['DiscountAmount'] / (float)$inv['GrossAmount']), 2) : 0,
                'discountAmount'      => (float)$inv['DiscountAmount'],
                'discountReason'      => $inv['DiscountType'] ? "Statutory {$inv['DiscountType']} Discount" : '',
                'authorizedBy'        => 'Billing Staff',
                'totalBill'           => (float)$inv['TotalPayable'],
                'amountPaid'          => (float)$inv['AmountPaid'],
                'outstandingBalance'  => (float)$inv['BalanceDue'],
                'status'              => strtoupper($inv['PaymentStatus']),
                'dueDate'             => !empty($inv['DueDate']) ? date('M d, Y', strtotime($inv['DueDate'])) : date('M d, Y', strtotime('+3 days', strtotime($inv['CreatedAt'] ?? 'now'))),
                'items'               => [
                    [
                        'service'    => 'Hospital Inpatient & Diagnostic Services',
                        'department' => 'Clinical Services',
                        'quantity'   => 1,
                        'unitPrice'  => (float)$inv['GrossAmount'],
                        'amount'     => (float)$inv['GrossAmount']
                    ]
                ]
            ];
            $billsList[] = $billObj;
            $invoicesList[] = $billObj;
        }

        // 4. Live Payments & Official Receipts
        $paymentsList = [];
        $receiptsList = [];
        $rawPayments = $this->billingManager->getPayments();
        foreach ($rawPayments as $pmt) {
            $pmtObj = [
                'id'              => 'PMT-2026-' . str_pad((string)$pmt['PaymentID'], 3, '0', STR_PAD_LEFT),
                'receiptId'       => $pmt['ReceiptNumber'],
                'orNumber'        => $pmt['ReceiptNumber'],
                'billId'          => $pmt['InvoiceNumber'],
                'invoiceNumber'   => $pmt['InvoiceNumber'],
                'patientId'       => $pmt['PatientCode'],
                'patientName'     => $pmt['FirstName'] . ' ' . $pmt['LastName'],
                'amountPaid'      => (float)$pmt['AmountPaid'],
                'amount'          => (float)$pmt['AmountPaid'],
                'method'          => $pmt['PaymentMethod'],
                'paymentMethod'   => $pmt['PaymentMethod'],
                'reference'       => $pmt['ReferenceNumber'] ?: 'N/A',
                'referenceNumber' => $pmt['ReferenceNumber'] ?: 'N/A',
                'date'            => date('M d, Y', strtotime($pmt['PaymentDate'] ?: 'now')),
                'time'            => date('h:i A', strtotime($pmt['PaymentDate'] ?: 'now')),
                'cashier'         => $pmt['CashierName'],
                'cashierName'     => $pmt['CashierName'],
                'notes'           => $pmt['AmountInWords'] ?: 'Payment processed and verified'
            ];
            $paymentsList[] = $pmtObj;
            $receiptsList[] = $pmtObj;
        }

        // 5. Compute Live Financial Metrics
        $chargesToday = array_sum(array_column($chargesList, 'total'));
        $paymentsToday = array_sum(array_column($paymentsList, 'amountPaid'));
        $outstandingBal = array_sum(array_column($billsList, 'outstandingBalance'));
        $pendingBillsCount = count(array_filter($billsList, fn($b) => $b['status'] !== 'PAID' && $b['status'] !== 'PAID IN FULL'));

        $currUser = (class_exists('\Session') && method_exists('\Session', 'getCurrentUser')) ? \Session::getCurrentUser() : null;
        $authUser = \Illuminate\Support\Facades\Auth::user();
        if ($authUser) {
            $userName = trim(($authUser->FirstName ?? '') . ' ' . ($authUser->LastName ?? '')) ?: ($authUser->name ?? $authUser->Username ?? 'Billing Officer');
        } elseif ($currUser) {
            $userName = $currUser['name'] ?? trim(($currUser['first_name'] ?? '') . ' ' . ($currUser['last_name'] ?? '')) ?: 'Maria Santos';
        } else {
            $userName = 'Maria Santos';
        }

        return [
            'currentUser' => [
                'name'       => $userName,
                'role'       => 'Billing / Cashier Staff',
                'department' => 'Cashier & Patient Accounts Division',
                'id'         => 'EMP-CSH-2026-089',
                'avatar'     => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=256&auto=format&fit=crop',
                'shift'      => 'Morning Shift (7:00 AM - 3:00 PM)',
                'station'    => 'Counter 3 - Main Hospital Lobby'
            ],
            'metrics' => [
                'chargesToday'          => round($chargesToday, 2),
                'paymentsToday'         => round($paymentsToday, 2),
                'outstandingBalance'    => round($outstandingBal, 2),
                'pendingBills'          => $pendingBillsCount,
                'dailyCollectionTarget' => 125000,
                'targetPercentage'      => min(100, (int)round(($paymentsToday / 125000) * 100)),
                'paymentBreakdown'      => [
                    'cash'         => round($paymentsToday * 0.5, 2),
                    'card'         => round($paymentsToday * 0.2, 2),
                    'gcash'        => round($paymentsToday * 0.2, 2),
                    'bankTransfer' => round($paymentsToday * 0.1, 2)
                ]
            ],
            'kpis' => [
                'chargesToday'          => round($chargesToday, 2),
                'paymentsToday'         => round($paymentsToday, 2),
                'outstandingBalance'    => round($outstandingBal, 2),
                'pendingBills'          => $pendingBillsCount
            ],
            'patients'         => $patientsList,
            'charges'          => $chargesList,
            'bills'            => $billsList,
            'invoices'         => $invoicesList,
            'payments'         => $paymentsList,
            'officialReceipts' => $receiptsList,
            'recentActivities' => [
                ['action' => 'Payment Processed', 'time' => '10 mins ago', 'user' => $userName, 'details' => 'Official Receipt issued to patient'],
                ['action' => 'Charges Aggregated', 'time' => '25 mins ago', 'user' => 'System', 'details' => 'Cross-module charges linked to patient account']
            ],
            'notifications'    => [
                ['id' => 1, 'type' => 'info', 'title' => 'Cashier Register Active', 'message' => 'Live billing connection established with MedicalRegistrationDB.', 'time' => 'Just now', 'read' => false]
            ],
            'settings'         => [
                'taxRate'              => 0.00,
                'seniorDiscountRate'   => 0.20,
                'pwdDiscountRate'      => 0.20,
                'employeeDiscountRate' => 0.10
            ]
        ];
    }

    /**
     * POS Feature: Get active medicines for retail pharmacy sales
     */
    public function posMedicines(Request $request): JsonResponse
    {
        try {
            $query = DB::table('pharmacy_inventory')
                ->where('Status', '!=', 'Inactive');

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('ItemCode', 'like', "%{$search}%")
                      ->orWhere('GenericName', 'like', "%{$search}%")
                      ->orWhere('BrandName', 'like', "%{$search}%")
                      ->orWhere('DosageForm', 'like', "%{$search}%");
                });
            }

            if ($request->filled('dosage_form') && $request->input('dosage_form') !== 'All') {
                $query->where('DosageForm', $request->input('dosage_form'));
            }

            $medicines = $query->orderBy('GenericName', 'asc')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $medicines
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load medicines: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POS Feature: Search hospital patients
     */
    public function posPatients(Request $request): JsonResponse
    {
        try {
            $search = trim($request->input('search', ''));
            if (empty($search)) {
                $patients = DB::table('patients')
                    ->select('PatientID', 'PatientCode', 'FirstName', 'LastName', 'DateOfBirth', 'Gender', 'ContactNumber')
                    ->orderBy('PatientID', 'desc')
                    ->limit(20)
                    ->get();
            } else {
                $patients = DB::table('patients')
                    ->where(function ($q) use ($search) {
                        $q->where('PatientCode', 'like', "%{$search}%")
                          ->orWhere('FirstName', 'like', "%{$search}%")
                          ->orWhere('LastName', 'like', "%{$search}%")
                          ->orWhere('ContactNumber', 'like', "%{$search}%");
                    })
                    ->select('PatientID', 'PatientCode', 'FirstName', 'LastName', 'DateOfBirth', 'Gender', 'ContactNumber')
                    ->orderBy('PatientID', 'desc')
                    ->limit(20)
                    ->get();
            }

            $mapped = $patients->map(function ($p) {
                $age = 'N/A';
                if (!empty($p->DateOfBirth)) {
                    $age = \Carbon\Carbon::parse($p->DateOfBirth)->age . ' yrs';
                }
                return [
                    'PatientID' => $p->PatientID,
                    'PatientCode' => $p->PatientCode,
                    'FullName' => trim($p->FirstName . ' ' . $p->LastName),
                    'Age' => $age,
                    'Gender' => $p->Gender ?: 'Unspecified',
                    'ContactNumber' => $p->ContactNumber ?: 'None'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $mapped
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search patients: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POS Feature: Complete checkout transaction
     */
    public function posCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'customer_type'   => 'required|in:Walk-in,Patient,walk-in,patient',
            'patient_id'      => 'nullable|integer',
            'customer_name'   => 'nullable|string|max:150',
            'customer_phone'  => 'nullable|string|max:50',
            'items'           => 'required|array|min:1',
            'items.*.inventory_id' => 'required|integer|min:1',
            'items.*.quantity'     => 'required|integer|min:1',
            'discount_type'   => 'nullable|string|max:50',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method'  => 'required|string|max:50',
            'amount_received' => 'required|numeric|min:0',
            'reference_number'=> 'nullable|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        $customerType = (stripos($request->input('customer_type'), 'patient') !== false) ? 'Patient' : 'Walk-in';
        $patientId = null;
        $customerName = trim($request->input('customer_name') ?: '');

        if ($customerType === 'Patient') {
            $pId = (int)$request->input('patient_id', 0);
            if ($pId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient selection is required for patient customer type.'
                ], 422);
            }
            $patient = DB::table('patients')->where('PatientID', $pId)->first();
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => "Selected patient record #{$pId} does not exist."
                ], 404);
            }
            $patientId = $pId;
            if (empty($customerName)) {
                $customerName = trim($patient->FirstName . ' ' . $patient->LastName);
            }
        }

        if (empty($customerName)) {
            $customerName = 'Walk-in Customer';
        }

        $itemsInput = $request->input('items');
        $user = auth()->user();
        $cashierId = $user ? $user->UserID : 1;
        $cashierName = $user ? ($user->FullName ?: $user->Username) : 'Official Cashier';

        try {
            $sale = DB::transaction(function () use ($request, $customerType, $patientId, $customerName, $itemsInput, $cashierId, $cashierName) {
                // 1. Lock and validate inventory items
                $processedItems = [];
                $subTotal = 0.00;

                foreach ($itemsInput as $item) {
                    $invId = (int)$item['inventory_id'];
                    $qty = (int)$item['quantity'];

                    // Row-lock inventory to ensure atomicity & concurrency safety
                    $inventory = DB::table('pharmacy_inventory')
                        ->where('InventoryID', $invId)
                        ->lockForUpdate()
                        ->first();

                    if (!$inventory) {
                        throw new \Exception("Medicine inventory record #{$invId} was not found.");
                    }

                    if ($inventory->CurrentStock < $qty) {
                        $medName = $inventory->BrandName ? "{$inventory->BrandName} ({$inventory->GenericName})" : $inventory->GenericName;
                        throw new \Exception("Insufficient stock for {$medName}. Requested: {$qty}, Available: {$inventory->CurrentStock}.");
                    }

                    $unitPrice = (float)$inventory->SellingPrice;
                    $itemSubTotal = round($unitPrice * $qty, 2);
                    $subTotal += $itemSubTotal;

                    $processedItems[] = [
                        'inventory' => $inventory,
                        'unit_price' => $unitPrice,
                        'quantity' => $qty,
                        'sub_total' => $itemSubTotal
                    ];
                }

                // 2. Compute Discounts & Totals
                $discountType = $request->input('discount_type') ?: 'None';
                $requestedDiscount = (float)($request->input('discount_amount') ?? 0.00);

                if (stripos($discountType, 'senior') !== false || stripos($discountType, 'pwd') !== false) {
                    $discountAmount = round($subTotal * 0.20, 2);
                } elseif (stripos($discountType, 'employee') !== false) {
                    $discountAmount = round($subTotal * 0.10, 2);
                } else {
                    $discountAmount = min($subTotal, max(0.00, $requestedDiscount));
                }

                $totalAmount = max(0.00, round($subTotal - $discountAmount, 2));
                $paymentMethod = $request->input('payment_method');
                $amountReceived = (float)$request->input('amount_received');

                if (strcasecmp($paymentMethod, 'Cash') === 0) {
                    if ($amountReceived < $totalAmount) {
                        throw new \Exception("Insufficient cash amount received. Total due is ₱" . number_format($totalAmount, 2) . ", but received ₱" . number_format($amountReceived, 2) . ".");
                    }
                    $changeAmount = round($amountReceived - $totalAmount, 2);
                } else {
                    if ($amountReceived <= 0) {
                        $amountReceived = $totalAmount;
                    }
                    $changeAmount = 0.00;
                }

                // 3. Generate unique Receipt Number
                $datePrefix = date('Ymd');
                $latestToday = DB::table('pos_sales')
                    ->where('ReceiptNumber', 'like', "POS-{$datePrefix}-%")
                    ->orderBy('SaleID', 'desc')
                    ->value('ReceiptNumber');

                if ($latestToday && preg_match('/POS-\d+-(\d+)/', $latestToday, $matches)) {
                    $seq = (int)$matches[1] + 1;
                } else {
                    $seq = 1;
                }
                $receiptNumber = sprintf("POS-%s-%04d", $datePrefix, $seq);

                // 4. Create POS Sale Record
                $saleId = DB::table('pos_sales')->insertGetId([
                    'ReceiptNumber'   => $receiptNumber,
                    'CustomerType'    => $customerType,
                    'PatientID'       => $patientId,
                    'CustomerName'    => $customerName,
                    'CustomerPhone'   => $request->input('customer_phone'),
                    'SubTotal'        => $subTotal,
                    'DiscountType'    => $discountType,
                    'DiscountAmount'  => $discountAmount,
                    'TotalAmount'     => $totalAmount,
                    'AmountReceived'  => $amountReceived,
                    'ChangeAmount'    => $changeAmount,
                    'PaymentMethod'   => $paymentMethod,
                    'ReferenceNumber' => $request->input('reference_number'),
                    'CashierID'       => $cashierId,
                    'CashierName'     => $cashierName,
                    'Status'          => 'Completed',
                    'Notes'           => $request->input('notes'),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                // 5. Create Sale Items and Deduct Inventory
                foreach ($processedItems as $pItem) {
                    $inv = $pItem['inventory'];
                    $qty = $pItem['quantity'];

                    $medDisplayName = $inv->BrandName ? "{$inv->BrandName} ({$inv->GenericName})" : $inv->GenericName;

                    DB::table('pos_sale_items')->insert([
                        'SaleID'       => $saleId,
                        'InventoryID'  => $inv->InventoryID,
                        'ItemCode'     => $inv->ItemCode,
                        'MedicineName' => $medDisplayName,
                        'DosageForm'   => $inv->DosageForm,
                        'Strength'     => $inv->Strength,
                        'UnitPrice'    => $pItem['unit_price'],
                        'Quantity'     => $qty,
                        'SubTotal'     => $pItem['sub_total'],
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    // Deduct stock in pharmacy_inventory
                    $newStock = $inv->CurrentStock - $qty;
                    DB::table('pharmacy_inventory')
                        ->where('InventoryID', $inv->InventoryID)
                        ->update([
                            'CurrentStock' => $newStock,
                            'Status'       => ($newStock <= 0) ? 'Out of Stock' : (($newStock <= ($inv->ReorderLevel ?? 10)) ? 'Low Stock' : 'In Stock')
                        ]);

                    // Record stock movement
                    DB::table('pharmacy_stock_movements')->insert([
                        'InventoryID'   => $inv->InventoryID,
                        'MovementType'  => 'Dispensed',
                        'Quantity'      => -$qty,
                        'ReferenceCode' => $receiptNumber,
                        'RecordedBy'    => $cashierId,
                        'CreatedAt'     => now()
                    ]);
                }

                return \App\Models\PosSale::with('items', 'patient')->find($saleId);
            });

            return response()->json([
                'success'     => true,
                'message'     => 'POS sale completed successfully!',
                'receipt_url' => url('/print/pos-receipt/' . $sale->SaleID),
                'sale'        => $sale
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * POS Feature: Sales history with date and search filters
     */
    public function posSalesHistory(Request $request): JsonResponse
    {
        try {
            $query = \App\Models\PosSale::with(['items', 'patient'])
                ->orderBy('SaleID', 'desc');

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('ReceiptNumber', 'like', "%{$search}%")
                      ->orWhere('CustomerName', 'like', "%{$search}%")
                      ->orWhere('ReferenceNumber', 'like', "%{$search}%");
                });
            }

            if ($request->filled('customer_type') && $request->input('customer_type') !== 'All') {
                $query->where('CustomerType', $request->input('customer_type'));
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->input('end_date'));
            }

            $sales = $query->limit(50)->get();

            return response()->json([
                'success' => true,
                'data' => $sales
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sales history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POS Feature: Get single sale details
     */
    public function posReceipt(int $id): JsonResponse
    {
        try {
            $sale = \App\Models\PosSale::with(['items', 'patient'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $sale,
                'receipt_url' => url('/print/pos-receipt/' . $sale->SaleID)
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found: ' . $e->getMessage()
            ], 404);
        }
    }
}
