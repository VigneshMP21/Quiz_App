<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$categories = $pdo->query("SELECT DISTINCT category FROM quizzes ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$editQuizId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$isEditMode = $editQuizId > 0;
$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'category' => '',
    'no_of_questions' => '',
    'total_marks' => '',
    'timer_minutes' => '10'
];

$existingQuestionCount = 0;
$editQuiz = null;

if ($isEditMode) {
    $stmt = $pdo->prepare("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes, unique_code
                          FROM quizzes
                          WHERE id = ?");
    $stmt->execute([$editQuizId]);
    $editQuiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editQuiz) {
        redirect('quiz.php', 'Quiz not found.', 'error');
    }

    $questionCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
    $questionCountStmt->execute([$editQuizId]);
    $existingQuestionCount = (int) $questionCountStmt->fetchColumn();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $formData = [
            'title' => (string) ($editQuiz['title'] ?? ''),
            'description' => (string) ($editQuiz['description'] ?? ''),
            'category' => (string) ($editQuiz['category'] ?? ''),
            'no_of_questions' => (string) ((int) ($editQuiz['no_of_questions'] ?? 0)),
            'total_marks' => (string) ((int) ($editQuiz['total_marks'] ?? 0)),
            'timer_minutes' => (string) ((int) ($editQuiz['timer_minutes'] ?? 10))
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['category'] = trim($_POST['category'] ?? '');
    $formData['no_of_questions'] = (string) ((int) ($_POST['no_of_questions'] ?? 0));
    $formData['total_marks'] = (string) ((int) ($_POST['total_marks'] ?? 0));
    $formData['timer_minutes'] = (string) ((int) ($_POST['timer_minutes'] ?? 10));

    $noOfQuestions = (int) $formData['no_of_questions'];
    $totalMarks = (int) $formData['total_marks'];
    $timerMinutes = (int) $formData['timer_minutes'];

    if ($formData['title'] === '' || $formData['category'] === '' || $noOfQuestions <= 0 || $totalMarks <= 0) {
        $errors[] = 'Please fill all required fields with valid values.';
    }

    if ($timerMinutes <= 0) {
        $errors[] = 'Timer must be at least 1 minute.';
    }

    if (empty($errors)) {
        if ($isEditMode) {
            if ($noOfQuestions < $existingQuestionCount) {
                $errors[] = 'Number of questions cannot be less than the questions already added to this quiz.';
            } else {
                $stmt = $pdo->prepare("UPDATE quizzes
                                      SET title = ?, description = ?, category = ?, no_of_questions = ?, total_marks = ?, timer_minutes = ?
                                      WHERE id = ?");

                if (
                    $stmt->execute([
                        $formData['title'],
                        $formData['description'],
                        $formData['category'],
                        $noOfQuestions,
                        $totalMarks,
                        $timerMinutes,
                        $editQuizId
                    ])
                ) {
                    redirect("create_quiz.php?edit_id=$editQuizId", 'Quiz updated successfully.');
                }
            }
        } else {
            $uniqueCode = generateUniqueCode();
            $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, category, no_of_questions, total_marks, timer_minutes, unique_code, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            if (
                $stmt->execute([
                    $formData['title'],
                    $formData['description'],
                    $formData['category'],
                    $noOfQuestions,
                    $totalMarks,
                    $timerMinutes,
                    $uniqueCode,
                    $_SESSION['user_id']
                ])
            ) {
                $quizId = $pdo->lastInsertId();
                redirect("admin/add_questions.php?quiz_id=$quizId", 'Quiz created successfully. Now add questions.');
            }
        }

        $errors[] = $isEditMode ? 'Failed to update quiz. Please try again.' : 'Failed to create quiz. Please try again.';
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$myQuizCount = (int) $stmt->fetchColumn();

$platformQuizCount = (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$categoryCount = count($categories);
$questionCountPreview = (int) ($formData['no_of_questions'] !== '' ? $formData['no_of_questions'] : 0);
$marksPreview = (int) ($formData['total_marks'] !== '' ? $formData['total_marks'] : 0);
$timerPreview = (int) ($formData['timer_minutes'] !== '' ? $formData['timer_minutes'] : 10);
$marksPerQuestion = $questionCountPreview > 0 ? round($marksPreview / $questionCountPreview, 1) : 0;
$formAction = 'create_quiz.php' . ($isEditMode ? '?edit_id=' . $editQuizId : '');
$heroKicker = $isEditMode ? 'Quiz editing' : 'Assessment builder';
$heroTitle = $isEditMode
    ? 'Refine quiz details before you return to question management'
    : 'Craft a quiz with stronger structure and launch clarity';
$heroSubtitle = $isEditMode
    ? 'Update the title, category, scoring envelope, and timer in one place. Your existing questions stay attached while you reshape the quiz.'
    : 'Set the title, category, difficulty envelope, and timing in one focused workspace before you move into question authoring.';
$heroPrimaryLabel = $isEditMode ? 'Save Changes' : 'Create and Add Questions';
$formPrimaryLabel = $isEditMode ? 'Update Quiz' : 'Create Quiz';

$isAdminView = true;
$homeLink = 'dashboard_admin.php';
$leaderboardLink = 'admin/view_leaderboard.php';
$logoutLink = 'logout.php';
$pageTitle = $isEditMode ? 'QuizPro - Edit Quiz' : 'QuizPro - Create Quiz';
$pageKey = 'create_quiz';
$pageBodyClass = 'page-create-quiz';
$headerContext = $isEditMode ? 'Quiz editor' : 'Builder workspace';
$pageFooterSummary = $isEditMode
    ? 'Structured quiz editing with clearer metadata control, question continuity, and admin visibility.'
    : 'Structured quiz creation with clearer authoring flow, launch guidance, and admin visibility.';

include 'includes/header.php';
?>
<?php displayMessage(); ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="app-grid app-builder-grid" style="margin-top: 24px;">
    <section class="app-panel">
        <div class="app-panel-head">
            <div>
                <span class="app-panel-kicker">Configuration</span>
                <h2 class="app-panel-title"><?php echo $isEditMode ? 'Edit quiz setup' : 'New quiz setup'; ?></h2>
            </div>
        </div>
        <p class="app-panel-text">
            <?php echo $isEditMode ? 'Adjust metadata without losing the current question set. Use manage questions after saving if you need to refine the quiz content.' : 'Start with clean metadata and balanced scoring. Once this saves, you will move directly into question authoring.'; ?>
        </p>

        <form action="<?php echo htmlspecialchars($formAction); ?>" method="POST" class="app-form-grid"
            id="create-quiz-form">
            <div class="app-form-section">
                <h3 class="app-section-title">Identity</h3>
                <div class="app-field">
                    <label for="title" class="app-label">Quiz Title*</label>
                    <input type="text" id="title" name="title" class="app-input"
                        value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                </div>
                <div class="app-field">
                    <label for="description" class="app-label">Description</label>
                    <textarea id="description" name="description" rows="5"
                        class="app-textarea"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                </div>
                <div class="app-field">
                    <label for="category" class="app-label">Category*</label>
                    <input type="text" id="category" name="category" class="app-input"
                        value="<?php echo htmlspecialchars($formData['category']); ?>" list="category-list" required>
                    <datalist id="category-list">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php endforeach; ?>
                    </datalist>
                    <small class="app-helper">Type to create a new category or select from existing ones.</small>
                </div>
            </div>

            <div class="app-form-section">
                <h3 class="app-section-title">Scoring and timing</h3>
                <div class="app-field-row">
                    <div class="app-field">
                        <label for="no_of_questions" class="app-label">Number of Questions*</label>
                        <input type="number" id="no_of_questions" name="no_of_questions" class="app-input" min="1"
                            value="<?php echo htmlspecialchars($formData['no_of_questions']); ?>" required>
                    </div>
                    <div class="app-field">
                        <label for="total_marks" class="app-label">Total Marks*</label>
                        <input type="number" id="total_marks" name="total_marks" class="app-input" min="1"
                            value="<?php echo htmlspecialchars($formData['total_marks']); ?>" required>
                    </div>
                    <div class="app-field">
                        <label for="timer_minutes" class="app-label">Timer (minutes)</label>
                        <input type="number" id="timer_minutes" name="timer_minutes" class="app-input" min="1"
                            value="<?php echo htmlspecialchars($formData['timer_minutes']); ?>">
                    </div>
                </div>
                <p class="app-helper">Balanced quizzes usually keep marks proportional to the number of questions and
                    use a timer that matches expected difficulty.</p>
            </div>

            <div class="app-actions">
                <button type="submit" class="app-button app-button-primary"><i
                        class="fas <?php echo $isEditMode ? 'fa-floppy-disk' : 'fa-plus'; ?>"></i>
                    <?php echo $formPrimaryLabel; ?></button>
                <button type="reset" class="app-button app-button-ghost"><i class="fas fa-trash-can"></i> Clear
                    Form</button>
            </div>
        </form>
    </section>


</div>
<?php include 'includes/footer.php'; ?>