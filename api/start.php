<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);
if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Session ID required']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        UPDATE interview_sessions 
        SET status = 'In Progress', 
            started_at = COALESCE(started_at, CURRENT_TIMESTAMP) 
        WHERE id = ? AND status NOT IN ('Completed', 'Analysis Completed')
    ");
    $stmt->execute([$sessionId]);

    echo json_encode(['success' => true, 'status' => 'In Progress']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database update error: ' . $e->getMessage()]);
}
