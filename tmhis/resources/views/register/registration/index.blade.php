@extends('layouts.register')

@section('title', 'Patient Intake & Pre-Consultation Form | Tupi Municipal Hospital Information Management System')

@section('content')

<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
  
  <!-- Minimal Top Bar for Intake Mode (No full navbar) -->
  <div class="flex items-center justify-between mb-6">
    @php
      $exitUrl = route('landing');
      if (Auth::check()) {
        $userRole = strtolower(Auth::user()->Role ?? '');
        $exitUrl = in_array($userRole, ['register', 'registrator', 'admin']) ? route('register.dashboard') : route('login', ['continue' => 1]);
      }
    @endphp
    <a href="{{ $exitUrl }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-blue-600 transition bg-white/90 px-3.5 py-2 rounded-xl border border-slate-200/90 shadow-2xs">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
      <span>{{ Auth::check() ? 'Exit to Portal' : 'Back to Home' }}</span>
    </a>
    <div class="flex items-center gap-2 bg-white/80 px-3 py-1.5 rounded-xl border border-slate-200/80 text-xs font-semibold text-slate-600">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
      <span>Patient Registration Portal</span>
    </div>
  </div>
  
  <!-- Stepper Progress Bar (Horizontal on Desktop, compact on Mobile) -->
  <div class="no-print mb-8">
    
    <!-- Desktop Horizontal Stepper Bar -->
    <div class="hidden md:flex items-center justify-between p-4 bg-white/90 backdrop-blur-md rounded-2xl border border-slate-200/90 shadow-2xs overflow-x-auto" id="registration-desktop-stepper">
      <!-- Injected via registration.js -->
    </div>

    <!-- Mobile Compact Progress Indicator -->
    <div class="md:hidden bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs space-y-2">
      <div class="flex items-center justify-between text-xs font-bold text-slate-700">
        <span id="reg-mobile-step-text">Step 1: Patient Information</span>
        <span class="text-blue-600 font-extrabold">EHR Intake</span>
      </div>
      <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
        <div id="reg-mobile-progress-fill" class="bg-blue-600 h-full rounded-full transition-all duration-300" style="width: 10%;"></div>
      </div>
    </div>

  </div>

  <!-- MAIN REGISTRATION APPLICATION CARD -->
  <div id="registration-card-container" class="bg-white/95 backdrop-blur-sm rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-200/50 p-6 sm:p-10 transition-all duration-300">
    
    <!-- Dynamic Step Content Mount Point -->
    <div id="reg-step-content-area">
      <!-- Dynamically rendered by registration.js with PHP/MySQL backend sync -->
    </div>

  </div>

</main>

<!-- Inject Server Master Data for Client Engine -->
<script>
  window.SERVER_DATA = {
    symptoms: <?= json_encode($dbSymptoms ?? []) ?>,
    bodySystems: <?= json_encode($dbSystems ?? []) ?>,
    bodyLocations: <?= json_encode($dbLocations ?? []) ?>,
    doctors: <?= json_encode($dbDoctors ?? []) ?>,
    apiEndpoints: {
      classify: '/api/register/classify',
      submit: '/api/register/submit',
      doctors: '/api/register/doctors',
      symptoms: '/api/register/symptoms'
    },
    baseUrl: '/'
  };
</script>

<!-- Scripts -->
<script src="{{ asset('section/register/js/data.js') }}"></script>
<script src="{{ asset('section/register/js/body-map.js') }}"></script>
<script src="{{ asset('section/register/assets/js/registration.js') }}"></script>
@endsection
