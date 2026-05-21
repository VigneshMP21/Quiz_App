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

if (!isset($_SESSION['quiz_question_orders']) || !is_array($_SESSION['quiz_question_orders'])) {
    $_SESSION['quiz_question_orders'] = [];
}

$questionIds = array_map(static fn($question) => (int) $question['id'], $questions);
$savedQuestionOrder = $_SESSION['quiz_question_orders'][$quiz_id] ?? [];
$savedQuestionOrder = is_array($savedQuestionOrder)
    ? array_values(array_map('intval', $savedQuestionOrder))
    : [];

$questionOrderIsValid = count($savedQuestionOrder) === count($questionIds)
    && empty(array_diff($questionIds, $savedQuestionOrder))
    && empty(array_diff($savedQuestionOrder, $questionIds));

if (!$questionOrderIsValid) {
    $savedQuestionOrder = $questionIds;
    shuffle($savedQuestionOrder);
    $_SESSION['quiz_question_orders'][$quiz_id] = $savedQuestionOrder;
}

$questionsById = [];
foreach ($questions as $question) {
    $questionsById[(int) $question['id']] = $question;
}

$questions = array_values(array_filter(array_map(
    static fn($questionId) => $questionsById[$questionId] ?? null,
    $savedQuestionOrder
)));

$totalTimeSeconds = max(60, (int) $quiz['timer_minutes'] * 60);
$passingScore = (int) ceil((float) $quiz['total_marks'] * 0.7);
$questionTotal = count($questions);
$quizDescription = trim((string) ($quiz['description'] ?? ''));
$heroSubtitle = $quizDescription !== ''
    ? $quizDescription
    : 'Move through the full quiz in one focused session. Track your time, answer deliberately, and submit when you are ready.';

if (!isset($_SESSION['quiz_timers']) || !is_array($_SESSION['quiz_timers'])) {
    $_SESSION['quiz_timers'] = [];
}

$now = time();
$timerSession = $_SESSION['quiz_timers'][$quiz_id] ?? null;
$timerSessionIsValid = is_array($timerSession)
    && !empty($timerSession['start_time'])
    && !empty($timerSession['expires_at']);

if (!$timerSessionIsValid) {
    $_SESSION['quiz_timers'][$quiz_id] = [
        'start_time' => $now,
        'expires_at' => $now + $totalTimeSeconds,
        'duration_seconds' => $totalTimeSeconds,
    ];
} else {
    $storedStartTime = (int) $timerSession['start_time'];
    $storedExpiresAt = (int) $timerSession['expires_at'];
    $storedDurationSeconds = isset($timerSession['duration_seconds'])
        ? (int) $timerSession['duration_seconds']
        : max(0, $storedExpiresAt - $storedStartTime);

    if ($storedDurationSeconds !== $totalTimeSeconds) {
        $_SESSION['quiz_timers'][$quiz_id]['expires_at'] = $storedStartTime + $totalTimeSeconds;
        $_SESSION['quiz_timers'][$quiz_id]['duration_seconds'] = $totalTimeSeconds;
    }
}

