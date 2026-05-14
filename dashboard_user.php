<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isAdmin()) {
    redirect('dashboard_admin.php');
}

// Join Quiz Logic (Process POST before headers if needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_by_code') {
    $code = strtoupper(trim($_POST['quiz_code'] ?? ''));
    if ($code !== '') {
        $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE unique_code = ?");
        $stmt->execute([$code]);
        $quiz = $stmt->fetch();
        if ($quiz) {
            redirect("quiz.php?quiz_id=" . $quiz['id']);
        } else {
            redirect("dashboard_user.php", "Invalid quiz code. Please check and try again.", "error");
        }
    } else {
        redirect("dashboard_user.php", "Please enter a quiz code.", "error");
    }
}

// Get user's recent quiz attempts
$stmt = $pdo->prepare("SELECT q.title, ua.score, ua.completed_at, q.total_marks, ua.id as attempt_id
                      FROM user_attempts ua 
                      JOIN quizzes q ON ua.quiz_id = q.id 
                      WHERE ua.user_id = ? 
                      ORDER BY ua.completed_at DESC 
                      LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentAttempts = $stmt->fetchAll();

// Get all quiz categories from the database
$categories = $pdo->query("SELECT DISTINCT category FROM quizzes ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get user's certificates
$stmt = $pdo->prepare("SELECT c.id, q.title, c.downloaded_at, c.certificate_path 
                      FROM certificates c
                      JOIN user_attempts ua ON c.attempt_id = ua.id
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      ORDER BY c.downloaded_at DESC
                      LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll();

// Get user stats
$stmt = $pdo->prepare("SELECT 
                      COUNT(ua.id) as total_attempts,
                      COUNT(c.id) as total_certificates,
                      COALESCE(AVG(CASE WHEN q.total_marks > 0 THEN (ua.score / q.total_marks) * 100 END), 0) as average_score,
                      COALESCE(MAX(CASE WHEN q.total_marks > 0 THEN (ua.score / q.total_marks) * 100 END), 0) as best_score,
                      COALESCE(SUM(CASE WHEN ua.score >= (q.total_marks * 0.7) THEN 1 ELSE 0 END), 0) as passed_attempts,
                      COALESCE(SUM(ua.score), 0) as total_points
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      LEFT JOIN certificates c ON ua.id = c.attempt_id
                      WHERE ua.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userStats = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT COUNT(*) 
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      LEFT JOIN certificates c ON ua.id = c.attempt_id
                      WHERE ua.user_id = ?
                      AND ua.score >= (q.total_marks * 0.7)
                      AND c.id IS NULL");
$stmt->execute([$_SESSION['user_id']]);
$pendingCertificates = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) 
                      FROM user_attempts
                      WHERE user_id = ?
                      AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$stmt->execute([$_SESSION['user_id']]);
$currentMonthAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) + 1
                      FROM (
                          SELECT ua.user_id, SUM(ua.score) AS total_score
                          FROM user_attempts ua
                          GROUP BY ua.user_id
                      ) ranked_scores
                      WHERE total_score > (
                          SELECT COALESCE(SUM(score), 0)
                          FROM user_attempts
                          WHERE user_id = ?
                      )");
$stmt->execute([$_SESSION['user_id']]);
$leaderboardRank = (int) $stmt->fetchColumn();

// Get total available quizzes
$stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
$totalQuizzes = $stmt->fetchColumn();

// Get leaderboard
$stmt = $pdo->prepare("SELECT u.username, SUM(ua.score) as total_score
                      FROM user_attempts ua
                      JOIN users u ON ua.user_id = u.id
                      GROUP BY ua.user_id
                      ORDER BY total_score DESC
                      LIMIT 5");
$stmt->execute();
$leaderboard = $stmt->fetchAll();

// Get recent activity
$stmt = $pdo->prepare("SELECT q.title, ua.score, ua.completed_at, q.total_marks
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      ORDER BY ua.completed_at DESC
                      LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$recentActivity = $stmt->fetchAll();

// Check certificate existence for each attempt
$attemptCertMap = [];
foreach ($recentAttempts as $attempt) {
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE attempt_id = ?");
    $stmt->execute([$attempt['attempt_id']]);
    $attemptCertMap[$attempt['attempt_id']] = $stmt->fetch();
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$totalAttempts = (int) ($userStats['total_attempts'] ?? 0);
$totalCertificates = (int) ($userStats['total_certificates'] ?? 0);
$averageScore = round((float) ($userStats['average_score'] ?? 0), 1);
$bestScore = round((float) ($userStats['best_score'] ?? 0));
$passedAttempts = (int) ($userStats['passed_attempts'] ?? 0);
$totalPoints = (int) ($userStats['total_points'] ?? 0);
$categoryCount = count($categories);
$userPassRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100) : 0;
$latestActivity = $recentActivity[0] ?? null;
$latestActivityScore = 0;
if ($latestActivity && !empty($latestActivity['total_marks'])) {
    $latestActivityScore = round(($latestActivity['score'] / $latestActivity['total_marks']) * 100);
}

$heroMessage = 'Start a fresh quiz and unlock your next milestone.';
if ($pendingCertificates > 0) {
    $heroMessage = 'You have ' . $pendingCertificates . ' certificate' . ($pendingCertificates === 1 ? '' : 's') . ' waiting to be claimed.';
} elseif ($totalAttempts > 0) {
    $heroMessage = 'Your recent performance is building steady momentum across categories.';
}

$isAdminView = false;
$homeLink = 'dashboard_user.php';
$logoutLink = 'logout.php';
$pageTitle = 'Dashboard - QuizPro';
$pageKey = 'dashboard';
$pageBodyClass = 'dash-body dash-user-page page-dashboard page-dashboard-user';
$headerContext = 'Learner cockpit';
$pageFooterSummary = 'Your personal performance cockpit for quiz attempts, certificates, activity, and leaderboard momentum.';
$headerRank = $leaderboardRank;
$notificationCount = $pendingCertificates;

include 'includes/header.php';
?>
        <div class="dash-content">

            <?php
            ob_start();
            displayMessage();
            $flashContent = ob_get_clean();
            if ($flashContent) { echo '<div class="dash-fade-in" style="margin-bottom:18px">' . $flashContent . '</div>'; }
            ?>

            <!-- Hero Section -->
            <section class="dash-hero dash-fade-in dash-hero-shell">
                <div class="dash-hero-copy">
                    <span class="dash-hero-kicker">Learning cockpit</span>
                    <h1 class="dash-hero-title">Welcome back, <span class="dash-hero-gradient"><?php echo $username; ?></span></h1>
                    <p class="dash-hero-sub">
                        You have collected <?php echo $totalPoints; ?> points across <?php echo $totalAttempts; ?> quiz attempts.
                        <?php echo htmlspecialchars($heroMessage); ?>
                    </p>
                    <div class="dash-hero-pills">
                        <span class="dash-hero-pill"><i class="fas fa-wave-square"></i> <?php echo $userPassRate; ?>% pass rate</span>
                        <span class="dash-hero-pill"><i class="fas fa-layer-group"></i> <?php echo $categoryCount; ?> active categories</span>
                        <span class="dash-hero-pill"><i class="fas fa-medal"></i> <?php echo $totalCertificates; ?> certificates earned</span>
                    </div>
                    <div class="dash-hero-actions">
                        <a href="quiz.php" class="dash-btn dash-btn-primary"><i class="fas fa-play"></i> Start a Quiz</a>
                        <a href="certificates.php" class="dash-btn dash-btn-outline" style="color: #4f46e5"><i class="fas fa-certificate"></i> Open Certificates</a>
                    </div>
                </div>
                <div class="dash-hero-panel">
                    <div class="dash-hero-panel-head">
                        <span>Performance snapshot</span>
                        <span class="dash-hero-panel-badge">Live</span>
                    </div>
                    <div class="dash-ring-card">
                        <span class="dash-ring-value" data-count="<?php echo $averageScore; ?>">0</span>
                        <span class="dash-ring-label">Average score</span>
                        <p>
                            <?php if ($latestActivity): ?>
                                Latest finish: <?php echo $latestActivityScore; ?>% on <?php echo htmlspecialchars($latestActivity['title']); ?>
                            <?php else: ?>
                                Complete your first quiz to unlock performance insights.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="dash-mini-stat-grid">
                        <article class="dash-mini-stat">
                            <span class="dash-mini-stat-label">Best run</span>
                            <strong class="dash-mini-stat-value" data-count="<?php echo $bestScore; ?>">0%</strong>
                        </article>
                        <article class="dash-mini-stat">
                            <span class="dash-mini-stat-label">Available quizzes</span>
                            <strong class="dash-mini-stat-value" data-count="<?php echo (int) $totalQuizzes; ?>">0</strong>
                        </article>
                        <article class="dash-mini-stat">
                            <span class="dash-mini-stat-label">Certificates pending</span>
                            <strong class="dash-mini-stat-value" data-count="<?php echo $pendingCertificates; ?>">0</strong>
                        </article>
                    </div>
                </div>
            </section>

        <!-- Stats Grid -->
        <div class="dash-stats-grid dash-stagger">
            <div class="dash-stat-card gradient-border-blue">
                <div class="dash-stat-card-icon"><i class="fas fa-pencil-alt"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $totalAttempts; ?>">0</span>
                    <span class="dash-stat-card-label">Quizzes Taken</span>
                    <span class="dash-stat-footnote"><?php echo $currentMonthAttempts; ?> completed this month</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-gold">
                <div class="dash-stat-card-icon"><i class="fas fa-certificate"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $totalCertificates; ?>">0</span>
                    <span class="dash-stat-card-label">Certificates Earned</span>
                    <span class="dash-stat-footnote"><?php echo $pendingCertificates; ?> waiting to be claimed</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-green">
                <div class="dash-stat-card-icon"><i class="fas fa-star"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $averageScore; ?>">0</span>
                    <span class="dash-stat-card-label">Average Score %</span>
                    <span class="dash-stat-footnote"><?php echo $passedAttempts; ?> strong finishes so far</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-rose">
                <div class="dash-stat-card-icon"><i class="fas fa-bolt"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $totalPoints; ?>">0</span>
                    <span class="dash-stat-card-label">Total Points</span>
                    <span class="dash-stat-footnote">Across <?php echo $categoryCount; ?> different learning tracks</span>
                </div>
            </div>
        </div>

        <!-- Two-Column Layout -->
        <div class="dash-two-col dash-stagger">

            <!-- Left Column -->
            <div class="dash-col-left">

                <!-- Recent Quiz Attempts -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-history"></i> Recent Quiz Attempts</h3>
                        <span class="dash-card-tag">Last 5 results</span>
                    </div>
                    <div class="dash-card-body">
                        <?php if (count($recentAttempts) > 0): ?>
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>Quiz</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                        <th>Certificate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttempts as $attempt):
                                        $pct = $attempt['total_marks'] > 0 ? round(($attempt['score'] / $attempt['total_marks']) * 100, 1) : 0;
                                        if ($pct >= 70) { $level = 'high'; $badgeLabel = 'High'; }
                                        elseif ($pct >= 40) { $level = 'medium'; $badgeLabel = 'Medium'; }
                                        else { $level = 'low'; $badgeLabel = 'Low'; }
                                        $hasCert = isset($attemptCertMap[$attempt['attempt_id']]) && $attemptCertMap[$attempt['attempt_id']];
                                    ?>
                                    <tr>
                                        <td data-label="Quiz"><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td data-label="Score">
                                            <div class="score-wrapper">
                                                <div class="score-progress-bar">
                                                    <div class="score-progress-fill <?php echo $level; ?>" style="width: <?php echo $pct; ?>%"></div>
                                                </div>
                                                <span class="score-badge <?php echo $level; ?>"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_marks']; ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Date"><?php echo date('M d, Y', strtotime($attempt['completed_at'])); ?></td>
                                        <td data-label="Certificate">
                                            <?php if ($hasCert): ?>
                                                <a href="certificates.php" class="dash-btn-small dash-btn-primary"><i class="fas fa-eye"></i> View</a>
                                            <?php elseif ($pct >= 70): ?>
                                                <a href="generate_certificate.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="dash-btn-small dash-btn-success"><i class="fas fa-download"></i> Get Certificate</a>
                                            <?php else: ?>
                                                <span class="score-badge low">Score too low</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="dash-empty-state">
                            <i class="fas fa-book-open"></i>
                            <p>No quiz attempts yet. <a href="quiz.php">Take your first quiz!</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quiz Categories -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-folder"></i> Quiz Categories</h3>
                        <span class="dash-card-tag"><?php echo $categoryCount; ?> open tracks</span>
                    </div>
                    <div class="dash-card-body">
                        <p class="dash-card-note">Move between categories quickly and keep your progress balanced across fundamentals, problem solving, and practical stacks.</p>
                        <?php if (count($categories) > 0): ?>
                        <div class="dash-category-grid">
                            <?php 
                            $iconMap = [
                                'H' => 'fa-code',
                                'C' => 'fa-code',
                                'P' => 'fa-database',
                                'J' => 'fa-brands fa-js',
                                'S' => 'fa-database',
                                'M' => 'fa-calculator',
                                'E' => 'fa-globe',
                                'G' => 'fa-globe',
                                'D' => 'fa-paint-brush',
                                'N' => 'fa-microchip',
                                'L' => 'fa-language',
                                'B' => 'fa-flask',
                                'A' => 'fa-chart-bar',
                                'R' => 'fa-random',
                                'F' => 'fa-film',
                                'W' => 'fa-pen-fancy',
                            ];
                            foreach ($categories as $cat): 
                                $firstLetter = strtoupper(substr($cat['name'] ?? $cat, 0, 1));
                                $icon = $iconMap[$firstLetter] ?? 'fa-folder';
                            ?>
                            <a href="quiz.php?category=<?php echo urlencode($cat['name'] ?? $cat); ?>" class="dash-category-card">
                                <div class="dash-category-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                                <span class="dash-category-name"><?php echo htmlspecialchars($cat['name'] ?? $cat); ?></span>
                                <span class="dash-category-start"><i class="fas fa-arrow-right"></i> Start</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="dash-empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No categories available yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column -->
            <div class="dash-col-right">

                <!-- Recent Certificates -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-trophy"></i> Recent Certificates</h3>
                        <span class="dash-card-tag"><?php echo $totalCertificates; ?> total</span>
                    </div>
                    <div class="dash-card-body">
                        <p class="dash-card-note">Every certificate here reflects a 70%+ quiz finish and builds your achievement trail.</p>
                        <?php if (count($certificates) > 0): ?>
                            <?php foreach ($certificates as $cert): ?>
                            <div class="dash-cert-card">
                                <div class="dash-cert-icon"><i class="fas fa-trophy gold"></i></div>
                                <div class="dash-cert-info">
                                    <span class="dash-cert-title"><?php echo htmlspecialchars($cert['title']); ?></span>
                                    <span class="dash-cert-date"><?php echo date('M d, Y', strtotime($cert['downloaded_at'])); ?></span>
                                </div>
                                <a href="<?php echo htmlspecialchars($cert['certificate_path']); ?>" class="dash-btn-small dash-btn-primary" download><i class="fas fa-download"></i></a>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="dash-empty-state">
                            <i class="fas fa-certificate"></i>
                            <p>No certificates earned yet. Score 70%+ on a quiz to earn one!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-satellite-dish"></i> Achievement Radar</h3>
                        <span class="dash-card-tag">Personal pulse</span>
                    </div>
                    <div class="dash-card-body">
                        <div class="dash-signal-grid">
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Leaderboard Rank</span>
                                <strong class="dash-signal-value" data-count="<?php echo $leaderboardRank; ?>">0</strong>
                                <p>Position based on total quiz score.</p>
                            </article>
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Pass Rate</span>
                                <strong class="dash-signal-value" data-count="<?php echo $userPassRate; ?>">0%</strong>
                                <p>Strong finishes across your completed quizzes.</p>
                            </article>
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Top Score</span>
                                <strong class="dash-signal-value" data-count="<?php echo $bestScore; ?>">0%</strong>
                                <p>Your best single-quiz performance so far.</p>
                            </article>
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Momentum</span>
                                <strong class="dash-signal-value" data-count="<?php echo $currentMonthAttempts; ?>">0</strong>
                                <p>Attempts completed during the current month.</p>
                            </article>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                        <span class="dash-card-tag">Live timeline</span>
                    </div>
                    <div class="dash-card-body">
                        <?php if (count($recentActivity) > 0): ?>
                        <div class="dash-activity-list">
                            <?php foreach ($recentActivity as $act):
                                $pct = $act['total_marks'] > 0 ? round(($act['score'] / $act['total_marks']) * 100, 1) : 0;
                                if ($pct >= 70) { $dotClass = 'green'; }
                                elseif ($pct >= 40) { $dotClass = 'yellow'; }
                                else { $dotClass = 'red'; }
                            ?>
                            <div class="dash-activity-item">
                                <span class="dash-activity-dot <?php echo $dotClass; ?>"></span>
                                <div class="dash-activity-content">
                                    <span class="dash-activity-title"><?php echo htmlspecialchars($act['title']); ?></span>
                                    <span class="dash-activity-meta">Score: <?php echo $act['score']; ?>/<?php echo $act['total_marks']; ?> &middot; <?php echo date('M d, Y', strtotime($act['completed_at'])); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="dash-empty-state">
                            <i class="fas fa-history"></i>
                            <p>No activity yet. Start a quiz to see your progress!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Leaderboard Mini -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-trophy"></i> Leaderboard</h3>
                        <span class="dash-card-tag">Top 5</span>
                    </div>
                    <div class="dash-card-body">
                        <?php if (count($leaderboard) > 0): ?>
                        <div class="dash-lb-list">
                            <?php $rank = 1; foreach ($leaderboard as $lb): 
                                $rankClass = '';
                                $rankIcon = '';
                                if ($rank === 1) { $rankClass = 'gold'; $rankIcon = '<i class="fas fa-crown"></i>'; }
                                elseif ($rank === 2) { $rankClass = 'silver'; }
                                elseif ($rank === 3) { $rankClass = 'bronze'; }
                            ?>
                            <div class="dash-lb-item <?php echo $rankClass; ?>">
                                <span class="dash-lb-rank"><?php if ($rankIcon) echo $rankIcon; else echo '#' . $rank; ?></span>
                                <span class="dash-lb-name"><?php echo htmlspecialchars($lb['username']); ?></span>
                                <span class="dash-lb-score"><?php echo (int)$lb['total_score']; ?></span>
                            </div>
                            <?php $rank++; endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="dash-empty-state">
                            <i class="fas fa-users"></i>
                            <p>No leaderboard data yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        <span class="dash-card-tag">Move faster</span>
                    </div>
                    <div class="dash-card-body">
                        <div class="dash-quick-grid">
                            <a href="quiz.php" class="dash-quick-btn blue"><i class="fas fa-pencil-alt"></i> Take Quiz</a>
                            <button type="button" class="dash-quick-btn green" onclick="openJoinModal()"><i class="fas fa-users"></i> Join Live</button>
                            <a href="certificates.php" class="dash-quick-btn gold"><i class="fas fa-certificate"></i> View Certificates</a>
                            <a href="contact.php" class="dash-quick-btn rose"><i class="fas fa-headset"></i> Get Support</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        </div>
    </div>

    <!-- Join Quiz Modal Overlay -->
    <div id="joinQuizModal" class="app-modal-overlay" style="display:none;">
        <div class="app-modal-card">
            <div class="app-modal-head">
                <h3 class="app-modal-title">Join Quiz</h3>
                <button type="button" class="app-modal-close" onclick="closeJoinModal()">&times;</button>
            </div>
            <div class="app-modal-body">
                <p>Enter the unique access code to jump straight into the quiz.</p>
                <form action="dashboard_user.php" method="POST" class="app-form">
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

<?php include 'includes/footer.php'; ?>
