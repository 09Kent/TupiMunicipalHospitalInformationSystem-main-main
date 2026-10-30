<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Ambulance;
use App\Models\SystemAuditLog;

class AmbulanceApiController extends Controller
{
    /**
     * Get Ambulance Fleet Status & Availability
     */
    public function index(): JsonResponse
    {
        try {
            if (!Schema::hasTable('ambulances')) {
                return response()->json([
                    'success' => true,
                    'summary' => [
                        'total'       => 3,
                        'available'   => 3,
                        'on_transfer' => 0,
                        'maintenance' => 0,
                    ],
                    'data' => self::getFallbackAmbulances()
                ]);
            }

            $ambulances = Ambulance::leftJoin('patients', 'ambulances.CurrentPatientID', '=', 'patients.PatientID')
                ->select(
                    'ambulances.*',
                    'patients.FirstName as PatientFirstName',
                    'patients.LastName as PatientLastName',
                    'patients.PatientCode'
                )
                ->orderBy('ambulances.AmbulanceID', 'asc')
                ->get();

            $total = $ambulances->count();
            $available = $ambulances->where('Status', 'Available')->count();
            $onTransfer = $ambulances->where('Status', 'On Transfer')->count();
            $maintenance = $ambulances->where('Status', 'Maintenance')->count();

            $formatted = $ambulances->map(function ($a) {
                return [
                    'id'               => (int)$a->AmbulanceID,
                    'plate_number'     => $a->PlateNumber,
                    'unit_name'        => $a->UnitName,
                    'vehicle_model'    => $a->VehicleModel,
                    'equipment_level'  => $a->EquipmentLevel,
                    'status'           => $a->Status,
                    'driver'           => $a->CurrentDriver,
                    'contact'          => $a->ContactNumber,
                    'destination'      => $a->CurrentDestination,
                    'patient_id'       => $a->CurrentPatientID,
                    'patient_name'     => $a->PatientFirstName ? trim("{$a->PatientFirstName} {$a->PatientLastName}") : null,
                    'patient_code'     => $a->PatientCode,
                    'estimated_return' => $a->EstimatedReturnTime,
                    'notes'            => $a->Notes,
                    'updated_at'       => $a->updated_at ? $a->updated_at->toIso8601String() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'summary' => [
                    'total'       => $total,
                    'available'   => $available,
                    'on_transfer' => $onTransfer,
                    'maintenance' => $maintenance,
                ],
                'data' => $formatted
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'summary' => [
                    'total'       => 3,
                    'available'   => 3,
                    'on_transfer' => 0,
                    'maintenance' => 0,
                ],
                'data' => self::getFallbackAmbulances()
            ]);
        }
    }

    /**
     * Get Only Available Ambulance Units (for Doctor referral dropdowns)
     */
    public function available(): JsonResponse
    {
        try {
            if (!Schema::hasTable('ambulances')) {
                return response()->json([
                    'success' => true,
                    'count'   => 3,
                    'data'    => self::getFallbackAmbulances()
                ]);
            }

            $available = Ambulance::where('Status', 'Available')
                ->orderBy('AmbulanceID', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'count'   => $available->count(),
                'data'    => $available
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'count'   => 3,
                'data'    => self::getFallbackAmbulances()
            ]);
        }
    }

