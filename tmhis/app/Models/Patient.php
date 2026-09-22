<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';
    protected $primaryKey = 'PatientID';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $guarded = [];

    public function consultationNotes()
    {
        return $this->hasMany(ConsultationNote::class, 'PatientID', 'PatientID');
    }

    public function diagnoses()
    {
        return $this->hasMany(Diagnosis::class, 'PatientID', 'PatientID');
    }

    public function treatmentPlans()
    {
        return $this->hasMany(TreatmentPlan::class, 'PatientID', 'PatientID');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'PatientID', 'PatientID');
    }

    public function laboratoryRequests()
    {
        return $this->hasMany(LaboratoryRequest::class, 'PatientID', 'PatientID');
    }

    public function laboratoryResults()
    {
        return $this->hasMany(LaboratoryResult::class, 'PatientID', 'PatientID');
    }

    public function vitals()
    {
        return $this->hasMany(PatientVital::class, 'PatientID', 'PatientID');
    }

    public function queues()
    {
        return $this->hasMany(PatientQueue::class, 'PatientID', 'PatientID');
    }

    public function dispensingRecords()
    {
        return $this->hasMany(DispensingRecord::class, 'PatientID', 'PatientID');
    }

    public function billingCharges()
    {
        return $this->hasMany(BillingCharge::class, 'PatientID', 'PatientID');
    }

    public function billingInvoices()
    {
        return $this->hasMany(BillingInvoice::class, 'PatientID', 'PatientID');
    }

    public function billingPayments()
    {
        return $this->hasMany(BillingPayment::class, 'PatientID', 'PatientID');
    }

    public function medicalCertificates()
    {
        return $this->hasMany(MedicalCertificate::class, 'PatientID', 'PatientID');
    }

    public function allergies()
    {
        return $this->hasMany(AllergyRecord::class, 'PatientID', 'PatientID');
    }
}
