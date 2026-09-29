<?php
/**
 * CarePulse AI - Database Initializer
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$port = 3307;

echo "[CarePulse AI] Initializing Database System...\n";

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // Reset database cleanly
    $pdo->exec("DROP DATABASE IF EXISTS `carepulse_db`");
    $pdo->exec("CREATE DATABASE `carepulse_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `carepulse_db`");
    echo " fresh database `carepulse_db` created.\n";

    // Run schema
    $schemaFile = __DIR__ . '/schema.sql';
    $schemaSql = file_get_contents($schemaFile);

    // Split and execute statements
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // MariaDB multi-statement or statement by statement
    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo " schema.sql executed successfully.\n";

    // Run seed
    $seedFile = __DIR__ . '/seed.sql';
    $seedSql = file_get_contents($seedFile);
    $seedStatements = array_filter(array_map('trim', explode(';', $seedSql)));
    foreach ($seedStatements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo " seed.sql executed successfully.\n";

    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $docCount = $pdo->query("SELECT COUNT(*) FROM doctor_profiles")->fetchColumn();
    $deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $aptCount = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

    echo "--------------------------------------------------------\n";
    echo "Database Status:\n";
    echo " - Users: $userCount\n";
    echo " - Doctors: $docCount\n";
    echo " - Departments: $deptCount\n";
    echo " - Seed Appointments: $aptCount\n";
    echo "Setup Complete! CarePulse AI database is ready to use.\n";
    echo "--------------------------------------------------------\n";

} catch (Exception $e) {
    echo " ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
