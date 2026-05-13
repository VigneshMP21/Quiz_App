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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - QuizPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dash-body">

<?php
ob_start();
displayMessage();
$flashContent = ob_get_clean();
if ($flashContent) { echo '<div class="dash-fade-in" style="margin-bottom:18px">' . $flashContent . '</div>'; }
?>

<!-- Sidebar -->
<aside class="dash-sidebar" id="dashSidebar">
    <div class="dash-sidebar-header">
        <div class="dash-logo">
            <i class="fas fa-bolt"></i>
            <span>QuizPro</span>
        </div>
        <button class="dash-close-sidebar" id="dashCloseSidebar"><i class="fas fa-times"></i></button>
    </div>
    <nav class="dash-nav">
        <a href="dashboard_admin.php" class="dash-nav-item active"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="manage_quizzes.php" class="dash-nav-item"><i class="fas fa-question-circle"></i><span>Quiz</span></a>
        <a href="create_quiz.php" class="dash-nav-item"><i class="fas fa-plus-circle"></i><span>Create Quiz</span></a>
        <a href="contact.php" class="dash-nav-item"><i class="fas fa-envelope"></i><span>Contact</span></a>
        <a href="leaderboard.php" class="dash-nav-item"><i class="fas fa-trophy"></i><span>Leaderboard</span></a>
        <a href="logout.php" class="dash-nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
    <div class="dash-sidebar-footer">
        <div class="dash-sidebar-user">
            <div class="dash-sidebar-avatar"><?php echo strtoupper(substr($adminUser, 0, 1)); ?></div>
            <div>
                <div class="dash-sidebar-name"><?php echo htmlspecialchars($adminUser); ?></div>
                <div class="dash-sidebar-role">Admin</div>
            </div>
        </div>
    </div>
</aside>

<!-- Overlay -->
<div class="dash-overlay" id="dashOverlay"></div>

<!-- Main Wrapper -->
<div class="dash-main">

    <!-- Top Bar -->
    <header class="dash-topbar">
        <button class="dash-toggle-btn" id="dashToggleBtn"><i class="fas fa-bars"></i></button>
        <div class="dash-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search...">
        </div>
        <div class="dash-topbar-right">
            <button class="dash-notif-btn"><i class="fas fa-bell"></i><span class="dash-notif-dot"></span></button>
            <div class="dash-topbar-avatar"><?php echo strtoupper(substr($adminUser, 0, 1)); ?></div>
        </div>
    </header>

    <!-- Content -->
    <div class="dash-content">

        <!-- Hero Section -->
        <div class="dash-hero">
            <div>
                <h1 class="dash-hero-title">Admin Dashboard</h1>
                <p class="dash-hero-sub">Monitor and manage your quiz platform</p>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="dash-cards">
            <div class="dash-card dash-card-border-purple">
                <div class="dash-card-icon dash-card-icon-purple"><i class="fas fa-clipboard-list"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Total Quizzes</span>
                    <span class="dash-card-count" data-count="<?php echo $totalQuizzes; ?>">0</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-blue">
                <div class="dash-card-icon dash-card-icon-blue"><i class="fas fa-users"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Total Users</span>
                    <span class="dash-card-count" data-count="<?php echo $totalUsers; ?>">0</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-cyan">
                <div class="dash-card-icon dash-card-icon-cyan"><i class="fas fa-check-circle"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Total Attempts</span>
                    <span class="dash-card-count" data-count="<?php echo $totalAttempts; ?>">0</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-gold">
                <div class="dash-card-icon dash-card-icon-gold"><i class="fas fa-star"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">Average Score</span>
                    <span class="dash-card-count" data-count="<?php echo $avgScore; ?>">0</span>
                </div>
            </div>
            <div class="dash-card dash-card-border-green">
                <div class="dash-card-icon dash-card-icon-green"><i class="fas fa-pen-fancy"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-label">My Quizzes</span>
                    <span class="dash-card-count" data-count="<?php echo $myQuizzes; ?>">0</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="dash-chart-grid">
            <div class="dash-chart-card">
                <h3 class="dash-chart-title">Quiz Participation</h3>
                <canvas id="chartParticipation"
                        data-labels='<?php echo json_encode($partLabels); ?>'
                        data-values='<?php echo json_encode($partValues); ?>'></canvas>
            </div>
            <div class="dash-chart-card">
                <h3 class="dash-chart-title">Category Distribution</h3>
                <canvas id="chartCategory"
                        data-labels='<?php echo json_encode($catLabels); ?>'
                        data-values='<?php echo json_encode($catValues); ?>'></canvas>
            </div>
        </div>

        <!-- Admin Grid -->
        <div class="dash-admin-grid">
            <!-- Top Quizzes -->
            <div class="dash-panel">
                <h3 class="dash-panel-title">Top Quizzes</h3>
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
                <h3 class="dash-panel-title">Top Users</h3>
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
                            <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="action-icon"><i class="fas fa-edit"></i></a>
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
            <a href="leaderboard.php" class="dash-btn dash-btn-outline"><i class="fas fa-trophy"></i> View Leaderboard</a>
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
</div>

<a href="create_quiz.php" class="dash-fab"><i class="fas fa-plus"></i></a>

<script src="assets/js/script.js"></script>
</body>
</html>
