<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Quiz Pro';
$pageKey = $pageKey ?? '';
$pageBodyClass = trim($pageBodyClass ?? '');
$pathPrefix = trim((string) ($pathPrefix ?? ''));
$pathPrefix = $pathPrefix === '' ? '' : rtrim(str_replace('\\', '/', $pathPrefix), '/') . '/';
$isAdminView = isset($isAdminView) ? (bool) $isAdminView : (function_exists('isAdmin') ? isAdmin() : false);
$homeLink = $homeLink ?? ($isAdminView ? 'dashboard_admin.php' : 'dashboard_user.php');
$leaderboardLink = $leaderboardLink ?? 'admin/view_leaderboard.php';
$logoutLink = $logoutLink ?? 'logout.php';
$profileLink = $profileLink ?? (function_exists('getProfilePagePath') ? getProfilePagePath($isAdminView) : ($isAdminView ? 'admin_profile.php' : 'user_profile.php'));
$changePasswordLink = $changePasswordLink ?? (function_exists('getChangePasswordPagePath') ? getChangePasswordPagePath($isAdminView) : ($isAdminView ? 'admin_change_password.php' : 'user_change_password.php'));
$headerContext = $headerContext ?? ($isAdminView ? 'Admin workspace' : 'User workspace');
$pageFooterSummary = $pageFooterSummary ?? 'A sharper interface for quizzes, certificates, collaboration, and platform flow.';
$headAssets = $headAssets ?? '';
$headAssets = is_array($headAssets) ? implode("\n", $headAssets) : (string) $headAssets;
$usernameRaw = (string) ($_SESSION['username'] ?? ($isAdminView ? 'Admin' : 'User'));
$profileImagePath = '';
$notificationCountProvided = isset($notificationCount);
$headerRankProvided = isset($headerRank);
$notificationCount = $notificationCountProvided ? (int) $notificationCount : 0;
$headerRank = $headerRankProvided ? max(1, (int) $headerRank) : 1;
$resolveAppPath = static function (string $path) use ($pathPrefix): string {
    if ($path === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#)~i', $path)) {
        return $path;
    }

    return $pathPrefix . ltrim($path, '/');
};
$resolvedHomeLink = $resolveAppPath($homeLink);
$resolvedLogoutLink = $resolveAppPath($logoutLink);
$resolvedProfileLink = $resolveAppPath($profileLink);

