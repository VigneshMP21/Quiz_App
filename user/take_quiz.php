<?php
session_start();
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (isAdmin()) {
    redirect('../dashboard_admin.php');
}

require_once '../includes/db.php';

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Get quiz details
$stmt = $pdo->prepare("SELECT id, title, no_of_questions, total_marks, timer_minutes 
                      FROM quizzes 
                      WHERE id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    redirect('../quiz.php', 'Quiz not found.');
}

// Check if user already attempted this quiz
$stmt = $pdo->prepare("SELECT id FROM user_attempts WHERE user_id = ? AND quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);

if ($stmt->rowCount() > 0) {
    redirect('../quiz.php?quiz_id=' . $quiz_id, 'You have already attempted this quiz.');
}

// Get quiz questions
$stmt = $pdo->prepare("SELECT id, question_text, option1, option2, option3, option4, correct_option, marks 
                      FROM questions 
                      WHERE quiz_id = ? 
                      ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

if (count($questions) !== $quiz['no_of_questions']) {
    redirect('../quiz.php', 'Quiz is not ready yet. Please try again later.');
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = $_POST['start_time'];
    $answers = $_POST['answers'] ?? [];
    
    $score = 0;
    $correct = 0;
    $wrong = 0;
    $answer_details = [];
    
    // Calculate score
    foreach ($questions as $question) {
        $user_answer = isset($answers[$question['id']]) ? (int)$answers[$question['id']] : 0;
        $is_correct = $user_answer === $question['correct_option'];
        
        if ($is_correct) {
            $score += $question['marks'];
            $correct++;
        } else {
            $wrong++;
        }
        
        $answer_details[] = [
            'question_id' => $question['id'],
            'question_text' => $question['question_text'],
            'user_answer' => $user_answer > 0 ? $question['option' . $user_answer] : 'Not answered',
            'correct_answer' => $question['option' . $question['correct_option']],
            'is_correct' => $is_correct
        ];
    }
    
    $time_taken = time() - $start_time;
    
    // Save attempt
    $stmt = $pdo->prepare("INSERT INTO user_attempts (user_id, quiz_id, score) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $quiz_id, $score]);
    
    // Store result in session for display on score page
    $_SESSION['quiz_result'] = [
        'quiz_id' => $quiz_id,
        'score' => $score,
        'correct' => $correct,
        'wrong' => $wrong,
        'time_taken' => $time_taken,
        'answers' => $answer_details
    ];
    
    redirect('../score.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <!-- <link rel="stylesheet" href="../assets/css/style.css"> -->
    <link rel="stylesheet" href="Enhanced.css">
    <style>
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 1.2em;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container quiz-container">
        <div class="timer" id="quizTimer">
            Time Left: <?php echo gmdate("i:s", $quiz['timer_minutes'] * 60); ?>
        </div>
        
        <form id="quizForm" method="POST" action="take_quiz.php?quiz_id=<?php echo $quiz_id; ?>">
            <input type="hidden" name="start_time" value="<?php echo time(); ?>">
            
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <p class="quiz-meta">Total Questions: <?php echo $quiz['no_of_questions']; ?> | Total Marks: <?php echo $quiz['total_marks']; ?></p>
            
            <div class="questions">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card" id="q<?php echo $index + 1; ?>">
                        <div class="question-header">
                            <h2>Question <?php echo $index + 1; ?> <span>(<?php echo $question['marks']; ?> marks)</span></h2>
                        </div>
                        
                        <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                        
                        <div class="question-options">
                            <label class="option">
                                <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="1" required>
                                <span><?php echo htmlspecialchars($question['option1']); ?></span>
                            </label>
                            
                            <label class="option">
                                <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="2">
                                <span><?php echo htmlspecialchars($question['option2']); ?></span>
                            </label>
                            
                            <label class="option">
                                <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="3">
                                <span><?php echo htmlspecialchars($question['option3']); ?></span>
                            </label>
                            
                            <label class="option">
                                <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="4">
                                <span><?php echo htmlspecialchars($question['option4']); ?></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="quiz-actions">
                <button type="submit" class="btn">Submit Quiz</button>
            </div>
        </form>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Timer functionality
        let timeLeft = <?php echo $quiz['timer_minutes'] * 60; ?>;
        const timerElement = document.getElementById('quizTimer');
        const quizForm = document.getElementById('quizForm');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `Time Left: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! Your quiz will be submitted automatically.');
                quizForm.submit();
            }
            
            timeLeft--;
        }
        
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>
</html>