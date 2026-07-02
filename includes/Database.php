<?php
/**
 * SmartFix AI - Database Handler Class (PDO Singleton Pattern)
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?Database $instance = null;
    private ?PDO $conn = null;

    // Private constructor to prevent direct instantiation
    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In development, show error. In production, write to logs.
            if (ENV === 'development') {
                throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
            } else {
                error_log("Database connection failure: " . $e->getMessage());
                http_response_code(500);
                exit("A database connection error occurred. Please try again later.");
            }
        }
    }

    // Get the single instance of Database class
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get the active PDO connection handle
    public function getConnection(): PDO {
        return $this->conn;
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize Singleton database class.");
    }
}
