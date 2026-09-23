<?php
// includes/header.php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

$currentUser = Session::getCurrentUser() ?: [
    'name' => 'Sarah Jenkins',
    'role' => 'Registrator',
    'email' => 'sarah.jenkins@tupimunicipal.gov.ph'
];

$pageTitle = $pageTitle ?? 'Tupi Municipal Hospital Information Management System';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="Tupi Municipal Hospital Information Management System - Registration Portal" />

  <!-- Offline Typography & Fonts -->
  <link rel="stylesheet" href="<?= asset('vendor/fonts/fonts.css') ?>" />

  <!-- Offline Tailwind CSS with Custom Blue Healthcare Palette -->
  <script src="<?= asset('vendor/tailwindcss/tailwind.js') ?>"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#EFF6FF',
              100: '#DBEAFE',
              200: '#BFDBFE',
              300: '#93C5FD',
              400: '#60A5FA',
              500: '#3B82F6',
              600: '#2563EB',
              700: '#1D4ED8',
              800: '#1E40AF',
              900: '#1E3A8A',
            },
            medical: {
              teal: '#0D9488',
              cyan: '#0284C7',
              emerald: '#10B981',
              slate: '#0F172A'
            }
          },
          fontFamily: {
            sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
          }
        }
      }
    }
  </script>

  <!-- Offline Lucide Icons -->
  <script src="<?= asset('vendor/lucide/lucide.min.js') ?>"></script>

  <!-- Offline Chart.js for Reports & Analytics -->
  <script src="<?= asset('vendor/chartjs/chart.min.js') ?>"></script>

  <!-- Custom Stylesheet -->
  <link rel="stylesheet" href="<?= base_url('css/styles.css') ?>" />
</head>
<body class="bg-medical-mesh min-h-screen text-slate-800 flex flex-col justify-between selection:bg-blue-600 selection:text-white">
