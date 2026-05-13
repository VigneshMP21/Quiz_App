<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$categories = getQuizCategories();
$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'category' => '',
    'no_of_questions' => '',
    'total_marks' => '',
    'timer_minutes' => '10'
];

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
        $uniqueCode = generateUniqueCode();
        $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, category, no_of_questions, total_marks, timer_minutes, unique_code, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([
            $formData['title'],
            $formData['description'],
            $formData['category'],
            $noOfQuestions,
            $totalMarks,
            $timerMinutes,
            $uniqueCode,
            $_SESSION['user_id']
        ])) {
            $quizId = $pdo->lastInsertId();
            redirect("admin/add_questions.php?quiz_id=$quizId", 'Quiz created successfully. Now add questions.');
        }

        $errors[] = 'Failed to create quiz. Please try again.';
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

$isAdminView = true;
$homeLink = 'dashboard_admin.php';
$leaderboardLink = 'admin/view_leaderboard.php';
$logoutLink = 'logout.php';
$pageTitle = 'QuizPro - Create Quiz';
$pageKey = 'create_quiz';
$pageBodyClass = 'page-create-quiz';
$headerContext = 'Builder workspace';
$pageFooterSummary = 'Structured quiz creation with clearer authoring flow, launch guidance, and admin visibility.';

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

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Assessment builder</span>
                    <h1 class="app-title">Craft a quiz with stronger structure and launch clarity</h1>
                    <p class="app-subtitle">Set the title, category, difficulty envelope, and timing in one focused workspace before you move into question authoring.</p>
                    <div class="app-actions">
                        <button type="submit" form="create-quiz-form" class="app-button app-button-primary"><i class="fas fa-rocket"></i> Create and Add Questions</button>
                        <a href="quiz.php" class="app-button app-button-ghost"><i class="fas fa-layer-group"></i> View Quiz Library</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Operational snapshot</span>
                        <span class="app-status-pill"><i class="fas fa-shield-halved"></i> Admin only</span>
                    </div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">My quizzes</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $myQuizCount; ?>">0</span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Platform total</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $platformQuizCount; ?>">0</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Published quizzes</span>
                    <strong class="app-metric-value" data-count="<?php echo $platformQuizCount; ?>">0</strong>
                    <p>Total quizzes currently stored in the platform.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Your authored set</span>
                    <strong class="app-metric-value" data-count="<?php echo $myQuizCount; ?>">0</strong>
                    <p>Assessments already created under your admin account.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Available categories</span>
                    <strong class="app-metric-value" data-count="<?php echo $categoryCount; ?>">0</strong>
                    <p>Content lanes available for organizing the new quiz.</p>
                </article>
            </section>

            <div class="app-grid app-builder-grid">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Configuration</span>
                            <h2 class="app-panel-title">New quiz setup</h2>
                        </div>
                    </div>
                    <p class="app-panel-text">Start with clean metadata and balanced scoring. Once this saves, you will move directly into question authoring.</p>

                    <form action="create_quiz.php" method="POST" class="app-form-grid" id="create-quiz-form">
                        <div class="app-form-section">
                            <h3 class="app-section-title">Identity</h3>
                            <div class="app-field">
                                <label for="title" class="app-label">Quiz Title*</label>
                                <input type="text" id="title" name="title" class="app-input" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                            </div>
                            <div class="app-field">
                                <label for="description" class="app-label">Description</label>
                                <textarea id="description" name="description" rows="5" class="app-textarea"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                            </div>
                            <div class="app-field">
                                <label for="category" class="app-label">Category*</label>
                                <select id="category" name="category" class="app-select" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $formData['category'] === $category ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="app-form-section">
                            <h3 class="app-section-title">Scoring and timing</h3>
                            <div class="app-field-row">
                                <div class="app-field">
                                    <label for="no_of_questions" class="app-label">Number of Questions*</label>
                                    <input type="number" id="no_of_questions" name="no_of_questions" class="app-input" min="1" value="<?php echo htmlspecialchars($formData['no_of_questions']); ?>" required>
                                </div>
                                <div class="app-field">
                                    <label for="total_marks" class="app-label">Total Marks*</label>
                                    <input type="number" id="total_marks" name="total_marks" class="app-input" min="1" value="<?php echo htmlspecialchars($formData['total_marks']); ?>" required>
                                </div>
                                <div class="app-field">
                                    <label for="timer_minutes" class="app-label">Timer (minutes)</label>
                                    <input type="number" id="timer_minutes" name="timer_minutes" class="app-input" min="1" value="<?php echo htmlspecialchars($formData['timer_minutes']); ?>">
                                </div>
                            </div>
                            <p class="app-helper">Balanced quizzes usually keep marks proportional to the number of questions and use a timer that matches expected difficulty.</p>
                        </div>

                        <div class="app-actions">
                            <button type="submit" class="app-button app-button-primary"><i class="fas fa-plus"></i> Create Quiz</button>
                            <a href="dashboard_admin.php" class="app-button app-button-ghost"><i class="fas fa-xmark"></i> Cancel</a>
                        </div>
                    </form>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Live preview</span>
                                <h2 class="app-panel-title">Current setup</h2>
                            </div>
                        </div>
                        <div class="app-preview-stack">
                            <div class="app-preview-stat">
                                <span>Questions</span>
                                <strong class="app-metric-value" data-count="<?php echo $questionCountPreview; ?>">0</strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Total marks</span>
                                <strong class="app-metric-value" data-count="<?php echo $marksPreview; ?>">0</strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Timer</span>
                                <strong class="app-metric-value" data-count="<?php echo $timerPreview; ?>">0</strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Marks per question</span>
                                <strong class="app-metric-static"><?php echo $marksPerQuestion > 0 ? htmlspecialchars((string) $marksPerQuestion) : '0'; ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Launch checklist</span>
                                <h2 class="app-panel-title">Before you publish</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Use a clear title that matches the intended skill level.</li>
                            <li><i class="fas fa-check-circle"></i> Keep the timer realistic for the number of questions.</li>
                            <li><i class="fas fa-check-circle"></i> Move into question entry immediately after saving.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Quick direction</span>
                                <h2 class="app-panel-title">Builder tips</h2>
                            </div>
                        </div>
                        <div class="app-faq-list">
                            <article class="app-faq-item">
                                <strong>Need a clean structure?</strong>
                                <p>Start with the quiz purpose, then align total marks and timer to the expected effort.</p>
                            </article>
                            <article class="app-faq-item">
                                <strong>Planning category coverage?</strong>
                                <p>Use the category field to keep quiz discovery and reporting organized across the platform.</p>
                            </article>
                        </div>
                    </section>
                </aside>
            </div>
<?php include 'includes/footer.php'; ?>
