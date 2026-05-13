<?php
session_start();
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

require_once '../includes/db.php';

$quizzes = $pdo->query("SELECT id, title FROM quizzes ORDER BY title")->fetchAll();
$totalQuizzes = count($quizzes);
$activeBoards = (int) $pdo->query("SELECT COUNT(DISTINCT quiz_id) FROM user_attempts")->fetchColumn();
$overallAttempts = (int) $pdo->query("SELECT COUNT(*) FROM user_attempts")->fetchColumn();
$overallTopScore = (float) $pdo->query("SELECT COALESCE(MAX(score), 0) FROM user_attempts")->fetchColumn();

$quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : null;
$leaderboard = [];
$quizTitle = null;
$selectedStats = [
    'attempts' => 0,
    'top_score' => 0,
    'avg_score' => 0,
    'latest_attempt' => null,
];

if ($quiz_id) {
    $stmt = $pdo->prepare("SELECT u.username, ua.score, ua.completed_at
                          FROM user_attempts ua
                          JOIN users u ON ua.user_id = u.id
                          WHERE ua.quiz_id = ?
                          ORDER BY ua.score DESC, ua.completed_at ASC
                          LIMIT 10");
    $stmt->execute([$quiz_id]);
    $leaderboard = $stmt->fetchAll();

    $titleStmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
    $titleStmt->execute([$quiz_id]);
    $quizTitle = $titleStmt->fetchColumn() ?: null;

    $statsStmt = $pdo->prepare("SELECT
                               COUNT(*) AS attempts,
                               COALESCE(MAX(score), 0) AS top_score,
                               COALESCE(AVG(score), 0) AS avg_score,
                               MAX(completed_at) AS latest_attempt
                               FROM user_attempts
                               WHERE quiz_id = ?");
    $statsStmt->execute([$quiz_id]);
    $selectedStats = $statsStmt->fetch() ?: $selectedStats;
}

$selectedAttempts = (int) ($selectedStats['attempts'] ?? 0);
$selectedTopScore = round((float) ($selectedStats['top_score'] ?? 0), 1);
$selectedAverageScore = round((float) ($selectedStats['avg_score'] ?? 0), 1);
$latestAttemptLabel = !empty($selectedStats['latest_attempt'])
    ? date('M j, Y', strtotime((string) $selectedStats['latest_attempt']))
    : 'No attempts yet';

$heroSummary = $quiz_id && $quizTitle
    ? 'Track the strongest performers for this quiz, compare attempt volume, and spot how the score curve is behaving over time.'
    : 'Select any published quiz to review the current leaderboard, recent high scorers, and participation signals from one admin surface.';

$pathPrefix = '..';
$isAdminView = true;
$homeLink = 'dashboard_admin.php';
$leaderboardLink = 'admin/view_leaderboard.php';
$logoutLink = 'logout.php';
$pageTitle = 'QuizPro - Leaderboard';
$pageKey = 'leaderboard';
$pageBodyClass = 'page-admin-leaderboard';
$headerContext = 'Leaderboard intelligence';
$pageFooterSummary = 'A focused leaderboard workspace for comparing top performers, score spread, and quiz-level participation signals.';

include '../includes/header.php';
?>
            <?php displayMessage(); ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Leaderboard intelligence</span>
                    <h1 class="app-title"><?php echo $quizTitle ? htmlspecialchars($quizTitle) : 'Quiz performance rankings'; ?></h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                    <div class="app-actions">
                        <a href="#leaderboard-filter" class="app-button app-button-primary"><i class="fas fa-filter"></i> Select Quiz</a>
                        <a href="../dashboard_admin.php" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Board pulse</span>
                        <span class="app-status-pill"><i class="fas fa-trophy"></i> Live ranking</span>
                    </div>
                    <div class="app-hero-panel-copy">
                        <strong>Latest attempt</strong>
                        <p><?php echo htmlspecialchars($latestAttemptLabel); ?></p>
                    </div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Active boards</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $activeBoards; ?>">0</span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Selected attempts</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $selectedAttempts; ?>">0</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Published quizzes</span>
                    <strong class="app-metric-value" data-count="<?php echo $totalQuizzes; ?>">0</strong>
                    <p>Quizzes currently available to inspect through the leaderboard filter.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Overall attempts</span>
                    <strong class="app-metric-value" data-count="<?php echo $overallAttempts; ?>">0</strong>
                    <p>Total attempt records captured across all quizzes.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Top score</span>
                    <strong class="app-metric-static"><?php echo htmlspecialchars((string) $overallTopScore); ?></strong>
                    <p>Highest raw score currently recorded anywhere on the platform.</p>
                </article>
            </section>

            <div class="app-grid app-contact-grid">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Choose a board</span>
                            <h2 class="app-panel-title">Filter leaderboard by quiz</h2>
                        </div>
                    </div>

                    <form method="GET" action="view_leaderboard.php" class="app-form-grid" id="leaderboard-filter">
                        <div class="app-field">
                            <label for="quiz_id" class="app-label">Select quiz</label>
                            <select id="quiz_id" name="quiz_id" class="app-select" onchange="this.form.submit()">
                                <option value="">-- Select a quiz --</option>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <option value="<?php echo (int) $quiz['id']; ?>" <?php echo $quiz_id === (int) $quiz['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quiz['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($quiz_id && !empty($leaderboard)): ?>
                        <div class="app-leaderboard-stack">
                            <?php foreach ($leaderboard as $index => $entry): ?>
                                <?php
                                $rank = $index + 1;
                                $rankClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : 'default'));
                                ?>
                                <article class="app-leaderboard-row">
                                    <span class="app-leaderboard-rank <?php echo $rankClass; ?>">#<?php echo $rank; ?></span>
                                    <div class="app-leaderboard-user">
                                        <strong><?php echo htmlspecialchars($entry['username']); ?></strong>
                                        <span><?php echo date('M j, Y', strtotime($entry['completed_at'])); ?></span>
                                    </div>
                                    <div class="app-leaderboard-score">
                                        <strong><?php echo htmlspecialchars((string) $entry['score']); ?></strong>
                                        <span>score</span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($quiz_id): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-icon"><i class="fas fa-chart-line"></i></div>
                            <h3>No attempts yet</h3>
                            <p>This quiz has not received any completed attempts yet, so the leaderboard is still empty.</p>
                        </div>
                    <?php else: ?>
                        <div class="app-empty-state">
                            <div class="app-empty-icon"><i class="fas fa-filter-circle-dollar"></i></div>
                            <h3>Select a quiz</h3>
                            <p>Choose any published quiz from the selector above to load its top performers and score activity.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Selected snapshot</span>
                                <h2 class="app-panel-title">Board metrics</h2>
                            </div>
                        </div>
                        <div class="app-preview-stack">
                            <div class="app-preview-stat">
                                <span>Attempts</span>
                                <strong><?php echo $selectedAttempts; ?></strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Top score</span>
                                <strong><?php echo htmlspecialchars((string) $selectedTopScore); ?></strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Average score</span>
                                <strong><?php echo htmlspecialchars((string) $selectedAverageScore); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">How to read it</span>
                                <h2 class="app-panel-title">Leaderboard guidance</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Rank is sorted by score, then earlier completion time when tied.</li>
                            <li><i class="fas fa-check-circle"></i> Use low attempt counts as a signal that a quiz may need better visibility.</li>
                            <li><i class="fas fa-check-circle"></i> Compare top score and average score to judge difficulty spread.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Admin shortcuts</span>
                                <h2 class="app-panel-title">Next actions</h2>
                            </div>
                        </div>
                        <div class="app-sidebar-actions">
                            <a href="../quiz.php" class="app-button app-button-primary"><i class="fas fa-layer-group"></i> Open Quiz Library</a>
                            <a href="../create_quiz.php" class="app-button app-button-ghost"><i class="fas fa-plus-circle"></i> Create Quiz</a>
                        </div>
                    </section>
                </aside>
            </div>
<?php include '../includes/footer.php'; ?>
