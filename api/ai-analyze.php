<?php
/**
 * SmartFix AI - AJAX Endpoint for AI Complaint Analysis
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/AIHelper.php';

// Enforce login for accessing API
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read input stream
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');

    if (empty($title) || empty($description)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and description are required for analysis.']);
        exit();
    }

    // 1. Check for potential duplicate complaints
    $duplicateId = AIHelper::checkDuplicates($title, $description);
    
    // 2. Perform AI classification and solution generation
    $analysis = AIHelper::analyzeComplaint($title, $description);
    
    // Attach duplicate ID to results
    $analysis['duplicate_id'] = $duplicateId;
    
    echo json_encode([
        'status' => 'success',
        'analysis' => $analysis
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);
exit();
