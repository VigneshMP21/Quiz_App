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

// Get ALL recent quiz attempts - score calculated based on total correct answer/total marks
$stmt = $pdo->prepare("SELECT q.title, u.username, 
                      (ua.score / q.total_marks) * 100 as score_percent, 
                      ua.completed_at 
                      FROM user_attempts ua 
                      JOIN quizzes q ON ua.quiz_id = q.id 
                      JOIN users u ON ua.user_id = u.id 
                      ORDER BY ua.completed_at DESC");
$stmt->execute();
$recentAttempts = $stmt->fetchAll();

// Analytics data
// Analytics data - average score percentage across all attempts
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
$totalQuizzes = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM user_attempts");
$totalAttempts = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT CASE WHEN SUM(q.total_marks) > 0 THEN (SUM(ua.score) / SUM(q.total_marks)) * 100 ELSE 0 END 
                    FROM user_attempts ua 
                    JOIN quizzes q ON ua.quiz_id = q.id");
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

// Top performing quizzes - AVG score calculation: (total correct marks) / (total attempts * total quiz marks)
$stmt = $pdo->query("SELECT q.title, COUNT(ua.id) as attempts, 
                    CASE WHEN COUNT(ua.id) > 0 THEN (SUM(ua.score) / (COUNT(ua.id) * q.total_marks)) * 100 ELSE 0 END as avg_score
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

// Analytics data - Daily resolution for Participation and Growth
$stmt = $pdo->query("SELECT DATE_FORMAT(completed_at, '%b %d') as day, COUNT(*) as attempts
                    FROM user_attempts
                    WHERE completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    GROUP BY DATE(completed_at)
                    ORDER BY DATE(completed_at) ASC");
$partData = $stmt->fetchAll();
$partLabels = array_column($partData, 'day');
$partValues = array_column($partData, 'attempts');

// Content Mix - Showing Quiz Distribution by Category (Ensures chart is visible even with no attempts)
$stmt = $pdo->query("SELECT category, COUNT(*) as total
                    FROM quizzes
                    GROUP BY category
                    ORDER BY total DESC
                    LIMIT 8");
$catData = $stmt->fetchAll();
$catLabels = array_column($catData, 'category');
$catValues = array_column($catData, 'total');

// Performance Data - Avg Score per Category (Bar Chart)
$stmt = $pdo->query("SELECT q.category, 
                    CASE WHEN COUNT(ua.id) > 0 THEN (SUM(ua.score) / (COUNT(ua.id) * q.total_marks)) * 100 ELSE 0 END as avg_pct
                    FROM quizzes q
                    LEFT JOIN user_attempts ua ON q.id = ua.quiz_id
                    GROUP BY q.category
                    ORDER BY avg_pct DESC
                    LIMIT 8");
$perfData = $stmt->fetchAll();
$perfLabels = array_column($perfData, 'category');
$perfValues = array_column($perfData, 'avg_pct');

// Growth Data - Daily resolution for User Signups
$stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %d') as day, COUNT(*) as users
                    FROM users
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY DATE(created_at) ASC");
$growthData = $stmt->fetchAll();
$growthLabels = array_column($growthData, 'day');
$growthValues = array_column($growthData, 'users');
// No longer needed: $categoryStats = $stmt->fetchAll();

// Recent activities - score as percentage
$stmt = $pdo->query("SELECT u.username, u.profile_image, q.title, 
                    (ua.score / q.total_marks) * 100 as score_percent, 
                    ua.completed_at
                    FROM user_attempts ua
                    JOIN users u ON ua.user_id = u.id
                    JOIN quizzes q ON ua.quiz_id = q.id
                    ORDER BY ua.completed_at DESC
                    LIMIT 10");
$recentActivities = $stmt->fetchAll();

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
$headAssets = <<<'HTML'
<link rel="stylesheet" href="assets/css/mobile_view.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
HTML;

include 'includes/header.php';
?>
<style>
    /* Reinforce progress fill colors for admin dashboard */
    .dash-progress-fill.high {
        background: linear-gradient(90deg, #10b981, #34d399) !important;
    }

    .dash-progress-fill.medium {
        background: linear-gradient(90deg, #f59e0b, #fbbf24) !important;
    }

    .dash-progress-fill.low {
        background: linear-gradient(90deg, #ef4444, #f87171) !important;
    }

    /* Recent Activity Avatar Styles */
    .dash-activity-avatar {
        width: 50px;
        height: 50px;
        border-radius: 20%;
        background: linear-gradient(135deg, #6366f1, #a855f7);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #fff;
        font-size: 14px;
        flex-shrink: 0;
        overflow: hidden;
        border: 2px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        position: relative;
    }

    .dash-activity-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .dash-activity-item {
        display: flex;
        gap: 15px;
        align-items: flex-start;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .dash-activity-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
</style>
<div class="dash-content">

    <?php
    ob_start();
    displayMessage();
    $flashContent = ob_get_clean();
    if ($flashContent) {
        echo '<div class="dash-fade-in" style="margin-bottom:18px">' . $flashContent . '</div>';
    }
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
                <span class="dash-hero-pill"><i class="fas fa-chart-line"></i> <?php echo $opsMomentum; ?> attempts per
                    quiz</span>
                <span class="dash-hero-pill"><i class="fas fa-award"></i> <?php echo $totalCertificates; ?> certificates
                    issued</span>
                <span class="dash-hero-pill"><i class="fas fa-layer-group"></i> <?php echo $activeCategories; ?> active
                    categories</span>
            </div>
            <div class="dash-hero-actions">
                <a href="create_quiz.php" class="dash-btn dash-btn-primary"><i class="fas fa-plus"></i> Create Quiz</a>
                <a href="admin/view_leaderboard.php" class="dash-btn dash-btn-primary"
                    style="background: linear-gradient(135deg, #6366f1, #4f46e5);"><i class="fas fa-trophy"></i> View
                    Leaderboard</a>
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
        <a href="create_quiz.php" class="dash-action-ribbon-btn"
            style="background: linear-gradient(135deg, #6a11cb, #2575fc); color: #fff; border: none;"><i
                class="fas fa-plus-circle"></i> Build a new quiz</a>
        <a href="quiz.php" class="dash-action-ribbon-btn"
            style="background: linear-gradient(135deg, #00c6ff, #0072ff); color: #fff; border: none;"><i
                class="fas fa-sliders-h"></i> Manage quiz catalog</a>
        <a href="admin/view_leaderboard.php" class="dash-action-ribbon-btn"
            style="background: linear-gradient(135deg, #f093fb, #f5576c); color: #fff; border: none;"><i
                class="fas fa-trophy"></i> Audit leaderboard</a>
        <a href="contact.php" class="dash-action-ribbon-btn"
            style="background: linear-gradient(135deg, #43e97b, #38f9d7); color: #000; border: none;"><i
                class="fas fa-headset"></i> Respond to messages</a>
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

    <!-- Charts Row - 4 Section Analytics -->
    <div class="dash-chart-grid dash-chart-grid-4">
        <div class="dash-chart-card">
            <div class="dash-card-header">
                <h3 class="dash-chart-title">Participation Flow</h3>
                <span class="dash-card-tag">Daily Pulse (1 day)</span>
            </div>
            <canvas id="chartParticipation" data-labels="<?php echo htmlspecialchars(json_encode($partLabels)); ?>"
                data-values="<?php echo htmlspecialchars(json_encode($partValues)); ?>"></canvas>
        </div>
        <div class="dash-chart-card">
            <div class="dash-card-header">
                <h3 class="dash-chart-title">Content Mix</h3>
                <span class="dash-card-tag">Pie: Category share</span>
            </div>
            <canvas id="chartCategory" data-labels="<?php echo htmlspecialchars(json_encode($catLabels)); ?>"
                data-values="<?php echo htmlspecialchars(json_encode($catValues)); ?>"></canvas>
        </div>
        <div class="dash-chart-card">
            <div class="dash-card-header">
                <h3 class="dash-chart-title">Performance Index</h3>
                <span class="dash-card-tag">Bar: Avg score %</span>
            </div>
            <canvas id="chartPerformance" data-labels="<?php echo htmlspecialchars(json_encode($perfLabels)); ?>"
                data-values="<?php echo htmlspecialchars(json_encode($perfValues)); ?>"></canvas>
        </div>
        <div class="dash-chart-card">
            <div class="dash-card-header">
                <h3 class="dash-chart-title">Growth Momentum</h3>
                <span class="dash-card-tag">Daily Signups (1 day)</span>
            </div>
            <canvas id="chartGrowth" data-labels="<?php echo htmlspecialchars(json_encode($growthLabels)); ?>"
                data-values="<?php echo htmlspecialchars(json_encode($growthValues)); ?>"></canvas>
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
                        <?php $rank = 1;
                        foreach ($topQuizzes as $qz): ?>
                            <tr>
                                <td data-label="Rank"><?php echo $rank++; ?></td>
                                <td data-label="Title"><?php echo htmlspecialchars($qz['title']); ?></td>
                                <td data-label="Attempts"><?php echo $qz['attempts']; ?></td>
                                <td data-label="Avg Score">
                                    <?php
                                    $qzAvg = round($qz['avg_score']);
                                    $qzClass = $qzAvg >= 70 ? 'high' : ($qzAvg >= 40 ? 'medium' : 'low');
                                    $qzColor = $qzAvg >= 70 ? '#10b981' : ($qzAvg >= 40 ? '#f59e0b' : '#ef4444');
                                    ?>
                                    <div class="dash-progress-bar">
                                        <div class="dash-progress-fill <?php echo $qzClass; ?>"
                                            style="width:<?php echo $qzAvg; ?>%; background-color: <?php echo $qzColor; ?>;">
                                        </div>
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
                        <?php $rank = 1;
                        foreach ($topUsers as $u): ?>
                            <tr>
                                <td data-label="Rank"><?php echo $rank++; ?></td>
                                <td data-label="Name"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td data-label="Attempts"><?php echo $u['attempts']; ?></td>
                                <td data-label="Total Score"><?php echo $u['total_score']; ?></td>
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
        <a href="create_quiz.php" class="dash-btn dash-btn-primary" style="color: white"><i class="fas fa-plus"></i>
            Create New Quiz</a>
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
                            <td data-label="Title"><?php echo htmlspecialchars($quiz['title']); ?></td>
                            <td data-label="Category"><?php echo htmlspecialchars($quiz['category']); ?></td>
                            <td data-label="Questions"><?php echo $quiz['no_of_questions']; ?></td>
                            <td data-label="Marks"><?php echo $quiz['total_marks']; ?></td>
                            <td data-label="Code"><code><?php echo htmlspecialchars($quiz['unique_code']); ?></code></td>
                            <td data-label="Actions">
                                <a href="admin/add_questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="action-icon"
                                    title="Manage questions"><i class="fas fa-edit"></i></a>
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
        <a href="admin/view_leaderboard.php" class="dash-btn dash-btn-primary"
            style="background: linear-gradient(135deg, #6366f1, #4f46e5);  color: white;"><i class="fas fa-trophy"></i>
            View Leaderboard</a>
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
                        <td data-label="Quiz"><?php echo htmlspecialchars($att['title']); ?></td>
                        <td data-label="User"><?php echo htmlspecialchars($att['username']); ?></td>
                        <td data-label="Score">
                            <?php
                            $attP = round($att['score_percent']);
                            $attClass = $attP >= 70 ? 'high' : ($attP >= 40 ? 'medium' : 'low');
                            $attColor = $attP >= 70 ? '#10b981' : ($attP >= 40 ? '#f59e0b' : '#ef4444');
                            ?>
                            <div class="dash-progress-bar">
                                <div class="dash-progress-fill <?php echo $attClass; ?>"
                                    style="width:<?php echo $attP; ?>%; background-color: <?php echo $attColor; ?>;"></div>
                            </div>
                            <?php echo round($att['score_percent'], 1); ?>%
                        </td>
                        <td data-label="Date"><?php echo date('M d, Y', strtotime($att['completed_at'])); ?></td>
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
        <?php foreach ($recentActivities as $act):
            $profileInitial = strtoupper(substr($act['username'], 0, 1));
            $actProfileImg = !empty($act['profile_image']) ? $act['profile_image'] : '';
            ?>
            <div class="dash-activity-item">
                <div class="dash-activity-avatar">
                    <?php if ($actProfileImg !== ''): ?>
                        <img src="<?php echo htmlspecialchars($actProfileImg); ?>"
                            alt="<?php echo htmlspecialchars($act['username']); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars($profileInitial); ?>
                    <?php endif; ?>
                </div>
                <div class="dash-activity-content">
                    <p><strong><?php echo htmlspecialchars($act['username']); ?></strong> completed
                        <strong><?php echo htmlspecialchars($act['title']); ?></strong> with score
                        <?php echo round($act['score_percent'], 1); ?>%
                    </p>
                    <span
                        class="dash-activity-time"><?php echo date('M d, Y h:i A', strtotime($act['completed_at'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<a href="create_quiz.php" class="dash-fab"><i class="fas fa-plus"></i></a>
<?php include 'includes/footer.php'; ?>
