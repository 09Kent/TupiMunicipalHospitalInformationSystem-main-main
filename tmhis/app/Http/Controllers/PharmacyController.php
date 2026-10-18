<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PharmacyInventory;

class PharmacyController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('pharmacy.dashboard.index');
    }

    public function addCatalog(Request $request)
    {
        $validated = $request->validate([
            'generic_name' => 'required|string|max:191',
            'brand_name'   => 'nullable|string|max:191',
            'category'     => 'nullable|string|max:100',
            'dosage_form'  => 'nullable|string|max:100',
            'strength'     => 'nullable|string|max:100',
            'route'        => 'nullable|string|max:50',
            'initial_stock'=> 'nullable|numeric',
        ]);

        $count = PharmacyInventory::count() + 1;
        $itemCode = 'MED-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

        $stock = (int)($validated['initial_stock'] ?? 100);
        $med = PharmacyInventory::create([
            'ItemCode'     => $itemCode,
            'GenericName'  => $validated['generic_name'],
            'BrandName'    => $validated['brand_name'] ?? '',
            'Category'     => $validated['category'] ?? 'General',
            'DosageForm'   => $validated['dosage_form'] ?? 'Tablet',
            'Strength'     => $validated['strength'] ?? '',
            'SellingPrice' => 0.00,
            'CurrentStock' => $stock,
            'ReorderLevel' => 20,
            'BatchNumber'  => 'B-' . date('Y') . '-' . str_pad((string)rand(10, 99), 3, '0', STR_PAD_LEFT),
            'ExpiryDate'   => date('Y-m-d', strtotime('+2 years')),
            'Supplier'     => 'Hospital Formulary Stock',
            'Status'       => 'Active',
            'UpdatedAt'    => now(),
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Medicine added to formulary successfully.',
            'item_code' => $itemCode,
            'med_id'    => $itemCode,
            'data' => [
                'item_code'    => $itemCode,
                'med_id'       => $itemCode,
                'generic_name' => $med->GenericName,
                'brand_name'   => $med->BrandName,
                'category'     => $med->Category,
                'dosage_form'  => $med->DosageForm,
                'strength'     => $med->Strength,
                'route'        => $validated['route'] ?? 'Oral',
                'status'       => 'Active',
            ]
        ]);
    }

    public function updateCatalog(Request $request)
    {
        $validated = $request->validate([
            'med_id'       => 'required|string',
            'generic_name' => 'required|string|max:191',
            'brand_name'   => 'nullable|string|max:191',
            'category'     => 'nullable|string|max:100',
            'dosage_form'  => 'nullable|string|max:100',
            'strength'     => 'nullable|string|max:100',
            'route'        => 'nullable|string|max:50',
            'status'       => 'nullable|string|max:50',
        ]);

        $med = PharmacyInventory::where('ItemCode', $validated['med_id'])
            ->orWhere('GenericName', $validated['generic_name'])
            ->first();

        if (!$med) {
            return response()->json([
                'success' => false,
                'message' => 'Medicine formulary entry not found.'
            ], 404);
        }

        $med->update([
            'GenericName' => $validated['generic_name'],
            'BrandName'   => $validated['brand_name'] ?? $med->BrandName,
            'Category'    => $validated['category'] ?? $med->Category,
            'DosageForm'  => $validated['dosage_form'] ?? $med->DosageForm,
            'Strength'    => $validated['strength'] ?? $med->Strength,
            'Status'      => $validated['status'] ?? $med->Status,
            'UpdatedAt'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medicine formulary entry updated successfully.'
        ]);
    }

    public function toggleCatalogStatus(Request $request)
    {
        $medId = $request->input('med_id');
        $name = $request->input('generic_name');

        $med = PharmacyInventory::where('ItemCode', $medId)
            ->orWhere('GenericName', $name)
            ->first();

        if (!$med) {
            return response()->json([
                'success' => false,
                'message' => 'Medicine not found in pharmacy formulary.'
            ], 404);
        }

        $newStatus = ($med->Status === 'Active' || $med->Status === 'In Stock') ? 'Inactive' : 'Active';
        $med->Status = $newStatus;
        $med->UpdatedAt = now();
        $med->save();

        return response()->json([
            'success' => true,
            'new_status' => $newStatus,
            'message' => "Medicine formulary status changed to {$newStatus}."
        ]);
    }

    public function dispense(Request $request)
    {
        $validated = $request->validate([
            'PrescriptionID'     => 'required|integer',
            'PatientID'          => 'required|integer',
            'MedicineName'       => 'nullable|string',
            'QuantityDispensed'  => 'nullable|string',
            'InventoryID'        => 'nullable|integer',
            'DosageInstructions' => 'nullable|string',
            'BatchNumber'        => 'nullable|string',
            'Notes'              => 'nullable|string',
        ]);

        try {
            require_once app_path('Services/Pharmacy/PharmacyManager.php');
            $mgr = new \PharmacyManager();
            $validated['DispenserName'] = auth()->user()->FullName ?? 'Pharmacist on Duty';
            $dispenseId = $mgr->dispense($validated);

            // Advance queue status to 'For Billing'
            $patientId = (int)$validated['PatientID'];
            $queueRecord = \App\Models\PatientQueue::where('PatientID', $patientId)
                ->whereIn('QueueStatus', ['Pharmacy', 'Waiting for Consultation', 'In Consultation'])
                ->orderBy('QueueID', 'desc')
                ->first();
            if ($queueRecord) {
                $queueRecord->update(['QueueStatus' => 'For Billing']);
            }

            $dispenseRecord = \Illuminate\Support\Facades\DB::table('dispensing_records')->where('DispenseID', $dispenseId)->first();

            \App\Models\SystemAuditLog::create([
                'UserID'    => auth()->id() ?? 1,
                'Action'    => 'DISPENSE_MEDICATION',
                'Details'   => "Dispensed prescription #{$validated['PrescriptionID']} (Dispense Record #{$dispenseId}) for Patient #{$patientId}. Queue moved to For Billing.",
                'IPAddress' => $request->ip(),
                'UserAgent' => $request->userAgent(),
                'CreatedAt' => now()
            ]);

            return response()->json([
                'success'       => true,
                'dispense_id'   => $dispenseId,
                'dispense_code' => $dispenseRecord->DispenseCode ?? ("DISP-" . date('Ymd') . "-{$dispenseId}"),
                'message'       => 'Medication successfully dispensed and stock deducted (FEFO). Patient forwarded to Billing.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => safe_error_message($e, 'Failed to dispense medication.')
            ], 422);
        }
    }

    public function stockIn(Request $request)
    {
        $validated = $request->validate([
            'inventory_id' => 'nullable|integer',
            'item_code'    => 'nullable|string',
            'quantity'     => 'required|integer|min:1',
            'batch_number' => 'nullable|string',
            'expiry_date'  => 'nullable|date',
            'supplier'     => 'nullable|string',
        ]);

        $query = PharmacyInventory::query();
        if (!empty($validated['inventory_id'])) {
            $query->where('InventoryID', $validated['inventory_id']);
        } elseif (!empty($validated['item_code'])) {
            $query->where('ItemCode', $validated['item_code']);
        } else {
            $query->orderBy('InventoryID', 'asc');
        }

        $item = $query->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Medication inventory item not found.'], 404);
        }

        $newStock = $item->CurrentStock + $validated['quantity'];
        $item->CurrentStock = $newStock;
        if (!empty($validated['batch_number'])) $item->BatchNumber = $validated['batch_number'];
        if (!empty($validated['expiry_date'])) $item->ExpiryDate = $validated['expiry_date'];
        if (!empty($validated['supplier'])) $item->Supplier = $validated['supplier'];
        $item->Status = ($newStock > 0) ? 'In Stock' : $item->Status;
        $item->UpdatedAt = now();
        $item->save();

        \Illuminate\Support\Facades\DB::table('pharmacy_stock_movements')->insert([
            'InventoryID'   => $item->InventoryID,
            'MovementType'  => 'Restock',
            'Quantity'      => $validated['quantity'],
            'ReferenceCode' => 'STK-' . date('Ymd') . '-' . rand(1000, 9999),
            'RecordedBy'    => auth()->user()->FullName ?? 'Pharmacist on Duty',
            'CreatedAt'     => now()
        ]);

        return response()->json([
            'success'   => true,
            'new_stock' => $newStock,
            'message'   => "Stock-In recorded. New inventory stock is {$newStock}."
        ]);
    }

    public function dispose(Request $request)
    {
        $validated = $request->validate([
            'inventory_id' => 'nullable|integer',
            'item_code'    => 'nullable|string',
            'quantity'     => 'required|integer|min:1',
            'reason'       => 'nullable|string',
        ]);

        $query = PharmacyInventory::query();
        if (!empty($validated['inventory_id'])) {
            $query->where('InventoryID', $validated['inventory_id']);
        } elseif (!empty($validated['item_code'])) {
            $query->where('ItemCode', $validated['item_code']);
        } else {
            $query->orderBy('InventoryID', 'asc');
        }

        $item = $query->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Medication inventory item not found.'], 404);
        }

        $qty = min($item->CurrentStock, $validated['quantity']);
        $newStock = max(0, $item->CurrentStock - $qty);
        $item->CurrentStock = $newStock;
        if ($newStock <= 0) $item->Status = 'Out of Stock';
        $item->UpdatedAt = now();
        $item->save();

        \Illuminate\Support\Facades\DB::table('pharmacy_stock_movements')->insert([
            'InventoryID'   => $item->InventoryID,
            'MovementType'  => 'Disposed',
            'Quantity'      => -$qty,
            'ReferenceCode' => 'DSP-' . date('Ymd') . '-' . rand(1000, 9999),
            'RecordedBy'    => auth()->user()->FullName ?? 'Pharmacist on Duty',
            'CreatedAt'     => now()
        ]);

        return response()->json([
            'success'   => true,
            'new_stock' => $newStock,
            'message'   => "Disposal recorded. Remaining inventory stock is {$newStock}."
        ]);
    }
}
