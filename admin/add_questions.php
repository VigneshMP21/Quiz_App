<?php
session_start();
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

require_once '../includes/db.php';

$quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;

$stmt = $pdo->prepare("SELECT id, title, description, no_of_questions, total_marks, timer_minutes, unique_code
                      FROM quizzes
                      WHERE id = ? AND created_by = ?");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    redirect('../create_quiz.php', 'Quiz not found or you do not have permission to edit it.', 'error');
}

$error = null;
$success = null;

$loadQuestions = static function (PDO $pdo, int $quizId): array {
    $questionsStmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
    $questionsStmt->execute([$quizId]);
    return $questionsStmt->fetchAll();
};

$questions = $loadQuestions($pdo, $quiz_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_question') {
        $question_text = trim($_POST['question_text'] ?? '');
        $option1 = trim($_POST['option1'] ?? '');
        $option2 = trim($_POST['option2'] ?? '');
        $option3 = trim($_POST['option3'] ?? '');
        $option4 = trim($_POST['option4'] ?? '');
        $correct_option = (int) ($_POST['correct_option'] ?? 0);
        $marks = max(1, (int) ($_POST['marks'] ?? 1));

        if ($question_text === '' || $option1 === '' || $option2 === '' || $option3 === '' || $option4 === '') {
            $error = 'Please fill all question fields.';
        } elseif ($correct_option < 1 || $correct_option > 4) {
            $error = 'Please select a valid correct option.';
        } elseif (count($questions) >= (int) $quiz['no_of_questions']) {
            $error = 'This quiz already has the required number of questions.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO questions
                                  (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt->execute([$quiz_id, $question_text, $option1, $option2, $option3, $option4, $correct_option, $marks])) {
                $success = 'Question added successfully.';
            } else {
                $error = 'Failed to add question. Please try again.';
            }
        }
    } elseif ($action === 'delete_question') {
        $question_id = (int) ($_POST['question_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ? AND quiz_id = ?");

        if ($stmt->execute([$question_id, $quiz_id])) {
            $success = 'Question deleted successfully.';
        } else {
            $error = 'Failed to delete question.';
        }
    }

    $questions = $loadQuestions($pdo, $quiz_id);
}

$questionCount = count($questions);
$remainingQuestions = max(0, (int) $quiz['no_of_questions'] - $questionCount);
$completionPercent = (int) round(($questionCount / max(1, (int) $quiz['no_of_questions'])) * 100);
$totalQuestionMarks = (int) array_sum(array_map(static function (array $question): int {
    return (int) ($question['marks'] ?? 0);
}, $questions));
$quizComplete = $questionCount >= (int) $quiz['no_of_questions'];
$completionLabel = $quizComplete
    ? 'Question set complete. Review the structure, then return to the quiz library or dashboard.'
    : 'Add the remaining questions with balanced options and clear scoring before publishing.';

$pathPrefix = '..';
$isAdminView = true;
$homeLink = 'dashboard_admin.php';
$leaderboardLink = 'admin/view_leaderboard.php';
$logoutLink = 'logout.php';
$pageTitle = 'QuizPro - Question Studio';
$pageKey = 'create_quiz';
$pageBodyClass = 'page-add-questions';
$headerContext = 'Question studio';
$pageFooterSummary = 'A structured authoring surface for building complete quizzes with balanced scoring, option quality, and launch readiness.';

include '../includes/header.php';
?>
            <?php displayMessage(); ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><p><?php echo htmlspecialchars($error); ?></p></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><p><?php echo htmlspecialchars($success); ?></p></div>
            <?php endif; ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Question authoring</span>
                    <h1 class="app-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($completionLabel); ?></p>
                    <div class="app-actions">
                        <?php if (!$quizComplete): ?>
                            <a href="#question-form" class="app-button app-button-primary"><i class="fas fa-plus"></i> Add Question</a>
                        <?php endif; ?>
                        <a href="../quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="app-button app-button-ghost"><i class="fas fa-eye"></i> Preview Quiz</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Studio pulse</span>
                        <span class="app-status-pill"><i class="fas fa-pen-ruler"></i> Admin flow</span>
                    </div>
                    <div class="app-hero-panel-copy">
                        <strong>Quiz code</strong>
                        <p><?php echo htmlspecialchars($quiz['unique_code'] ?: 'Pending'); ?></p>
                    </div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Timer</span>
                            <span class="app-hero-mini-static"><?php echo (int) $quiz['timer_minutes']; ?> min</span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Completion</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $completionPercent; ?>">0</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Questions added</span>
                    <strong class="app-metric-value" data-count="<?php echo $questionCount; ?>">0</strong>
                    <p>Current authored questions already attached to this quiz.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Remaining slots</span>
                    <strong class="app-metric-value" data-count="<?php echo $remainingQuestions; ?>">0</strong>
                    <p>Questions still needed to reach the configured quiz size.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Marks authored</span>
                    <strong class="app-metric-value" data-count="<?php echo $totalQuestionMarks; ?>">0</strong>
                    <p>Total marks currently represented across the authored question set.</p>
                </article>
            </section>

            <div class="app-grid app-builder-grid">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Build questions</span>
                            <h2 class="app-panel-title">Compose the next item</h2>
                        </div>
                        <span class="app-status-pill"><?php echo $questionCount; ?> / <?php echo (int) $quiz['no_of_questions']; ?> complete</span>
                    </div>

                    <div class="app-progress-shell">
                        <div class="app-progress-meta">
                            <span>Authoring progress</span>
                            <strong><?php echo $completionPercent; ?>%</strong>
                        </div>
                        <div class="app-progress-track">
                            <div class="app-progress-fill" style="width: <?php echo $completionPercent; ?>%"></div>
                        </div>
                    </div>

                    <?php if ($quizComplete): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-icon"><i class="fas fa-circle-check"></i></div>
                            <h3>Quiz complete</h3>
                            <p>You have added the full question set for this quiz. Review, preview, or create another quiz when ready.</p>
                            <div class="app-actions">
                                <a href="../quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="app-button app-button-primary"><i class="fas fa-eye"></i> View Quiz</a>
                                <a href="../dashboard_admin.php" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form action="" method="POST" class="app-form-grid" id="question-form">
                            <input type="hidden" name="action" value="add_question">

                            <div class="app-form-section">
                                <h3 class="app-section-title">Question content</h3>
                                <div class="app-field">
                                    <label for="question_text" class="app-label">Question text*</label>
                                    <textarea id="question_text" name="question_text" rows="4" class="app-textarea" required></textarea>
                                </div>
                            </div>

                            <div class="app-form-section">
                                <h3 class="app-section-title">Answer options</h3>
                                <div class="app-field-row">
                                    <div class="app-field">
                                        <label for="option1" class="app-label">Option 1*</label>
                                        <input type="text" id="option1" name="option1" class="app-input" required>
                                    </div>
                                    <div class="app-field">
                                        <label for="option2" class="app-label">Option 2*</label>
                                        <input type="text" id="option2" name="option2" class="app-input" required>
                                    </div>
                                </div>
                                <div class="app-field-row">
                                    <div class="app-field">
                                        <label for="option3" class="app-label">Option 3*</label>
                                        <input type="text" id="option3" name="option3" class="app-input" required>
                                    </div>
                                    <div class="app-field">
                                        <label for="option4" class="app-label">Option 4*</label>
                                        <input type="text" id="option4" name="option4" class="app-input" required>
                                    </div>
                                </div>
                            </div>

                            <div class="app-form-section">
                                <h3 class="app-section-title">Scoring logic</h3>
                                <div class="app-field-row app-field-row-compact">
                                    <div class="app-field">
                                        <label for="correct_option" class="app-label">Correct option*</label>
                                        <select id="correct_option" name="correct_option" class="app-select" required>
                                            <option value="1">Option 1</option>
                                            <option value="2">Option 2</option>
                                            <option value="3">Option 3</option>
                                            <option value="4">Option 4</option>
                                        </select>
                                    </div>
                                    <div class="app-field">
                                        <label for="marks" class="app-label">Marks*</label>
                                        <input type="number" id="marks" name="marks" min="1" value="1" class="app-input" required>
                                    </div>
                                </div>
                                <p class="app-helper">Keep the scoring consistent with the total marks configured for the quiz.</p>
                            </div>

                            <div class="app-actions">
                                <button type="submit" class="app-button app-button-primary"><i class="fas fa-plus-circle"></i> Save Question</button>
                                <a href="../create_quiz.php" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Back to Builder</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Quiz brief</span>
                                <h2 class="app-panel-title">Setup details</h2>
                            </div>
                        </div>
                        <div class="app-preview-stack">
                            <div class="app-preview-stat">
                                <span>Target questions</span>
                                <strong><?php echo (int) $quiz['no_of_questions']; ?></strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Total marks</span>
                                <strong><?php echo (int) $quiz['total_marks']; ?></strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Timer</span>
                                <strong><?php echo (int) $quiz['timer_minutes']; ?>m</strong>
                            </div>
                        </div>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Authoring tips</span>
                                <h2 class="app-panel-title">Question quality checklist</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Keep the question prompt specific and unambiguous.</li>
                            <li><i class="fas fa-check-circle"></i> Make distractor options realistic but clearly incorrect.</li>
                            <li><i class="fas fa-check-circle"></i> Balance marks with difficulty and total quiz intent.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Next actions</span>
                                <h2 class="app-panel-title">Move the quiz forward</h2>
                            </div>
                        </div>
                        <div class="app-sidebar-actions">
                            <a href="../quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="app-button app-button-primary"><i class="fas fa-eye"></i> Preview Quiz</a>
                            <a href="../admin/view_leaderboard.php" class="app-button app-button-ghost"><i class="fas fa-trophy"></i> View Leaderboard</a>
                        </div>
                    </section>
                </aside>
            </div>

            <?php if (!empty($questions)): ?>
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Question library</span>
                            <h2 class="app-panel-title">Existing questions</h2>
                        </div>
                        <span class="app-status-pill"><?php echo $questionCount; ?> stored</span>
                    </div>

                    <div class="app-question-stack">
                        <?php foreach ($questions as $index => $question): ?>
                            <article class="app-question-shell">
                                <div class="app-question-shell-top">
                                    <div>
                                        <span class="app-question-order">Question <?php echo $index + 1; ?></span>
                                        <h3><?php echo htmlspecialchars($question['question_text']); ?></h3>
                                    </div>
                                    <form action="" method="POST" class="app-delete-inline">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>">
                                        <button type="submit" class="app-button app-button-ghost"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>

                                <div class="app-option-grid">
                                    <?php for ($optionIndex = 1; $optionIndex <= 4; $optionIndex++): ?>
                                        <?php
                                        $optionKey = 'option' . $optionIndex;
                                        $isCorrect = (int) $question['correct_option'] === $optionIndex;
                                        ?>
                                        <div class="app-option-pill <?php echo $isCorrect ? 'correct' : ''; ?>">
                                            <span><?php echo $optionIndex; ?>.</span>
                                            <strong><?php echo htmlspecialchars($question[$optionKey]); ?></strong>
                                            <?php if ($isCorrect): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <div class="app-meta-row">
                                    <span><i class="fas fa-bullseye"></i> Marks: <?php echo (int) $question['marks']; ?></span>
                                    <span><i class="fas fa-circle-check"></i> Correct option: <?php echo (int) $question['correct_option']; ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
<?php include '../includes/footer.php'; ?>