$quizStartTime = (int) $_SESSION['quiz_timers'][$quiz_id]['start_time'];
$quizExpiresAt = (int) $_SESSION['quiz_timers'][$quiz_id]['expires_at'];
$remainingTimeSeconds = max(0, $quizExpiresAt - $now);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = $quizStartTime > 0 ? $quizStartTime : (isset($_POST['start_time']) ? (int) $_POST['start_time'] : time());
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

    // Trigger Notifications
    $username = $_SESSION['username'] ?? 'User';
    $quizTitle = $quiz['title'];
    $totalMarks = $quiz['total_marks'];

    // 1. User Quiz Completed Notification
    addNotification($pdo, $_SESSION['user_id'], "Quiz Completed: " . $quizTitle, "You completed the quiz '" . $quizTitle . "' with a score of " . $score . " / " . $totalMarks . ".", "quiz_completed", "dashboard_user.php");

    // 2. Admin Quiz Attempted Notification
    addNotification($pdo, null, "New Quiz Attempt", "User '" . $username . "' completed '" . $quizTitle . "' with a score of " . $score . " / " . $totalMarks . ".", "quiz_attempted", "admin/view_leaderboard.php?quiz_id=" . $quiz_id);

    // Auto-generate certificate if score is 70% or higher
    if ($score >= $passingScore) {
        require_once '../includes/certificate_image_service.php';
        try {
            generateAndSaveCertificate($attemptId, $pdo);
            // 3. User Certificate Issued Notification
            addNotification($pdo, $_SESSION['user_id'], "Certificate Unlocked!", "Congratulations! You scored " . $score . " / " . $totalMarks . " and unlocked your certificate for '" . $quizTitle . "'!", "certificate_issued", "certificates.php");
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

    unset(
        $_SESSION['quiz_timers'][$quiz_id],
        $_SESSION['quiz_reloads'][$quiz_id],
        $_SESSION['quiz_question_orders'][$quiz_id]
    );

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
$headAssets = '<link rel="stylesheet" href="../assets/css/mobile_view.css">';

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
                    background: rgba(15, 23, 42, 0.95);
                    backdrop-filter: blur(8px);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    animation: fadeIn 0.3s ease;
                }
                .app-reload-modal {
                    background: #ffffff;
                    color: #1e293b;
                    padding: 3rem;
                    border-radius: 1.5rem;
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                    border: 1px solid #e2e8f0;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                }
                .app-reload-modal h2 {
                    color: #0f172a;
                    margin-bottom: 1rem;
                }
                .app-reload-modal p {
                    color: #475569;
                    line-height: 1.6;
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
                    background: #f8fafc;
                    border-radius: 1rem;
                    border: 1px solid #e2e8f0;
                }
                .app-reload-stat-item span {
                    display: block;
                    font-size: 0.875rem;
                    color: #64748b;
                    margin-bottom: 0.5rem;
                }
                .app-reload-stat-item strong {
                    font-size: 1.5rem;
                    color: #0f172a;
                }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

                /* Hide questions except active one */
                .app-take-question { display: none; }
                .app-take-question.is-active { display: block; }

                .page-take-quiz .app-quiz-nav-grid,
                .app-shell-page.page-take-quiz .app-quiz-nav-grid {
                    display: flex;
                    flex-wrap: nowrap;
                    gap: 10px;
                    overflow-x: auto;
                    overflow-y: hidden;
                    padding: 2px 2px 10px;
                    scroll-behavior: smooth;
                    scrollbar-width: thin;
                    scrollbar-color: rgba(37, 99, 235, 0.45) rgba(226, 232, 240, 0.65);
                    -webkit-overflow-scrolling: touch;
                }

                .page-take-quiz .app-quiz-nav-grid::-webkit-scrollbar,
                .app-shell-page.page-take-quiz .app-quiz-nav-grid::-webkit-scrollbar {
                    height: 8px;
                }

                .page-take-quiz .app-quiz-nav-grid::-webkit-scrollbar-track,
                .app-shell-page.page-take-quiz .app-quiz-nav-grid::-webkit-scrollbar-track {
                    background: rgba(226, 232, 240, 0.65);
                    border-radius: 999px;
                }

                .page-take-quiz .app-quiz-nav-grid::-webkit-scrollbar-thumb,
                .app-shell-page.page-take-quiz .app-quiz-nav-grid::-webkit-scrollbar-thumb {
                    background: rgba(37, 99, 235, 0.45);
                    border-radius: 999px;
                }

                .page-take-quiz .app-quiz-nav-btn,
                .app-shell-page.page-take-quiz .app-quiz-nav-btn {
                    flex: 0 0 48px;
                    width: 48px;
                }
                
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

                .app-quiz-header-timer {
                    min-width: 150px;
                    display: inline-flex;
                    flex-direction: column;
                    align-items: flex-end;
                    gap: 4px;
                    padding: 12px 16px;
                    border-radius: 18px;
                    background: #f8fafc;
                    border: 1px solid rgba(148, 163, 184, 0.24);
                    box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
                }

                .app-quiz-header-timer span {
                    color: #334155;
                    font-size: 12px;
                    font-weight: 700;
                }

                .app-quiz-header-timer strong {
                    color: #0f172a;
                    font-size: 22px;
                    font-weight: 800;
                    line-height: 1;
                }

                .app-quiz-header-timer strong[data-state="warning"] {
                    color: #b45309;
                }

                .app-quiz-header-timer strong[data-state="danger"] {
                    color: #dc2626;
                }

                @media (max-width: 640px) {
                    .app-shell-page.page-take-quiz .app-main-panel .app-panel-head {
                        display: grid;
                        grid-template-columns: minmax(0, 1fr) auto;
                        align-items: start;
                        gap: 12px;
                    }

                    .app-shell-page.page-take-quiz .app-quiz-header-timer {
                        min-width: 112px;
                        align-items: center;
                        justify-self: end;
                        padding: 10px 12px;
                        border-radius: 14px;
                        background: #f8fafc;
                        border-color: rgba(148, 163, 184, 0.24);
                        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
                    }

                    .app-shell-page.page-take-quiz .app-quiz-header-timer span {
                        color: #334155;
                        font-size: 11px;
                    }

                    .app-shell-page.page-take-quiz .app-quiz-header-timer strong {
                        color: #0f172a;
                        font-size: 18px;
                    }

                    .app-shell-page.page-take-quiz .app-quiz-header-timer strong[data-state="warning"] {
                        color: #b45309;
                    }

                    .app-shell-page.page-take-quiz .app-quiz-header-timer strong[data-state="danger"] {
                        color: #dc2626;
                    }

                    .app-shell-page.page-take-quiz .app-sidebar .app-preview-stack {
                        grid-template-columns: 1fr;
                    }

                    .app-shell-page.page-take-quiz .app-sidebar .app-preview-stat {
                        display: flex;
                        flex-direction: row;
                        align-items: center;
                        justify-content: space-between;
                        gap: 16px;
                        width: 100%;
                    }

                    .app-shell-page.page-take-quiz .app-sidebar .app-preview-stat span {
                        max-width: none;
                        color: #334155;
                        line-height: 1.35;
                    }

                    .app-shell-page.page-take-quiz .app-sidebar .app-preview-stat strong {
                        display: inline-flex;
                        align-items: center;
                        justify-content: flex-end;
                        gap: 6px;
                        min-width: 74px;
                        color: #0f172a;
                        font-size: 18px;
                        line-height: 1;
                        white-space: nowrap;
                    }
                }

                @media (max-width: 420px) {
                    .app-shell-page.page-take-quiz .app-main-panel .app-panel-head {
                        grid-template-columns: 1fr;
                    }

                    .app-shell-page.page-take-quiz .app-quiz-header-timer {
                        width: 100%;
                        flex-direction: row;
                        justify-content: space-between;
                    }

                    .app-shell-page.page-take-quiz .app-sidebar .app-preview-stat {
                        padding: 14px 16px;
                    }
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
                        <div class="app-quiz-header-timer" aria-live="polite">
                            <span>Time left</span>
                            <strong id="appQuizTimer"
                                data-seconds="<?php echo $remainingTimeSeconds; ?>"
                                data-total-seconds="<?php echo $totalTimeSeconds; ?>"
                                data-expires-at="<?php echo $quizExpiresAt; ?>"
                                data-server-now="<?php echo $now; ?>">00:00</strong>
                        </div>
                    </div>
                    <p class="app-panel-text">You can move between questions freely before submission. The timer continues until you submit or the session expires.</p>

                    <form id="quizForm" method="POST" action="take_quiz.php?quiz_id=<?php echo $quiz_id; ?>" autocomplete="off">
                        <input type="hidden" name="start_time" value="<?php echo $quizStartTime; ?>">

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
                                            <button type="submit" form="quizForm" class="app-button app-button-primary" data-submit-lock data-loading-submit>
                                                <span class="submit-loader" aria-hidden="true"></span>
                                                <i class="fas fa-check-double"></i>
                                                <span data-submit-label>Submit Quiz</span>
                                            </button>
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
                    const questionCards = Array.from(document.querySelectorAll('[data-quiz-question]'));
                    const navButtons = Array.from(document.querySelectorAll('[data-quiz-nav]'));
                    const navGrid = document.querySelector('.app-quiz-nav-grid');
                    const answeredElements = Array.from(document.querySelectorAll('[data-quiz-answered], [data-quiz-sidebar-answered]'));
                    const statusText = document.querySelector('[data-quiz-status-text]');
                    const progressFill = document.querySelector('[data-quiz-progress]');
                    const submitLocks = Array.from(document.querySelectorAll('[data-submit-lock]'));
                    const totalQuestions = questionCards.length;
                    const totalSeconds = parseInt(timerElement?.dataset.totalSeconds || timerElement?.dataset.seconds || '0', 10);
                    const initialSeconds = parseInt(timerElement?.dataset.seconds || '0', 10);
                    const expiresAt = parseInt(timerElement?.dataset.expiresAt || '0', 10);
                    const serverNow = parseInt(timerElement?.dataset.serverNow || '0', 10);
                    const clientServerOffset = serverNow > 0 ? (serverNow * 1000) - Date.now() : 0;
                    let timeLeft = Math.max(0, initialSeconds);
                    let submitted = false;
                    let currentQuestion = 1;
                    const storageKey = 'quiz_<?php echo $quiz_id; ?>_answers';
                    const currentQuestionKey = 'quiz_<?php echo $quiz_id; ?>_current_question';

                    const getServerAlignedNowSeconds = () => Math.floor((Date.now() + clientServerOffset) / 1000);

                    const refreshTimeLeft = () => {
                        if (expiresAt > 0) {
                            timeLeft = Math.max(0, expiresAt - getServerAlignedNowSeconds());
                        } else {
                            timeLeft = Math.max(0, timeLeft);
                        }
                    };

                    const saveCurrentQuestion = (questionNumber) => {
                        if (questionNumber < 1 || questionNumber > totalQuestions) return;

                        try {
                            localStorage.setItem(currentQuestionKey, questionNumber.toString());
                        } catch (e) {}

                        try {
                            sessionStorage.setItem(currentQuestionKey, questionNumber.toString());
                        } catch (e) {}

                        if (window.history && window.history.replaceState) {
                            window.history.replaceState(null, '', `#q${questionNumber}`);
                        }
                    };

                    const getSavedCurrentQuestion = () => {
                        const normalizeQuestionNumber = (value) => {
                            const questionNumber = parseInt(value || '0', 10);
                            return questionNumber >= 1 && questionNumber <= totalQuestions ? questionNumber : 0;
                        };

                        const hashQuestion = window.location.hash.match(/^#q(\d+)$/i);
                        const questionFromHash = hashQuestion ? normalizeQuestionNumber(hashQuestion[1]) : 0;
                        if (questionFromHash > 0) {
                            return questionFromHash;
                        }

                        try {
                            const sessionQuestion = normalizeQuestionNumber(sessionStorage.getItem(currentQuestionKey));
                            if (sessionQuestion > 0) {
                                return sessionQuestion;
                            }
                        } catch (e) {}

                        try {
                            const savedQuestion = normalizeQuestionNumber(localStorage.getItem(currentQuestionKey));
                            return savedQuestion > 0 ? savedQuestion : 1;
                        } catch (e) {
                            return 1;
                        }
                    };

                    const getQuestionNumberFromField = (field) => {
                        const questionCard = field.closest('[data-quiz-question]');
                        if (!questionCard) return 0;
                        const questionNumber = parseInt(questionCard.dataset.questionIndex || '0', 10);
                        return questionNumber >= 1 && questionNumber <= totalQuestions ? questionNumber : 0;
                    };

                    const setSubmitLoading = () => {
                        submitLocks.forEach((button) => {
                            button.disabled = true;
                            button.classList.add('is-loading');
                            button.setAttribute('aria-busy', 'true');

                            const label = button.querySelector('[data-submit-label]');
                            if (label) {
                                label.textContent = 'Submitting...';
                            }
                        });
                    };

                    const keepActiveNavVisible = () => {
                        if (!navGrid) return;
                        const activeButton = navButtons.find((btn) => parseInt(btn.dataset.quizNav || '0', 10) === currentQuestion);
                        if (!activeButton) return;

                        const targetLeft = activeButton.offsetLeft - (navGrid.clientWidth / 2) + (activeButton.clientWidth / 2);
                        navGrid.scrollTo({
                            left: Math.max(0, targetLeft),
                            behavior: 'smooth'
                        });
                    };

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

                            keepActiveNavVisible();
                            saveCurrentQuestion(questionNumber);
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
                    };

                    const updateTimerText = () => {
                        const formatted = formatTime(Math.max(0, timeLeft));
                        if (timerElement) {
                            timerElement.textContent = formatted;
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
                        });
                    });

                    // Restore saved answers from localStorage
                    try {
                        const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                        Object.keys(saved).forEach(questionId => {
                            const val = saved[questionId];
                            const radio = form.querySelector(`input[name="answers[${questionId}]"][value="${val}"]`);
                            if (radio) {
                                radio.checked = true;
                            }
                        });
                    } catch (e) {}

                    form.querySelectorAll('input[type="radio"]').forEach((input) => {
                        input.addEventListener('change', (e) => {
                            syncQuestionState();
                            const answeredQuestionNumber = getQuestionNumberFromField(e.target);
                            if (answeredQuestionNumber > 0) {
                                saveCurrentQuestion(answeredQuestionNumber);
                            }
                            // Save answer to localStorage
                            try {
                                const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                                const nameMatch = e.target.name.match(/answers\[(\d+)\]/);
                                if (nameMatch) {
                                    saved[nameMatch[1]] = e.target.value;
                                    localStorage.setItem(storageKey, JSON.stringify(saved));
                                }
                            } catch (e) {}
                        });
                    });
                    
                    // Auto-submit on 3 reloads
                    const reloadsUsed = <?php echo $reloadsUsed; ?>;
                    if (reloadsUsed >= 3 && !submitted) {
                        submitted = true;
                        setSubmitLoading();
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
                        setSubmitLoading();
                        try {
                            localStorage.removeItem(storageKey);
                            localStorage.removeItem(currentQuestionKey);
                            sessionStorage.removeItem(currentQuestionKey);
                        } catch (e) {}
                    });

                    refreshTimeLeft();
                    updateTimerText();
                    syncQuestionState();
                    setCurrentQuestion(getSavedCurrentQuestion());

                    if (timeLeft <= 0 && !submitted) {
                        submitted = true;
                        setSubmitLoading();
                        form.submit();
                        return;
                    }

                    const timerInterval = window.setInterval(() => {
                        refreshTimeLeft();
                        updateTimerText();

                        if (timeLeft <= 0) {
                            window.clearInterval(timerInterval);
                            if (!submitted) {
                                submitted = true;
                                setSubmitLoading();
                                form.submit();
                            }
                        }
                    }, 1000);
                })();
            </script>
<?php include '../includes/footer.php'; ?>
