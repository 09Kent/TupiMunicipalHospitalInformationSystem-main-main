<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "print_logs table: " . (Illuminate\Support\Facades\Schema::hasTable('print_logs') ? 'YES' : 'NO') . PHP_EOL;
echo "system_audit_logs table: " . (Illuminate\Support\Facades\Schema::hasTable('system_audit_logs') ? 'YES' : 'NO') . PHP_EOL;
echo "billing_invoices: " . implode(', ', Illuminate\Support\Facades\Schema::getColumnListing('billing_invoices')) . PHP_EOL;
echo "billing_payments: " . implode(', ', Illuminate\Support\Facades\Schema::getColumnListing('billing_payments')) . PHP_EOL;
echo "referrals: " . implode(', ', Illuminate\Support\Facades\Schema::getColumnListing('referrals')) . PHP_EOL;
echo "print_logs: " . implode(', ', Illuminate\Support\Facades\Schema::getColumnListing('print_logs')) . PHP_EOL;





