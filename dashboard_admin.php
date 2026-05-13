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

// Get ALL recent quiz attempts (removed LIMIT 5)
$stmt = $pdo->prepare("SELECT q.title, u.username, ua.score, ua.completed_at 
                      FROM user_attempts ua 
                      JOIN quizzes q ON ua.quiz_id = q.id 
                      JOIN users u ON ua.user_id = u.id 
                      ORDER BY ua.completed_at DESC");
$stmt->execute();
$recentAttempts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
    <style>
        /* Scrollable containers */
        .scrollable-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .quiz-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .quiz-table th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }
        
        /* Style scrollbars */
        .scrollable-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .scrollable-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .scrollable-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Adjust section spacing */
        .dashboard-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <nav>
                <ul>
                    <li><a href="dashboard_admin.php" class="active">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <li><a href="create_quiz.php">Create Quiz</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="admin/view_leaderboard.php">Leaderboard</a></li>
                    <li><a href="includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <?php displayMessage(); ?>
            
            <section class="dashboard-section">
                <h2>Your Quizzes</h2>
                <?php if (empty($recentQuizzes)): ?>
                    <p>You haven't created any quizzes yet.</p>
                <?php else: ?>
                    <div class="scrollable-container">
                        <table class="quiz-table">
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
                                        <td><?php echo $quiz['unique_code']; ?></td>
                                        <td>
                                            <a href="admin/add_questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-small">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <a href="create_quiz.php" class="btn">Create New Quiz</a>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2>Quiz Attempts</h2>
                <?php if (empty($recentAttempts)): ?>
                    <p>No quiz attempts yet.</p>
                <?php else: ?>
                    <div class="scrollable-container">
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>User</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAttempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td><?php echo $attempt['score']; ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($attempt['completed_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="action-buttons">
                    <a href="admin/view_leaderboard.php" class="btn">View Leaderboard</a>
                </div>
            </section>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>