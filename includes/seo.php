<?php

const QUIZPRO_SITE_BASE_URL = 'https://quizpro.free.nf';
const QUIZPRO_SITE_NAME = 'Quiz Pro';
const QUIZPRO_LOGO_URL = QUIZPRO_SITE_BASE_URL . '/assets/images/quizPro.png';

function seoSlug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('~[^a-z0-9]+~', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'quiz';
}

function seoUrl(string $path = ''): string
{
    $path = trim($path);
    if ($path === '') {
        return QUIZPRO_SITE_BASE_URL . '/';
    }

    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    return QUIZPRO_SITE_BASE_URL . '/' . ltrim($path, '/');
}

function seoCategoryUrl(string $category): string
{
    return seoUrl('category/' . seoSlug($category));
}

function seoQuizUrl(array $quiz): string
{
    $category = (string) ($quiz['category'] ?? 'quiz');
    $title = (string) ($quiz['title'] ?? 'quiz');
    return seoUrl('quiz/' . seoSlug($category) . '/' . seoSlug($title));
}

function seoBuildDescription(string $title, string $category = '', string $description = ''): string
{
    $base = trim($description);
    if ($base === '') {
        $base = trim($title . ' online quiz practice');
    }

    $keywordTail = trim($category) !== ''
        ? ' Practice ' . $category . ' MCQs, mock tests, timed quiz attempts, and certificate-ready learning on Quiz Pro.'
        : ' Practice online quizzes, mock tests, timed attempts, and certificate-ready learning on Quiz Pro.';

    return substr($base . $keywordTail, 0, 158);
}

function seoMeta(array $overrides = []): array
{
    $title = (string) ($overrides['title'] ?? 'Quiz Pro - Free Online Quiz Platform');
    $description = (string) ($overrides['description'] ?? 'Quiz Pro is a free online quiz platform for programming quiz practice, GK quiz preparation, science quiz learning, aptitude tests, and mock tests.');
    $canonical = (string) ($overrides['canonical'] ?? seoUrl());
    $image = (string) ($overrides['image'] ?? QUIZPRO_LOGO_URL);
    $robots = (string) ($overrides['robots'] ?? 'index,follow,max-image-preview:large');
    $type = (string) ($overrides['type'] ?? 'website');

    return [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'image' => $image,
        'robots' => $robots,
        'type' => $type,
        'keywords' => (string) ($overrides['keywords'] ?? 'online quiz, programming quiz, GK quiz, science quiz, aptitude test, free mock test'),
        'schema' => $overrides['schema'] ?? [],
        'prev' => $overrides['prev'] ?? null,
        'next' => $overrides['next'] ?? null,
    ];
}

function seoOrganizationSchema(): array
{
    return [
        '@type' => 'Organization',
        '@id' => seoUrl('#organization'),
        'name' => QUIZPRO_SITE_NAME,
        'url' => seoUrl(),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => QUIZPRO_LOGO_URL,
            'width' => 1200,
            'height' => 630,
        ],
    ];
}

function seoWebsiteSchema(): array
{
    return [
        '@type' => 'WebSite',
        '@id' => seoUrl('#website'),
        'name' => QUIZPRO_SITE_NAME,
        'url' => seoUrl(),
        'publisher' => ['@id' => seoUrl('#organization')],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => seoUrl('quiz.php?search={search_term_string}'),
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function seoQuizSchema(array $quiz): array
{
    $title = (string) ($quiz['title'] ?? 'Quiz');
    $category = (string) ($quiz['category'] ?? 'Quiz');

    return [
        '@type' => 'Course',
        '@id' => seoQuizUrl($quiz) . '#course',
        'name' => $title,
        'description' => seoBuildDescription($title, $category, (string) ($quiz['description'] ?? '')),
        'url' => seoQuizUrl($quiz),
        'provider' => ['@id' => seoUrl('#organization')],
        'educationalLevel' => 'Beginner to intermediate',
        'teaches' => $category . ' quiz preparation',
        'timeRequired' => 'PT' . max(1, (int) ($quiz['timer_minutes'] ?? 10)) . 'M',
    ];
}

function seoCategorySchema(string $category, array $quizzes): array
{
    return [
        '@type' => 'ItemList',
        '@id' => seoCategoryUrl($category) . '#itemlist',
        'name' => $category . ' quizzes',
        'url' => seoCategoryUrl($category),
        'itemListElement' => array_values(array_map(static function (array $quiz, int $index): array {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) ($quiz['title'] ?? 'Quiz'),
                'url' => seoQuizUrl($quiz),
            ];
        }, $quizzes, array_keys($quizzes))),
    ];
}

function seoProgramSchema(): array
{
    return [
        '@type' => 'EducationalOccupationalProgram',
        '@id' => seoUrl('#quiz-preparation-program'),
        'name' => 'Quiz Pro online quiz preparation',
        'description' => 'Programming quiz preparation, aptitude mock tests, GK learning, science MCQs, and interview preparation quizzes.',
        'provider' => ['@id' => seoUrl('#organization')],
        'programType' => 'Online quiz preparation',
        'url' => seoUrl(),
    ];
}

function seoRenderHead(array $meta): string
{
    $schemaGraph = array_values(array_filter(array_merge([
        seoOrganizationSchema(),
        seoWebsiteSchema(),
        seoProgramSchema(),
    ], $meta['schema'] ?? [])));

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => $schemaGraph,
    ];

    ob_start();
    ?>
    <!-- SEO: canonical, robots, social sharing, and structured data for production domain. -->
    <meta name="description" content="<?php echo htmlspecialchars((string) $meta['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars((string) $meta['keywords']); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars((string) $meta['robots']); ?>">
    <meta name="googlebot" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?php echo htmlspecialchars((string) $meta['canonical']); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(seoUrl()); ?>">
    <?php if (!empty($meta['prev'])): ?>
        <link rel="prev" href="<?php echo htmlspecialchars((string) $meta['prev']); ?>">
    <?php endif; ?>
    <?php if (!empty($meta['next'])): ?>
        <link rel="next" href="<?php echo htmlspecialchars((string) $meta['next']); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?php echo htmlspecialchars((string) $meta['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars((string) $meta['description']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars((string) $meta['image']); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?php echo htmlspecialchars((string) $meta['canonical']); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars((string) $meta['type']); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars(QUIZPRO_SITE_NAME); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars((string) $meta['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars((string) $meta['description']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars((string) $meta['image']); ?>">
    <script type="application/ld+json"><?php echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php
    return trim((string) ob_get_clean());
}
