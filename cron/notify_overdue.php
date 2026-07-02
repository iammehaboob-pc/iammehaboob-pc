<?php
/**
 * SmartFix AI - Cron Job: Notify Overdue Tickets
 * Run daily via cron: 0 8 * * * php /path/to/cron/notify_overdue.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $db = Database::getInstance()->getConnection();

    // Find tickets exceeding SLA hours that are still unresolved
    $stmt = $db->query("
        SELECT c.id, c.title, c.student_id, c.staff_id, p.sla_hours, c.created_at
        FROM complaints c
        LEFT JOIN priorities p ON c.priority_id = p.id
        WHERE c.status NOT IN ('resolved', 'rejected')
          AND TIMESTAMPDIFF(HOUR, c.created_at, NOW()) > COALESCE(p.sla_hours, 48)
    ");
    $overdue = $stmt->fetchAll();

    $count = 0;
    foreach ($overdue as $ticket) {
        $ticketRef = '#TC-' . str_pad($ticket['id'], 4, '0', STR_PAD_LEFT);
        $hoursElapsed = round((time() - strtotime($ticket['created_at'])) / 3600);

        // Notify student
        NotificationHelper::create(
            $ticket['student_id'],
            'Ticket Overdue Alert',
            "Your ticket {$ticketRef} has been pending for {$hoursElapsed} hours. We are escalating this to our team.",
            "student/view-complaint.php?id={$ticket['id']}"
        );

        // Notify assigned staff (if any)
        if ($ticket['staff_id']) {
            NotificationHelper::create(
                $ticket['staff_id'],
                'SLA Breach Warning',
                "Ticket {$ticketRef} has breached its SLA ({$ticket['sla_hours']}h). Please prioritize resolution.",
                "staff/view-complaint.php?id={$ticket['id']}"
            );
        }
        $count++;
        echo "Notified for ticket {$ticketRef}" . PHP_EOL;
    }

    echo "Done. {$count} overdue tickets notified." . PHP_EOL;
} catch (Exception $e) {
    error_log('Cron notify_overdue error: ' . $e->getMessage());
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
