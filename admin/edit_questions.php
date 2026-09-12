<?php
require_once __DIR__ . '/../includes/auth.php';
initSession();
if (!checkAdminAuth()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$pdo = getDbConnection();

$interviewId = (int)($_GET['id'] ?? 0);
if (!$interviewId) {
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM interviews WHERE id = ?");
$stmt->execute([$interviewId]);
$interview = $stmt->fetch();

if (!$interview) {
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $questions = $_POST['questions'] ?? [];

    $validQuestions = array_filter(array_map('trim', $questions), fn($q) => !empty($q));

    if (empty($title)) {
        $error = 'Interview title cannot be empty.';
    } elseif (empty($validQuestions)) {
        $error = 'Interview must have at least one question.';
    } else {
        $pdo->beginTransaction();
        try {
            // Update interview details
            $uStmt = $pdo->prepare("UPDATE interviews SET title = ?, description = ? WHERE id = ?");
            $uStmt->execute([$title, $description, $interviewId]);

            // Replace questions
            $pdo->prepare("DELETE FROM questions WHERE interview_id = ?")->execute([$interviewId]);
            $qStmt = $pdo->prepare("INSERT INTO questions (interview_id, question, question_order) VALUES (?, ?, ?)");
            $order = 1;
            foreach ($validQuestions as $qText) {
                $qStmt->execute([$interviewId, $qText, $order++]);
            }

            $pdo->commit();
            $success = 'Interview and questions updated successfully.';

            // Reload interview
            $stmt = $pdo->prepare("SELECT * FROM interviews WHERE id = ?");
            $stmt->execute([$interviewId]);
            $interview = $stmt->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Fetch current questions
$qList = $pdo->prepare("SELECT * FROM questions WHERE interview_id = ? ORDER BY question_order ASC");
$qList->execute([$interviewId]);
$existingQuestions = $qList->fetchAll();

$pageTitle = 'Edit Interview Questions';
require_once __DIR__ . '/header.php';
?>

<div class="container-narrow">
    <div style="margin-bottom: 24px;">
        <a href="dashboard.php" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Dashboard</a>
        <h1 style="margin-top: 8px;">Edit Interview Questions</h1>
        <p>Update questions for "<strong><?= htmlspecialchars($interview['title']) ?></strong>".</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="edit_questions.php?id=<?= $interviewId ?>" id="editForm">
            <div class="form-group">
                <label class="form-label" for="title">Interview Title</label>
                <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($interview['title']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Role Description & Context</label>
                <textarea id="description" name="description" class="form-control"><?= htmlspecialchars($interview['description']) ?></textarea>
            </div>

            <div style="margin-top: 30px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 1.15rem; margin-bottom: 2px;">Interview Questions</h2>
                    <p style="font-size: 0.85rem; margin-bottom: 0;">These exact questions will be converted to voice speech via Piper TTS.</p>
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="addQuestionBtn">+ Add Question</button>
            </div>

            <div id="questionsContainer">
                <?php foreach ($existingQuestions as $idx => $q): ?>
                    <div class="question-row" style="display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start;">
                        <span style="font-weight: 600; color: var(--text-muted); padding-top: 10px; min-width: 24px;"><?= $idx + 1 ?>.</span>
                        <div style="flex: 1;">
                            <input type="text" name="questions[]" class="form-control" value="<?= htmlspecialchars($q['question']) ?>" required>
                        </div>
                        <?php if (count($existingQuestions) > 1): ?>
                            <button type="button" class="btn btn-outline btn-sm remove-question-btn" style="color: var(--danger);">&times;</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="dashboard.php" class="btn btn-outline">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
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