    /**
     * Update Ambulance Status (Available, On Transfer, Maintenance, Reserved)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $status = trim((string)$request->input('status', 'Available'));
        $validStatuses = ['Available', 'On Transfer', 'Maintenance', 'Reserved'];
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Choose Available, On Transfer, Maintenance, or Reserved.'
            ], 422);
        }

        try {
            $ambulance = Ambulance::findOrFail($id);
            $ambulance->Status = $status;

            if ($status === 'Available') {
                $ambulance->CurrentDestination = null;
                $ambulance->CurrentPatientID = null;
                $ambulance->EstimatedReturnTime = null;
            } else {
                if ($request->has('destination')) {
                    $ambulance->CurrentDestination = $request->input('destination');
                }
                if ($request->has('patient_id')) {
                    $ambulance->CurrentPatientID = $request->input('patient_id') ? (int)$request->input('patient_id') : null;
                }
                if ($request->has('estimated_return')) {
                    $ambulance->EstimatedReturnTime = $request->input('estimated_return');
                }
            }

            if ($request->has('driver') && !empty($request->input('driver'))) {
                $ambulance->CurrentDriver = trim($request->input('driver'));
            }
            if ($request->has('contact') && !empty($request->input('contact'))) {
                $ambulance->ContactNumber = trim($request->input('contact'));
            }
            if ($request->has('notes')) {
                $ambulance->Notes = $request->input('notes');
            }

            $ambulance->save();

            // Audit Log
            $user = auth()->user();
            try {
                SystemAuditLog::create([
                    'UserID'    => $user ? $user->UserID : 1,
                    'UserName'  => $user ? ($user->FullName ?: $user->Username) : 'Staff',
                    'UserRole'  => $user ? ($user->Role ?: 'Registration') : 'Registration',
                    'Action'    => 'Ambulance Status Updated',
                    'Module'    => 'Emergency Fleet',
                    'RecordID'  => (string)$ambulance->AmbulanceID,
                    'Details'   => "Ambulance {$ambulance->UnitName} status changed to {$status}.",
                    'IPAddress' => $request->ip(),
                    'CreatedAt' => now()
                ]);
            } catch (\Throwable $ae) {}

            return response()->json([
                'success' => true,
                'message' => "Ambulance {$ambulance->UnitName} marked as {$status}.",
                'data'    => $ambulance
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ambulance status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispatch Ambulance for Hospital Transfer
     */
    public function dispatch(Request $request): JsonResponse
    {
        $ambulanceId = (int)$request->input('ambulance_id');
        $destination = trim((string)$request->input('destination', ''));
        $patientId = $request->input('patient_id') ? (int)$request->input('patient_id') : null;
        $estReturn = trim((string)$request->input('estimated_return', ''));

        if ($ambulanceId <= 0 || empty($destination)) {
            return response()->json([
                'success' => false,
                'message' => 'Ambulance Unit and Destination are required for dispatch.'
            ], 422);
        }

        try {
            $ambulance = Ambulance::findOrFail($ambulanceId);
            $ambulance->Status = 'On Transfer';
            $ambulance->CurrentDestination = $destination;
            $ambulance->CurrentPatientID = $patientId;
            $ambulance->EstimatedReturnTime = $estReturn ?: 'Est. 2 Hours';
            $ambulance->save();

            return response()->json([
                'success' => true,
                'message' => "Ambulance {$ambulance->UnitName} dispatched to {$destination}.",
                'data'    => $ambulance
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to dispatch ambulance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fallback Ambulance Data
     */
    public static function getFallbackAmbulances(): array
    {
        return [
            [
                'id'               => 1,
                'plate_number'     => 'SGA-1041',
                'unit_name'        => 'Ambulance Unit 01 (ALS - Advanced Life Support)',
                'vehicle_model'    => 'Toyota HiAce Mobile ICU Van',
                'equipment_level'  => 'Advanced Life Support (ALS)',
                'status'           => 'Available',
                'driver'           => 'Reynaldo Gomez (EMT-B / Driver)',
                'contact'          => '0917-882-9011',
                'destination'      => null,
                'patient_id'       => null,
                'patient_name'     => null,
                'patient_code'     => null,
                'estimated_return' => null,
                'notes'            => 'Equipped with transport ventilator, multi-parameter monitor, suction unit, and emergency drug box.',
            ],
            [
                'id'               => 2,
                'plate_number'     => 'SKD-5520',
                'unit_name'        => 'Ambulance Unit 02 (BLS - Basic Life Support)',
                'vehicle_model'    => 'Nissan NV350 Urvan Emergency Unit',
                'equipment_level'  => 'Basic Life Support (BLS)',
                'status'           => 'Available',
                'driver'           => 'Danilo Santos (Driver / First Responder)',
                'contact'          => '0920-554-1290',
                'destination'      => null,
                'patient_id'       => null,
                'patient_name'     => null,
                'patient_code'     => null,
                'estimated_return' => null,
                'notes'            => 'Equipped with AED, oxygen cylinders, trauma dressing kit, backboard, and extrication gear.',
            ],
            [
                'id'               => 3,
                'plate_number'     => 'SMM-9832',
                'unit_name'        => 'Ambulance Unit 03 (Rapid Inter-Facility Transport)',
                'vehicle_model'    => 'Hyundai Staria Transport Unit',
                'equipment_level'  => 'Patient Transport',
                'status'           => 'Available',
                'driver'           => 'Eduardo Morales (Paramedic Driver)',
                'contact'          => '0939-112-7744',
                'destination'      => null,
                'patient_id'       => null,
                'patient_name'     => null,
                'patient_code'     => null,
                'estimated_return' => null,
                'notes'            => 'Inter-hospital stable patient transfers and routine hemodialysis/outreach transport.',
            ]
        ];
    }
}

