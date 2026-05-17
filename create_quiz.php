<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$categories = $pdo->query("SELECT DISTINCT category FROM quizzes ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$editQuizId = 0;
$isEditMode = false;
$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'category' => '',
    'no_of_questions' => '',
    'total_marks' => '',
    'timer_minutes' => '10'
];

$existingQuestionCount = 0;
$editQuiz = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['category'] = trim($_POST['category'] ?? '');
    $formData['no_of_questions'] = (string) ((int) ($_POST['no_of_questions'] ?? 0));
    $formData['total_marks'] = (string) ((int) ($_POST['total_marks'] ?? 0));
    $formData['timer_minutes'] = (string) ((int) ($_POST['timer_minutes'] ?? 10));

    $noOfQuestions = (int) $formData['no_of_questions'];
    $totalMarks = (int) $formData['total_marks'];
    $timerMinutes = (int) $formData['timer_minutes'];

    if ($formData['title'] === '' || $formData['category'] === '' || $noOfQuestions <= 0 || $totalMarks <= 0) {
        $errors[] = 'Please fill all required fields with valid values.';
    }

    if ($timerMinutes <= 0) {
        $errors[] = 'Timer must be at least 1 minute.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $uniqueCode = generateUniqueCode();
            $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, category, no_of_questions, total_marks, timer_minutes, unique_code, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$formData['title'], $formData['description'], $formData['category'], $noOfQuestions, $totalMarks, $timerMinutes, $uniqueCode, $_SESSION['user_id']]);
            
            $quizId = $pdo->lastInsertId();
            
            // Handle questions insert
            $questionsData = $_POST['questions'] ?? [];
            foreach ($questionsData as $index => $q) {
                $qText = trim($q['text'] ?? '');
                $o1 = trim($q['option1'] ?? '');
                $o2 = trim($q['option2'] ?? '');
                $o3 = trim($q['option3'] ?? '');
                $o4 = trim($q['option4'] ?? '');
                $correct = (int) ($q['correct'] ?? 0);
                $qMarks = (int) ($q['marks'] ?? 1);

                if ($qText !== '') {
                    $ins = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$quizId, $qText, $o1, $o2, $o3, $o4, $correct, $qMarks]);
                }
            }
            
            $pdo->commit();
            redirect("quiz.php", 'Quiz and questions created successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$existingQuestions = [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$myQuizCount = (int) $stmt->fetchColumn();

$platformQuizCount = (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$categoryCount = count($categories);
$questionCountPreview = (int) ($formData['no_of_questions'] !== '' ? $formData['no_of_questions'] : 0);
$marksPreview = (int) ($formData['total_marks'] !== '' ? $formData['total_marks'] : 0);
$timerPreview = (int) ($formData['timer_minutes'] !== '' ? $formData['timer_minutes'] : 10);
$marksPerQuestion = $questionCountPreview > 0 ? round($marksPreview / $questionCountPreview, 1) : 0;
$formAction = 'create_quiz.php' . ($isEditMode ? '?edit_id=' . $editQuizId : '');
$heroKicker = $isEditMode ? 'Quiz editing' : 'Assessment builder';
$heroTitle = $isEditMode
    ? 'Refine quiz details before you return to question management'
    : 'Craft a quiz with stronger structure and launch clarity';
$heroSubtitle = $isEditMode
    ? 'Update the title, category, scoring envelope, and timer in one place. Your existing questions stay attached while you reshape the quiz.'
    : 'Set the title, category, difficulty envelope, and timing in one focused workspace before you move into question authoring.';
$heroPrimaryLabel = $isEditMode ? 'Save Changes' : 'Create and Add Questions';
$formPrimaryLabel = $isEditMode ? 'Update Quiz' : 'Create Quiz';

$isAdminView = true;
$homeLink = 'dashboard_admin.php';
$leaderboardLink = 'admin/view_leaderboard.php';
$logoutLink = 'logout.php';
$pageTitle = $isEditMode ? 'QuizPro - Edit Quiz' : 'QuizPro - Create Quiz';
$pageKey = 'create_quiz';
$pageBodyClass = 'page-create-quiz';
$headerContext = $isEditMode ? 'Quiz editor' : 'Builder workspace';
$pageFooterSummary = $isEditMode
    ? 'Structured quiz editing with clearer metadata control, question continuity, and admin visibility.'
    : 'Structured quiz creation with clearer authoring flow, launch guidance, and admin visibility.';
$headAssets = '<link rel="stylesheet" href="assets/css/mobile_view.css">';

include 'includes/header.php';
?>
<?php displayMessage(); ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="app-grid app-builder-grid" style="margin-top: 24px;">
    <section class="app-panel">
        <div class="app-panel-head">
            <div>
                <span class="app-panel-kicker">Configuration</span>
                <h2 class="app-panel-title"><?php echo $isEditMode ? 'Edit quiz setup' : 'New quiz setup'; ?></h2>
            </div>
        </div>
        <p class="app-panel-text">
            <?php echo $isEditMode ? 'Adjust metadata without losing the current question set. Use manage questions after saving if you need to refine the quiz content.' : 'Start with clean metadata and balanced scoring. Once this saves, you will move directly into question authoring.'; ?>
        </p>

        <form action="<?php echo htmlspecialchars($formAction); ?>" method="POST" class="app-form-grid"
            id="create-quiz-form">
            <div class="app-form-section">
                <h3 class="app-section-title">Identity</h3>
                <div class="app-field">
                    <label for="title" class="app-label">Quiz Title*</label>
                    <input type="text" id="title" name="title" class="app-input"
                        value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                </div>
                <div class="app-field">
                    <label for="description" class="app-label">Description</label>
                    <textarea id="description" name="description" rows="5"
                        class="app-textarea"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                </div>
                <div class="app-field">
                    <label for="category" class="app-label">Category*</label>
                    <input type="text" id="category" name="category" class="app-input"
                        value="<?php echo htmlspecialchars($formData['category']); ?>" list="category-list" required>
                    <datalist id="category-list">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php endforeach; ?>
                    </datalist>
                    <small class="app-helper">Type to create a new category or select from existing ones.</small>
                </div>
            </div>

            <div class="app-form-section">
                <h3 class="app-section-title">Scoring and timing</h3>
                <div class="app-field-row">
                    <div class="app-field">
                        <label for="no_of_questions" class="app-label">Number of Questions*</label>
                        <input type="number" id="no_of_questions" name="no_of_questions" class="app-input" min="1"
                            value="<?php echo htmlspecialchars($formData['no_of_questions']); ?>" required>
                    </div>
                    <div class="app-field">
                        <label for="total_marks" class="app-label">Total Marks*</label>
                        <input type="number" id="total_marks" name="total_marks" class="app-input" min="1"
                            value="<?php echo htmlspecialchars($formData['total_marks']); ?>" required>
                    </div>
                    <div class="app-field">
                        <label for="timer_minutes" class="app-label">Timer (minutes)</label>
                        <input type="number" id="timer_minutes" name="timer_minutes" class="app-input" min="1"
                            value="<?php echo htmlspecialchars($formData['timer_minutes']); ?>">
                    </div>
                </div>
                <p class="app-helper">Balanced quizzes usually keep marks proportional to the number of questions and
                    use a timer that matches expected difficulty.</p>
            </div>

            <div class="app-form-section" id="questions-builder-section" style="display: none;">
                <h3 class="app-section-title">Question Authoring</h3>
                <p class="app-helper">Define each question, its options, and the correct answer below. Navigation buttons will appear based on the number of questions set above.</p>
                
                <div class="app-question-nav" id="question-nav">
                    <!-- Dynamic navigation buttons -->
                </div>

                <div id="questions-container">
                    <!-- Dynamic question blocks -->
                </div>

                <div class="app-field-row app-question-builder-controls">
                    <button type="button" class="app-button app-button-ghost" id="prev-question-btn"><i class="fas fa-chevron-left"></i> Previous Question</button>
                    <button type="button" class="app-button app-button-ghost" id="next-question-btn">Next Question <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <div class="app-actions">
                <button type="submit" class="app-button app-button-primary"><i
                        class="fas <?php echo $isEditMode ? 'fa-floppy-disk' : 'fa-plus'; ?>"></i>
                    <?php echo $formPrimaryLabel; ?></button>
                <button type="reset" class="app-button app-button-ghost"><i class="fas fa-trash-can"></i> Clear
                    Form</button>
            </div>
        </form>
    </section>


</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const noOfQuestionsInput = document.getElementById('no_of_questions');
        const totalMarksInput = document.getElementById('total_marks');
        const questionsBuilderSection = document.getElementById('questions-builder-section');
        const questionNav = document.getElementById('question-nav');
        const questionsContainer = document.getElementById('questions-container');
        const prevBtn = document.getElementById('prev-question-btn');
        const nextBtn = document.getElementById('next-question-btn');
        
        let currentQuestionIndex = 0;
        let totalQuestions = 0;
        
        // Initial data if editing
        const existingQuestions = <?php echo json_encode($existingQuestions); ?>;

        function updateUI() {
            const count = parseInt(noOfQuestionsInput.value) || 0;
            const totalMarks = parseInt(totalMarksInput.value) || 0;
            const marksPerQuestion = count > 0 ? Math.floor(totalMarks / count) : 0;
            const remainderMarks = count > 0 ? (totalMarks % count) : 0;

            if (count > 0) {
                questionsBuilderSection.style.display = 'grid';
                if (count !== totalQuestions) {
                    renderQuestionBlocks(count, marksPerQuestion, remainderMarks);
                    totalQuestions = count;
                }
                showQuestion(currentQuestionIndex);
            } else {
                questionsBuilderSection.style.display = 'none';
            }
        }

        function renderQuestionBlocks(count, baseMarks, remainder) {
            questionNav.innerHTML = '';
            questionsContainer.innerHTML = '';
            
            for (let i = 0; i < count; i++) {
                // Nav button
                const navBtn = document.createElement('button');
                navBtn.type = 'button';
                navBtn.className = 'app-question-number';
                navBtn.textContent = i + 1;
                navBtn.onclick = () => showQuestion(i);
                questionNav.appendChild(navBtn);

                // Question block
                const existing = existingQuestions[i] || {};
                const marksForThis = i < remainder ? baseMarks + 1 : baseMarks;

                const block = document.createElement('div');
                block.className = 'app-question-block';
                block.innerHTML = `
                    <div class="app-field">
                        <input type="hidden" name="questions[${i}][id]" value="${existing.id || ''}">
                        <label class="app-label">Question ${i + 1} Text*</label>
                        <textarea name="questions[${i}][text]" class="app-textarea" rows="3" required>${existing.question_text || ''}</textarea>
                    </div>
                    <div class="app-field-row" style="margin-top: 12px;">
                        <div class="app-field">
                            <label class="app-label">Option 1*</label>
                            <input type="text" name="questions[${i}][option1]" class="app-input" value="${existing.option1 || ''}" required>
                        </div>
                        <div class="app-field">
                            <label class="app-label">Option 2*</label>
                            <input type="text" name="questions[${i}][option2]" class="app-input" value="${existing.option2 || ''}" required>
                        </div>
                    </div>
                    <div class="app-field-row" style="margin-top: 12px;">
                        <div class="app-field">
                            <label class="app-label">Option 3*</label>
                            <input type="text" name="questions[${i}][option3]" class="app-input" value="${existing.option3 || ''}" required>
                        </div>
                        <div class="app-field">
                            <label class="app-label">Option 4*</label>
                            <input type="text" name="questions[${i}][option4]" class="app-input" value="${existing.option4 || ''}" required>
                        </div>
                    </div>
                    <div class="app-field-row" style="margin-top: 12px;">
                        <div class="app-field">
                            <label class="app-label">Correct Option*</label>
                            <select name="questions[${i}][correct]" class="app-select" required>
                                <option value="1" ${existing.correct_option == 1 ? 'selected' : ''}>Option 1</option>
                                <option value="2" ${existing.correct_option == 2 ? 'selected' : ''}>Option 2</option>
                                <option value="3" ${existing.correct_option == 3 ? 'selected' : ''}>Option 3</option>
                                <option value="4" ${existing.correct_option == 4 ? 'selected' : ''}>Option 4</option>
                            </select>
                        </div>
                        <div class="app-field">
                            <label class="app-label">Marks for this question</label>
                            <input type="number" name="questions[${i}][marks]" class="app-input" value="${existing.marks || marksForThis}" required min="1">
                        </div>
                    </div>
                `;
                questionsContainer.appendChild(block);
            }
        }

        function showQuestion(index) {
            currentQuestionIndex = index;
            const blocks = questionsContainer.getElementsByClassName('app-question-block');
            const navButtons = questionNav.getElementsByClassName('app-question-number');
            
            for (let i = 0; i < blocks.length; i++) {
                blocks[i].classList.toggle('active', i === index);
                navButtons[i].classList.toggle('active', i === index);
            }
            
            prevBtn.disabled = index === 0;
            nextBtn.disabled = index === totalQuestions - 1;
            
            // Apply ghost styling to disabled buttons via CSS or opacity
            prevBtn.style.opacity = index === 0 ? '0.5' : '1';
            nextBtn.style.opacity = index === totalQuestions - 1 ? '0.5' : '1';
        }

        noOfQuestionsInput.addEventListener('input', updateUI);
        totalMarksInput.addEventListener('input', updateUI);
        
        prevBtn.onclick = () => { if (currentQuestionIndex > 0) showQuestion(currentQuestionIndex - 1); };
        nextBtn.onclick = () => { if (currentQuestionIndex < totalQuestions - 1) showQuestion(currentQuestionIndex + 1); };

        // Initial trigger
        updateUI();
    });
</script>

<?php include 'includes/footer.php'; ?>
