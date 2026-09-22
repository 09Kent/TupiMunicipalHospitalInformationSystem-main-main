<?php
/**
 * HTTP Session-Aware E2E Test - Uses cookie jar for proper session persistence
 */

$baseUrl = 'http://127.0.0.1:8000';
$passed = 0;
$failed = 0;
$cookieJar = tempnam(sys_get_temp_dir(), 'tmhis_cookies_');

function httpReq($url, $method = 'GET', $data = [], $cookieJar = '') {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'redirect' => $redirectUrl];
}

function login($baseUrl, $username, $password, $cookieJar) {
    // Clear cookie jar
    if (file_exists($cookieJar)) {
        file_put_contents($cookieJar, '');
    }
    
    // Get login page + CSRF
    $r = httpReq("$baseUrl/login", 'GET', [], $cookieJar);
    preg_match('/<input[^>]*name="_token"[^>]*value="([^"]*)"/', $r['body'], $m);
    $token = $m[1] ?? '';
    if (empty($token)) return false;
    
    // Login
    $r = httpReq("$baseUrl/login", 'POST', [
        '_token' => $token,
        'email' => $username,
        'password' => $password,
    ], $cookieJar);
    
    if ($r['code'] !== 302) return false;
    preg_match('/Location:\s*(.+)/i', $r['headers'], $loc);
    $location = trim($loc[1] ?? '');
    return !empty($location) && !str_contains($location, '/login');
}

function test($name, $condition, $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $name\n";
    } else {
        $failed++;
        echo "  [FAIL] $name" . ($detail ? " -- $detail" : "") . "\n";
    }
}

echo "============================================================\n";
echo " TMHIS SESSION-AWARE HTTP E2E TEST\n";
echo "============================================================\n\n";

// ============================================================
// DOCTOR PORTAL
// ============================================================
echo "--- DOCTOR PORTAL ---\n";
$loggedIn = login($baseUrl, 'dr.lewis', 'password123', $cookieJar);
test("Doctor login", $loggedIn);

if ($loggedIn) {
    $pages = [
        '/doctor' => 'Dashboard',
        '/doctor/patients' => 'Patients', 
        '/doctor/diagnosis' => 'Diagnosis',
        '/doctor/prescriptions' => 'Prescriptions',
        '/doctor/laboratory' => 'Laboratory',
        '/doctor/referrals' => 'Referrals',
        '/doctor/certificates' => 'Certificates',
        '/doctor/settings' => 'Settings',
    ];
    
    foreach ($pages as $uri => $label) {
        $r = httpReq("$baseUrl$uri", 'GET', [], $cookieJar);
        $ok = $r['code'] == 200;
        if (!$ok && $r['code'] == 302) {
            // Follow redirect once
            preg_match('/Location:\s*(.+)/i', $r['headers'], $m);
            $redir = trim($m[1] ?? '');
            if (str_contains($redir, '/login')) {
                test("Doctor: $label", false, "Redirected to login - session lost");
                continue;
            }
            $r = httpReq($redir, 'GET', [], $cookieJar);
            $ok = $r['code'] == 200;
        }
        
        $hasError = false;
        $errorMsg = '';
        if ($ok) {
            if (str_contains($r['body'], 'ErrorException') || str_contains($r['body'], 'Fatal error') || str_contains($r['body'], 'ParseError')) {
                $hasError = true;
                preg_match('/<title>(.*?)<\/title>/s', $r['body'], $tm);
                $errorMsg = trim(strip_tags($tm[1] ?? 'Unknown'));
                preg_match('/message.*?:(.*?)(?:<br|<\/div|<\/p)/si', $r['body'], $em);
                $errorMsg .= ' | ' . trim(strip_tags($em[1] ?? ''));
            }
            if (str_contains($r['body'], 'Undefined variable') || str_contains($r['body'], 'Undefined array key')) {
                $hasError = true;
                preg_match('/Undefined (variable|array key)[^<]*/i', $r['body'], $em);
                $errorMsg = trim($em[0] ?? 'Undefined variable/key error');
            }
        }
        
        test("Doctor: $label", $ok && !$hasError, $hasError ? $errorMsg : (!$ok ? "HTTP {$r['code']}" : ""));
    }
    
    // Logout
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Doctor: Logout", $r['code'] == 302);
}

// ============================================================
// NURSE PORTAL
// ============================================================
echo "\n--- NURSE PORTAL ---\n";
$loggedIn = login($baseUrl, 'nurse', 'password123', $cookieJar);
test("Nurse login", $loggedIn);

if ($loggedIn) {
    $pages = [
        '/nurse' => 'Dashboard',
        '/nurse/triage' => 'Triage',
        '/nurse/patients' => 'Patients',
        '/nurse/vitals' => 'Vital Signs',
        '/nurse/tasks' => 'Tasks',
        '/nurse/queue' => 'Queue',
        '/nurse/settings' => 'Settings',
    ];
    
    foreach ($pages as $uri => $label) {
        $r = httpReq("$baseUrl$uri", 'GET', [], $cookieJar);
        $ok = $r['code'] == 200;
        if (!$ok && $r['code'] == 302) {
            preg_match('/Location:\s*(.+)/i', $r['headers'], $m);
            $redir = trim($m[1] ?? '');
            if (str_contains($redir, '/login')) {
                test("Nurse: $label", false, "Redirected to login");
                continue;
            }
            $r = httpReq($redir, 'GET', [], $cookieJar);
            $ok = $r['code'] == 200;
        }
        
        $hasError = str_contains($r['body'], 'ErrorException') || str_contains($r['body'], 'Fatal error') || str_contains($r['body'], 'Undefined variable');
        test("Nurse: $label", $ok && !$hasError, $hasError ? "PHP Error" : (!$ok ? "HTTP {$r['code']}" : ""));
    }
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Nurse: Logout", $r['code'] == 302);
}

