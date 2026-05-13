<?php
session_start();
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

require_once '../includes/db.php';

// Get all quizzes
$quizzes = $pdo->query("SELECT id, title FROM quizzes ORDER BY title")->fetchAll();

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$leaderboard = [];

if ($quiz_id) {
    // Get leaderboard for selected quiz
    $stmt = $pdo->prepare("SELECT u.username, ua.score, ua.completed_at 
                          FROM user_attempts ua 
                          JOIN users u ON ua.user_id = u.id 
                          WHERE ua.quiz_id = ? 
                          ORDER BY ua.score DESC, ua.completed_at ASC 
                          LIMIT 10");
    $stmt->execute([$quiz_id]);
    $leaderboard = $stmt->fetchAll();
    
    // Get quiz title
    $quiz_title = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
    $quiz_title->execute([$quiz_id]);
    $quiz_title = $quiz_title->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Leaderboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Quiz Leaderboard</h1>
            <nav>
                <ul>
                    <li><a href="../dashboard_admin.php">Home</a></li>
                    <li><a href="../quiz.php">Quiz</a></li>
                    <li><a href="../create_quiz.php">Create Quiz</a></li>
                    <li><a href="../contact.php">Contact</a></li>
                    <li><a href="view_leaderboard.php" class="active">Leaderboard</a></li>
                    <li><a href="../includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <section class="leaderboard-section">
                <h2>View Leaderboard</h2>
                
                <div class="quiz-selector">
                    <form method="GET" action="view_leaderboard.php">
                        <div class="form-group">
                            <label for="quiz_id">Select Quiz:</label>
                            <select id="quiz_id" name="quiz_id" onchange="this.form.submit()">
                                <option value="">-- Select a Quiz --</option>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <option value="<?php echo $quiz['id']; ?>" <?php echo $quiz_id == $quiz['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quiz['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <?php if ($quiz_id && !empty($leaderboard)): ?>
                    <div class="leaderboard-container">
                        <h3>Top Scores for: <?php echo htmlspecialchars($quiz_title); ?></h3>
                        
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Username</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $index => $entry): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                        <td><?php echo $entry['score']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($entry['completed_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($quiz_id): ?>
                    <p>No attempts yet for this quiz.</p>
                <?php else: ?>
                    <p>Please select a quiz to view its leaderboard.</p>
                <?php endif; ?>
            </section>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>