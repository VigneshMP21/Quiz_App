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

// Get user's certificates (limited to 3 for dashboard display)
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - User Dashboard</title>
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .certificate-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .certificate-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .certificate-date {
            color: #7f8c8d;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .view-all {
            text-align: right;
            margin-top: 10px;
        }

        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 992px) {
            .dashboard-sections {
                grid-template-columns: 2fr 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <nav>
                <ul>
                    <li><a href="dashboard_user.php" class="active">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <li><a href="join_quiz.php">Join Quiz</a></li>
                    <li><a href="certificates.php">Certificates</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>

        <main class="dashboard-content">
            <?php displayMessage(); ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $userStats['total_attempts'] ?? 0; ?></div>
                    <div class="stat-label">Quizzes Taken</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $userStats['total_certificates'] ?? 0; ?></div>
                    <div class="stat-label">Certificates Earned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo round($userStats['average_score'] ?? 0, 1); ?></div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>

            <div class="dashboard-sections">
                <section class="dashboard-section">
                    <h2>Recent Quiz Attempts</h2>
                    <?php if (empty($recentAttempts)): ?>
                        <p>You haven't taken any quizzes yet. <a href="quiz.php">Take your first quiz now!</a></p>
                    <?php else: ?>
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                    <th>Certificate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAttempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_marks']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($attempt['completed_at'])); ?></td>
                                        <td>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT id FROM certificates WHERE attempt_id = ?");
                                            $stmt->execute([$attempt['attempt_id']]);
                                            $certExists = $stmt->fetch();

                                            if ($certExists): ?>
                                                <span class="text-success">Certificate Earned</span>
                                            <?php elseif ($attempt['score'] >= ($attempt['total_marks'] * 0.7)): ?>
                                                <a href="generate_certificate.php?attempt_id=<?php echo $attempt['attempt_id']; ?>"
                                                    class="btn-small">Get Certificate</a>
                                            <?php else: ?>
                                                <span class="text-muted">Score too low</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <h2 style="margin-top: 30px;">Quiz Categories</h2>
                    <div class="category-grid">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card">
                                <h3><?php echo htmlspecialchars($category); ?></h3>
                                <a href="quiz.php?category=<?php echo urlencode($category); ?>" class="btn">Take Quiz</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="dashboard-section">
                    <h2>Recent Certificates</h2>
                    <?php if (empty($certificates)): ?>
                        <p>You haven't earned any certificates yet. Score 70% or higher on a quiz to earn one!</p>
                    <?php else: ?>
                        <div class="certificate-grid">
                            <?php foreach ($certificates as $cert): ?>
                                <div class="certificate-card">
                                    <div class="certificate-title"><?php echo htmlspecialchars($cert['title']); ?></div>
                                    <div class="certificate-date">
                                        Earned on <?php echo date('M j, Y', strtotime($cert['downloaded_at'])); ?>
                                    </div>
                                    <a href="<?php echo $cert['certificate_path']; ?>" class="btn-small" download>Download</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="view-all">
                            <a href="certificates.php" class="btn">View All Certificates</a>
                        </div>
                    <?php endif; ?>

                    <h2 style="margin-top: 30px;">Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="quiz.php" class="btn">Take a New Quiz</a>
                        <a href="join_quiz.php" class="btn" style="margin-top: 10px;">Join a Live Quiz</a>
                        <a href="certificates.php" class="btn" style="margin-top: 10px;">View My Certificates</a>
                    </div>
                </section>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>