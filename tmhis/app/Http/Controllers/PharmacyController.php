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

        if ($med) {
            $med->update([
                'GenericName' => $validated['generic_name'],
                'BrandName'   => $validated['brand_name'] ?? $med->BrandName,
                'Category'    => $validated['category'] ?? $med->Category,
                'DosageForm'  => $validated['dosage_form'] ?? $med->DosageForm,
                'Strength'    => $validated['strength'] ?? $med->Strength,
                'Status'      => $validated['status'] ?? $med->Status,
                'UpdatedAt'   => now(),
            ]);
        }

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

        $newStatus = 'Inactive';
        if ($med) {
            $newStatus = ($med->Status === 'Active' || $med->Status === 'In Stock') ? 'Inactive' : 'Active';
            $med->Status = $newStatus;
            $med->UpdatedAt = now();
            $med->save();
        }

        return response()->json([
            'success' => true,
            'new_status' => $newStatus,
            'message' => "Medicine formulary status changed to {$newStatus}."
        ]);
    }
}
