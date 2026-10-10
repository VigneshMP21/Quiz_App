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
$pageTitle = 'Quiz Pro - Free Online Quiz Platform for Programming, Science & General Knowledge';
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
$latestQuizzes = [];

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

    $latestStmt = $pdo->query("SELECT id, title, description, category, no_of_questions, total_marks, timer_minutes, unique_code, COALESCE(created_at, CURRENT_DATE) AS created_at
                              FROM quizzes
                              ORDER BY created_at DESC, id DESC
                              LIMIT 6");
    $latestQuizzes = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categoryCards = [];
    $latestQuizzes = [];
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

if (empty($latestQuizzes)) {
    $latestQuizzes = [
        ['id' => 0, 'title' => 'PHP Basics', 'description' => 'Practice beginner PHP MCQs.', 'category' => 'Programming', 'timer_minutes' => 15],
        ['id' => 0, 'title' => 'General Knowledge Starter', 'description' => 'Build GK confidence with quick questions.', 'category' => 'General Knowledge', 'timer_minutes' => 12],
        ['id' => 0, 'title' => 'Science MCQ Practice', 'description' => 'Review core science quiz concepts.', 'category' => 'Science', 'timer_minutes' => 14],
    ];
}

$popularSearches = [
    'programming quiz preparation',
    'aptitude mock tests',
    'GK learning quiz',
    'science MCQs',
    'interview preparation quizzes',
    'free online mock test',
];

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

$siteBaseUrl = 'https://quizpro.free.nf';
$canonicalUrl = 'https://quizpro.free.nf/';
$siteLogoUrl = 'https://quizpro.free.nf/assets/images/quizPro.png';
$homeDescription = 'Quiz Pro is a free online quiz platform for programming quiz practice, GK quiz preparation, science quiz learning, aptitude test training, and free mock test progress tracking.';
$homeKeywords = 'online quiz, programming quiz, GK quiz, science quiz, aptitude test, free mock test, quiz platform, general knowledge quiz, coding quiz, certificate quiz';
$organizationName = 'Quiz Pro';
$faqItems = [
    [
        'question' => 'What is Quiz Pro?',
        'answer' => 'Quiz Pro is a free online quiz platform for learners to practice programming quizzes, GK quizzes, science quizzes, aptitude tests, and mock tests.',
    ],
    [
        'question' => 'Can I take programming and science quizzes online?',
        'answer' => 'Yes. Quiz Pro supports focused quiz categories such as programming, science, general knowledge, web development, and other practice topics.',
    ],
    [
        'question' => 'Does Quiz Pro provide certificates?',
        'answer' => 'Quiz Pro can generate achievement certificates for strong quiz results after a learner completes eligible quizzes.',
    ],
    [
        'question' => 'Is Quiz Pro mobile friendly?',
        'answer' => 'Yes. Quiz Pro is designed for responsive quiz practice on mobile, tablet, and desktop screens.',
    ],
];
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $canonicalUrl . '#organization',
            'name' => $organizationName,
            'url' => $canonicalUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $siteLogoUrl,
            ],
        ],
        [
            '@type' => 'EducationalOrganization',
            '@id' => $canonicalUrl . '#educational-organization',
            'name' => $organizationName,
            'url' => $canonicalUrl,
            'description' => $homeDescription,
            'logo' => $siteLogoUrl,
        ],
        [
            '@type' => 'WebSite',
            '@id' => $canonicalUrl . '#website',
            'name' => $organizationName,
            'url' => $canonicalUrl,
            'description' => $homeDescription,
            'publisher' => [
                '@id' => $canonicalUrl . '#organization',
            ],
            'inLanguage' => 'en',
            'educationalUse' => ['Practice', 'Assessment', 'Mock Test'],
            'keywords' => $homeKeywords,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $canonicalUrl . 'quiz.php?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => 'WebApplication',
            '@id' => $canonicalUrl . '#quiz-platform',
            'name' => $organizationName,
            'url' => $canonicalUrl,
            'applicationCategory' => 'EducationalApplication',
            'operatingSystem' => 'Web',
            'description' => $homeDescription,
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD',
            ],
            'featureList' => [
                'Online quiz practice',
                'Programming quiz categories',
                'GK quiz preparation',
                'Science quiz learning',
                'Aptitude test practice',
                'Free mock test flow',
                'Timed quiz attempts',
                'Certificate generation',
            ],
        ],
        [
            '@type' => 'ItemList',
            '@id' => $canonicalUrl . '#latest-quizzes',
            'name' => 'Latest Quiz Pro online quizzes',
            'itemListElement' => array_values(array_map(static function (array $quizItem, int $index): array {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => (string) ($quizItem['title'] ?? 'Quiz'),
                    'url' => function_exists('seoQuizUrl') ? seoQuizUrl($quizItem) : 'https://quizpro.free.nf/',
                ];
            }, $latestQuizzes, array_keys($latestQuizzes))),
        ],
        [
            '@type' => 'EducationalOccupationalProgram',
            '@id' => $canonicalUrl . '#quiz-preparation-program',
            'name' => 'Quiz Pro online quiz preparation',
            'description' => 'Programming quiz preparation, aptitude mock tests, GK learning, science MCQs, and interview preparation quizzes.',
            'provider' => [
                '@id' => $canonicalUrl . '#organization',
            ],
            'programType' => 'Online quiz preparation',
            'url' => $canonicalUrl,
        ],
        [
            '@type' => 'BreadcrumbList',
            '@id' => $canonicalUrl . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $canonicalUrl,
                ],
            ],
        ],
        [
            '@type' => 'FAQPage',
            '@id' => $canonicalUrl . '#faq',
            'mainEntity' => array_map(static function (array $faqItem): array {
                return [
                    '@type' => 'Question',
                    'name' => $faqItem['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faqItem['answer'],
                    ],
                ];
            }, $faqItems),
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="monetag" content="4845effa80937e3ba1de598f1c06c6ff">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($homeDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($homeKeywords); ?>">
    <meta name="author" content="Quiz Pro">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index,follow,max-image-preview:large">
    <meta name="revisit-after" content="7 days">
    <meta name="language" content="English">
    <meta name="theme-color" content="#14b8a6">
    <meta name="application-name" content="Quiz Pro">
    <meta name="apple-mobile-web-app-title" content="Quiz Pro">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <link rel="alternate" hreflang="en" href="https://quizpro.free.nf/">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($homeDescription); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($siteLogoUrl); ?>">
    <meta property="og:image:alt" content="Quiz Pro online quiz platform logo">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Quiz Pro">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($homeDescription); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($siteLogoUrl); ?>">
    <meta name="twitter:image:alt" content="Quiz Pro online quiz platform logo">
    <!-- SEO files: add /robots.txt and /sitemap.xml at the site root, then submit sitemap.xml in Google Search Console. -->
    <!-- CLS note: keep logo dimensions fixed and preload the primary logo asset used above the fold. -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" href="<?php echo htmlspecialchars($siteLogoUrl); ?>" as="image" type="image/png">
    <link rel="preload" href="assets/css/style.css" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="<?php echo htmlspecialchars($siteLogoUrl); ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?php echo htmlspecialchars($siteLogoUrl); ?>" sizes="192x192" type="image/png">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($siteLogoUrl); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($siteLogoUrl); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($siteLogoUrl); ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <script type="application/ld+json">
        <?php echo json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
</head>

<body class="app-shell-page <?php echo htmlspecialchars($pageBodyClass); ?>">
    <div class="site-loader" aria-hidden="true">
        <span class="site-loader-mark"><img src="<?php echo htmlspecialchars($siteLogoUrl); ?>" alt="" width="64" height="64"></span>
        <span class="site-loader-ring"></span>
    </div>
    <script>
        // Loader fallback: remove the public-home loader even if the deferred global script is delayed.
        (function () {
            var clearLoader = function () {
                document.body.classList.add('page-loaded');
                window.setTimeout(function () {
                    document.querySelectorAll('.site-loader').forEach(function (loader) {
                        loader.remove();
                    });
                }, 500);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', clearLoader, { once: true });
            } else {
                clearLoader();
            }
        })();
    </script>
    <canvas id="particles-canvas"></canvas>
    <div id="mouse-glow"></div>

    <div class="app-shell public-home-shell">
        <header class="public-home-header">
            <a href="index.php" class="public-home-brand" aria-label="Quiz Pro home">
                <span class="public-home-logo">
                    <img src="<?php echo htmlspecialchars($siteLogoUrl); ?>" alt="Quiz Pro online quiz platform logo" width="52" height="52" loading="eager" decoding="async">
                </span>
                <span>Quiz Pro</span>
            </a>

            <nav class="public-home-actions" aria-label="Account actions">
                <a href="login.php" class="public-home-link">Login</a>
                <a href="register.php" class="public-home-button">Register</a>
            </nav>
        </header>

        <main class="app-main public-home-main">
            <?php displayMessage(); ?>

            <section class="public-hero">
                <div class="public-hero-copy">
                    <span class="app-kicker"><i class="fas fa-bolt"></i> Skill practice hub</span>
                    <h1 class="public-hero-title">Build quiz confidence with free online quiz practice.</h1>
                    <p class="public-hero-text">Choose programming quiz, GK quiz, science quiz, aptitude test, and free mock test categories, then practice with focused quizzes, review scores, unlock certificates, and keep your learning progress organized in one place.</p>
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
                        <h2>Start with programming, science, GK, and aptitude quiz topics</h2>
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
                                <p><?php echo (int) ($categoryCard['quiz_count'] ?? 0); ?> online quizzes · <?php echo (int) ($categoryCard['question_count'] ?? 0); ?> practice questions</p>
                            </div>
                            <span><?php echo (int) round((float) ($categoryCard['average_timer'] ?? 0)); ?> min avg</span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="public-section public-feature-band" id="about">
                <div class="public-feature">
                    <i class="fas fa-stopwatch"></i>
                    <strong>Timed quiz practice</strong>
                    <span>Build speed with online quiz timers and clear free mock test flow.</span>
                </div>
                <div class="public-feature">
                    <i class="fas fa-ranking-star"></i>
                    <strong>Quiz rank tracking</strong>
                    <span>Compare progress and keep improving through leaderboard context.</span>
                </div>
                <div class="public-feature">
                    <i class="fas fa-certificate"></i>
                    <strong>Certificates</strong>
                    <span>Celebrate strong results with generated achievement certificates.</span>
                </div>
            </section>

            <section class="public-section">
                <div class="public-section-head">
                    <div>
                        <span class="app-panel-kicker">Latest quiz categories</span>
                        <h2>Fresh quiz paths for better practice depth</h2>
                    </div>
                </div>

                <div class="public-category-grid">
                    <?php foreach (array_slice($categoryCards, 0, 4) as $categoryCard): ?>
                        <?php $categoryName = (string) ($categoryCard['category'] ?? 'Quiz'); ?>
                        <article class="public-category-card">
                            <div class="public-category-icon"><i class="fas fa-layer-group"></i></div>
                            <div>
                                <h3><?php echo htmlspecialchars($categoryName); ?> practice</h3>
                                <p>Explore <?php echo htmlspecialchars($categoryName); ?> online quiz preparation with timed mock tests and MCQ practice.</p>
                            </div>
                            <a href="<?php echo htmlspecialchars(seoCategoryUrl($categoryName)); ?>" class="app-button app-button-ghost">Open</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="public-section">
                <div class="public-section-head">
                    <div>
                        <span class="app-panel-kicker">Latest quizzes</span>
                        <h2>Recently added online quiz practice</h2>
                    </div>
                </div>

                <div class="public-category-grid">
                    <?php foreach ($latestQuizzes as $latestQuiz): ?>
                        <article class="public-category-card">
                            <div class="public-category-icon"><i class="fas fa-book-open"></i></div>
                            <div>
                                <h3><?php echo htmlspecialchars((string) ($latestQuiz['title'] ?? 'Quiz')); ?></h3>
                                <p><?php echo htmlspecialchars(seoBuildDescription((string) ($latestQuiz['title'] ?? 'Quiz'), (string) ($latestQuiz['category'] ?? ''), (string) ($latestQuiz['description'] ?? ''))); ?></p>
                            </div>
                            <a href="<?php echo htmlspecialchars(seoQuizUrl($latestQuiz)); ?>" class="app-button app-button-ghost">View</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="public-section">
                <div class="public-section-head">
                    <div>
                        <span class="app-panel-kicker">Popular searches</span>
                        <h2>High-intent quiz preparation topics</h2>
                    </div>
                </div>

                <div class="public-category-grid">
                    <?php foreach ($popularSearches as $popularSearch): ?>
                        <article class="public-category-card">
                            <div class="public-category-icon"><i class="fas fa-magnifying-glass"></i></div>
                            <div>
                                <h3><?php echo htmlspecialchars($popularSearch); ?></h3>
                                <p>Use Quiz Pro for <?php echo htmlspecialchars($popularSearch); ?> with focused online quizzes, timed attempts, and score tracking.</p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="public-section" id="faq">
                <div class="public-section-head">
                    <div>
                        <span class="app-panel-kicker">Quiz Pro FAQ</span>
                        <h2>Online quiz platform questions</h2>
                    </div>
                </div>

                <div class="public-category-grid">
                    <?php foreach ($faqItems as $faqItem): ?>
                        <article class="public-category-card">
                            <div class="public-category-icon"><i class="fas fa-circle-question"></i></div>
                            <div>
                                <h3><?php echo htmlspecialchars($faqItem['question']); ?></h3>
                                <p><?php echo htmlspecialchars($faqItem['answer']); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

<?php include 'includes/footer.php'; ?>
