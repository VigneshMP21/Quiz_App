<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$isAdminView = isAdmin();
$homeLink = $isAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$logoutLink = 'logout.php';

if (!isset($_SESSION['quiz_result'])) {
    redirect($homeLink);
}

$result = $_SESSION['quiz_result'];
unset($_SESSION['quiz_result']);

$stmt = $pdo->prepare("SELECT title, category, total_marks, timer_minutes FROM quizzes WHERE id = ?");
$stmt->execute([(int) $result['quiz_id']]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    redirect($homeLink, 'Quiz result could not be loaded.', 'error');
}

$totalMarks = max(1, (int) $quiz['total_marks']);
$percentage = ((int) $result['score'] / $totalMarks) * 100;
$percentageRounded = round($percentage, 1);
$totalQuestions = count($result['answers'] ?? []);
$correctAnswers = (int) ($result['correct'] ?? 0);
$wrongAnswers = (int) ($result['wrong'] ?? 0);
$unansweredAnswers = (int) ($result['unanswered'] ?? max(0, $totalQuestions - $correctAnswers - $wrongAnswers));
$timeTaken = max(0, (int) ($result['time_taken'] ?? 0));
$timeTakenLabel = gmdate($timeTaken >= 3600 ? 'H:i:s' : 'i:s', $timeTaken);
$attemptId = (int) ($result['attempt_id'] ?? 0);
$isPass = $percentage >= 70;
$scoreTone = $percentage >= 85 ? 'elite' : ($percentage >= 70 ? 'strong' : ($percentage >= 50 ? 'steady' : 'rebuild'));
$performanceLabel = $percentage >= 85
    ? 'Excellent finish'
    : ($isPass ? 'Certificate-ready performance' : ($percentage >= 50 ? 'Solid attempt with room to improve' : 'Rebuild and try a different challenge'));
$heroSummary = $isPass
    ? 'You cleared the 70% threshold. Review the answers below, then claim your certificate or move into the next quiz.'
    : 'Your result is recorded. Review the answer breakdown, identify weak spots, and use the quiz library to sharpen the next attempt.';
$existingCertificate = null;

if (!$isAdminView && $attemptId > 0) {
    $certStmt = $pdo->prepare("SELECT id, certificate_path FROM certificates WHERE attempt_id = ?");
    $certStmt->execute([$attemptId]);
    $existingCertificate = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$certificateActionHref = null;
$certificateActionLabel = null;

if (!$isAdminView && $isPass) {
    if ($existingCertificate && !empty($existingCertificate['certificate_path'])) {
        $certificateActionHref = 'certificates.php';
        $certificateActionLabel = 'Open Certificate';
    } elseif ($attemptId > 0) {
        $certificateActionHref = 'generate_certificate.php?attempt_id=' . $attemptId;
        $certificateActionLabel = 'Generate Certificate';
    }
}

$pageTitle = 'QuizPro - Result';
$pageKey = 'quiz';
$pageBodyClass = 'page-score';
$headerContext = 'Result review';
$pageFooterSummary = 'A structured result review with answer analysis, score visibility, and next-step actions.';
$headAssets = '<link rel="stylesheet" href="assets/css/mobile_view.css">';

include 'includes/header.php';
?>
            <?php displayMessage(); ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Result review</span>
                    <h1 class="app-title"><?php echo htmlspecialchars((string) $quiz['title']); ?></h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                    <div class="app-score-hero-stats">
                        <div class="app-score-stat">
                            <span class="app-score-stat-label">Score</span>
                            <strong class="app-score-stat-value"><?php echo (int) $result['score']; ?> / <?php echo $totalMarks; ?></strong>
                        </div>
                        <div class="app-score-stat">
                            <span class="app-score-stat-label">Correct Answers</span>
                            <strong class="app-score-stat-value"><?php echo $correctAnswers; ?></strong>
                        </div>
                        <div class="app-score-stat">
                            <span class="app-score-stat-label">Time Taken</span>
                            <strong class="app-score-stat-value"><?php echo htmlspecialchars($timeTakenLabel); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Performance</span>
                        <span class="app-result-badge <?php echo $isPass ? 'pass' : 'retry'; ?>">
                            <i class="fas <?php echo $isPass ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                            <?php echo $isPass ? 'Passed' : 'Below threshold'; ?>
                        </span>
                    </div>
                    <div class="app-result-gauge" style="--result-value: <?php echo max(0, min(100, round($percentage))); ?>;">
                        <strong><?php echo $percentageRounded; ?>%</strong>
                        <span><?php echo htmlspecialchars($performanceLabel); ?></span>
                    </div>
                </div>
            </section>

            <div style="margin-top: 3rem;">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Answer breakdown</span>
                            <h2 class="app-panel-title">Question-by-question review</h2>
                        </div>
                        <span class="app-status-pill"><?php echo $totalQuestions; ?> total</span>
                    </div>
                    <p class="app-panel-text">Use the review below to understand where you were accurate, where you missed, and which answers to revisit before your next quiz.</p>

                    <?php if (empty($result['answers'])): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-icon"><i class="fas fa-clipboard-question"></i></div>
                            <h3>No answer detail available</h3>
                            <p>The attempt summary is missing the detailed answer record for this quiz.</p>
                            <a href="quiz.php" class="app-button app-button-primary"><i class="fas fa-layer-group"></i> Open Quiz Library</a>
                        </div>
                    <?php else: ?>
                        <div class="app-result-review-list">
                            <?php foreach ($result['answers'] as $index => $answer): ?>
                                <?php
                                $userAnswer = (string) ($answer['user_answer'] ?? 'Not answered');
                                $correctAnswer = (string) ($answer['correct_answer'] ?? '');
                                $isCorrect = !empty($answer['is_correct']);
                                $userAnswerTone = $isCorrect ? 'good' : ($userAnswer === 'Not answered' ? 'neutral' : 'bad');
                                ?>
                                <article class="app-result-review-card <?php echo $isCorrect ? 'correct' : 'wrong'; ?>">
                                    <div class="app-result-review-head">
                                        <span class="app-question-order">Question <?php echo $index + 1; ?></span>
                                        <span class="app-result-review-status <?php echo $isCorrect ? 'correct' : 'wrong'; ?>">
                                            <i class="fas <?php echo $isCorrect ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                                            <?php echo $isCorrect ? 'Correct' : ($userAnswer === 'Not answered' ? 'Unanswered' : 'Wrong'); ?>
                                        </span>
                                    </div>
                                    <h3><?php echo htmlspecialchars((string) ($answer['question_text'] ?? '')); ?></h3>
                                    <div class="app-result-answer-grid">
                                        <div class="app-result-answer-box <?php echo $userAnswerTone; ?>">
                                            <span>Your answer</span>
                                            <strong><?php echo htmlspecialchars($userAnswer); ?></strong>
                                        </div>
                                        <div class="app-result-answer-box good">
                                            <span>Correct answer</span>
                                            <strong><?php echo htmlspecialchars($correctAnswer); ?></strong>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="app-score-actions">
                <a href="<?php echo htmlspecialchars($homeLink); ?>" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <?php if ($certificateActionHref !== null): ?>
                    <a href="<?php echo htmlspecialchars($certificateActionHref); ?>" class="app-button app-button-primary"><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($certificateActionLabel); ?></a>
                <?php endif; ?>
            </div>
<?php include 'includes/footer.php'; ?>