if (isset($pdo) && !empty($_SESSION['user_id'])) {
    try {
        if (function_exists('getUserById')) {
            $currentUser = getUserById($pdo, (int) $_SESSION['user_id']);
            if ($currentUser) {
                $usernameRaw = trim((string) ($currentUser['username'] ?? '')) !== '' ? (string) $currentUser['username'] : $usernameRaw;
                $profileImagePath = trim((string) ($currentUser['profile_image'] ?? ''));
                if (function_exists('syncSessionUser')) {
                    syncSessionUser($currentUser);
                }
            }
        }

        if ($isAdminView) {
            if (!$notificationCountProvided) {
                $notificationCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
            }
        } else {
            if (!$headerRankProvided) {
                $rankStmt = $pdo->prepare("SELECT COUNT(*) + 1
                                          FROM (
                                              SELECT ua.user_id, SUM(ua.score) AS total_score
                                              FROM user_attempts ua
                                              GROUP BY ua.user_id
                                          ) ranked_scores
                                          WHERE total_score > (
                                              SELECT COALESCE(SUM(score), 0)
                                              FROM user_attempts
                                              WHERE user_id = ?
                                          )");
                $rankStmt->execute([$_SESSION['user_id']]);
                $headerRank = max(1, (int) $rankStmt->fetchColumn());
            }

            if (!$notificationCountProvided) {
                $notifyStmt = $pdo->prepare("SELECT COUNT(*)
                                            FROM user_attempts ua
                                            JOIN quizzes q ON ua.quiz_id = q.id
                                            LEFT JOIN certificates c ON ua.id = c.attempt_id
                                            WHERE ua.user_id = ?
                                            AND ua.score >= (q.total_marks * 0.7)
                                            AND c.id IS NULL");
                $notifyStmt->execute([$_SESSION['user_id']]);
                $notificationCount = (int) $notifyStmt->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        if (!$notificationCountProvided) {
            $notificationCount = 0;
        }
        if (!$headerRankProvided) {
            $headerRank = 1;
        }
    }
}

$username = htmlspecialchars($usernameRaw);
$profileInitial = strtoupper(substr($usernameRaw, 0, 1));
$resolvedProfileImagePath = $profileImagePath !== '' ? $resolveAppPath($profileImagePath) : '';

$navItems = [
    [
        'key' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => 'fas fa-house',
        'href' => $homeLink,
        'show' => true,
    ],
    [
        'key' => 'quiz',
        'label' => 'Quiz',
        'icon' => 'fas fa-layer-group',
        'href' => 'quiz.php',
        'show' => true,
    ],
    [
        'key' => 'join_quiz',
        'label' => 'Join Quiz',
        'icon' => 'fas fa-users',
        'href' => 'join_quiz.php',
        'show' => !$isAdminView,
    ],
    [
        'key' => 'create_quiz',
        'label' => 'Create Quiz',
        'icon' => 'fas fa-plus-circle',
        'href' => 'create_quiz.php',
        'show' => $isAdminView,
    ],
    [
        'key' => 'certificates',
        'label' => 'Certificates',
        'icon' => 'fas fa-certificate',
        'href' => 'certificates.php',
        'show' => !$isAdminView,
    ],
    [
        'key' => 'leaderboard',
        'label' => 'Leaderboard',
        'icon' => 'fas fa-trophy',
        'href' => $leaderboardLink,
        'show' => $isAdminView,
    ],
    [
        'key' => 'contact',
        'label' => 'Contact',
        'icon' => 'fas fa-envelope-open-text',
        'href' => 'contact.php',
        'show' => true,
    ],
    [
        'key' => 'profile',
        'label' => 'Profile',
        'icon' => 'fas fa-id-badge',
        'href' => $profileLink,
        'show' => true,
        'footer' => false,
    ],
    [
        'key' => 'change_password',
        'label' => 'Change Password',
        'icon' => 'fas fa-key',
        'href' => $changePasswordLink,
        'show' => true,
        'footer' => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($resolveAppPath('assets/css/style.css')); ?>">
    <?php if ($headAssets !== ''): ?>
        <?php echo $headAssets; ?>
    <?php endif; ?>
    <style>
        .dismiss-animation {
            transition: opacity 0.8s ease, transform 0.8s ease !important;
            opacity: 0 !important;
            transform: translateY(-20px) !important;
        }
    </style>
</head>
<body class="app-shell-page <?php echo $isAdminView ? 'app-shell-admin' : 'app-shell-user'; ?> <?php echo htmlspecialchars($pageBodyClass); ?>">
    <div class="app-shell">
        <div class="app-nav-drawer-overlay" data-app-sidebar-overlay></div>

        <aside class="app-nav-drawer" id="appSidebarDrawer" aria-hidden="true">
            <div class="app-nav-drawer-header">
                <a href="<?php echo htmlspecialchars($resolvedHomeLink); ?>" class="app-topbar-brand">
                    <span class="app-topbar-brand-mark"><i class="fas fa-bolt"></i></span>
                    <span class="app-topbar-brand-text">
                        <strong>Quiz Pro</strong>
                        <small><?php echo htmlspecialchars($headerContext); ?></small>
                    </span>
                </a>
                <button type="button" class="app-nav-drawer-close" data-app-sidebar-close aria-label="Close sidebar">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <nav class="app-nav-drawer-menu">
                <?php foreach ($navItems as $navItem): ?>
                    <?php if (!$navItem['show']) continue; ?>
                    <a
                        href="<?php echo htmlspecialchars($resolveAppPath($navItem['href'])); ?>"
                        class="app-nav-drawer-link <?php echo $pageKey === $navItem['key'] ? 'active' : ''; ?>"
                    >
                        <i class="<?php echo htmlspecialchars($navItem['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($navItem['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="app-nav-drawer-footer">
                <div class="app-nav-drawer-user">
                    <?php if ($resolvedProfileImagePath !== ''): ?>
                        <img src="<?php echo htmlspecialchars($resolvedProfileImagePath); ?>" alt="<?php echo $username; ?>" class="app-topbar-avatar app-topbar-avatar-image">
                    <?php else: ?>
                        <span class="app-topbar-avatar"><?php echo htmlspecialchars($profileInitial); ?></span>
                    <?php endif; ?>
                    <div class="app-nav-drawer-user-meta">
                        <strong><?php echo $username; ?></strong>
                        <span><?php echo $isAdminView ? 'Administrator' : 'Learner'; ?></span>
                    </div>
                </div>
                <a href="<?php echo htmlspecialchars($resolvedLogoutLink); ?>" class="app-nav-drawer-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <header class="app-shell-topbar">
            <div class="app-topbar-left">
                <button
                    type="button"
                    class="app-topbar-toggle"
                    data-app-sidebar-toggle
                    aria-expanded="false"
                    aria-controls="appSidebarDrawer"
                    aria-label="Open sidebar"
                >
                    <i class="fas fa-bars"></i>
                </button>

                <a href="<?php echo htmlspecialchars($resolvedHomeLink); ?>" class="app-topbar-brand">
                    <span class="app-topbar-brand-mark"><i class="fas fa-bolt"></i></span>
                    <span class="app-topbar-brand-text">
                        <strong>Quiz Pro</strong>
                        <small><?php echo htmlspecialchars($headerContext); ?></small>
                    </span>
                </a>
            </div>

            <div class="app-topbar-right">
                <div class="app-topbar-rank">
                    <i class="fas fa-ranking-star"></i>
                    <span>Rank #<?php echo $headerRank; ?></span>
                </div>

                <button type="button" class="app-topbar-icon-btn" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="app-topbar-badge"><?php echo min($notificationCount, 99); ?></span>
                    <?php endif; ?>
                </button>

                <a href="<?php echo htmlspecialchars($resolvedProfileLink); ?>" class="app-topbar-profile" aria-label="Profile">
                    <?php if ($resolvedProfileImagePath !== ''): ?>
                        <img src="<?php echo htmlspecialchars($resolvedProfileImagePath); ?>" alt="<?php echo $username; ?>" class="app-topbar-avatar app-topbar-avatar-image">
                    <?php else: ?>
                        <span class="app-topbar-avatar"><?php echo htmlspecialchars($profileInitial); ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <main class="app-main">
