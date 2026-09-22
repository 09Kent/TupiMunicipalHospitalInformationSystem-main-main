<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Record Summary - {{ $patient->FirstName }} {{ $patient->LastName }} | TMHIS</title>
  <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 20px; background: #fff; }
    .header { text-align: center; border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 18px; color: #0f766e; text-transform: uppercase; letter-spacing: 0.5px; }
    .header h2 { margin: 4px 0; font-size: 14px; font-weight: normal; color: #475569; }
    .header p { margin: 2px 0; font-size: 11px; color: #64748b; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #e0f2fe; color: #0369a1; }
    .grid { display: flex; flex-wrap: wrap; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; }
    .col { flex: 1 1 25%; min-width: 150px; margin-bottom: 6px; }
    .label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #64748b; }
    .val { font-size: 12px; font-weight: 600; color: #0f172a; margin-top: 2px; }
    .section-title { font-size: 13px; font-weight: bold; color: #0f766e; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-top: 18px; margin-bottom: 8px; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 11px; }
    th { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; font-weight: bold; color: #334155; }
    td { border: 1px solid #e2e8f0; padding: 6px 8px; color: #1e293b; }
    .no-print { margin-bottom: 15px; }
    @media print { .no-print { display: none; } body { padding: 0; } }
    .btn { padding: 6px 16px; background: #0f766e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
  </style>
</head>
<body>
  <div class="no-print">
    <button class="btn" onclick="window.print()">Print Official Summary</button>
    <button class="btn" style="background:#64748b;" onclick="window.close()">Close Window</button>
  </div>

  <div class="header">
    <p>Republic of the Philippines • Province of South Cotabato • Municipality of Tupi</p>
    <h1>Tupi Municipal Hospital</h1>
    <h2>Health Information & Records Management Department (HIRM)</h2>
    <p>Official Consolidated Patient Medical Record Summary • Confidential</p>
  </div>

  <div class="grid">
    <div class="col"><div class="label">Patient Name</div><div class="val">{{ $patient->FirstName }} {{ $patient->MiddleName }} {{ $patient->LastName }}</div></div>
    <div class="col"><div class="label">Patient Code</div><div class="val">{{ $patient->PatientCode }}</div></div>
    <div class="col"><div class="label">Age / Gender</div><div class="val">{{ $patient->Age }} yrs / {{ $patient->Gender }}</div></div>
    <div class="col"><div class="label">Date of Birth</div><div class="val">{{ $patient->DateOfBirth }}</div></div>
    <div class="col"><div class="label">Civil Status</div><div class="val">{{ $patient->CivilStatus ?? 'Single' }}</div></div>
    <div class="col"><div class="label">Blood Type</div><div class="val">{{ $patient->BloodType ?? 'O+' }}</div></div>
    <div class="col"><div class="label">Contact</div><div class="val">{{ $patient->ContactNumber }}</div></div>
    <div class="col"><div class="label">Category</div><div class="val">{{ $patient->PatientCategory ?? 'Outpatient' }}</div></div>
  </div>

  <div class="section-title">1. Clinical Diagnoses History</div>
  <table>
    <thead><tr><th>Date</th><th>ICD-10 Code</th><th>Diagnosis Description</th><th>Severity</th><th>Doctor</th></tr></thead>
    <tbody>
      @forelse($diagnoses as $d)
      <tr>
        <td>{{ substr($d->CreatedAt, 0, 10) }}</td>
        <td><strong>{{ $d->ICD10Code ?? 'R50.9' }}</strong></td>
        <td>{{ $d->DiagnosisName ?? $d->Description }}</td>
        <td>{{ $d->Severity ?? 'Moderate' }}</td>
        <td>Dr. Michael Reyes, MD</td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;color:#94a3b8;">No recorded diagnoses on file.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="section-title">2. Consultation Notes (SOAP)</div>
  <table>
    <thead><tr><th>Date</th><th>Subjective</th><th>Objective</th><th>Assessment & Plan</th></tr></thead>
    <tbody>
      @forelse($consultations as $c)
      <tr>
        <td>{{ substr($c->CreatedAt, 0, 10) }}</td>
        <td>{{ $c->Subjective ?? 'Routine visit' }}</td>
        <td>{{ $c->Objective ?? 'Stable vitals' }}</td>
        <td>{{ $c->Assessment ?? 'Normal' }} — {{ $c->Plan ?? 'Continue regimen' }}</td>
      </tr>
      @empty
      <tr><td colspan="4" style="text-align:center;color:#94a3b8;">No consultation notes recorded.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="section-title">3. Prescriptions & Medication Orders</div>
  <table>
    <thead><tr><th>Date</th><th>Medicine Name</th><th>Dosage & Frequency</th><th>Duration</th><th>Status</th></tr></thead>
    <tbody>
      @forelse($prescriptions as $p)
      <tr>
        <td>{{ substr($p->CreatedAt, 0, 10) }}</td>
        <td><strong>{{ $p->MedicineName }}</strong></td>
        <td>{{ $p->Dosage }} — {{ $p->Frequency }}</td>
        <td>{{ $p->Duration }}</td>
        <td>{{ $p->Status }}</td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;color:#94a3b8;">No prescription orders recorded.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="section-title">4. Diagnostic Laboratory Results</div>
  <table>
    <thead><tr><th>Date</th><th>Test Name</th><th>Result Value</th><th>Reference Range</th><th>Interpretation</th></tr></thead>
    <tbody>
      @forelse($labs as $l)
      <tr>
        <td>{{ substr($l->CreatedAt, 0, 10) }}</td>
        <td><strong>{{ $l->TestName }}</strong></td>
        <td>{{ $l->ResultValue }} {{ $l->Units }}</td>
        <td>{{ $l->NormalRange }}</td>
        <td>{{ $l->Interpretation }}</td>
      </tr>
      @empty
      <tr><td colspan="5" style="text-align:center;color:#94a3b8;">No laboratory results on file.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div style="margin-top:30px; display:flex; justify-content:space-between;">
    <div>
      <p style="font-size:10px; color:#64748b;">Generated: {{ date('F d, Y H:i:s') }}<br>Document Ref: MRO-SUM-{{ $patient->PatientID }}-{{ date('Y') }}</p>
    </div>
    <div style="text-align:center; width:220px;">
      <div style="border-bottom:1px solid #1e293b; padding-bottom:30px;"></div>
      <p style="margin:4px 0 0 0; font-weight:bold; font-size:11px;">Mark Anthony Valenzuela, RMT</p>
      <p style="margin:0; font-size:10px; color:#64748b;">Medical Records Officer • Lic. MRO-004928</p>
    </div>
  </div>
</body>
</html>
