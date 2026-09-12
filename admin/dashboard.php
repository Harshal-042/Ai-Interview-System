<?php
require_once __DIR__ . '/../includes/auth.php';
initSession();
if (!checkAdminAuth()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$pdo = getDbConnection();

// Fetch summary metrics
$interviewsCount = $pdo->query("SELECT COUNT(*) FROM interviews")->fetchColumn();
$candidatesCount = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$completedCount = $pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE status IN ('Completed', 'Analysis Completed')")->fetchColumn();
$avgScore = $pdo->query("SELECT ROUND(AVG(overall_score), 1) FROM interview_analysis")->fetchColumn() ?: 0;

// Fetch interviews list
$interviews = $pdo->query("
    SELECT i.*, 
           (SELECT COUNT(*) FROM questions q WHERE q.interview_id = i.id) AS question_count,
           (SELECT COUNT(*) FROM interview_sessions s WHERE s.interview_id = i.id) AS session_count
    FROM interviews i 
    ORDER BY i.id DESC
")->fetchAll();

// Fetch completed / recent sessions
$sessions = $pdo->query("
    SELECT s.*, 
           i.title AS interview_title,
           c.name AS candidate_name,
           c.email AS candidate_email,
           a.overall_score,
           a.rating
    FROM interview_sessions s
    JOIN interviews i ON s.interview_id = i.id
    JOIN candidates c ON s.candidate_id = c.id
    LEFT JOIN interview_analysis a ON a.session_id = s.id
    ORDER BY s.id DESC
")->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Admin Dashboard</h1>
        <p style="margin-bottom: 0;">Manage structured interviews, view candidate recordings, and review AI assessments.</p>
    </div>
    <div class="page-header-actions">
        <a href="candidates.php" class="btn btn-outline">Generate Interview Link</a>
        <a href="create_interview.php" class="btn btn-primary">+ Create New Interview</a>
    </div>
</div>

<div class="grid-stats">
    <div class="stat-box">
        <div class="stat-label">Total Interviews</div>
        <div class="stat-value"><?= (int)$interviewsCount ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Registered Candidates</div>
        <div class="stat-value"><?= (int)$candidatesCount ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Completed Assessments</div>
        <div class="stat-value"><?= (int)$completedCount ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Average Candidate Score</div>
        <div class="stat-value"><?= htmlspecialchars($avgScore) ?><span style="font-size: 1rem; color: var(--text-muted); font-weight: normal;">/100</span></div>
    </div>
</div>

<!-- Completed & Recent Interviews Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Candidate Interview Sessions</h2>
        <span style="font-size: 0.85rem; color: var(--text-muted);"><?= count($sessions) ?> sessions</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Interview Assessment</th>
                    <th>Status</th>
                    <th>Score / Rating</th>
                    <th>Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No interview sessions recorded yet. Generate an interview link for a candidate to get started.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($s['candidate_name']) ?></strong>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($s['candidate_email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($s['interview_title']) ?></td>
                            <td>
                                <?php 
                                    $statusStr = trim($s['status'] ?? '');
                                    $isCompleted = ($statusStr === 'Completed' || $statusStr === 'Analysis Completed');
                                    $isInProgress = ($statusStr === 'In Progress');
                                    $isProcessing = ($statusStr === 'Processing');
                                ?>
                                <?php if ($isCompleted): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php elseif ($isInProgress): ?>
                                    <span class="badge badge-warning">In Progress</span>
                                <?php elseif ($isProcessing): ?>
                                    <span class="badge badge-warning">Processing</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?= htmlspecialchars($statusStr ?: 'Created') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s['overall_score'] !== null): ?>
                                    <strong><?= (int)$s['overall_score'] ?>/100</strong>
                                    <span class="badge badge-primary" style="margin-left: 6px;"><?= htmlspecialchars($s['rating'] ?: 'Evaluated') ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Pending Completion</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= htmlspecialchars(date('M j, Y g:i a', strtotime($s['created_at']))) ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($isCompleted): ?>
                                    <a href="<?= adminUrl('results.php?session_id=' . (int)$s['id']) ?>" class="btn btn-primary btn-sm">View Result & Video</a>
                                <?php elseif ($isInProgress): ?>
                                    <a href="../candidate/interview.php?token=<?= urlencode($s['token']) ?>" target="_blank" class="btn btn-outline btn-sm" title="Interview in progress">Candidate Link</a>
                                <?php else: ?>
                                    <a href="../candidate/interview.php?token=<?= urlencode($s['token']) ?>" target="_blank" class="btn btn-outline btn-sm">Candidate Link</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Configured Questionnaires -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Interviews & Questions</h2>
        <a href="create_interview.php" class="btn btn-primary btn-sm">+ Add Interview</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Questions</th>
                    <th>Sessions</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interviews as $inv): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($inv['title']) ?></strong>
                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($inv['description']) ?></div>
                        </td>
                        <td><span class="badge badge-secondary"><?= (int)$inv['question_count'] ?> Questions</span></td>
                        <td><?= (int)$inv['session_count'] ?> candidates</td>
                        <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars(date('M j, Y', strtotime($inv['created_at']))) ?></td>
                        <td style="text-align: right;">
                            <a href="edit_questions.php?id=<?= (int)$inv['id'] ?>" class="btn btn-outline btn-sm">Edit Questions</a>
                            <a href="candidates.php?interview_id=<?= (int)$inv['id'] ?>" class="btn btn-primary btn-sm">Assign Candidate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
