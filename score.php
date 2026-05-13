<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db.php';

// Check if quiz attempt data is available
if (!isset($_SESSION['quiz_result'])) {
    redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php');
}

$result = $_SESSION['quiz_result'];
unset($_SESSION['quiz_result']);

// Get quiz details
$stmt = $pdo->prepare("SELECT title, total_marks FROM quizzes WHERE id = ?");
$stmt->execute([$result['quiz_id']]);
$quiz = $stmt->fetch();

// Calculate percentage
$percentage = ($result['score'] / $quiz['total_marks']) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Quiz Result</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
    <style>
        /* Add these styles to your existing CSS */
        .answer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .answer-table th, .answer-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .answer-table th {
            background-color: #f8f9fa;
        }
        .answer-correct {
            color: #28a745;
            font-weight: bold;
        }
        .answer-wrong {
            color: #dc3545;
            font-weight: bold;
        }
        .result-actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Quiz Result</h1>
            <nav>
                <ul>
                    <li><a href="<?php echo isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php'; ?>">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="create_quiz.php">Create Quiz</a></li>
                    <?php else: ?>
                        <li><a href="join_quiz.php">Join Quiz</a></li>
                    <?php endif; ?>
                    <li><a href="contact.php">Contact</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/view_leaderboard.php">Leaderboard</a></li>
                    <?php endif; ?>
                    <li><a href="includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <section class="quiz-result">
                <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                
                <div class="result-summary">
                    <div class="result-card">
                        <h3>Your Score</h3>
                        <div class="score-display">
                            <span class="score"><?php echo $result['score']; ?></span>
                            <span class="total">/ <?php echo $quiz['total_marks']; ?></span>
                        </div>
                        <div class="percentage"><?php echo round($percentage, 2); ?>%</div>
                    </div>
                    
                    <div class="result-details">
                        <h3>Performance Summary</h3>
                        <ul>
                            <li>
                                <span>Correct Answers:</span>
                                <span><?php echo $result['correct']; ?></span>
                            </li>
                            <li>
                                <span>Wrong Answers:</span>
                                <span><?php echo $result['wrong']; ?></span>
                            </li>
                            <li>
                                <span>Time Taken:</span>
                                <span><?php echo gmdate("i:s", $result['time_taken']); ?> minutes</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if (!empty($result['answers'])): ?>
                    <div class="answer-review">
                        <h3>Answer Review</h3>
                        <div class="table-responsive">
                            <table class="answer-table">
                                <thead>
                                    <tr>
                                        <th width="40%">Question</th>
                                        <th width="20%">Your Answer</th>
                                        <th width="20%">Correct Answer</th>
                                        <th width="20%">Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['answers'] as $answer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($answer['question_text']); ?></td>
                                            <td><?php echo htmlspecialchars($answer['user_answer']); ?></td>
                                            <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                                            <td>
                                                <span class="answer-<?php echo $answer['is_correct'] ? 'correct' : 'wrong'; ?>">
                                                    <?php echo $answer['is_correct'] ? '✓ Correct' : '✗ Wrong'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="result-actions">
                    <a href="<?php echo isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php'; ?>" class="btn">Back to Dashboard</a>
                    <?php if (!isAdmin() && $percentage < 70): ?>
                        <a href="quiz.php?quiz_id=<?php echo $result['quiz_id']; ?>" class="btn btn-primary">Retake Quiz</a>
                    <?php endif; ?>
                    <?php if (!isAdmin() && $percentage >= 70): ?>
                        <a href="generate_certificate.php?attempt_id=<?php echo $result['attempt_id'] ?? ''; ?>" class="btn btn-success">Get Certificate</a>
                    <?php endif; ?>
                </div>
            </section>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>