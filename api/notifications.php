<?php
/**
 * SmartFix AI - Notifications API Endpoint
 * Returns JSON of notifications for the logged-in user.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/bootstrap.php';

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = Session::get('user_id');
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $notifications = NotificationHelper::getNotifications($userId, false);
            $unread = NotificationHelper::getUnreadCount($userId);
            echo json_encode(['success' => true, 'data' => $notifications, 'unread_count' => $unread]);
            break;
        case 'mark_read':
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                NotificationHelper::markAsRead($id, $userId);
                echo json_encode(['success' => true]);
            } else {
                NotificationHelper::markAllAsRead($userId);
                echo json_encode(['success' => true, 'message' => 'All marked read']);
            }
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
