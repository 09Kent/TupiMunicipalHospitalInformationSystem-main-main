<?php
// Section/Pharmacy/config/Database.php
require_once __DIR__ . '/../../../app/Helpers/legacy_bridge.php';

if (!class_exists('Database', false)) {
    class Database
    {
        public static function getConnection(): PDO
        {
            return \Database::getConnection();
        }
    }
}
