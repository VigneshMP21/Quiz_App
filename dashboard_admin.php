<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

require_once 'includes/db.php';

// Get ALL recent quizzes created by admin
$stmt = $pdo->prepare("SELECT id, title, category, no_of_questions, total_marks, unique_code 
                      FROM quizzes 
                      WHERE created_by = ? 
                      ORDER BY id DESC");
$stmt->execute([$_SESSION['user_id']]);
$recentQuizzes = $stmt->fetchAll();

// Get ALL recent quiz attempts
$stmt = $pdo->prepare("SELECT q.title, u.username, ua.score, ua.completed_at 
                      FROM user_attempts ua 
                      JOIN quizzes q ON ua.quiz_id = q.id 
                      JOIN users u ON ua.user_id = u.id 
                      ORDER BY ua.completed_at DESC");
$stmt->execute();
$recentAttempts = $stmt->fetchAll();

// Analytics data
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
$totalQuizzes = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM user_attempts");
$totalAttempts = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(AVG(score), 0) FROM user_attempts");
$avgScore = round($stmt->fetchColumn(), 1);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$myQuizzes = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM certificates");
$totalCertificates = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT category) FROM quizzes");
$activeCategories = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM user_attempts WHERE DATE(completed_at) = CURDATE()");
$todayAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$newUsersThisMonth = (int) $stmt->fetchColumn();

// Top performing quizzes
$stmt = $pdo->query("SELECT q.title, COUNT(ua.id) as attempts, COALESCE(AVG(ua.score), 0) as avg_score
                    FROM quizzes q
                    LEFT JOIN user_attempts ua ON q.id = ua.quiz_id
                    GROUP BY q.id
                    ORDER BY attempts DESC
                    LIMIT 5");
$topQuizzes = $stmt->fetchAll();

// Top users
$stmt = $pdo->query("SELECT u.username, COUNT(ua.id) as attempts, COALESCE(SUM(ua.score), 0) as total_score
                    FROM users u
                    LEFT JOIN user_attempts ua ON u.id = ua.user_id
                    GROUP BY u.id
                    ORDER BY total_score DESC
                    LIMIT 5");
$topUsers = $stmt->fetchAll();

// Category performance
$stmt = $pdo->query("SELECT q.category, COUNT(ua.id) as attempts, COALESCE(AVG(ua.score), 0) as avg_score
                    FROM quizzes q
                    LEFT JOIN user_attempts ua ON q.id = ua.quiz_id
                    GROUP BY q.category
                    ORDER BY attempts DESC");
$categoryStats = $stmt->fetchAll();

// Recent activities
$stmt = $pdo->query("SELECT u.username, q.title, ua.score, ua.completed_at
                    FROM user_attempts ua
                    JOIN users u ON ua.user_id = u.id
                    JOIN quizzes q ON ua.quiz_id = q.id
                    ORDER BY ua.completed_at DESC
                    LIMIT 10");
$recentActivities = $stmt->fetchAll();

// Monthly user growth (last 6 months)
$stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
                    FROM users
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY month
                    ORDER BY month ASC");
$userGrowth = $stmt->fetchAll();

// Participation data for chart
$stmt = $pdo->query("SELECT DATE_FORMAT(completed_at, '%Y-%m') as month, COUNT(*) as count
                    FROM user_attempts
                    WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY month
                    ORDER BY month ASC");
$participationData = $stmt->fetchAll();
$partLabels = []; $partValues = [];
foreach ($participationData as $d) { $partLabels[] = $d['month']; $partValues[] = $d['count']; }

// Category labels/values for chart
$catLabels = []; $catValues = [];
foreach ($categoryStats as $d) { $catLabels[] = $d['category']; $catValues[] = (int)$d['attempts']; }

$adminUser = $_SESSION['username'] ?? 'Admin';
$opsMomentum = $totalQuizzes > 0 ? round($totalAttempts / $totalQuizzes, 1) : 0;
$heroSummary = 'Platform activity is stable with ' . $todayAttempts . ' attempts logged today and ' . $newUsersThisMonth . ' new users this month.';

$isAdminView = true;
$homeLink = 'dashboard_admin.php';
$leaderboardLink = 'admin/view_leaderboard.php';
$logoutLink = 'logout.php';
$pageTitle = 'Admin Dashboard - QuizPro';
$pageKey = 'dashboard';
$pageBodyClass = 'dash-body dash-admin-page page-dashboard page-dashboard-admin';
$headerContext = 'Control room';
$pageFooterSummary = 'A live operations surface for quiz publishing, participation signals, growth, and leaderboard oversight.';
$headAssets = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';

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
            <div class="dash-hero dash-fade-in dash-hero-shell dash-hero-admin">
            <div class="dash-hero-copy">
                <span class="dash-hero-kicker">Operations command</span>
                <h1 class="dash-hero-title">Admin Dashboard</h1>
                <p class="dash-hero-sub">
                    Monitor growth, participation, and content quality from one focused control surface.
                    <?php echo htmlspecialchars($heroSummary); ?>
                </p>
                <div class="dash-hero-pills">
                    <span class="dash-hero-pill"><i class="fas fa-chart-line"></i> <?php echo $opsMomentum; ?> attempts per quiz</span>
                    <span class="dash-hero-pill"><i class="fas fa-award"></i> <?php echo $totalCertificates; ?> certificates issued</span>
                    <span class="dash-hero-pill"><i class="fas fa-layer-group"></i> <?php echo $activeCategories; ?> active categories</span>
                </div>
                <div class="dash-hero-actions">
                    <a href="create_quiz.php" class="dash-btn dash-btn-primary"><i class="fas fa-plus"></i> Create Quiz</a>
                    <a href="admin/view_leaderboard.php" class="dash-btn dash-btn-outline"><i class="fas fa-trophy"></i> View Leaderboard</a>
                </div>
            </div>
            <div class="dash-hero-panel">
                <div class="dash-hero-panel-head">
                    <span>Platform health</span>
                    <span class="dash-hero-panel-badge">Ops live</span>
                </div>
                <div class="dash-ring-card">
                    <span class="dash-ring-value" data-count="<?php echo $avgScore; ?>">0</span>
                    <span class="dash-ring-label">Average score</span>
                    <p>Use this as your quality baseline while reviewing question difficulty and quiz balance.</p>
                </div>
                <div class="dash-mini-stat-grid">
                    <article class="dash-mini-stat">
                        <span class="dash-mini-stat-label">New users</span>
                        <strong class="dash-mini-stat-value" data-count="<?php echo $newUsersThisMonth; ?>">0</strong>
                    </article>
                    <article class="dash-mini-stat">
                        <span class="dash-mini-stat-label">My quizzes</span>
                        <strong class="dash-mini-stat-value" data-count="<?php echo (int) $myQuizzes; ?>">0</strong>
                    </article>
                    <article class="dash-mini-stat">
                        <span class="dash-mini-stat-label">Certificates</span>
                        <strong class="dash-mini-stat-value" data-count="<?php echo $totalCertificates; ?>">0</strong>
                    </article>
                </div>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="dash-cards">
            <div class="dash-card dash-card-border-purple">
                <div class="dash-card-icon dash-card-icon-purple"><i class="fas fa-clipboard-list"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Total Quizzes</span>
                    <span class="dash-card-count" data-count="<?php echo $totalQuizzes; ?>">0</span>
                    <span class="dash-stat-footnote"><?php echo $activeCategories; ?> categories currently live</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-blue">
                <div class="dash-card-icon dash-card-icon-blue"><i class="fas fa-users"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Total Users</span>
                    <span class="dash-card-count" data-count="<?php echo $totalUsers; ?>">0</span>
                    <span class="dash-stat-footnote"><?php echo $newUsersThisMonth; ?> joined this month</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-cyan">
                <div class="dash-card-icon dash-card-icon-cyan"><i class="fas fa-check-circle"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Total Attempts</span>
                    <span class="dash-card-count" data-count="<?php echo $totalAttempts; ?>">0</span>
                    <span class="dash-stat-footnote"><?php echo $todayAttempts; ?> completed today</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-gold">
                <div class="dash-card-icon dash-card-icon-gold"><i class="fas fa-star"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Average Score</span>
                    <span class="dash-card-count" data-count="<?php echo $avgScore; ?>">0</span>
                    <span class="dash-stat-footnote">Platform quality benchmark</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-green">
                <div class="dash-card-icon dash-card-icon-green"><i class="fas fa-pen-fancy"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">My Quizzes</span>
                    <span class="dash-card-count" data-count="<?php echo $myQuizzes; ?>">0</span>
                    <span class="dash-stat-footnote"><?php echo $opsMomentum; ?> average attempts per quiz</span>
                </div>
            </div>
        </div>

        <div class="dash-action-ribbon dash-stagger">
            <a href="create_quiz.php" class="dash-action-ribbon-btn"><i class="fas fa-plus-circle"></i> Build a new quiz</a>
            <a href="quiz.php" class="dash-action-ribbon-btn"><i class="fas fa-sliders-h"></i> Manage quiz catalog</a>
            <a href="admin/view_leaderboard.php" class="dash-action-ribbon-btn"><i class="fas fa-trophy"></i> Audit leaderboard</a>
            <a href="contact.php" class="dash-action-ribbon-btn"><i class="fas fa-headset"></i> Respond to messages</a>
        </div>

        <div class="dash-signal-grid dash-signal-grid-admin">
            <article class="dash-signal-card">
                <span class="dash-signal-label">Certificates Issued</span>
                <strong class="dash-signal-value" data-count="<?php echo $totalCertificates; ?>">0</strong>
                <p>Total reward outputs generated by the platform.</p>
            </article>
            <article class="dash-signal-card">
                <span class="dash-signal-label">Active Categories</span>
                <strong class="dash-signal-value" data-count="<?php echo $activeCategories; ?>">0</strong>
                <p>Content domains available for learners right now.</p>
            </article>
            <article class="dash-signal-card">
                <span class="dash-signal-label">Monthly Signups</span>
                <strong class="dash-signal-value" data-count="<?php echo $newUsersThisMonth; ?>">0</strong>
                <p>Fresh user growth recorded during this month.</p>
            </article>
            <article class="dash-signal-card">
                <span class="dash-signal-label">Attempts Today</span>
                <strong class="dash-signal-value" data-count="<?php echo $todayAttempts; ?>">0</strong>
                <p>Live participation level across all published quizzes.</p>
            </article>
        </div>

        <!-- Charts Row -->
        <div class="dash-chart-grid">
            <div class="dash-chart-card">
                <div class="dash-card-header">
                    <h3 class="dash-chart-title">Quiz Participation</h3>
                    <span class="dash-card-tag">6-month view</span>
                </div>
                <canvas id="chartParticipation"
                        data-labels='<?php echo json_encode($partLabels); ?>'
                        data-values='<?php echo json_encode($partValues); ?>'></canvas>
            </div>
            <div class="dash-chart-card">
                <div class="dash-card-header">
                    <h3 class="dash-chart-title">Category Distribution</h3>
                    <span class="dash-card-tag">Content mix</span>
                </div>
                <canvas id="chartCategory"
                        data-labels='<?php echo json_encode($catLabels); ?>'
                        data-values='<?php echo json_encode($catValues); ?>'></canvas>
            </div>
        </div>

        <!-- Admin Grid -->
        <div class="dash-admin-grid">
            <!-- Top Quizzes -->
            <div class="dash-panel">
                <div class="dash-card-header">
                    <h3 class="dash-panel-title">Top Quizzes</h3>
                    <span class="dash-card-tag">Most attempted</span>
                </div>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Attempts</th>
                                <th>Avg Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($topQuizzes as $qz): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($qz['title']); ?></td>
                                <td><?php echo $qz['attempts']; ?></td>
                                <td>
                                    <div class="dash-progress-bar">
                                        <div class="dash-progress-fill" style="width:<?php echo round($qz['avg_score']); ?>%"></div>
                                    </div>
                                    <?php echo round($qz['avg_score'], 1); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Top Users -->
            <div class="dash-panel">
                <div class="dash-card-header">
                    <h3 class="dash-panel-title">Top Users</h3>
                    <span class="dash-card-tag">Score leaders</span>
                </div>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Attempts</th>
                                <th>Total Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($topUsers as $u): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo $u['attempts']; ?></td>
                                <td><?php echo $u['total_score']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quiz Management -->
        <div class="dash-section-header">
            <h2 class="dash-section-title">Your Quizzes</h2>
            <a href="create_quiz.php" class="dash-btn dash-btn-primary"><i class="fas fa-plus"></i> Create New Quiz</a>
        </div>
        <?php if (empty($recentQuizzes)): ?>
        <div class="dash-empty">
            <i class="fas fa-inbox"></i>
            <p>No quizzes yet. Create your first quiz!</p>
        </div>
        <?php else: ?>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Questions</th>
                        <th>Marks</th>
                        <th>Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentQuizzes as $quiz): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                        <td><?php echo htmlspecialchars($quiz['category']); ?></td>
                        <td><?php echo $quiz['no_of_questions']; ?></td>
                        <td><?php echo $quiz['total_marks']; ?></td>
                        <td><code><?php echo htmlspecialchars($quiz['unique_code']); ?></code></td>
                        <td>
                            <a href="admin/add_questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="action-icon" title="Manage questions"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Quiz Attempts -->
        <div class="dash-section-header">
            <h2 class="dash-section-title">Recent Quiz Attempts</h2>
            <a href="admin/view_leaderboard.php" class="dash-btn dash-btn-outline"><i class="fas fa-trophy"></i> View Leaderboard</a>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>User</th>
                        <th>Score</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAttempts as $att): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($att['title']); ?></td>
                        <td><?php echo htmlspecialchars($att['username']); ?></td>
                        <td>
                            <div class="dash-progress-bar">
                                <div class="dash-progress-fill" style="width:<?php echo round($att['score']); ?>%"></div>
                            </div>
                            <?php echo round($att['score'], 1); ?>%
                        </td>
                        <td><?php echo date('M d, Y', strtotime($att['completed_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Activities -->
        <div class="dash-section-header">
            <h2 class="dash-section-title">Recent Activities</h2>
        </div>
        <div class="dash-activity">
            <?php foreach ($recentActivities as $act): ?>
            <div class="dash-activity-item">
                <div class="dash-activity-dot"></div>
                <div class="dash-activity-content">
                    <p><strong><?php echo htmlspecialchars($act['username']); ?></strong> completed <strong><?php echo htmlspecialchars($act['title']); ?></strong> with score <?php echo round($act['score'], 1); ?>%</p>
                    <span class="dash-activity-time"><?php echo date('M d, Y h:i A', strtotime($act['completed_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        </div>

        <a href="create_quiz.php" class="dash-fab"><i class="fas fa-plus"></i></a>
<?php include 'includes/footer.php'; ?>
