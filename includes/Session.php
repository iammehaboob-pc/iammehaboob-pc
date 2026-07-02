<?php
/**
 * SmartFix AI - Secure Session Management Class
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

require_once __DIR__ . '/../config/config.php';

class Session {
    
    // Start session securely
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session cookie parameters
            $cookieParams = session_get_cookie_params();
            
            // Check if HTTPS is active
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'domain' => $cookieParams['domain'] ?? '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            session_start();
        }

        // Validate session to prevent hijacking
        self::validateSession();
    }

    // Validate that session details match the user's current environment
    private static function validateSession(): void {
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['last_activity'] = time();
        } else {
            // Check for potential session hijacking
            if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') || 
                $_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
                self::destroy();
                http_response_code(403);
                exit("Session verification failed. Access denied.");
            }

            // Check for timeout
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                self::destroy();
                header("Location: " . SITE_URL . "/login.php?timeout=1");
                exit();
            }

            // Update last activity time
            $_SESSION['last_activity'] = time();
        }

        // Periodically regenerate session ID (every 5 minutes of activity)
        if (!isset($_SESSION['created_time'])) {
            $_SESSION['created_time'] = time();
        } elseif (time() - $_SESSION['created_time'] > 300) {
            session_regenerate_id(true);
            $_SESSION['created_time'] = time();
        }
    }

    // Set a session variable
    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    // Get a session variable
    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    // Check if session has a key
    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    // Remove specific session key
    public static function remove(string $key): void {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    // Authenticate a user and set details
    public static function login(int $userId, string $role, string $email, string $name): void {
        session_regenerate_id(true);
        self::set('user_id', $userId);
        self::set('role', strtolower($role));
        self::set('email', $email);
        self::set('name', $name);
        self::set('logged_in', true);
        self::set('last_activity', time());
        self::set('created_time', time());
    }

    // Check if user is logged in
    public static function isLoggedIn(): bool {
        return self::get('logged_in') === true;
    }

    // Enforce role-based access control (RBAC)
    public static function requireRole(string|array $roles): void {
        self::start();

        if (!self::isLoggedIn()) {
            header("Location: " . SITE_URL . "/login.php");
            exit();
        }

        $userRole = self::get('role');
        $allowedRoles = is_array($roles) ? array_map('strtolower', $roles) : [strtolower($roles)];

        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            // Render basic access denied page or redirect
            exit("Access Denied: You do not have permission to view this resource.");
        }
    }

    // Destroy session (Logout)
    public static function destroy(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
