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
$headAssets = '<link rel="stylesheet" href="assets/css/mobile_view.css">';

include 'includes/header.php';
?>
            <?php displayMessage(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><p><?php echo htmlspecialchars($error); ?></p></div>
            <?php endif; ?>



            <div class="app-grid" style="margin-top: 24px;">
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


            </div>
<?php include 'includes/footer.php'; ?>