// ============================================================
// ADMIN PORTAL
// ============================================================
echo "\n--- ADMIN PORTAL ---\n";
$loggedIn = login($baseUrl, 'admin', 'password123', $cookieJar);
test("Admin login", $loggedIn);

if ($loggedIn) {
    $r = httpReq("$baseUrl/admin", 'GET', [], $cookieJar);
    $ok = $r['code'] == 200;
    test("Admin: Dashboard", $ok, "HTTP {$r['code']}");
    
    // Test admin API
    $r = httpReq("$baseUrl/admin/api/users?action=list", 'GET', [], $cookieJar);
    test("Admin: Users API", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/admin/api/departments?action=list", 'GET', [], $cookieJar);
    test("Admin: Departments API", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/admin/api/logs?action=list", 'GET', [], $cookieJar);
    test("Admin: Logs API", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Admin: Logout", $r['code'] == 302);
}

// ============================================================
// REGISTRATION PORTAL
// ============================================================
echo "\n--- REGISTRATION PORTAL ---\n";
$loggedIn = login($baseUrl, 'registrator', 'password123', $cookieJar);
test("Registration login", $loggedIn);

if ($loggedIn) {
    $pages = [
        '/register' => 'Dashboard',
        '/register/patients' => 'Patients',
        '/register/queue' => 'Queue',
        '/register/appointments' => 'Appointments',
        '/register/reports' => 'Reports',
    ];
    
    foreach ($pages as $uri => $label) {
        $r = httpReq("$baseUrl$uri", 'GET', [], $cookieJar);
        $ok = $r['code'] == 200;
        test("Registration: $label", $ok, "HTTP {$r['code']}");
    }
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Registration: Logout", $r['code'] == 302);
}

// ============================================================
// MEDTECH PORTAL
// ============================================================
echo "\n--- MEDTECH PORTAL ---\n";
$loggedIn = login($baseUrl, 'medtech', 'password123', $cookieJar);
test("MedTech login", $loggedIn);

if ($loggedIn) {
    $r = httpReq("$baseUrl/medtech", 'GET', [], $cookieJar);
    test("MedTech: Dashboard", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("MedTech: Logout", $r['code'] == 302);
}

// ============================================================
// PHARMACY PORTAL
// ============================================================
echo "\n--- PHARMACY PORTAL ---\n";
$loggedIn = login($baseUrl, 'pharmacist', 'password123', $cookieJar);
test("Pharmacy login", $loggedIn);

if ($loggedIn) {
    $r = httpReq("$baseUrl/pharmacy", 'GET', [], $cookieJar);
    test("Pharmacy: Dashboard", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Pharmacy: Logout", $r['code'] == 302);
}

// ============================================================
// BILLING PORTAL
// ============================================================
echo "\n--- BILLING PORTAL ---\n";
$loggedIn = login($baseUrl, 'cashier', 'password123', $cookieJar);
test("Billing login", $loggedIn);

if ($loggedIn) {
    $r = httpReq("$baseUrl/billing", 'GET', [], $cookieJar);
    test("Billing: Dashboard", $r['code'] == 200, "HTTP {$r['code']}");
    
    // Test billing APIs
    $r = httpReq("$baseUrl/billing/api/data", 'GET', [], $cookieJar);
    test("Billing: Data API", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Billing: Logout", $r['code'] == 302);
}

// ============================================================
// RECORDS PORTAL
// ============================================================
echo "\n--- RECORDS PORTAL ---\n";
$loggedIn = login($baseUrl, 'records', 'password123', $cookieJar);
test("Records login", $loggedIn);

if ($loggedIn) {
    $r = httpReq("$baseUrl/records", 'GET', [], $cookieJar);
    test("Records: Dashboard", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Records: Logout", $r['code'] == 302);
}

// ============================================================
// DIRECTOR PORTAL
// ============================================================
echo "\n--- DIRECTOR PORTAL ---\n";
$loggedIn = login($baseUrl, 'director', 'password123', $cookieJar);
test("Director login", $loggedIn);

if ($loggedIn) {
    $r = httpReq("$baseUrl/director", 'GET', [], $cookieJar);
    test("Director: Dashboard", $r['code'] == 200, "HTTP {$r['code']}");
    
    // Test Director APIs
    $r = httpReq("$baseUrl/director/api/dashboard-stats", 'GET', [], $cookieJar);
    test("Director: Stats API", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/director/api/department-performance", 'GET', [], $cookieJar);
    test("Director: Dept Performance API", $r['code'] == 200, "HTTP {$r['code']}");
    
    $r = httpReq("$baseUrl/logout", 'GET', [], $cookieJar);
    test("Director: Logout", $r['code'] == 302);
}

// Cleanup
@unlink($cookieJar);

// ============================================================
// SUMMARY
// ============================================================
echo "\n============================================================\n";
echo " FINAL SESSION-AWARE HTTP TEST SUMMARY\n";
echo "============================================================\n";
echo "  PASSED: $passed\n";
echo "  FAILED: $failed\n";
echo "  TOTAL: " . ($passed + $failed) . "\n";
echo "  RESULT: " . ($failed === 0 ? "ALL TESTS PASSED ✓" : "$failed TESTS FAILED ✗") . "\n";
echo "============================================================\n";
