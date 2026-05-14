<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$isAdminView = isAdmin();
$homeLink = $isAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$leaderboardLink = 'admin/view_leaderboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdminView && ($_POST['action'] ?? '') === 'delete_quiz') {
    $deleteQuizId = (int) ($_POST['quiz_id'] ?? 0);
    $returnCategory = trim((string) ($_POST['return_category'] ?? ''));
    $returnUrl = 'quiz.php' . ($returnCategory !== '' ? '?category=' . urlencode($returnCategory) : '');

    if ($deleteQuizId <= 0) {
        redirect($returnUrl, 'Invalid quiz selection.', 'error');
    }

    try {
        $quizLookupStmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
        $quizLookupStmt->execute([$deleteQuizId]);
        $quizToDelete = $quizLookupStmt->fetch(PDO::FETCH_ASSOC);

        if (!$quizToDelete) {
            redirect($returnUrl, 'Quiz not found.', 'error');
        }

        $pdo->beginTransaction();

        $deleteCertificatesStmt = $pdo->prepare("DELETE FROM certificates
                                                WHERE attempt_id IN (
                                                    SELECT id FROM user_attempts WHERE quiz_id = ?
                                                )");
        $deleteCertificatesStmt->execute([$deleteQuizId]);

        $deleteAttemptsStmt = $pdo->prepare("DELETE FROM user_attempts WHERE quiz_id = ?");
        $deleteAttemptsStmt->execute([$deleteQuizId]);

        $deleteQuizStmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
        $deleteQuizStmt->execute([$deleteQuizId]);

        if ($deleteQuizStmt->rowCount() !== 1) {
            throw new RuntimeException('Quiz delete failed.');
        }

        $pdo->commit();
        redirect($returnUrl, 'Quiz deleted successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirect($returnUrl, 'Failed to delete quiz. Please try again.', 'error');
    }
}

// Join Quiz Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_by_code') {
    $code = strtoupper(trim($_POST['quiz_code'] ?? ''));
    if ($code !== '') {
        $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE unique_code = ?");
        $stmt->execute([$code]);
        $quiz = $stmt->fetch();
        if ($quiz) {
            redirect("quiz.php?quiz_id=" . $quiz['id']);
        } else {
            redirect("quiz.php", "Invalid quiz code. Please check and try again.", "error");
        }
    } else {
        redirect("quiz.php", "Please enter a quiz code.", "error");
    }
}

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$quizId = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : null;
$quiz = null;
$quizzes = [];

