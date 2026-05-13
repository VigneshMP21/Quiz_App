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

// Get user's recent quiz attempts
$stmt = $pdo->prepare("SELECT q.title, ua.score, ua.completed_at, q.total_marks, ua.id as attempt_id
                      FROM user_attempts ua 
                      JOIN quizzes q ON ua.quiz_id = q.id 
                      WHERE ua.user_id = ? 
                      ORDER BY ua.completed_at DESC 
                      LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentAttempts = $stmt->fetchAll();

// Get all quiz categories
$categories = getQuizCategories();

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
                      AVG(ua.score) as average_score
                      FROM user_attempts ua
                      LEFT JOIN certificates c ON ua.id = c.attempt_id
                      WHERE ua.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userStats = $stmt->fetch();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - QuizPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dash-body">

<!-- Sidebar Overlay (mobile) -->
<div class="dash-overlay" id="dashOverlay"></div>

<!-- Sidebar -->
<aside class="dash-sidebar" id="dashSidebar">
    <div class="dash-sidebar-header">
        <i class="fas fa-bolt dash-sidebar-logo-icon"></i>
        <span class="dash-sidebar-logo-text">QuizPro</span>
    </div>
    <nav class="dash-sidebar-nav">
        <a href="dashboard_user.php" class="dash-nav-link active"><i class="fas fa-home"></i> Home</a>
        <a href="quiz.php" class="dash-nav-link"><i class="fas fa-question-circle"></i> Quiz</a>
        <a href="join_quiz.php" class="dash-nav-link"><i class="fas fa-users"></i> Join Quiz</a>
        <a href="certificates.php" class="dash-nav-link"><i class="fas fa-certificate"></i> Certificates</a>
        <a href="contact.php" class="dash-nav-link"><i class="fas fa-envelope"></i> Contact</a>
    </nav>
    <div class="dash-sidebar-footer">
        <div class="dash-sidebar-user">
            <div class="dash-sidebar-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <div class="dash-sidebar-user-info">
                <span class="dash-sidebar-user-name"><?php echo $username; ?></span>
                <span class="dash-sidebar-user-role">User</span>
            </div>
        </div>
        <a href="logout.php" class="dash-nav-link logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- Sidebar Toggle Button -->
<button class="dash-toggle-btn" id="dashToggleBtn" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Main Content -->
<div class="dash-main" id="dashMain">
    <!-- Top Bar -->
    <header class="dash-topbar">
        <div class="dash-search-wrapper">
            <i class="fas fa-search dash-search-icon"></i>
            <input type="text" class="dash-search-input" placeholder="Search quizzes..." readonly>
        </div>
        <div class="dash-topbar-right">
            <div class="dash-notification">
                <i class="fas fa-bell dash-notif-icon"></i>
                <span class="dash-notif-badge">0</span>
            </div>
            <div class="dash-topbar-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        </div>
    </header>

    <!-- Dashboard Content -->
    <div class="dash-content">

        <?php
        ob_start();
        displayMessage();
        $flashContent = ob_get_clean();
        if ($flashContent) { echo '<div class="dash-fade-in" style="margin-bottom:18px">' . $flashContent . '</div>'; }
        ?>

        <!-- Hero Section -->
        <section class="dash-hero dash-fade-in">
            <h1 class="dash-hero-title">Welcome back, <span class="dash-hero-gradient"><?php echo $username; ?></span>!</h1>
            <p class="dash-hero-quote" id="dashQuote">"Knowledge is power. Test it, prove it, own it."</p>
        </section>

        <!-- Stats Grid -->
        <div class="dash-stats-grid dash-stagger">
            <div class="dash-stat-card gradient-border-blue">
                <div class="dash-stat-card-icon"><i class="fas fa-pencil-alt"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $userStats['total_attempts'] ?? 0; ?>">0</span>
                    <span class="dash-stat-card-label">Quizzes Taken</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-gold">
                <div class="dash-stat-card-icon"><i class="fas fa-certificate"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $userStats['total_certificates'] ?? 0; ?>">0</span>
                    <span class="dash-stat-card-label">Certificates Earned</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-green">
                <div class="dash-stat-card-icon"><i class="fas fa-star"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo round($userStats['average_score'] ?? 0, 1); ?>">0</span>
                    <span class="dash-stat-card-label">Average Score</span>
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
                                                <a href="certificate.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="dash-btn-small dash-btn-success"><i class="fas fa-download"></i> Get Certificate</a>
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
                    </div>
                    <div class="dash-card-body">
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
                    </div>
                    <div class="dash-card-body">
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

                <!-- Recent Activity Timeline -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activity</h3>
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
                    </div>
                    <div class="dash-card-body">
                        <div class="dash-quick-grid">
                            <a href="quiz.php" class="dash-quick-btn blue"><i class="fas fa-pencil-alt"></i> Take Quiz</a>
                            <a href="join_quiz.php" class="dash-quick-btn green"><i class="fas fa-users"></i> Join Live</a>
                            <a href="certificates.php" class="dash-quick-btn gold"><i class="fas fa-certificate"></i> View Certificates</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="assets/js/script.js"></script>

</body>
</html>
