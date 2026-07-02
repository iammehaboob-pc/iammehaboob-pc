<?php
/**
 * SmartFix AI - Security Class
 * Contains functions for input validation, XSS prevention, CSRF tokens, and rate-limiting.
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Session.php';

class Security {

    // Sanitize string input
    public static function sanitizeString(string $data): string {
        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Sanitize output for HTML context (alias for clarity)
    public static function escapeHtml(string $data): string {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Validate email format
    public static function validateEmail(string $email): bool {
        return (bool) filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    }

    // Generate CSRF Token and store in session
    public static function generateCsrfToken(): string {
        Session::start();
        $token = bin2hex(random_bytes(32));
        Session::set('csrf_token', $token);
        Session::set('csrf_token_time', time());
        return $token;
    }

    // Verify CSRF Token
    public static function verifyCsrfToken(?string $token): bool {
        Session::start();
        $savedToken = Session::get('csrf_token');
        $tokenTime = Session::get('csrf_token_time');

        if (empty($token) || empty($savedToken) || empty($tokenTime)) {
            return false;
        }

        // Check if token has expired
        if (time() - $tokenTime > CSRF_TOKEN_EXPIRE) {
            self::invalidateCsrfToken();
            return false;
        }

        // Constant time comparison to prevent timing attacks
        return hash_equals($savedToken, $token);
    }

    // Invalidate CSRF Token
    public static function invalidateCsrfToken(): void {
        Session::remove('csrf_token');
        Session::remove('csrf_token_time');
    }

    // Secure input data array recursively (e.g., $_POST or $_GET)
    public static function sanitizeArray(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $cleanKey = self::sanitizeString($key);
            if (is_array($value)) {
                $sanitized[$cleanKey] = self::sanitizeArray($value);
            } else {
                $sanitized[$cleanKey] = self::sanitizeString($value);
            }
        }
        return $sanitized;
    }

    // Check rate limits for specific action (e.g., login attempts)
    // Backed by MySQL rate_limits table, falling back to session-based limits if DB is unavailable.
    public static function checkRateLimit(string $key, int $limit, int $timeframe): bool {
        // Development-only: prevent local lockouts from blocking testing
        if (ENV === 'development' && $key === 'login') {
            return true;
        }
        try {
            $db = Database::getInstance()->getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $now = time();
            $cutoff = $now - $timeframe;

            // Delete expired entries
            $deleteStmt = $db->prepare("DELETE FROM rate_limits WHERE attempted_at < ?");
            $deleteStmt->execute([$cutoff]);

            // Count active attempts
            $countStmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND action_key = ? AND attempted_at >= ?");
            $countStmt->execute([$ip, $key, $cutoff]);
            $attempts = (int)$countStmt->fetchColumn();

            if ($attempts >= $limit) {
                return false;
            }

            // Record this attempt
            $insertStmt = $db->prepare("INSERT INTO rate_limits (ip_address, action_key, attempted_at) VALUES (?, ?, ?)");
            $insertStmt->execute([$ip, $key, $now]);
            return true;
        } catch (Exception $e) {
            error_log("Database rate-limiting error, falling back to session: " . $e->getMessage());
            return self::checkSessionRateLimit($key, $limit, $timeframe);
        }
    }

    // Helper for session rate limit fallback
    private static function checkSessionRateLimit(string $key, int $limit, int $timeframe): bool {
        Session::start();
        $attempts = Session::get('rate_' . $key, []);
        $now = time();

        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeframe) {
            return ($now - $timestamp) < $timeframe;
        });

        if (count($attempts) >= $limit) {
            return false;
        }

        $attempts[] = $now;
        Session::set('rate_' . $key, $attempts);
        return true;
    }
}
