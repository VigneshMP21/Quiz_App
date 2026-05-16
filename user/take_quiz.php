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

    // Auto-generate certificate if score is 70% or higher
    if ($score >= $passingScore) {
        require_once '../includes/certificate_image_service.php';
        try {
            generateAndSaveCertificate($attemptId, $pdo);
        } catch (Exception $e) {
            error_log('Auto-certificate generation failed: ' . $e->getMessage());
        }
    }

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

// Track reloads
if (!isset($_SESSION['quiz_reloads'])) {
    $_SESSION['quiz_reloads'] = [];
}

if (!isset($_SESSION['quiz_reloads'][$quiz_id])) {
    $_SESSION['quiz_reloads'][$quiz_id] = 0;
} else {
    // Only count as reload if it's a GET request (not the initial POST submit)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION['quiz_reloads'][$quiz_id]++;
    }
}

$reloadsUsed = $_SESSION['quiz_reloads'][$quiz_id];
$reloadsRemaining = 3 - $reloadsUsed;

if ($reloadsUsed >= 3) {
    // Auto-submit would happen in JS usually, but we can also trigger a redirect or force POST here if needed.
    // For now, we'll let JS handle the auto-submit to preserve any answers stored in local storage if we had any.
}

include '../includes/header.php';
?>
            <?php if ($reloadsUsed > 0 && $reloadsUsed < 3): ?>
            <div id="reloadOverlay" class="app-reload-overlay">
                <div class="app-reload-modal">
                    <div class="app-reload-icon"><i class="fas fa-redo-alt"></i></div>
                    <h2>Page Reload Detected</h2>
                    <p>You have refreshed the page. You are allowed a maximum of <strong>3</strong> reloads per quiz session.</p>
                    <div class="app-reload-stats">
                        <div class="app-reload-stat-item">
                            <span>Attempts Used</span>
                            <strong><?php echo $reloadsUsed; ?> / 3</strong>
                        </div>
                        <div class="app-reload-stat-item">
                            <span>Remaining</span>
                            <strong><?php echo $reloadsRemaining; ?></strong>
                        </div>
                    </div>
                    <button type="button" class="app-button app-button-primary" onclick="document.getElementById('reloadOverlay').remove()">Continue Quiz</button>
                </div>
            </div>
            <?php endif; ?>

            <style>
                .app-reload-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    backdrop-filter: blur(8px);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    animation: fadeIn 0.3s ease;
                }
                .app-reload-modal {
                    background: var(--app-bg-panel);
                    padding: 3rem;
                    border-radius: 1.5rem;
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                    border: 1px solid var(--app-border);
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                }
                .app-reload-icon {
                    font-size: 3rem;
                    color: var(--app-primary);
                    margin-bottom: 1.5rem;
                }
                .app-reload-stats {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 1.5rem;
                    margin: 2rem 0;
                    padding: 1.5rem;
                    background: var(--app-bg-subtle);
                    border-radius: 1rem;
                }
                .app-reload-stat-item span {
                    display: block;
                    font-size: 0.875rem;
                    opacity: 0.7;
                    margin-bottom: 0.5rem;
                }
                .app-reload-stat-item strong {
                    font-size: 1.25rem;
                    color: var(--app-text);
                }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

                /* Hide questions except active one */
                .app-take-question { display: none; }
                .app-take-question.is-active { display: block; }
                
                .app-question-nav-controls {
                    display: flex;
                    gap: 1rem;
                    margin-top: 2rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid var(--app-border);
                }
                .app-question-nav-controls .app-button {
                    flex: 1;
                }
                
                /* Layout Reordering */
                .app-take-layout {
                    display: flex;
                    flex-direction: column;
                    gap: 2rem;
                }
                .app-sidebar {
                    width: 100%;
                    order: 1;
                }
                .app-main-panel {
                    order: 2;
                }
            </style>
            <?php displayMessage(); ?>



            <div class="app-take-layout">
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
                                <strong id="appQuizMiniTimer" data-seconds="<?php echo $totalTimeSeconds; ?>">00:00</strong>
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
                </aside>

                <section class="app-panel app-main-panel">
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
                                $isLast = ($questionNumber === $questionTotal);
                                $options = [
                                    1 => $question['option1'],
                                    2 => $question['option2'],
                                    3 => $question['option3'],
                                    4 => $question['option4'],
                                ];
                                $letters = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D'];
                                ?>
                                <article class="app-question-shell app-take-question <?php echo $index === 0 ? 'is-active' : ''; ?>" id="q<?php echo $questionNumber; ?>" data-quiz-question data-question-index="<?php echo $questionNumber; ?>">
                                    <div class="app-question-shell-top">
                                        <div>
                                            <span class="app-question-order">Question <?php echo $questionNumber; ?> of <?php echo $questionTotal; ?></span>
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

                                    <div class="app-question-nav-controls">
                                        <?php if ($index > 0): ?>
                                            <button type="button" class="app-button app-button-ghost" onclick="appQuizNav.prev()"><i class="fas fa-chevron-left"></i> Previous</button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="app-button app-button-outline" onclick="appQuizNav.clear(<?php echo $questionNumber; ?>)"><i class="fas fa-eraser"></i> Clear Option</button>
                                        
                                        <?php if ($isLast): ?>
                                            <button type="submit" form="quizForm" class="app-button app-button-primary" data-submit-lock><i class="fas fa-check-double"></i> Submit Quiz</button>
                                        <?php else: ?>
                                            <button type="button" class="app-button app-button-primary" onclick="appQuizNav.next()">Next Question <i class="fas fa-chevron-right"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </section>
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
                    const totalSeconds = parseInt(timerElement?.dataset.seconds || miniTimerElement?.dataset.seconds || '0', 10);
                    let timeLeft = totalSeconds;
                    let submitted = false;
                    let currentQuestion = 1;

                    // Global navigation controller
                    window.appQuizNav = {
                        goTo: (questionNumber) => {
                            if (questionNumber < 1 || questionNumber > totalQuestions) return;
                            currentQuestion = questionNumber;
                            
                            questionCards.forEach(card => {
                                const cardIndex = parseInt(card.dataset.questionIndex, 10);
                                card.classList.toggle('is-active', cardIndex === questionNumber);
                            });
                            
                            navButtons.forEach(btn => {
                                const btnIndex = parseInt(btn.dataset.quizNav, 10);
                                btn.classList.toggle('is-current', btnIndex === questionNumber);
                            });

                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        },
                        next: () => window.appQuizNav.goTo(currentQuestion + 1),
                        prev: () => window.appQuizNav.goTo(currentQuestion - 1),
                        clear: (questionNumber) => {
                            const card = document.getElementById(`q${questionNumber}`);
                            if (!card) return;
                            card.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);
                            syncQuestionState();
                        }
                    };

                    const formatTime = (seconds) => {
                        const minutes = Math.floor(seconds / 60);
                        const remainder = seconds % 60;
                        return `${minutes.toString().padStart(2, '0')}:${remainder.toString().padStart(2, '0')}`;
                    };

                    const setTimerState = () => {
                        let state = 'normal';
                        if (timeLeft <= 60) {
                            state = 'danger';
                        } else if (timeLeft <= Math.max(120, Math.floor(totalSeconds * 0.25))) {
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
                        window.appQuizNav.goTo(questionNumber);
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

                    // window.addEventListener('scroll', syncCurrentFromScroll, { passive: true });
                    
                    // Auto-submit on 3 reloads
                    const reloadsUsed = <?php echo $reloadsUsed; ?>;
                    if (reloadsUsed >= 3 && !submitted) {
                        submitted = true;
                        alert('You have reached the maximum number of reloads (3). The quiz will now be submitted automatically.');
                        form.submit();
                    }

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
