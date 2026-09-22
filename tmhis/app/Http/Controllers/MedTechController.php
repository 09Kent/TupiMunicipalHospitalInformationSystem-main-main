<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaboratoryRequest;
use App\Models\LaboratorySample;
use App\Models\LaboratoryResult;
use App\Models\TestCatalog;
use App\Models\ReferenceRange;
use App\Models\Patient;
use App\Models\SystemAuditLog;
use Illuminate\Support\Facades\Auth;

class MedTechController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('medtech.dashboard.index');
    }

    /**
     * Update Laboratory Test Request Status
     */
    public function updateRequestStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        $labReq = LaboratoryRequest::where('RequestID', $id)
            ->orWhere('RequestCode', $id)
            ->firstOrFail();

        $labReq->Status = $validated['status'];
        if (!empty($validated['remarks'])) {
            $labReq->ClinicalNotes = ($labReq->ClinicalNotes ? $labReq->ClinicalNotes . "\n" : '') . '[Status Update: ' . $validated['status'] . '] ' . $validated['remarks'];
        }
        $labReq->save();

        $this->logAudit($request, 'UPDATE_LAB_REQUEST_STATUS', "Updated lab request #{$labReq->RequestID} ({$labReq->RequestCode}) status to {$labReq->Status}", $labReq->RequestCode);

        return response()->json([
            'success' => true,
            'message' => "Request #{$labReq->RequestCode} status updated to {$labReq->Status}",
            'data' => $labReq
        ]);
    }

    /**
     * Create Sample Collection Record
     */
    public function createSample(Request $request)
    {
        $validated = $request->validate([
            'request_id' => 'required',
            'patient_id' => 'nullable|integer',
            'specimen_type' => 'required|string|max:100',
            'collected_by' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $labReq = LaboratoryRequest::where('RequestID', $validated['request_id'])
            ->orWhere('RequestCode', $validated['request_id'])
            ->first();

        $patientId = $validated['patient_id'] ?? ($labReq ? $labReq->PatientID : 1);
        $reqId = $labReq ? $labReq->RequestID : (is_numeric($validated['request_id']) ? $validated['request_id'] : 1);

        $barcode = 'SMP-2026-' . str_pad((string)(LaboratorySample::max('SampleID') + 1), 5, '0', STR_PAD_LEFT);

        $sample = LaboratorySample::create([
            'SampleBarcode' => $barcode,
            'RequestID' => $reqId,
            'PatientID' => $patientId,
            'SpecimenType' => $validated['specimen_type'],
            'CollectionDate' => now(),
            'CollectedBy' => $validated['collected_by'] ?? (Auth::user()->name ?? 'Medical Technologist'),
            'ProcessingStatus' => 'Received',
            'StorageLocation' => $validated['storage_location'] ?? 'Rack A-01',
            'Notes' => $validated['notes'] ?? 'Specimen collected and verified in laboratory.',
        ]);

        if ($labReq && $labReq->Status === 'Pending') {
            $labReq->Status = 'Received';
            $labReq->save();
        }

        SystemAuditLog::create([
            'UserID' => Auth::id() ?? 1,
            'Action' => 'CREATE_LAB_SAMPLE',
            'Details' => "Logged sample {$barcode} for request #{$reqId}",
            'IPAddress' => $request->ip(),
            'UserAgent' => $request->userAgent(),
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Sample record {$barcode} created successfully.",
            'data' => $sample
        ]);
    }

    /**
     * Update Sample Processing Status
     */
    public function updateSampleStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $sample = LaboratorySample::where('SampleID', $id)
            ->orWhere('SampleBarcode', $id)
            ->firstOrFail();

        $sample->ProcessingStatus = $validated['status'];
        if (!empty($validated['notes'])) {
            $sample->Notes = ($sample->Notes ? $sample->Notes . "\n" : '') . '[Status Update: ' . $validated['status'] . '] ' . $validated['notes'];
        }
        $sample->save();

        SystemAuditLog::create([
            'UserID' => Auth::id() ?? 1,
            'Action' => 'UPDATE_LAB_SAMPLE_STATUS',
            'Details' => "Updated specimen {$sample->SampleBarcode} status to {$sample->ProcessingStatus}",
            'IPAddress' => $request->ip(),
            'UserAgent' => $request->userAgent(),
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Specimen {$sample->SampleBarcode} status updated to {$sample->ProcessingStatus}",
            'data' => $sample
        ]);
    }

    /**
     * Create Laboratory Test Result
     */
    public function createResult(Request $request)
    {
        $validated = $request->validate([
            'request_id' => 'required',
            'test_name' => 'required|string|max:150',
            'result_value' => 'required|string|max:255',
            'normal_range' => 'required|string|max:150',
            'units' => 'nullable|string|max:50',
            'interpretation' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $labReq = LaboratoryRequest::where('RequestID', $validated['request_id'])
            ->orWhere('RequestCode', $validated['request_id'])
            ->first();

        $reqId = $labReq ? $labReq->RequestID : (is_numeric($validated['request_id']) ? $validated['request_id'] : 1);
        $patientId = $labReq ? $labReq->PatientID : 1;
        $doctorId = $labReq ? $labReq->DoctorID : 1;

        $result = LaboratoryResult::create([
            'RequestID' => $reqId,
            'PatientID' => $patientId,
            'DoctorID' => $doctorId,
            'TestName' => $validated['test_name'],
            'ResultValue' => $validated['result_value'],
            'NormalRange' => $validated['normal_range'],
            'Units' => $validated['units'] ?? '',
            'Interpretation' => $validated['interpretation'],
            'Notes' => $validated['notes'] ?? 'Diagnostic evaluation verified by laboratory.',
            'ResultDate' => now()->toDateString(),
            'CreatedAt' => now(),
        ]);

        if ($labReq) {
            $labReq->Status = 'Completed';
            $labReq->save();
        }

        SystemAuditLog::create([
            'UserID' => Auth::id() ?? 1,
            'Action' => 'CREATE_LAB_RESULT',
            'Details' => "Recorded laboratory result for {$validated['test_name']} on request #{$reqId}",
            'IPAddress' => $request->ip(),
            'UserAgent' => $request->userAgent(),
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Laboratory test result for {$validated['test_name']} recorded successfully.",
            'data' => $result
        ]);
    }

    /**
     * Update Laboratory Test Result
     */
    public function updateResult(Request $request, $id)
    {
        $validated = $request->validate([
            'result_value' => 'nullable|string|max:255',
            'interpretation' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $result = LaboratoryResult::where('ResultID', $id)
            ->orWhere('ResultID', str_replace('RES-2026-', '', $id))
            ->firstOrFail();

        if (isset($validated['result_value'])) {
            $result->ResultValue = $validated['result_value'];
        }
        if (isset($validated['interpretation'])) {
            $result->Interpretation = $validated['interpretation'];
        }
        if (isset($validated['notes'])) {
            $result->Notes = $validated['notes'];
        }
        $result->save();

        SystemAuditLog::create([
            'UserID' => Auth::id() ?? 1,
            'Action' => 'UPDATE_LAB_RESULT',
            'Details' => "Amended laboratory result #{$result->ResultID}",
            'IPAddress' => $request->ip(),
            'UserAgent' => $request->userAgent(),
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Laboratory result #{$result->ResultID} updated successfully.",
            'data' => $result
        ]);
    }

    /**
     * Maintain Test Catalog: Create
     */
    public function createCatalog(Request $request)
    {
        $validated = $request->validate([
            'test_name' => 'required|string|max:200',
            'category' => 'required|string|max:100',
            'specimen_type' => 'required|string|max:100',
            'turnaround_time' => 'nullable|string|max:50',
            'standard_price' => 'required|numeric|min:0',
        ]);

        $nextId = (int)TestCatalog::max('CatalogID') + 1;
        $code = 'TST-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);

        $catalog = TestCatalog::create([
            'TestCode' => $code,
            'TestName' => $validated['test_name'],
            'Category' => $validated['category'],
            'SpecimenType' => $validated['specimen_type'],
            'TurnaroundTime' => $validated['turnaround_time'] ?? '1-2 Hours',
            'StandardPrice' => $validated['standard_price'],
            'Status' => 'Active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Test catalog entry '{$catalog->TestName}' created successfully.",
            'data' => $catalog
        ]);
    }

    /**
     * Maintain Test Catalog: Update
     */
    public function updateCatalog(Request $request, $id)
    {
        $validated = $request->validate([
            'test_name' => 'required|string|max:200',
            'category' => 'required|string|max:100',
            'specimen_type' => 'required|string|max:100',
            'turnaround_time' => 'nullable|string|max:50',
            'standard_price' => 'required|numeric|min:0',
            'status' => 'nullable|string|max:100',
        ]);

        $item = TestCatalog::where('CatalogID', $id)
            ->orWhere('TestCode', $id)
            ->firstOrFail();

        $item->TestName = $validated['test_name'];
        $item->Category = $validated['category'];
        $item->SpecimenType = $validated['specimen_type'];
        $item->TurnaroundTime = $validated['turnaround_time'] ?? $item->TurnaroundTime;
        $item->StandardPrice = $validated['standard_price'];
        if (!empty($validated['status'])) {
            $item->Status = $validated['status'];
        }
        $item->save();

        return response()->json([
            'success' => true,
            'message' => "Catalog entry '{$item->TestName}' updated.",
            'data' => $item
        ]);
    }

    /**
     * Maintain Test Catalog: Toggle Status (Activate / Deactivate)
     */
    public function toggleCatalog(Request $request, $id)
    {
        $item = TestCatalog::where('CatalogID', $id)
            ->orWhere('TestCode', $id)
            ->firstOrFail();

        $item->Status = ($item->Status === 'Active') ? 'Inactive' : 'Active';
        $item->save();

        return response()->json([
            'success' => true,
            'message' => "Test catalog entry '{$item->TestName}' is now {$item->Status}.",
            'data' => $item
        ]);
    }

    /**
     * Update Reference Range
     */
    public function updateRange(Request $request, $id)
    {
        $validated = $request->validate([
            'parameter_name' => 'required|string|max:100',
            'male_range' => 'required|string|max:100',
            'female_range' => 'required|string|max:100',
            'unit' => 'required|string|max:50',
            'critical_low' => 'nullable|string|max:50',
            'critical_high' => 'nullable|string|max:50',
        ]);

        $range = ReferenceRange::find($id);
        if (!$range) {
            $range = ReferenceRange::where('ParameterName', $validated['parameter_name'])->first();
        }

        if ($range) {
            $range->update([
                'MaleRange' => $validated['male_range'],
                'FemaleRange' => $validated['female_range'],
                'Unit' => $validated['unit'],
                'CriticalLow' => $validated['critical_low'],
                'CriticalHigh' => $validated['critical_high'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Reference range updated successfully.",
            'data' => $range
        ]);
    }

    /**
     * Printable Diagnostic Laboratory Report
     */
    public function printReport($id)
    {
        $cleanId = is_numeric($id) ? (int)$id : (int)str_replace('RES-2026-', '', $id);
        $result = LaboratoryResult::with(['patient'])->find($cleanId);

        if (!$result) {
            $result = LaboratoryResult::with(['patient'])->first();
        }

        $patient = $result ? $result->patient : Patient::first();
        $request = $result ? LaboratoryRequest::find($result->RequestID) : null;

        return view('medtech.report_print', compact('result', 'patient', 'request'));
    }

    private function logAudit(Request $request, string $action, string $details, ?string $recordId = null): void
    {
        try {
            SystemAuditLog::create([
                'UserID' => Auth::id() ?? 1,
                'Action' => $action,
                'Details' => $details . ($recordId ? " [Record ID: {$recordId}]" : ''),
                'IPAddress' => $request->ip(),
                'UserAgent' => $request->userAgent(),
                'CreatedAt' => now()
            ]);
        } catch (\Throwable $e) {
            // Non-blocking audit failure
        }
    }
}

