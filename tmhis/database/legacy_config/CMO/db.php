<?php
/**
 * Tupi Municipal Hospital Information Management System
 * Role 2: Hospital Chief / Medical Director Database Connection (MySQL PDO)
 */

declare(strict_types=1);

namespace TMHIS;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;
    private static string $host = '127.0.0.1';
    private static string $dbName = 'MedicalRegistrationDB';
    private static string $username = 'root';
    private static string $password = '';
    private static int $port = 3306;

    public static function getConnection(): PDO {
        if (class_exists('\Illuminate\Support\Facades\DB', false) || class_exists('Illuminate\Support\Facades\DB')) {
            try {
                return \Illuminate\Support\Facades\DB::connection()->getPdo();
            } catch (\Throwable $e) {}
        }
        require_once __DIR__ . '/../../../app/Helpers/legacy_bridge.php';
        return \Database::getConnection();
    }

    public static function logAudit(string $action, string $report = '-', string $details = ''): void {
        try {
            $db = self::getConnection();
            $stmt = $db->prepare("
                INSERT INTO system_audit_logs (UserName, UserRole, Action, Module, Details)
                VALUES ('Dr. Maria Santos', 'Medical Director', :action, 'Director Oversight', :details)
            ");
            $stmt->execute([
                ':action' => $action,
                ':details' => $report . ' - ' . $details
            ]);
        } catch (PDOException $e) {
            // Silently ignore
        }
    }
}
