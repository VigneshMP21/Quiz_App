<?php

session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db.php';

// Get quizzes by category if specified
$category = isset($_GET['category']) ? trim($_GET['category']) : null;
$quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : null;

if ($category) {
    $stmt = $pdo->prepare("SELECT id, title, description, category, no_of_questions, total_marks 
                          FROM quizzes 
                          WHERE category = ? 
                          ORDER BY title");
    $stmt->execute([$category]);
    $quizzes = $stmt->fetchAll();
} elseif ($quiz_id) {
    // Get specific quiz
    $stmt = $pdo->prepare("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes 
                          FROM quizzes 
                          WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        redirect('quiz.php', 'Quiz not found.');
    }
} else {
    // Get all quizzes
    $stmt = $pdo->query("SELECT id, title, description, category, no_of_questions, total_marks 
                         FROM quizzes 
                         ORDER BY category, title");
    $quizzes = $stmt->fetchAll();
}

$categories = getQuizCategories();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - <?php echo isset($quiz) ? htmlspecialchars($quiz['title']) : 'Quizzes'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>

<body>
    <div class="container">
        <header class="dashboard-header">
            <h1><?php echo isAdmin() ? 'Admin' : 'User'; ?> Dashboard - Quizzes</h1>
            <nav>
                <ul>
                    <li><a href="<?php echo isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php'; ?>">Home</a></li>
                    <li><a href="quiz.php" class="active">Quiz</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="create_quiz.php">Create Quiz</a></li>
                    <?php else: ?>
                        <li><a href="join_quiz.php">Join Quiz</a></li>
                    <?php endif; ?>
                    <li><a href="certificates.php">Certificates</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/view_leaderboard.php">Leaderboard</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>

        <main class="dashboard-content">
            <?php displayMessage(); ?>

            <?php if (isset($quiz)): ?>
                <!-- Quiz Details Page -->
                <section class="quiz-details">
                    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <p class="quiz-category">Category: <?php echo htmlspecialchars($quiz['category']); ?></p>

                    <?php if (!empty($quiz['description'])): ?>
                        <div class="quiz-description">
                            <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="quiz-meta">
                        <div class="meta-item">
                            <span>Questions:</span>
                            <strong><?php echo $quiz['no_of_questions']; ?></strong>
                        </div>
                        <div class="meta-item">
                            <span>Total Marks:</span>
                            <strong><?php echo $quiz['total_marks']; ?></strong>
                        </div>
                        <div class="meta-item">
                            <span>Time Limit:</span>
                            <strong><?php echo $quiz['timer_minutes']; ?> minutes</strong>
                        </div>
                    </div>

                    <div class="quiz-instructions">
                        <h3>Instructions:</h3>
                        <ul>
                            <li>Read each question carefully before answering.</li>
                            <li>You cannot go back to previous questions once answered.</li>
                            <li>The quiz will automatically submit when time expires.</li>
                            <li>Do not refresh the page during the quiz.</li>
                        </ul>
                    </div>

                    <div class="quiz-actions">
                        <a href="user/take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn">Start Quiz</a>
                        <a href="quiz.php" class="btn btn-secondary">Back to Quizzes</a>
                    </div>
                </section>
            <?php else: ?>
                <!-- Quiz Listing Page -->
                <section class="quiz-filters">
                    <h2>Browse Quizzes</h2>

                    <div class="filter-options">
                        <form method="GET" action="quiz.php">
                            <div class="form-group">
                                <label for="category">Filter by Category:</label>
                                <select id="category" name="category" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="quiz-list">
                    <?php if (empty($quizzes)): ?>
                        <p>No quizzes found.</p>
                    <?php else: ?>
                        <div class="quiz-grid">
                            <?php foreach ($quizzes as $q): ?>
                                <!-- Inside the quiz-list section, modify the quiz-card div -->
                                <div class="quiz-card">
                                    <h3><?php echo htmlspecialchars($q['title']); ?></h3>
                                    <p class="quiz-category"><?php echo htmlspecialchars($q['category']); ?></p>
                                    <div class="quiz-stats">
                                        <span>Questions: <?php echo $q['no_of_questions']; ?></span>
                                        <span>Marks: <?php echo $q['total_marks']; ?></span>
                                    </div>
                                    <div class="quiz-actions">
                                        <a href="quiz.php?quiz_id=<?php echo $q['id']; ?>" class="btn">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>