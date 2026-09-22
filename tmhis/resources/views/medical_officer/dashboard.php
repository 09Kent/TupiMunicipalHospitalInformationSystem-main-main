<?php
/**
 * TUPI MUNICIPAL HOSPITAL INFORMATION MANAGEMENT SYSTEM
 * Role 3: Medical Records Officer
 * Health Information & Records Management (HIRM)
 * 
 * PHP Main Application Entrypoint
 */

require_once __DIR__ . '/config/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical Records Officer | Tupi Municipal Hospital Information Management System</title>
  
  <!-- Favicon / Meta -->
  <meta name="description" content="Tupi Municipal Hospital Information Management System - Medical Records Officer Portal">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="<?= asset('section/records/css/styles.css') ?>?v=<?= file_exists(public_path('section/records/css/styles.css')) ? filemtime(public_path('section/records/css/styles.css')) : time() ?>">
  
  <!-- Lucide Icons CDN -->
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

  <div class="app-container">

    <!-- ====================================================================
         LEFT VERTICAL SIDEBAR (PHP Component)
         ==================================================================== -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- ====================================================================
         MAIN VIEW WRAPPER
         ==================================================================== -->
    <div class="main-wrapper">

      <!-- Top Header (PHP Component) -->
      <?php include __DIR__ . '/includes/header.php'; ?>

      <!-- Main Content Area (Dynamic single-page view router) -->
      <main class="content-body" id="mainContentArea">
        <!-- Rendered dynamically by js/app.js -->
      </main>

    </div>

  </div>

  <!-- ====================================================================
       MODALS & DRAWERS (PHP Component)
       ==================================================================== -->
  <?php include __DIR__ . '/includes/modals.php'; ?>

  <!-- Live Server Data -->
  <script>
    window.TMHIS_APP_URL = "<?= url('/') ?>";
    window.SERVER_RECORDS_DATA = <?= json_encode(
      $serverData ?? [
        'currentOfficer' => $currentOfficer ?? [],
        'patients'       => []
      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  </script>
  <!-- App Scripts -->
  <script src="<?= asset('section/records/js/data.js') ?>?v=<?= file_exists(public_path('section/records/js/data.js')) ? filemtime(public_path('section/records/js/data.js')) : time() ?>"></script>
  <script src="<?= asset('section/records/js/app.js') ?>?v=<?= file_exists(public_path('section/records/js/app.js')) ? filemtime(public_path('section/records/js/app.js')) : time() ?>"></script>

</body>
</html>
