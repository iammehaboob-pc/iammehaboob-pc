<?php
/**
 * SmartFix AI - Database Setup & Password Hash Generator
 * Run this ONCE after importing schema.sql to set proper bcrypt passwords for seed users.
 * Access via: http://localhost/smartfix-ai/database/setup.php
 * DELETE THIS FILE after running it.
 */

require_once __DIR__ . '/../config/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Define seed user passwords
    $users = [
        ['id' => 1, 'password' => 'Admin@123'],
        ['id' => 2, 'password' => 'Staff@123'],
        ['id' => 3, 'password' => 'Staff@123'],
        ['id' => 4, 'password' => 'Staff@123'],
        ['id' => 5, 'password' => 'Student@123'],
    ];

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

    echo "<h2>SmartFix AI - Database Setup</h2>";
    echo "<pre>";

    foreach ($users as $user) {
        $hash = password_hash($user['password'], PASSWORD_BCRYPT);
        $stmt->execute([$hash, $user['id']]);
        echo "User ID {$user['id']} => password hashed successfully\n";
    }

    echo "\n--- Setup Complete ---\n";
    echo "\nDefault Login Credentials:\n";
    echo "Admin:   admin@smartfixai.edu / Admin@123\n";
    echo "Staff:   it_staff@smartfixai.edu / Staff@123\n";
    echo "Student: student@smartfixai.edu / Student@123\n";
    echo "\n⚠️  DELETE THIS FILE AFTER RUNNING IT!\n";
    echo "</pre>";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
