<?php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php');
}

$resolveAppPath = static function (string $path): string {
    return $path;
};

$headerContext = 'Public quiz library';
$pageTitle = 'Quiz Pro - Learn, Compete, Improve';
$pageBodyClass = 'page-public-home';
$navItems = [
    [
        'label' => 'Home',
        'icon' => 'fas fa-house',
        'href' => 'index.php',
        'show' => true,
    ],
    [
        'label' => 'Sign In',
        'icon' => 'fas fa-right-to-bracket',
        'href' => 'login.php',
        'show' => true,
    ],
    [
        'label' => 'Register',
        'icon' => 'fas fa-user-plus',
        'href' => 'register.php',
        'show' => true,
    ],
    [
        'label' => 'Contact',
        'icon' => 'fas fa-envelope',
        'href' => 'contact.php',
        'show' => true,
    ],
];

$libraryStats = [
    'total_quizzes' => 0,
    'total_categories' => 0,
    'total_questions' => 0,
];
$categoryCards = [];

try {
    require_once 'includes/db.php';

    $libraryStats = $pdo->query("SELECT
        COUNT(*) AS total_quizzes,
        COUNT(DISTINCT category) AS total_categories,
        COALESCE(SUM(no_of_questions), 0) AS total_questions
        FROM quizzes")->fetch(PDO::FETCH_ASSOC) ?: $libraryStats;

    $categoryStmt = $pdo->query("SELECT
        category,
        COUNT(*) AS quiz_count,
        COALESCE(SUM(no_of_questions), 0) AS question_count,
        COALESCE(AVG(timer_minutes), 0) AS average_timer
        FROM quizzes
        WHERE category IS NOT NULL AND category <> ''
        GROUP BY category
        ORDER BY category
        LIMIT 8");
    $categoryCards = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categoryCards = [];
}

if (empty($categoryCards)) {
    $categoryCards = [
        ['category' => 'Programming', 'quiz_count' => 6, 'question_count' => 90, 'average_timer' => 15],
        ['category' => 'General Knowledge', 'quiz_count' => 4, 'question_count' => 60, 'average_timer' => 12],
        ['category' => 'Science', 'quiz_count' => 5, 'question_count' => 75, 'average_timer' => 14],
        ['category' => 'Web Development', 'quiz_count' => 7, 'question_count' => 105, 'average_timer' => 18],
    ];
    $libraryStats = [
        'total_quizzes' => 22,
        'total_categories' => count($categoryCards),
        'total_questions' => 330,
    ];
}

$categoryIcons = [
    'A' => 'fa-atom',
    'C' => 'fa-code',
    'D' => 'fa-database',
    'G' => 'fa-earth-americas',
    'H' => 'fa-landmark',
    'J' => 'fa-terminal',
    'M' => 'fa-square-root-variable',
    'P' => 'fa-laptop-code',
    'S' => 'fa-flask',
    'W' => 'fa-globe',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="Quiz Pro helps learners practice quiz categories, track progress, and compete through a modern quiz platform.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="app-shell-page <?php echo htmlspecialchars($pageBodyClass); ?>">
    <div class="app-shell public-home-shell">
        <header class="public-home-header">
            <a href="index.php" class="public-home-brand" aria-label="Quiz Pro home">
                <span class="public-home-logo">
                    <img src="assets/images/quizPro.png" alt="Quiz Pro Logo">
                </span>
                <span>Quiz Pro</span>
            </a>

            <nav class="public-home-actions" aria-label="Account actions">
                <a href="login.php" class="public-home-link">Login</a>
                <a href="register.php" class="public-home-button">Register</a>
            </nav>
        </header>

        <main class="app-main public-home-main">
            <section class="public-hero">
                <div class="public-hero-copy">
                    <span class="app-kicker"><i class="fas fa-bolt"></i> Skill practice hub</span>
                    <h1 class="public-hero-title">Build quiz confidence across every category.</h1>
                    <p class="public-hero-text">Choose a category, practice with focused quizzes, review scores, unlock certificates, and keep your learning progress organized in one place.</p>
                    <div class="public-hero-actions">
                        <a href="register.php" class="app-button app-button-primary"><i class="fas fa-user-plus"></i> Create free account</a>
                        <a href="login.php" class="app-button app-button-ghost"><i class="fas fa-right-to-bracket"></i> Sign in</a>
                    </div>
                </div>

                <div class="public-hero-panel" aria-label="Quiz Pro overview">
                    <div class="public-stat-grid">
                        <div class="public-stat-card">
                            <span>Quizzes</span>
                            <strong><?php echo (int) ($libraryStats['total_quizzes'] ?? 0); ?>+</strong>
                        </div>
                        <div class="public-stat-card">
                            <span>Categories</span>
                            <strong><?php echo (int) ($libraryStats['total_categories'] ?? 0); ?></strong>
                        </div>
                        <div class="public-stat-card">
                            <span>Questions</span>
                            <strong><?php echo (int) ($libraryStats['total_questions'] ?? 0); ?>+</strong>
                        </div>
                    </div>
                    <div class="public-flow-card">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <strong>Track every attempt</strong>
                            <span>Scores, ranks, certificates, and progress insights are ready after sign in.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="public-section">
                <div class="public-section-head">
                    <div>
                        <span class="app-panel-kicker">Quiz categories</span>
                        <h2>Start with a focused topic</h2>
                    </div>
                    <a href="register.php" class="app-button app-button-ghost"><i class="fas fa-layer-group"></i> Explore after signup</a>
                </div>

                <div class="public-category-grid">
                    <?php foreach ($categoryCards as $categoryCard): ?>
                        <?php
                        $categoryName = (string) ($categoryCard['category'] ?? 'Quiz');
                        $categoryInitial = strtoupper(substr($categoryName, 0, 1));
                        $categoryIcon = $categoryIcons[$categoryInitial] ?? 'fa-book-open';
                        ?>
                        <article class="public-category-card">
                            <div class="public-category-icon"><i class="fas <?php echo $categoryIcon; ?>"></i></div>
                            <div>
                                <h3><?php echo htmlspecialchars($categoryName); ?></h3>
                                <p><?php echo (int) ($categoryCard['quiz_count'] ?? 0); ?> quizzes · <?php echo (int) ($categoryCard['question_count'] ?? 0); ?> questions</p>
                            </div>
                            <span><?php echo (int) round((float) ($categoryCard['average_timer'] ?? 0)); ?> min avg</span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="public-section public-feature-band">
                <div class="public-feature">
                    <i class="fas fa-stopwatch"></i>
                    <strong>Timed practice</strong>
                    <span>Build speed with quiz timers and clear attempt flow.</span>
                </div>
                <div class="public-feature">
                    <i class="fas fa-ranking-star"></i>
                    <strong>Rank tracking</strong>
                    <span>Compare progress and keep improving through leaderboard context.</span>
                </div>
                <div class="public-feature">
                    <i class="fas fa-certificate"></i>
                    <strong>Certificates</strong>
                    <span>Celebrate strong results with generated achievement certificates.</span>
                </div>
            </section>

<?php include 'includes/footer.php'; ?>
