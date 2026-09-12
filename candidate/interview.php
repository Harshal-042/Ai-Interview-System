<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = getDbConnection();

$token = trim($_GET['token'] ?? '');
if (empty($token) && isset($_GET['link'])) {
    $token = trim($_GET['link']);
}

// If candidate pasted a full URL or query string into the token parameter
if (!empty($token)) {
    $token = trim($token, " \t\n\r\0\x0B\"'");
    if (preg_match('/[?&]token=([^&#\s]+)/i', $token, $matches)) {
        $token = urldecode($matches[1]);
    }
}

if (empty($token)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Interview Link Required - AI Interview System</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body style="background-color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div class="card" style="max-width: 480px; width: 100%; text-align: center; padding: 36px 24px;">
            <div style="font-size: 2.2rem; margin-bottom: 12px;">🔗</div>
            <h2 style="font-size: 1.3rem; margin-bottom: 8px;">No Interview Link Provided</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; line-height: 1.5;">
                Please paste your full interview link or token on the homepage to start your session.
            </p>
            <a href="../index.php" class="btn btn-primary" style="width: 100%;">Go to Homepage &rarr;</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*,
           i.title AS interview_title, i.description AS interview_description,
           c.name AS candidate_name, c.email AS candidate_email
    FROM interview_sessions s
    JOIN interviews i ON s.interview_id = i.id
    JOIN candidates c ON s.candidate_id = c.id
    WHERE s.token = ?
");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Interview Link - AI Interview System</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body style="background-color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div class="card" style="max-width: 480px; width: 100%; text-align: center; padding: 36px 24px;">
            <div style="font-size: 2.2rem; margin-bottom: 12px;">⚠️</div>
            <h2 style="font-size: 1.3rem; margin-bottom: 8px;">Invalid or Expired Link</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; line-height: 1.5;">
                The interview link or token you entered was not found in the system. Please verify the link provided by your recruiter.
            </p>
            <a href="../index.php" class="btn btn-primary" style="width: 100%;">Return to Home & Re-enter Link</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($session['status'] === 'Completed' || $session['status'] === 'Analysis Completed') {
    header('Location: finish.php');
    exit;
}

// Fetch questions
$qStmt = $pdo->prepare("SELECT id, question, question_order FROM questions WHERE interview_id = ? ORDER BY question_order ASC");
$qStmt->execute([$session['interview_id']]);
$questions = $qStmt->fetchAll();

if (empty($questions)) {
    die("No interview questions have been configured for this assessment yet.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Assessment - <?= htmlspecialchars($session['interview_title']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .candidate-wrapper {
            max-width: 860px;
            margin: 30px auto;
            padding: 0 16px;
        }
        .live-badge {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body style="background-color: #f1f5f9;">

<div class="candidate-wrapper">
    <!-- Header info for candidate (No admin links) -->
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.6rem; margin-bottom: 4px;"><?= htmlspecialchars($session['interview_title']) ?></h1>
        <p style="font-size: 0.95rem; color: var(--text-muted);">
            Candidate: <strong><?= htmlspecialchars($session['candidate_name']) ?></strong> &bull; Self-Paced Continuous Assessment
        </p>
    </div>

    <!-- Step 1: Pre-Interview Check & Camera Setup -->
    <div id="setupPhase" class="card">
        <h2 class="card-title" style="margin-bottom: 12px;">Audio & Video Setup</h2>
        <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6;">
            Please ensure you are in a quiet, well-lit room. Your camera and microphone will be used to record a <strong>single continuous interview</strong>. Each question will be read aloud to you using Piper voice synthesis.
        </p>

        <div style="margin: 20px 0; background: #000; border-radius: var(--radius-md); overflow: hidden; position: relative; aspect-ratio: 16/9; max-height: 380px; display: flex; align-items: center; justify-content: center;">
            <video id="setupPreview" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover;"></video>
            <div id="cameraPlaceholder" style="color: #94a3b8; text-align: center; padding: 20px;">
                <p style="margin-bottom: 12px; font-weight: 500;">Click "Enable Camera & Microphone" below to begin setup.</p>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 16px;">
            <div id="deviceStatus" style="font-size: 0.88rem; color: var(--text-muted);">
                Status: Awaiting media permissions
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;" class="setup-btn-group">
                <button type="button" id="enableMediaBtn" class="btn btn-outline">Enable Camera & Microphone</button>
                <button type="button" id="startInterviewBtn" class="btn btn-primary" disabled>Start Continuous Interview</button>
            </div>
        </div>
    </div>

    <!-- Step 2: Active Continuous Interview -->
    <div id="interviewPhase" class="interview-box" style="display: none;">
        <div class="interview-header">
            <div class="rec-status">
                <span class="rec-dot"></span>
                <span>CONTINUOUS RECORDING</span>
            </div>
            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);" id="timerDisplay">
                00:00
            </div>
            <div class="badge badge-primary" id="questionProgressBadge" style="font-size: 0.85rem; padding: 4px 10px;">
                Question 1 of <?= count($questions) ?>
            </div>
        </div>

        <!-- Candidate Video View -->
        <div class="video-container" style="max-height: 380px;">
            <video id="liveVideo" autoplay playsinline muted class="video-preview"></video>
            <div style="position: absolute; bottom: 12px; left: 16px; background: rgba(0,0,0,0.6); color: #fff; padding: 4px 10px; border-radius: var(--radius-sm); font-size: 0.8rem;">
                <?= htmlspecialchars($session['candidate_name']) ?>
            </div>
        </div>

        <!-- Question Display Card -->
        <div class="question-card">
            <div class="question-badge" id="questionNumberLabel">Question 1</div>
            <div class="question-text" id="currentQuestionText">
                <?= htmlspecialchars($questions[0]['question']) ?>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 16px;">
                <button type="button" id="replayVoiceBtn" class="btn btn-outline btn-sm">
                    &#128266; Repeat Question Voice (Piper TTS)
                </button>
                <span id="ttsStatus" style="font-size: 0.8rem; color: var(--text-muted);"></span>
            </div>

            <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">Live Speech-to-Text Recognition</div>
            <div class="transcript-preview" id="liveTranscript">
                Listening to your response... Speak clearly into your microphone.
            </div>
        </div>

        <div class="interview-controls">
            <span style="font-size: 0.85rem; color: var(--text-muted);">Take your time answering before moving forward.</span>
            <button type="button" id="nextQuestionBtn" class="btn btn-primary">
                Next Question &rarr;
            </button>
        </div>
    </div>

    <!-- Step 3: Upload & Analysis Processing Overlay -->
    <div id="processingPhase" class="card" style="display: none; text-align: center; padding: 40px 20px;">
        <h2 style="margin-bottom: 12px;">Finalizing Your Interview</h2>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 24px;">
            Your continuous video and speech recording is being safely uploaded and processed through the AI evaluation pipeline.
        </p>

        <div style="width: 100%; max-width: 440px; height: 8px; background: #e2e8f0; border-radius: 9999px; overflow: hidden; margin: 0 auto 20px;">
            <div id="progressBar" style="width: 20%; height: 100%; background: var(--primary); transition: width 0.4s ease;"></div>
        </div>

        <div id="processingStatusText" style="font-size: 0.9rem; font-weight: 500; color: var(--text-main);">
            Uploading continuous recording...
        </div>
    </div>
</div>

<script>
    // Pass session and questions data safely into vanilla JS
    window.INTERVIEW_CONFIG = {
        sessionId: <?= (int)$session['id'] ?>,
        token: <?= json_encode($session['token']) ?>,
        questions: <?= json_encode($questions) ?>
    };
</script>
<script src="../assets/js/interview.js"></script>
</body>
</html>
