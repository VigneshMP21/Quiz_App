<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isAdmin()) {
    redirect('dashboard_admin.php');
}

require_once 'includes/db.php';

$error = null;
$quizCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quizCode = strtoupper(trim($_POST['quiz_code'] ?? ''));
    
    if ($quizCode === '') {
        $error = 'Please enter a quiz code.';
    } else {
        $stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE unique_code = ?");
        $stmt->execute([$quizCode]);
        $quiz = $stmt->fetch();
        
        if ($quiz) {
            redirect("quiz.php?quiz_id=" . $quiz['id'], "Joining quiz: " . $quiz['title']);
        }

        $error = 'Invalid quiz code. Please check and try again.';
    }
}

$totalQuizzes = (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$activeCategories = (int) $pdo->query("SELECT COUNT(DISTINCT category) FROM quizzes")->fetchColumn();
$totalAttempts = (int) $pdo->query("SELECT COUNT(*) FROM user_attempts")->fetchColumn();

$isAdminView = false;
$homeLink = 'dashboard_user.php';
$logoutLink = 'logout.php';
$pageTitle = 'QuizPro - Join Quiz';
$pageKey = 'join_quiz';
$pageBodyClass = 'page-join-quiz';
$headerContext = 'Live entry flow';
$pageFooterSummary = 'Secure access to published quizzes through shared codes and cleaner learner entry flow.';

include 'includes/header.php';
?>
            <?php displayMessage(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><p><?php echo htmlspecialchars($error); ?></p></div>
            <?php endif; ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Secure quiz entry</span>
                    <h1 class="app-title">Join a quiz instantly with a verified access code</h1>
                    <p class="app-subtitle">Use the code shared by your instructor or organizer to jump straight into the correct quiz experience without searching manually.</p>
                    <div class="app-actions">
                        <a href="#join-quiz-form" class="app-button app-button-primary"><i class="fas fa-right-to-bracket"></i> Enter Code</a>
                        <a href="quiz.php" class="app-button app-button-ghost"><i class="fas fa-layer-group"></i> Explore Library</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Platform readiness</span>
                        <span class="app-status-pill"><i class="fas fa-bolt"></i> Live</span>
                    </div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Published quizzes</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $totalQuizzes; ?>">0</span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Active categories</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $activeCategories; ?>">0</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Quiz inventory</span>
                    <strong class="app-metric-value" data-count="<?php echo $totalQuizzes; ?>">0</strong>
                    <p>Published quizzes currently available through the platform.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Attempt history</span>
                    <strong class="app-metric-value" data-count="<?php echo $totalAttempts; ?>">0</strong>
                    <p>Total attempts already completed across the platform.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Certificate threshold</span>
                    <strong class="app-metric-static">70%+</strong>
                    <p>A strong finish can unlock certificate generation after the quiz.</p>
                </article>
            </section>

            <div class="app-grid app-join-grid">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Enter code</span>
                            <h2 class="app-panel-title">Join with confidence</h2>
                        </div>
                    </div>
                    <p class="app-panel-text">Codes are case-sensitive access keys mapped directly to the intended quiz. Paste or type the code exactly as shared.</p>

                    <form action="join_quiz.php" method="POST" class="app-form-grid" id="join-quiz-form">
                        <div class="app-field">
                            <label for="quiz_code" class="app-label">Quiz Code</label>
                            <input
                                type="text"
                                id="quiz_code"
                                name="quiz_code"
                                class="app-input app-code-input"
                                value="<?php echo htmlspecialchars($quizCode); ?>"
                                maxlength="20"
                                data-code-input
                                required
                            >
                        </div>

                        <div class="app-actions">
                            <button type="submit" class="app-button app-button-primary"><i class="fas fa-arrow-right"></i> Join Quiz</button>
                            <a href="dashboard_user.php" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        </div>
                    </form>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">How it works</span>
                                <h2 class="app-panel-title">Three fast steps</h2>
                            </div>
                        </div>
                        <div class="app-step-list">
                            <article class="app-step-card">
                                <span class="app-step-index">01</span>
                                <div>
                                    <strong>Get the code</strong>
                                    <p>Use the exact code shared by your instructor or organizer.</p>
                                </div>
                            </article>
                            <article class="app-step-card">
                                <span class="app-step-index">02</span>
                                <div>
                                    <strong>Verify the quiz</strong>
                                    <p>The code routes you directly to the matched quiz details page.</p>
                                </div>
                            </article>
                            <article class="app-step-card">
                                <span class="app-step-index">03</span>
                                <div>
                                    <strong>Start when ready</strong>
                                    <p>Review instructions, check the timer, and begin the attempt.</p>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Need an alternate path?</span>
                                <h2 class="app-panel-title">Use the quiz library</h2>
                            </div>
                        </div>
                        <p class="app-panel-text">If you do not have a code, browse the quiz library and start from available categories instead.</p>
                        <div class="app-sidebar-actions">
                            <a href="quiz.php" class="app-button app-button-primary"><i class="fas fa-layer-group"></i> Open Library</a>
                            <a href="contact.php" class="app-button app-button-ghost"><i class="fas fa-headset"></i> Ask for Help</a>
                        </div>
                    </section>
                </aside>
            </div>
<?php include 'includes/footer.php'; ?>
