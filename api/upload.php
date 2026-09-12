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
    echo json_encode(['error' => 'Valid session_id is required']);
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id FROM interview_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found']);
    exit;
}

if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorCode = $_FILES['video']['error'] ?? 'no_file';
    $errorMsg = 'Video upload error code: ' . $errorCode;
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        $errorMsg = 'Video file exceeds maximum server upload limit';
    } elseif ($errorCode === UPLOAD_ERR_PARTIAL) {
        $errorMsg = 'Video file upload was interrupted';
    } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
        $errorMsg = 'No video stream file was received';
    }
    echo json_encode(['error' => $errorMsg, 'code' => $errorCode]);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/interviews';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$originalName = $_FILES['video']['name'] ?? '';
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, ['webm', 'mp4', 'mkv', 'mov', 'avi'])) {
    $extension = 'webm';
}

$filename = "continuous_interview_{$sessionId}_" . time() . ".{$extension}";
$targetPath = $uploadDir . '/' . $filename;
$relativePath = 'uploads/interviews/' . $filename;

if (!move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded video recording']);
    exit;
}

// Update session with video path
$uStmt = $pdo->prepare("UPDATE interview_sessions SET video_path = ?, status = CASE WHEN status IN ('Completed', 'Analysis Completed') THEN status ELSE 'Processing' END WHERE id = ?");
$uStmt->execute([$relativePath, $sessionId]);

echo json_encode([
    'success' => true,
    'message' => 'Continuous video recording uploaded successfully',
    'session_id' => $sessionId,
    'video_path' => $relativePath,
    'file_size' => filesize($targetPath)
]);
