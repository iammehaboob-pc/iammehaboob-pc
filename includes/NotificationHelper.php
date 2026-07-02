<?php
/**
 * SmartFix AI - Notification Helper Class
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

class NotificationHelper {
    
    // Create a new notification for a specific user
    public static function create(int $userId, string $title, string $message, ?string $link = null): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            return $stmt->execute([$userId, $title, $message, $link]);
        } catch (PDOException $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }

    // Get all notifications for a user (optionally unread only)
    public static function getNotifications(int $userId, bool $unreadOnly = false): array {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            if ($unreadOnly) {
                $sql .= " AND is_read = 0";
            }
            $sql .= " ORDER BY created_at DESC LIMIT 50";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to fetch notifications: " . $e->getMessage());
            return [];
        }
    }

    // Get count of unread notifications for a user
    public static function getUnreadCount(int $userId): int {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Failed to count unread notifications: " . $e->getMessage());
            return 0;
        }
    }

    // Mark a notification as read
    public static function markAsRead(int $notificationId, int $userId): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }

    // Mark all notifications as read for a user
    public static function markAllAsRead(int $userId): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to mark all notifications as read: " . $e->getMessage());
            return false;
        }
    }
}
