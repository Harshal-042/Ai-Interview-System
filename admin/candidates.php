<?php
require_once __DIR__ . '/../includes/auth.php';
initSession();
if (!checkAdminAuth()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$pdo = getDbConnection();

$selectedInterviewId = (int)($_GET['interview_id'] ?? 0);
$error = '';
$success = '';

// Handle Candidate Creation & Link Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_link'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $interviewId = (int)($_POST['interview_id'] ?? 0);

    if (empty($name) || empty($email) || !$interviewId) {
        $error = 'Candidate Name, Email, and Selected Interview are required.';
    } else {
        try {
            // Find or insert candidate
            $cStmt = $pdo->prepare("SELECT id FROM candidates WHERE email = ?");
            $cStmt->execute([$email]);
            $candidateId = $cStmt->fetchColumn();

            if (!$candidateId) {
                $ins = $pdo->prepare("INSERT INTO candidates (name, email, phone) VALUES (?, ?, ?)");
                $ins->execute([$name, $email, $phone]);
                $candidateId = $pdo->lastInsertId();
            }

            // Generate unique secure token
            $token = bin2hex(random_bytes(16));

            $sStmt = $pdo->prepare("INSERT INTO interview_sessions (interview_id, candidate_id, token, status) VALUES (?, ?, ?, 'Created')");
            $sStmt->execute([$interviewId, $candidateId, $token]);

            $success = 'Candidate registered and secure interview link generated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to generate link: ' . $e->getMessage();
        }
    }
}

// Fetch all interviews for dropdown
$interviews = $pdo->query("SELECT id, title FROM interviews ORDER BY id DESC")->fetchAll();

// Fetch all candidate sessions with tokens
$sessions = $pdo->query("
    SELECT s.id AS session_id, s.token, s.status, s.created_at,
           c.name AS candidate_name, c.email AS candidate_email, c.phone AS candidate_phone,
           i.title AS interview_title
    FROM interview_sessions s
    JOIN candidates c ON s.candidate_id = c.id
    JOIN interviews i ON s.interview_id = i.id
    ORDER BY s.id DESC
")->fetchAll();

$pageTitle = 'Candidates & Interview Links';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px;">
    <h1>Candidates & Interview Links</h1>
    <p>Generate unique, secure interview URLs to invite candidates. Each link conducts a one-time continuous interview.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="candidates-layout">
    <!-- Add Candidate Form -->
    <div class="card">
        <h2 class="card-title" style="margin-bottom: 16px;">Generate Interview Link</h2>
        <form method="POST" action="candidates.php">
            <input type="hidden" name="generate_link" value="1">

            <div class="form-group">
                <label class="form-label" for="interview_id">Assign to Interview <span style="color: var(--danger);">*</span></label>
                <select name="interview_id" id="interview_id" class="form-control" required>
                    <option value="">-- Select Interview --</option>
                    <?php foreach ($interviews as $inv): ?>
                        <option value="<?= (int)$inv['id'] ?>" <?= ($selectedInterviewId === (int)$inv['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inv['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Candidate Full Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Taylor Reed" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Candidate Email <span style="color: var(--danger);">*</span></label>
                <input type="email" id="email" name="email" class="form-control" placeholder="e.g. taylor.reed@example.com" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone Number (Optional)</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create & Generate Link</button>
        </form>
    </div>

    <!-- Generated Links Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Generated Candidate Links</h2>
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?= count($sessions) ?> invitations</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Interview</th>
                        <th>Status</th>
                        <th>Unique Interview Link</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                No candidate links generated yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $s): ?>
                            <?php 
                                $candidateUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/candidate/interview.php?token=" . urlencode($s['token']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['candidate_name']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($s['candidate_email']) ?></div>
                                </td>
                                <td style="font-size: 0.88rem;"><?= htmlspecialchars($s['interview_title']) ?></td>
                                <td>
                                    <?php 
                                        $cStatus = trim($s['status'] ?? '');
                                        $cIsCompleted = ($cStatus === 'Completed' || $cStatus === 'Analysis Completed');
                                    ?>
                                    <?php if ($cIsCompleted): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php elseif ($cStatus === 'In Progress' || $cStatus === 'Processing'): ?>
                                        <span class="badge badge-warning"><?= htmlspecialchars($cStatus) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= htmlspecialchars($cStatus ?: 'Created') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <input type="text" class="form-control" style="font-size: 0.75rem; padding: 4px 8px; width: 210px; background: #f8fafc;" value="<?= htmlspecialchars($candidateUrl) ?>" readonly id="link-<?= (int)$s['session_id'] ?>">
                                        <button type="button" class="btn btn-outline btn-sm copy-btn" data-target="link-<?= (int)$s['session_id'] ?>" title="Copy link">Copy</button>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($cIsCompleted): ?>
                                        <a href="<?= adminUrl('results.php?session_id=' . (int)$s['session_id']) ?>" class="btn btn-primary btn-sm">View Result & Video</a>
                                    <?php else: ?>
                                        <a href="../candidate/interview.php?token=<?= urlencode($s['token']) ?>" target="_blank" class="btn btn-outline btn-sm" style="color: var(--primary);">Candidate Link</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                input.select();
                navigator.clipboard.writeText(input.value).then(() => {
                    const originalText = btn.textContent;
                    btn.textContent = 'Copied!';
                    btn.style.color = 'var(--success)';
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.style.color = '';
                    }, 2000);
                });
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