$libraryStats = $pdo->query("SELECT 
                            COUNT(*) AS total_quizzes,
                            COUNT(DISTINCT category) AS total_categories,
                            COALESCE(SUM(no_of_questions), 0) AS total_questions,
                            COALESCE(AVG(timer_minutes), 0) AS average_timer
                            FROM quizzes")->fetch(PDO::FETCH_ASSOC);

if ($category !== '') {
    $stmt = $pdo->prepare("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes, unique_code 
                          FROM quizzes 
                          WHERE category = ? 
                          ORDER BY title");
    $stmt->execute([$category]);
    $quizzes = $stmt->fetchAll();
} elseif ($quizId) {
    $stmt = $pdo->prepare("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes, unique_code 
                          FROM quizzes 
                          WHERE id = ?");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        redirect('quiz.php', 'Quiz not found.', 'error');
    }
} else {
    $stmt = $pdo->query("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes, unique_code 
                         FROM quizzes 
                         ORDER BY category, title");
    $quizzes = $stmt->fetchAll();
}

$categories = getQuizCategories();
$visibleQuizCount = count($quizzes);
$averageTimerRounded = (int) round((float) ($libraryStats['average_timer'] ?? 0));
$totalQuestionLoad = 0;
$categoryPeerCount = 0;

if ($quiz) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE category = ?");
    $stmt->execute([$quiz['category']]);
    $categoryPeerCount = (int) $stmt->fetchColumn();
} else {
    foreach ($quizzes as $listedQuiz) {
        $totalQuestionLoad += (int) $listedQuiz['no_of_questions'];
    }
}

$heroTitle = $quiz
    ? htmlspecialchars($quiz['title'])
    : ($isAdminView ? 'Quiz library and publishing surface' : 'Explore quizzes and choose your next challenge');
$heroSubtitle = $quiz
    ? (!empty($quiz['description'])
        ? htmlspecialchars($quiz['description'])
        : 'Review the structure, scoring, and timing before you move into the quiz flow.')
    : ($category !== ''
        ? 'Filtered view for the ' . htmlspecialchars($category) . ' category. Compare available quizzes and open the one that fits your goal.'
        : 'Browse the full quiz catalog, filter by category, and move into a quiz detail view with clearer context.');
$categoryIconMap = [
    'H' => 'fa-code',
    'C' => 'fa-laptop-code',
    'J' => 'fa-microchip',
    'P' => 'fa-database',
    'R' => 'fa-diagram-project',
    'D' => 'fa-chart-simple',
    'M' => 'fa-calculator',
    'A' => 'fa-brain'
];

$pageTitle = 'QuizPro - ' . ($quiz ? $quiz['title'] : 'Quizzes');
$pageKey = 'quiz';
$pageBodyClass = 'page-quiz';
$headerContext = $isAdminView ? 'Operations library' : 'Learning library';
$pageFooterSummary = 'Professional quiz browsing, detail review, and launch flow across the full catalog.';

include 'includes/header.php';
?>
            <?php displayMessage(); ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker"><?php echo $quiz ? 'Quiz briefing' : 'Quiz catalog'; ?></span>
                    <h1 class="app-title"><?php echo $heroTitle; ?></h1>
                    <p class="app-subtitle"><?php echo $heroSubtitle; ?></p>
                    <div class="app-actions">
                        <?php if ($quiz): ?>
                            <?php if ($isAdminView): ?>
                                <a href="create_quiz.php" class="app-button app-button-primary"><i class="fas fa-plus"></i> Create Another Quiz</a>
                            <?php else: ?>
                                <a href="user/take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="app-button app-button-primary"><i class="fas fa-play"></i> Start Quiz</a>
                            <?php endif; ?>
                            <a href="quiz.php<?php echo $quiz['category'] ? '?category=' . urlencode($quiz['category']) : ''; ?>" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Back to Library</a>
                        <?php else: ?>
                        <?php if ($isAdminView): ?>
                            <a href="create_quiz.php" class="app-button app-button-primary">
                                <i class="fas fa-plus"></i> Create Quiz
                            </a>
                        <?php else: ?>
                            <button type="button" class="app-button app-button-primary" onclick="openJoinModal()">
                                <i class="fas fa-right-to-bracket"></i> Join with Code
                            </button>
                        <?php endif; ?>
                            <a href="contact.php" class="app-button app-button-ghost"><i class="fas fa-headset"></i> Get Support</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span><?php echo $quiz ? 'Quiz essentials' : 'Library health'; ?></span>
                        <span class="app-status-pill"><i class="fas fa-sparkles"></i> Live</span>
                    </div>
                    <div class="app-hero-stack">
                        <?php if ($quiz): ?>
                            <div class="app-hero-mini-card">
                                <span class="app-hero-mini-label">Questions</span>
                                <span class="app-hero-mini-value app-metric-value" data-count="<?php echo (int) $quiz['no_of_questions']; ?>">0</span>
                            </div>
                            <div class="app-hero-mini-card">
                                <span class="app-hero-mini-label">Timer</span>
                                <span class="app-hero-mini-value app-metric-value" data-count="<?php echo (int) $quiz['timer_minutes']; ?>">0</span>
                            </div>
                        <?php else: ?>
                            <div class="app-hero-mini-card">
                                <span class="app-hero-mini-label">Published quizzes</span>
                                <span class="app-hero-mini-value app-metric-value" data-count="<?php echo (int) ($libraryStats['total_quizzes'] ?? 0); ?>">0</span>
                            </div>
                            <div class="app-hero-mini-card">
                                <span class="app-hero-mini-label">Active categories</span>
                                <span class="app-hero-mini-value app-metric-value" data-count="<?php echo (int) ($libraryStats['total_categories'] ?? 0); ?>">0</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if ($quiz): ?>
                <section class="app-metric-grid">
                    <article class="app-metric-card">
                        <span class="app-metric-label">Total marks</span>
                        <strong class="app-metric-value" data-count="<?php echo (int) $quiz['total_marks']; ?>">0</strong>
                        <p>Total score available across the full quiz.</p>
                    </article>
                    <article class="app-metric-card">
                        <span class="app-metric-label">Category peers</span>
                        <strong class="app-metric-value" data-count="<?php echo $categoryPeerCount; ?>">0</strong>
                        <p>Other quizzes currently published in this category.</p>
                    </article>
                    <article class="app-metric-card">
                        <span class="app-metric-label">Access code</span>
                        <strong class="app-metric-static"><?php echo htmlspecialchars($quiz['unique_code'] ?: 'N/A'); ?></strong>
                        <p>Use this code when joining directly through the code-entry flow.</p>
                    </article>
                </section>

                <div class="app-grid app-detail-layout">
                    <section class="app-panel">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Structure</span>
                                <h2 class="app-panel-title">Quiz meta and instructions</h2>
                            </div>
                            <span class="app-status-pill"><?php echo htmlspecialchars($quiz['category']); ?></span>
                        </div>

                        <div class="app-detail-meta-grid">
                            <article class="app-detail-meta-card">
                                <i class="fas fa-list-check"></i>
                                <div>
                                    <strong><?php echo (int) $quiz['no_of_questions']; ?> Questions</strong>
                                    <p>Move through each question with a clear score target in mind.</p>
                                </div>
                            </article>
                            <article class="app-detail-meta-card">
                                <i class="fas fa-stopwatch"></i>
                                <div>
                                    <strong><?php echo (int) $quiz['timer_minutes']; ?> Minute Timer</strong>
                                    <p>Plan your pace early to avoid a rushed finish near the end.</p>
                                </div>
                            </article>
                            <article class="app-detail-meta-card">
                                <i class="fas fa-bullseye"></i>
                                <div>
                                    <strong><?php echo (int) $quiz['total_marks']; ?> Total Marks</strong>
                                    <p>Use the scoring load to judge how much precision each answer needs.</p>
                                </div>
                            </article>
                        </div>

                        <div class="app-instruction-list">
                            <article class="app-instruction-card">
                                <i class="fas fa-circle-check"></i>
                                <div>
                                    <strong>Read carefully</strong>
                                    <p>Take a moment to understand each question before committing to an answer.</p>
                                </div>
                            </article>
                            <article class="app-instruction-card">
                                <i class="fas fa-gauge-high"></i>
                                <div>
                                    <strong>Manage time actively</strong>
                                    <p>The timer keeps running, so avoid spending too long on a single question.</p>
                                </div>
                            </article>
                            <article class="app-instruction-card">
                                <i class="fas fa-paper-plane"></i>
                                <div>
                                    <strong>Submission is final</strong>
                                    <p>Once the timer expires, the system submits automatically.</p>
                                </div>
                            </article>
                            <article class="app-instruction-card">
                                <i class="fas fa-certificate"></i>
                                <div>
                                    <strong>Certificates unlock at 70%+</strong>
                                    <p>Strong performance can turn into a certificate once the result is recorded.</p>
                                </div>
                            </article>
                        </div>
                    </section>

                    <aside class="app-sidebar">
                        <section class="app-panel app-panel-compact">
                            <div class="app-panel-head">
                                <div>
                                    <span class="app-panel-kicker">Ready check</span>
                                    <h2 class="app-panel-title">Before you begin</h2>
                                </div>
                            </div>
                            <ul class="app-note-list">
                                <li><i class="fas fa-check-circle"></i> Stable internet and a quiet window for the timer.</li>
                                <li><i class="fas fa-check-circle"></i> Read the full description and scoring structure first.</li>
                                <li><i class="fas fa-check-circle"></i> Start only when you can complete the full attempt.</li>
                            </ul>
                        </section>

                        <section class="app-panel app-panel-compact">
                            <div class="app-panel-head">
                                <div>
                                    <span class="app-panel-kicker">Next actions</span>
                                    <h2 class="app-panel-title"><?php echo $isAdminView ? 'Admin shortcuts' : 'Learner shortcuts'; ?></h2>
                                </div>
                            </div>
                            <div class="app-sidebar-actions">
                                <?php if ($isAdminView): ?>
                                    <a href="create_quiz.php" class="app-button app-button-primary"><i class="fas fa-plus"></i> Build Another Quiz</a>
                                    <a href="<?php echo $leaderboardLink; ?>" class="app-button app-button-ghost"><i class="fas fa-trophy"></i> View Leaderboard</a>
                                <?php else: ?>
                                    <a href="user/take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="app-button app-button-primary"><i class="fas fa-play"></i> Start Quiz</a>
                                    <a href="join_quiz.php" class="app-button app-button-ghost"><i class="fas fa-right-to-bracket"></i> Join Another</a>
                                <?php endif; ?>
                            </div>
                        </section>
                    </aside>
                </div>
            <?php else: ?>
                <!-- Metric grid removed -->

                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Filter and browse</span>
                            <h2 class="app-panel-title">Discover the right quiz</h2>
                        </div>
                        <?php if ($category !== ''): ?>
                            <span class="app-status-pill"><i class="fas fa-filter"></i> <?php echo htmlspecialchars($category); ?></span>
                        <?php else: ?>
                            <span class="app-status-pill"><i class="fas fa-layer-group"></i> All categories</span>
                        <?php endif; ?>
                    </div>

                    <div class="app-filter-shell">
                        <form method="GET" action="quiz.php" class="app-filter-form">
                            <div class="app-field">
                                <label for="category" class="app-label">Filter by category</label>
                                <select id="category" name="category" class="app-select" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $categoryName): ?>
                                        <option value="<?php echo htmlspecialchars($categoryName); ?>" <?php echo $category === $categoryName ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($categoryName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($category !== ''): ?>
                                <a href="quiz.php" class="app-button app-button-ghost"><i class="fas fa-rotate-left"></i> Reset Filter</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (empty($quizzes)): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-icon"><i class="fas fa-layer-group"></i></div>
                            <h3>No quizzes found</h3>
                            <p>Try another category or return later when more quizzes are available.</p>
                            <a href="quiz.php" class="app-button app-button-primary"><i class="fas fa-compass"></i> Show All Quizzes</a>
                        </div>
                    <?php else: ?>
                        <div class="app-quiz-grid">
                            <?php foreach ($quizzes as $listedQuiz): ?>
                                <?php
                                $categoryInitial = strtoupper(substr($listedQuiz['category'], 0, 1));
                                $categoryIcon = $categoryIconMap[$categoryInitial] ?? 'fa-book';
                                $description = trim((string) ($listedQuiz['description'] ?? ''));
                                $description = $description !== '' ? $description : 'Open the detail page to review structure, time, and scoring before you start.';
                                if (strlen($description) > 120) {
                                    $description = substr($description, 0, 117) . '...';
                                }
                                ?>
                                <article class="app-quiz-card">
                                    <div class="app-quiz-card-head">
                                        <div class="app-quiz-icon"><i class="fas <?php echo $categoryIcon; ?>"></i></div>
                                        <span class="app-quiz-chip"><?php echo htmlspecialchars($listedQuiz['category']); ?></span>
                                    </div>
                                    <h3><?php echo htmlspecialchars($listedQuiz['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($description); ?></p>
                                    <div class="app-quiz-stats">
                                        <span><i class="fas fa-list-check"></i> <?php echo (int) $listedQuiz['no_of_questions']; ?> Questions</span>
                                        <span><i class="fas fa-star"></i> <?php echo (int) $listedQuiz['total_marks']; ?> Marks</span>
                                        <span><i class="fas fa-stopwatch"></i> <?php echo (int) $listedQuiz['timer_minutes']; ?> Min</span>
                                    </div>
                                    <div class="app-quiz-card-actions">
                                        <a href="quiz.php?quiz_id=<?php echo $listedQuiz['id']; ?>" class="app-button app-button-primary"><i class="fas fa-eye"></i> View Details</a>
                                        <?php if ($isAdminView): ?>
                                            <div class="app-quiz-card-tools">
                                                <a href="create_quiz.php?edit_id=<?php echo $listedQuiz['id']; ?>" class="app-quiz-card-icon" title="Edit quiz" aria-label="Edit quiz">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <form action="quiz.php<?php echo $category !== '' ? '?category=' . urlencode($category) : ''; ?>" method="POST" class="app-delete-inline" onsubmit="return confirm('Delete this quiz and all related attempts?');">
                                                    <input type="hidden" name="action" value="delete_quiz">
                                                    <input type="hidden" name="quiz_id" value="<?php echo (int) $listedQuiz['id']; ?>">
                                                    <input type="hidden" name="return_category" value="<?php echo htmlspecialchars($category); ?>">
                                                    <button type="submit" class="app-quiz-card-icon app-quiz-card-icon-danger" title="Delete quiz" aria-label="Delete quiz">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
<?php include 'includes/footer.php'; ?>

    <!-- Join Quiz Modal Overlay -->
    <div id="joinQuizModal" class="app-modal-overlay" style="display:none;">
        <div class="app-modal-card">
            <div class="app-modal-head">
                <h3 class="app-modal-title">Join Quiz</h3>
                <button type="button" class="app-modal-close" onclick="closeJoinModal()">&times;</button>
            </div>
            <div class="app-modal-body">
                <p>Enter the unique access code to jump straight into the quiz.</p>
                <form action="quiz.php" method="POST" class="app-form">
                    <input type="hidden" name="action" value="join_by_code">
                    <div class="app-field">
                        <label for="modal_quiz_code" class="app-label">Unique Code</label>
                        <input type="text" name="quiz_code" id="modal_quiz_code" class="app-input" placeholder="E.g. QUIZ-123" required maxlength="20">
                    </div>
                    <div class="app-modal-actions">
                        <button type="submit" class="app-button app-button-primary">Join Quiz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openJoinModal() {
            const modal = document.getElementById('joinQuizModal');
            modal.style.display = 'flex';
            document.getElementById('modal_quiz_code').focus();
        }

        function closeJoinModal() {
            document.getElementById('joinQuizModal').style.display = 'none';
        }

        // Close on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeJoinModal();
        });

        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('joinQuizModal');
            if (event.target == modal) closeJoinModal();
        }
    </script>
