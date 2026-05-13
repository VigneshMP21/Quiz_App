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

$quiz_id = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;

$stmt = $pdo->prepare("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes
                      FROM quizzes
                      WHERE id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    redirect('../quiz.php', 'Quiz not found.', 'error');
}

$stmt = $pdo->prepare("SELECT id FROM user_attempts WHERE user_id = ? AND quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);

if ($stmt->rowCount() > 0) {
    redirect('../quiz.php?quiz_id=' . $quiz_id, 'You have already attempted this quiz.', 'error');
}

$stmt = $pdo->prepare("SELECT id, question_text, option1, option2, option3, option4, correct_option, marks
                      FROM questions
                      WHERE quiz_id = ?
                      ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($questions) !== (int) $quiz['no_of_questions']) {
    redirect('../quiz.php', 'Quiz is not ready yet. Please try again later.', 'error');
}

$totalTimeSeconds = max(60, (int) $quiz['timer_minutes'] * 60);
$passingScore = (int) ceil((float) $quiz['total_marks'] * 0.7);
$questionTotal = count($questions);
$quizDescription = trim((string) ($quiz['description'] ?? ''));
$heroSubtitle = $quizDescription !== ''
    ? $quizDescription
    : 'Move through the full quiz in one focused session. Track your time, answer deliberately, and submit when you are ready.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = isset($_POST['start_time']) ? (int) $_POST['start_time'] : time();
    $answers = $_POST['answers'] ?? [];

    $score = 0;
    $correct = 0;
    $wrong = 0;
    $unanswered = 0;
    $answer_details = [];

    foreach ($questions as $question) {
        $user_answer = isset($answers[$question['id']]) ? (int) $answers[$question['id']] : 0;
        $is_correct = $user_answer > 0 && $user_answer === (int) $question['correct_option'];

        if ($is_correct) {
            $score += (int) $question['marks'];
            $correct++;
        } elseif ($user_answer === 0) {
            $unanswered++;
        } else {
            $wrong++;
        }

        $answer_details[] = [
            'question_id' => (int) $question['id'],
            'question_text' => (string) $question['question_text'],
            'user_answer' => $user_answer > 0 ? (string) $question['option' . $user_answer] : 'Not answered',
            'correct_answer' => (string) $question['option' . $question['correct_option']],
            'is_correct' => $is_correct,
            'marks' => (int) $question['marks'],
        ];
    }

    $time_taken = min($totalTimeSeconds, max(0, time() - $start_time));

    $stmt = $pdo->prepare("INSERT INTO user_attempts (user_id, quiz_id, score) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $quiz_id, $score]);
    $attemptId = (int) $pdo->lastInsertId();

    $_SESSION['quiz_result'] = [
        'attempt_id' => $attemptId,
        'quiz_id' => $quiz_id,
        'quiz_title' => (string) $quiz['title'],
        'score' => $score,
        'correct' => $correct,
        'wrong' => $wrong,
        'unanswered' => $unanswered,
        'time_taken' => $time_taken,
        'answers' => $answer_details,
    ];

    redirect('../score.php');
}

$pathPrefix = '..';
$isAdminView = false;
$homeLink = 'dashboard_user.php';
$logoutLink = 'logout.php';
$pageTitle = 'QuizPro - ' . $quiz['title'];
$pageKey = 'quiz';
$pageBodyClass = 'page-take-quiz';
$headerContext = 'Active quiz session';
$pageFooterSummary = 'A focused quiz session with structured timing, answer tracking, and a cleaner submit flow.';

include '../includes/header.php';
?>
            <?php displayMessage(); ?>

            <section class="app-hero app-hero-session">
                <div class="app-hero-copy">
                    <span class="app-kicker">Focused attempt</span>
                    <h1 class="app-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSubtitle); ?></p>
                    <div class="app-actions">
                        <a href="../quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Exit to Brief</a>
                        <button type="submit" form="quizForm" class="app-button app-button-primary" data-submit-lock><i class="fas fa-paper-plane"></i> Submit Quiz</button>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Session clock</span>
                        <span class="app-status-pill"><i class="fas fa-stopwatch"></i> Live</span>
                    </div>
                    <div class="app-quiz-timer-display" id="appQuizTimer" data-seconds="<?php echo $totalTimeSeconds; ?>" data-state="normal">00:00</div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Category</span>
                            <span class="app-hero-mini-static"><?php echo htmlspecialchars((string) $quiz['category']); ?></span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Questions</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $questionTotal; ?>">0</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Total questions</span>
                    <strong class="app-metric-value" data-count="<?php echo $questionTotal; ?>">0</strong>
                    <p>Complete every question in one uninterrupted attempt.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Total marks</span>
                    <strong class="app-metric-value" data-count="<?php echo (int) $quiz['total_marks']; ?>">0</strong>
                    <p>Maximum score available if every answer is correct.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Certificate threshold</span>
                    <strong class="app-metric-static"><?php echo $passingScore; ?> / <?php echo (int) $quiz['total_marks']; ?></strong>
                    <p>Reach 70% or higher to unlock certificate eligibility.</p>
                </article>
            </section>

            <div class="app-grid app-take-layout">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Question stream</span>
                            <h2 class="app-panel-title">Complete each question with a clear pace</h2>
                        </div>
                    </div>
                    <p class="app-panel-text">You can move between questions freely before submission. The timer continues until you submit or the session expires.</p>

                    <form id="quizForm" method="POST" action="take_quiz.php?quiz_id=<?php echo $quiz_id; ?>" autocomplete="off">
                        <input type="hidden" name="start_time" value="<?php echo time(); ?>">

                        <div class="app-progress-shell">
                            <div class="app-progress-meta">
                                <span data-quiz-status-text>0 answered, <?php echo $questionTotal; ?> remaining</span>
                                <strong><span data-quiz-answered>0</span> / <?php echo $questionTotal; ?></strong>
                            </div>
                            <div class="app-progress-track">
                                <div class="app-progress-fill" data-quiz-progress style="width: 0%"></div>
                            </div>
                        </div>

                        <div class="app-question-stack">
                            <?php foreach ($questions as $index => $question): ?>
                                <?php
                                $questionNumber = $index + 1;
                                $options = [
                                    1 => $question['option1'],
                                    2 => $question['option2'],
                                    3 => $question['option3'],
                                    4 => $question['option4'],
                                ];
                                $letters = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D'];
                                ?>
                                <article class="app-question-shell app-take-question" id="q<?php echo $questionNumber; ?>" data-quiz-question data-question-index="<?php echo $questionNumber; ?>">
                                    <div class="app-question-shell-top">
                                        <div>
                                            <span class="app-question-order">Question <?php echo $questionNumber; ?></span>
                                            <h3><?php echo htmlspecialchars((string) $question['question_text']); ?></h3>
                                        </div>
                                        <span class="app-status-pill"><?php echo (int) $question['marks']; ?> marks</span>
                                    </div>

                                    <div class="app-answer-grid">
                                        <?php foreach ($options as $optionNumber => $optionText): ?>
                                            <label class="app-answer-option">
                                                <input type="radio" name="answers[<?php echo (int) $question['id']; ?>]" value="<?php echo $optionNumber; ?>">
                                                <span class="app-answer-option-shell">
                                                    <span class="app-answer-option-marker"><?php echo $letters[$optionNumber]; ?></span>
                                                    <span class="app-answer-option-copy"><?php echo htmlspecialchars((string) $optionText); ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Session navigator</span>
                                <h2 class="app-panel-title">Jump to any question</h2>
                            </div>
                        </div>
                        <div class="app-preview-stack">
                            <div class="app-preview-stat">
                                <span>Time left</span>
                                <strong id="appQuizMiniTimer">00:00</strong>
                            </div>
                            <div class="app-preview-stat">
                                <span>Questions answered</span>
                                <strong><span data-quiz-sidebar-answered>0</span> / <?php echo $questionTotal; ?></strong>
                            </div>
                        </div>
                        <div class="app-quiz-nav-grid">
                            <?php for ($questionNumber = 1; $questionNumber <= $questionTotal; $questionNumber++): ?>
                                <button type="button" class="app-quiz-nav-btn" data-quiz-nav="<?php echo $questionNumber; ?>"><?php echo $questionNumber; ?></button>
                            <?php endfor; ?>
                        </div>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Submission guidance</span>
                                <h2 class="app-panel-title">Before you submit</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Review unanswered questions in the navigator before final submission.</li>
                            <li><i class="fas fa-check-circle"></i> The quiz submits automatically when the timer reaches zero.</li>
                            <li><i class="fas fa-check-circle"></i> Results open immediately after submission with full answer review.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Finish</span>
                                <h2 class="app-panel-title">Lock in this attempt</h2>
                            </div>
                        </div>
                        <p class="app-panel-text">Submit when you are satisfied with your answers. Unanswered questions will be recorded as unanswered, not skipped silently.</p>
                        <div class="app-sidebar-actions">
                            <button type="submit" form="quizForm" class="app-button app-button-primary" data-submit-lock><i class="fas fa-paper-plane"></i> Submit Quiz</button>
                            <a href="../quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="app-button app-button-ghost"><i class="fas fa-circle-info"></i> Quiz Brief</a>
                        </div>
                    </section>
                </aside>
            </div>

            <script>
                (function () {
                    const form = document.getElementById('quizForm');
                    if (!form) return;

                    const timerElement = document.getElementById('appQuizTimer');
                    const miniTimerElement = document.getElementById('appQuizMiniTimer');
                    const questionCards = Array.from(document.querySelectorAll('[data-quiz-question]'));
                    const navButtons = Array.from(document.querySelectorAll('[data-quiz-nav]'));
                    const answeredElements = Array.from(document.querySelectorAll('[data-quiz-answered], [data-quiz-sidebar-answered]'));
                    const statusText = document.querySelector('[data-quiz-status-text]');
                    const progressFill = document.querySelector('[data-quiz-progress]');
                    const submitLocks = Array.from(document.querySelectorAll('[data-submit-lock]'));
                    const totalQuestions = questionCards.length;
                    let timeLeft = parseInt(timerElement?.dataset.seconds || '0', 10);
                    let submitted = false;
                    let currentQuestion = 1;

                    const formatTime = (seconds) => {
                        const minutes = Math.floor(seconds / 60);
                        const remainder = seconds % 60;
                        return `${minutes.toString().padStart(2, '0')}:${remainder.toString().padStart(2, '0')}`;
                    };

                    const setTimerState = () => {
                        let state = 'normal';
                        if (timeLeft <= 60) {
                            state = 'danger';
                        } else if (timeLeft <= Math.max(120, Math.floor(parseInt(timerElement.dataset.seconds || '0', 10) * 0.25))) {
                            state = 'warning';
                        }

                        if (timerElement) {
                            timerElement.dataset.state = state;
                        }
                        if (miniTimerElement) {
                            miniTimerElement.dataset.state = state;
                        }
                    };

                    const updateTimerText = () => {
                        const formatted = formatTime(Math.max(0, timeLeft));
                        if (timerElement) {
                            timerElement.textContent = formatted;
                        }
                        if (miniTimerElement) {
                            miniTimerElement.textContent = formatted;
                        }
                        setTimerState();
                    };

                    const setCurrentQuestion = (questionNumber) => {
                        currentQuestion = questionNumber;
                        questionCards.forEach((card) => {
                            const isCurrent = parseInt(card.dataset.questionIndex || '0', 10) === questionNumber;
                            card.classList.toggle('is-current', isCurrent);
                        });
                        navButtons.forEach((button) => {
                            const isCurrent = parseInt(button.dataset.quizNav || '0', 10) === questionNumber;
                            button.classList.toggle('is-current', isCurrent);
                        });
                    };

                    const syncQuestionState = () => {
                        let answeredCount = 0;

                        questionCards.forEach((card) => {
                            const checked = card.querySelector('input[type="radio"]:checked');
                            const isAnswered = !!checked;
                            if (isAnswered) {
                                answeredCount++;
                            }
                            card.classList.toggle('is-answered', isAnswered);
                        });

                        navButtons.forEach((button) => {
                            const target = document.getElementById(`q${button.dataset.quizNav}`);
                            const isAnswered = !!target?.querySelector('input[type="radio"]:checked');
                            button.classList.toggle('is-answered', isAnswered);
                        });

                        const remaining = totalQuestions - answeredCount;
                        const percent = totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;

                        answeredElements.forEach((element) => {
                            element.textContent = answeredCount.toString();
                        });

                        if (statusText) {
                            statusText.textContent = `${answeredCount} answered, ${remaining} remaining`;
                        }

                        if (progressFill) {
                            progressFill.style.width = `${percent}%`;
                        }

                        return { answeredCount, remaining };
                    };

                    navButtons.forEach((button) => {
                        button.addEventListener('click', () => {
                            const questionNumber = parseInt(button.dataset.quizNav || '0', 10);
                            const target = document.getElementById(`q${questionNumber}`);
                            if (!target) return;
                            setCurrentQuestion(questionNumber);
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    });

                    form.querySelectorAll('input[type="radio"]').forEach((input) => {
                        input.addEventListener('change', syncQuestionState);
                    });

                    const syncCurrentFromScroll = () => {
                        let activeQuestion = currentQuestion;

                        questionCards.forEach((card) => {
                            const rect = card.getBoundingClientRect();
                            if (rect.top <= 180) {
                                activeQuestion = parseInt(card.dataset.questionIndex || '0', 10);
                            }
                        });

                        setCurrentQuestion(activeQuestion);
                    };

                    window.addEventListener('scroll', syncCurrentFromScroll, { passive: true });

                    form.addEventListener('submit', (event) => {
                        if (submitted) {
                            return;
                        }

                        const remaining = syncQuestionState().remaining;
                        if (remaining > 0) {
                            const shouldSubmit = window.confirm(`You still have ${remaining} unanswered question${remaining === 1 ? '' : 's'}. Submit anyway?`);
                            if (!shouldSubmit) {
                                event.preventDefault();
                                return;
                            }
                        }

                        submitted = true;
                        submitLocks.forEach((button) => {
                            button.disabled = true;
                        });
                    });

                    updateTimerText();
                    syncQuestionState();
                    setCurrentQuestion(1);
                    syncCurrentFromScroll();

                    const timerInterval = window.setInterval(() => {
                        timeLeft -= 1;
                        updateTimerText();

                        if (timeLeft <= 0) {
                            window.clearInterval(timerInterval);
                            if (!submitted) {
                                submitted = true;
                                form.submit();
                            }
                        }
                    }, 1000);
                })();
            </script>
<?php include '../includes/footer.php'; ?>
