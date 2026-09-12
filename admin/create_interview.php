<?php
require_once __DIR__ . '/../includes/auth.php';
initSession();
if (!checkAdminAuth()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$pdo = getDbConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $questions = $_POST['questions'] ?? [];

    // Filter non-empty questions
    $validQuestions = array_filter(array_map('trim', $questions), fn($q) => !empty($q));

    if (empty($title)) {
        $error = 'Please provide an interview title.';
    } elseif (empty($validQuestions)) {
        $error = 'Please provide at least one interview question.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO interviews (title, description, status, created_by) VALUES (?, ?, 'active', ?)");
            $stmt->execute([$title, $description, $_SESSION['admin_id'] ?? 1]);
            $interviewId = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("INSERT INTO questions (interview_id, question, question_order) VALUES (?, ?, ?)");
            $order = 1;
            foreach ($validQuestions as $qText) {
                $qStmt->execute([$interviewId, $qText, $order++]);
            }

            $pdo->commit();
            header('Location: ' . adminUrl('candidates.php?interview_id=' . $interviewId . '&created=1'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to create interview: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Interview';
require_once __DIR__ . '/header.php';
?>

<div class="container-narrow">
    <div style="margin-bottom: 24px;">
        <a href="dashboard.php" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Dashboard</a>
        <h1 style="margin-top: 8px;">Create New Interview</h1>
        <p>Set up a structured interview. The candidate will be asked these exact questions via voice audio.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="create_interview.php" id="interviewForm">
            <div class="form-group">
                <label class="form-label" for="title">Interview Title / Role <span style="color: var(--danger);">*</span></label>
                <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Senior PHP Backend Engineer Screening" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Role Description & Context</label>
                <textarea id="description" name="description" class="form-control" placeholder="Brief details regarding expectations, tech stack, and evaluation context..."></textarea>
            </div>

            <div style="margin-top: 30px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 1.15rem; margin-bottom: 2px;">Interview Questions</h2>
                    <p style="font-size: 0.85rem; margin-bottom: 0;">Add the exact questions the system will speak to the candidate.</p>
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="addQuestionBtn">+ Add Question</button>
            </div>

            <div id="questionsContainer">
                <div class="question-row" style="display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start;">
                    <span style="font-weight: 600; color: var(--text-muted); padding-top: 10px; min-width: 24px;">1.</span>
                    <div style="flex: 1;">
                        <input type="text" name="questions[]" class="form-control" placeholder="Enter question 1 text..." required>
                    </div>
                </div>
                <div class="question-row" style="display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start;">
                    <span style="font-weight: 600; color: var(--text-muted); padding-top: 10px; min-width: 24px;">2.</span>
                    <div style="flex: 1;">
                        <input type="text" name="questions[]" class="form-control" placeholder="Enter question 2 text...">
                    </div>
                    <button type="button" class="btn btn-outline btn-sm remove-question-btn" style="color: var(--danger);">&times;</button>
                </div>
                <div class="question-row" style="display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start;">
                    <span style="font-weight: 600; color: var(--text-muted); padding-top: 10px; min-width: 24px;">3.</span>
                    <div style="flex: 1;">
                        <input type="text" name="questions[]" class="form-control" placeholder="Enter question 3 text...">
                    </div>
                    <button type="button" class="btn btn-outline btn-sm remove-question-btn" style="color: var(--danger);">&times;</button>
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap;" class="form-actions">
                <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Interview & Continue</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('questionsContainer');
    const addBtn = document.getElementById('addQuestionBtn');

    function updateRowNumbers() {
        const rows = container.querySelectorAll('.question-row');
        rows.forEach((row, idx) => {
            const numSpan = row.querySelector('span');
            if (numSpan) numSpan.textContent = (idx + 1) + '.';
        });
    }

    addBtn.addEventListener('click', () => {
        const rowCount = container.querySelectorAll('.question-row').length + 1;
        const row = document.createElement('div');
        row.className = 'question-row';
        row.style = 'display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start;';
        row.innerHTML = `
            <span style="font-weight: 600; color: var(--text-muted); padding-top: 10px; min-width: 24px;">${rowCount}.</span>
            <div style="flex: 1;">
                <input type="text" name="questions[]" class="form-control" placeholder="Enter question text..." required>
            </div>
            <button type="button" class="btn btn-outline btn-sm remove-question-btn" style="color: var(--danger);">&times;</button>
        `;
        container.appendChild(row);
    });

    container.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-question-btn')) {
            const rows = container.querySelectorAll('.question-row');
            if (rows.length > 1) {
                e.target.closest('.question-row').remove();
                updateRowNumbers();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
