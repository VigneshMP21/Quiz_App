<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isAdmin()) {
    redirect('dashboard_admin.php');
}

// Join Quiz Logic (Process POST before headers if needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_by_code') {
    $code = strtoupper(trim($_POST['quiz_code'] ?? ''));
    if ($code !== '') {
        $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE unique_code = ?");
        $stmt->execute([$code]);
        $quiz = $stmt->fetch();
        if ($quiz) {
            redirect("quiz.php?quiz_id=" . $quiz['id']);
        } else {
            redirect("dashboard_user.php", "Invalid quiz code. Please check and try again.", "error");
        }
    } else {
        redirect("dashboard_user.php", "Please enter a quiz code.", "error");
    }
}

// Get user's recent quiz attempts
$stmt = $pdo->prepare("SELECT q.title, ua.score, ua.completed_at, q.total_marks, ua.id as attempt_id
                      FROM user_attempts ua 
                      JOIN quizzes q ON ua.quiz_id = q.id 
                      WHERE ua.user_id = ? 
                      ORDER BY ua.completed_at DESC 
                      LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentAttempts = $stmt->fetchAll();

// Get all quiz categories from the database
$categories = $pdo->query("SELECT DISTINCT category FROM quizzes ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get user's certificates
$stmt = $pdo->prepare("SELECT c.id, q.title, c.downloaded_at, c.certificate_path 
                      FROM certificates c
                      JOIN user_attempts ua ON c.attempt_id = ua.id
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      ORDER BY c.downloaded_at DESC
                      LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll();

// Get user stats
$stmt = $pdo->prepare("SELECT 
                      COUNT(ua.id) as total_attempts,
                      COUNT(c.id) as total_certificates,
                      COALESCE(AVG(CASE WHEN q.total_marks > 0 THEN (ua.score / q.total_marks) * 100 END), 0) as average_score,
                      COALESCE(MAX(CASE WHEN q.total_marks > 0 THEN (ua.score / q.total_marks) * 100 END), 0) as best_score,
                      COALESCE(SUM(CASE WHEN ua.score >= (q.total_marks * 0.7) THEN 1 ELSE 0 END), 0) as passed_attempts,
                      COALESCE(SUM(ua.score), 0) as total_points
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      LEFT JOIN certificates c ON ua.id = c.attempt_id
                      WHERE ua.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userStats = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT COUNT(*) 
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      LEFT JOIN certificates c ON ua.id = c.attempt_id
                      WHERE ua.user_id = ?
                      AND ua.score >= (q.total_marks * 0.7)
                      AND c.id IS NULL");
$stmt->execute([$_SESSION['user_id']]);
$pendingCertificates = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) 
                      FROM user_attempts
                      WHERE user_id = ?
                      AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$stmt->execute([$_SESSION['user_id']]);
$currentMonthAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) + 1
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
$stmt->execute([$_SESSION['user_id']]);
$leaderboardRank = (int) $stmt->fetchColumn();

// Get total available quizzes
$stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
$totalQuizzes = $stmt->fetchColumn();

// Get leaderboard
$stmt = $pdo->prepare("SELECT u.username, SUM(ua.score) as total_score
                      FROM user_attempts ua
                      JOIN users u ON ua.user_id = u.id
                      GROUP BY ua.user_id
                      ORDER BY total_score DESC
                      LIMIT 5");
$stmt->execute();
$leaderboard = $stmt->fetchAll();

// Get recent activity
$stmt = $pdo->prepare("SELECT q.title, ua.score, ua.completed_at, q.total_marks
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      ORDER BY ua.completed_at DESC
                      LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$recentActivity = $stmt->fetchAll();

// Check certificate existence for each attempt
$attemptCertMap = [];
foreach ($recentAttempts as $attempt) {
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE attempt_id = ?");
    $stmt->execute([$attempt['attempt_id']]);
    $attemptCertMap[$attempt['attempt_id']] = $stmt->fetch();
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$totalAttempts = (int) ($userStats['total_attempts'] ?? 0);
$totalCertificates = (int) ($userStats['total_certificates'] ?? 0);
$averageScore = round((float) ($userStats['average_score'] ?? 0), 1);
$bestScore = round((float) ($userStats['best_score'] ?? 0));
$passedAttempts = (int) ($userStats['passed_attempts'] ?? 0);
$totalPoints = (int) ($userStats['total_points'] ?? 0);
$categoryCount = count($categories);
$userPassRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100) : 0;
$latestActivity = $recentActivity[0] ?? null;
$latestActivityScore = 0;
if ($latestActivity && !empty($latestActivity['total_marks'])) {
    $latestActivityScore = round(($latestActivity['score'] / $latestActivity['total_marks']) * 100);
}

$heroMessage = 'Start a fresh quiz and unlock your next milestone.';
if ($pendingCertificates > 0) {
    $heroMessage = 'You have ' . $pendingCertificates . ' certificate' . ($pendingCertificates === 1 ? '' : 's') . ' waiting to be claimed.';
} elseif ($totalAttempts > 0) {
    $heroMessage = 'Your recent performance is building steady momentum across categories.';
}

// Prepare data for Performance Trend Chart
$chartLabels = [];
$chartData = [];
$chronologicalActivity = array_reverse($recentActivity);
foreach ($chronologicalActivity as $act) {
    $chartLabels[] = date('M d', strtotime($act['completed_at']));
    $chartData[] = $act['total_marks'] > 0 ? round(($act['score'] / $act['total_marks']) * 100) : 0;
}

// Prepare data for Category Mastery Chart
$stmt = $pdo->prepare("SELECT q.category, COUNT(ua.id) as attempts, AVG(CASE WHEN q.total_marks > 0 THEN (ua.score / q.total_marks) * 100 END) as avg_score
                      FROM user_attempts ua
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      GROUP BY q.category");
$stmt->execute([$_SESSION['user_id']]);
$categoryStats = $stmt->fetchAll();

$catLabels = [];
$catData = [];
foreach ($categoryStats as $stat) {
    $catLabels[] = $stat['category'] ?: 'General';
    $catData[] = round($stat['avg_score']);
}

// Prepare data for Pass/Fail Ratio Chart
$failedAttempts = max(0, $totalAttempts - $passedAttempts);
$passFailData = [$passedAttempts, $failedAttempts];

// Prepare data for Score Distribution Chart
$stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN (ua.score / q.total_marks) * 100 < 50 THEN 1 ELSE 0 END) as range_0_49,
    SUM(CASE WHEN (ua.score / q.total_marks) * 100 >= 50 AND (ua.score / q.total_marks) * 100 < 70 THEN 1 ELSE 0 END) as range_50_69,
    SUM(CASE WHEN (ua.score / q.total_marks) * 100 >= 70 AND (ua.score / q.total_marks) * 100 < 90 THEN 1 ELSE 0 END) as range_70_89,
    SUM(CASE WHEN (ua.score / q.total_marks) * 100 >= 90 THEN 1 ELSE 0 END) as range_90_100
    FROM user_attempts ua
    JOIN quizzes q ON ua.quiz_id = q.id
    WHERE ua.user_id = ? AND q.total_marks > 0");
$stmt->execute([$_SESSION['user_id']]);
$distribution = $stmt->fetch();
$scoreDistData = [
    (int) ($distribution['range_0_49'] ?? 0),
    (int) ($distribution['range_50_69'] ?? 0),
    (int) ($distribution['range_70_89'] ?? 0),
    (int) ($distribution['range_90_100'] ?? 0)
];

$isAdminView = false;
$homeLink = 'dashboard_user.php';
$logoutLink = 'logout.php';
$pageTitle = 'Dashboard - QuizPro';
$pageKey = 'dashboard';
$pageBodyClass = 'dash-body dash-user-page page-dashboard page-dashboard-user';
$headerContext = 'Learner cockpit';
$pageFooterSummary = 'Your personal performance cockpit for quiz attempts, certificates, activity, and leaderboard momentum.';
$headerRank = $leaderboardRank;
$notificationCount = $pendingCertificates;
$headAssets = <<<'HTML'
<style>
    /* Fix alignment and prevent ugly word wrapping in hero panel mini stats */
    .page-dashboard-user .dash-hero-panel .dash-mini-stat {
        padding: 12px 8px;
    }

    .page-dashboard-user .dash-hero-panel .dash-mini-stat-label {
        font-size: 8.5px;
        letter-spacing: 0.04em;
        line-height: 1.3;
        word-break: keep-all;
        overflow-wrap: normal;
        white-space: normal;
    }

    .page-dashboard-user .dash-hero-panel .dash-mini-stat-value {
        font-size: 22px;
        margin-top: 6px;
    }

    .page-dashboard-user .dash-chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .page-dashboard-user .dash-chart-grid--spaced {
        margin-top: 1.5rem;
    }

    .page-dashboard-user .dash-chart-tile {
        margin: 0;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .page-dashboard-user .dash-chart-body {
        padding-top: 1rem;
        flex: 1;
    }

    .page-dashboard-user .dash-chart-canvas-wrap {
        position: relative;
        height: 220px;
        width: 100%;
    }

    .page-dashboard-user .dash-chart-empty {
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        margin: 0;
        border: none;
        background: transparent;
    }

    .page-dashboard-user .dash-chart-empty i {
        font-size: 2rem;
        color: var(--dash-text-muted);
        margin-bottom: 0.5rem;
    }

    .page-dashboard-user .dash-chart-empty p {
        margin: 0;
    }

    .page-dashboard-user .table-responsive {
        overflow-x: auto;
        overflow-y: hidden;
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.14);
        background: rgba(255, 255, 255, 0.78);
    }

    .page-dashboard-user .dash-table {
        min-width: 680px;
    }

    .page-dashboard-user .dash-category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
    }

    .page-dashboard-user .dash-category-card {
        position: relative;
        overflow: hidden;
        min-height: 100%;
        border-radius: 22px;
        padding: 1.35rem;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0;
        text-decoration: none;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid rgba(203, 213, 225, 0.88);
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    }

    .page-dashboard-user .dash-category-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #38bdf8 0%, #14b8a6 58%, #f59e0b 100%);
    }

    .page-dashboard-user .dash-category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 22px 38px rgba(14, 116, 144, 0.12);
        border-color: rgba(125, 211, 252, 0.9);
    }

    .page-dashboard-user .dash-category-icon-wrapper {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1.25rem;
        transition: transform 0.3s ease, background 0.3s ease, color 0.3s ease;
    }

    .page-dashboard-user .dash-category-card:hover .dash-category-icon-wrapper {
        background: linear-gradient(135deg, #2563eb 0%, #14b8a6 100%);
        color: #ffffff;
        transform: translateY(-2px) scale(1.04);
    }

    .page-dashboard-user .dash-category-name {
        width: 100%;
        margin-bottom: 1.1rem;
        color: #0f172a;
        font-size: 1.08rem;
        font-weight: 700;
        line-height: 1.35;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        overflow-wrap: anywhere;
    }

    .page-dashboard-user .dash-category-action {
        width: 100%;
        margin-top: auto;
        padding-top: 0.95rem;
        border-top: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .page-dashboard-user .dash-category-action i {
        transition: transform 0.3s ease;
    }

    .page-dashboard-user .dash-category-card:hover .dash-category-action {
        color: #0f766e;
    }

    .page-dashboard-user .dash-category-card:hover .dash-category-action i {
        transform: translateX(4px);
    }

    .page-dashboard-user .dash-cert-title,
    .page-dashboard-user .dash-activity-title,
    .page-dashboard-user .dash-lb-name {
        display: block;
        overflow-wrap: anywhere;
    }

    .page-dashboard-user .dash-activity-content,
    .page-dashboard-user .dash-cert-info {
        min-width: 0;
    }

    .page-dashboard-user .app-topbar-rank {
        display: none;
    }

    .page-dashboard-user .app-topbar-right {
        gap: 16px;
    }

    .page-dashboard-user .app-topbar-icon-btn,
    .page-dashboard-user .app-topbar-profile {
        width: 58px;
        height: 58px;
        border-radius: 18px;
    }

    .page-dashboard-user .app-topbar-icon-btn i {
        font-size: 18px;
    }

    .page-dashboard-user .app-topbar-badge {
        top: 8px;
        right: 8px;
        min-width: 22px;
        height: 22px;
    }

    .page-dashboard-user .app-topbar-avatar {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        font-size: 15px;
    }

    @media (max-width: 480px) {
        .page-dashboard-user .app-shell {
            width: min(100% - 12px, 1480px);
            margin: 12px auto;
        }

        .page-dashboard-user .app-shell-topbar {
            top: 8px;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px;
            margin-bottom: 18px;
            border-radius: 22px;
        }

        .page-dashboard-user .app-topbar-left {
            width: auto;
            flex: 1;
            min-width: 0;
            gap: 8px;
            align-items: center;
        }

        .page-dashboard-user .app-topbar-brand {
            flex: 1;
            min-width: 0;
            gap: 10px;
        }

        .page-dashboard-user .app-topbar-brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            font-size: 15px;
        }

        .page-dashboard-user .app-topbar-brand-text strong {
            max-width: 92px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 15px;
        }

        .page-dashboard-user .app-topbar-brand-text small {
            display: none;
        }

        .page-dashboard-user .app-topbar-right {
            width: auto;
            flex-shrink: 0;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .page-dashboard-user .app-topbar-toggle {
            width: 40px;
            height: 40px;
            border-radius: 14px;
        }

        .page-dashboard-user .app-topbar-icon-btn,
        .page-dashboard-user .app-topbar-profile {
            width: 48px;
            height: 48px;
            border-radius: 16px;
        }

        .page-dashboard-user .app-topbar-icon-btn i {
            font-size: 19px;
        }

        .page-dashboard-user .app-topbar-badge {
            top: 9px;
            right: 9px;
        }

        .page-dashboard-user .app-topbar-avatar {
            width: 32px;
            height: 32px;
            border-radius: 12px;
        }

        .page-dashboard-user .app-main {
            gap: 18px;
        }

        .page-dashboard-user .dash-content {
            padding: 18px 10px 28px;
        }

        .page-dashboard-user .dash-hero-shell {
            min-height: auto;
            gap: 18px;
            padding: 20px 16px;
            border-radius: 24px;
        }

        .page-dashboard-user .dash-hero-shell::after {
            right: -20%;
            bottom: -18%;
            width: 180px;
            height: 180px;
        }

        .page-dashboard-user .dash-hero-kicker {
            margin-bottom: 14px;
            padding: 6px 10px;
            font-size: 10px;
            letter-spacing: 0.14em;
        }

        .page-dashboard-user .dash-hero-title {
            font-size: clamp(1.8rem, 9vw, 2.2rem);
            line-height: 1.06;
        }

        .page-dashboard-user .dash-hero-sub {
            font-size: 13px;
            line-height: 1.65;
        }

        .page-dashboard-user .dash-hero-pills {
            gap: 10px;
            margin-top: 18px;
        }

        .page-dashboard-user .dash-hero-pill {
            width: 100%;
            justify-content: flex-start;
            padding: 12px 14px;
            border-radius: 16px;
        }

        .page-dashboard-user .dash-hero-actions {
            gap: 10px;
            margin-top: 18px;
        }

        .page-dashboard-user .dash-btn {
            width: 100%;
            justify-content: center;
            padding: 12px 16px;
        }

        .page-dashboard-user .dash-hero-panel {
            gap: 14px;
            padding: 16px;
            border-radius: 20px;
            animation: none;
        }

        .page-dashboard-user .dash-hero-panel-head {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .page-dashboard-user .dash-ring-card {
            padding: 16px;
        }

        .page-dashboard-user .dash-ring-value {
            width: 108px;
            height: 108px;
            margin: 0 auto 4px;
            font-size: 28px;
        }

        .page-dashboard-user .dash-ring-label,
        .page-dashboard-user .dash-ring-card p {
            text-align: center;
        }

        .page-dashboard-user .dash-mini-stat-grid,
        .page-dashboard-user .dash-stats-grid,
        .page-dashboard-user .dash-signal-grid,
        .page-dashboard-user .dash-quick-grid,
        .page-dashboard-user .dash-category-grid {
            grid-template-columns: 1fr;
        }

        .page-dashboard-user .dash-mini-stat {
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .page-dashboard-user .dash-mini-stat-value {
            margin-top: 0;
            font-size: 24px;
            text-align: right;
        }

        .page-dashboard-user .dash-stats-grid {
            gap: 12px;
            margin-bottom: 20px;
        }

        .page-dashboard-user .dash-stat-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            padding: 16px;
            border-radius: 20px;
        }

        .page-dashboard-user .dash-stat-card-icon {
            width: 46px;
            height: 46px;
            margin-bottom: 0;
            border-radius: 14px;
        }

        .page-dashboard-user .stat-card-dash-value {
            margin-bottom: 2px;
            font-size: 26px;
        }

        .page-dashboard-user .dash-two-col {
            gap: 16px;
        }

        .page-dashboard-user .dash-card.glass {
            margin-bottom: 16px;
            padding: 16px;
            border-radius: 20px;
        }

        .page-dashboard-user .dash-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 14px;
        }

        .page-dashboard-user .dash-card-header h3 {
            font-size: 15px;
            line-height: 1.4;
        }

        .page-dashboard-user .dash-card-tag {
            align-self: flex-start;
        }

        .page-dashboard-user .dash-card-note,
        .page-dashboard-user .dash-stat-footnote {
            font-size: 12px;
            line-height: 1.6;
        }

        .page-dashboard-user .table-responsive {
            overflow: visible;
            border: none;
            background: transparent;
        }

        .page-dashboard-user .dash-table,
        .page-dashboard-user .dash-table thead,
        .page-dashboard-user .dash-table tbody,
        .page-dashboard-user .dash-table tr,
        .page-dashboard-user .dash-table td {
            display: block;
        }

        .page-dashboard-user .dash-table {
            min-width: 0;
        }

        .page-dashboard-user .dash-table thead {
            display: none;
        }

        .page-dashboard-user .dash-table tbody {
            display: grid;
            gap: 12px;
        }

        .page-dashboard-user .dash-table tbody tr {
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.98));
            box-shadow: 0 14px 24px rgba(15, 23, 42, 0.06);
        }

        .page-dashboard-user .dash-table td {
            padding: 0;
            border-bottom: none;
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            font-size: 13px;
        }

        .page-dashboard-user .dash-table td + td {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed rgba(148, 163, 184, 0.24);
        }

        .page-dashboard-user .dash-table td::before {
            content: attr(data-label);
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .page-dashboard-user .dash-table td[data-label="Quiz"] {
            align-items: start;
            font-weight: 600;
        }

        .page-dashboard-user .score-wrapper {
            width: 100%;
            display: grid;
            gap: 8px;
        }

        .page-dashboard-user .score-progress-bar {
            max-width: none;
            width: 100%;
        }

        .page-dashboard-user .dash-table td[data-label="Certificate"] .dash-btn-small,
        .page-dashboard-user .dash-table td[data-label="Certificate"] .score-badge {
            width: 100%;
            justify-content: center;
        }

        .page-dashboard-user .dash-chart-grid {
            gap: 12px;
            margin: 16px 0;
        }

        .page-dashboard-user .dash-chart-canvas-wrap {
            height: 200px;
        }

        .page-dashboard-user .dash-chart-body {
            padding-top: 0.75rem;
        }

        .page-dashboard-user .dash-category-card {
            padding: 16px;
            border-radius: 20px;
        }

        .page-dashboard-user .dash-category-icon-wrapper {
            width: 50px;
            height: 50px;
            margin-bottom: 1rem;
        }

        .page-dashboard-user .dash-category-name {
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .page-dashboard-user .dash-cert-card {
            gap: 12px;
            padding: 14px;
            align-items: flex-start;
            flex-wrap: wrap;
            border-radius: 18px;
        }

        .page-dashboard-user .dash-cert-info {
            flex: 1 1 calc(100% - 60px);
            margin-bottom: 0;
        }

        .page-dashboard-user .dash-cert-card .dash-btn-small {
            width: 100%;
            justify-content: center;
        }

        .page-dashboard-user .dash-signal-card {
            padding: 16px;
            border-radius: 18px;
        }

        .page-dashboard-user .dash-signal-value {
            font-size: 26px;
        }

        .page-dashboard-user .dash-activity-item {
            gap: 12px;
            padding: 14px 0;
        }

        .page-dashboard-user .dash-activity-meta {
            display: block;
            margin-top: 6px;
            line-height: 1.6;
        }

        .page-dashboard-user .dash-lb-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            padding: 14px;
            border-radius: 16px;
        }

        .page-dashboard-user .dash-quick-btn {
            min-height: 48px;
            border-radius: 16px;
        }

        .page-dashboard-user .app-modal-overlay {
            padding: 10px;
        }

        .page-dashboard-user .app-modal-card {
            width: min(calc(100vw - 20px), 400px);
            border-radius: 24px;
        }

        .page-dashboard-user .app-modal-head {
            padding: 20px 20px 18px;
        }

        .page-dashboard-user .app-modal-title {
            font-size: 18px;
        }

        .page-dashboard-user .app-modal-body {
            padding: 20px;
        }

        .page-dashboard-user .app-modal-actions {
            margin-top: 18px;
        }

        .page-dashboard-user .app-modal-actions .app-button {
            width: 100%;
        }
    }
</style>
HTML;

include 'includes/header.php';
?>
        <div class="dash-content">

            <?php
            ob_start();
            displayMessage();
            $flashContent = ob_get_clean();
            if ($flashContent) {
                echo '<div class="dash-fade-in" style="margin-bottom:18px">' . $flashContent . '</div>';
            }
            ?>

            <!-- Hero Section -->
            <section class="dash-hero dash-fade-in dash-hero-shell">
                <div class="dash-hero-copy">
                    <span class="dash-hero-kicker">Learning cockpit</span>
                    <h1 class="dash-hero-title">Welcome back, <span class="dash-hero-gradient"><?php echo $username; ?></span></h1>
                    <p class="dash-hero-sub">
                        You have collected <?php echo $totalPoints; ?> points across <?php echo $totalAttempts; ?> quiz attempts.
                        <?php echo htmlspecialchars($heroMessage); ?>
                    </p>
                    <div class="dash-hero-pills">
                        <span class="dash-hero-pill"><i class="fas fa-wave-square"></i> <?php echo $userPassRate; ?>% pass rate</span>
                        <span class="dash-hero-pill"><i class="fas fa-layer-group"></i> <?php echo $categoryCount; ?> active categories</span>
                        <span class="dash-hero-pill"><i class="fas fa-medal"></i> <?php echo $totalCertificates; ?> certificates earned</span>
                    </div>
                    <div class="dash-hero-actions">
                        <a href="quiz.php" class="dash-btn dash-btn-primary"><i class="fas fa-play"></i> Start a Quiz</a>
                        <a href="certificates.php" class="dash-btn dash-btn-outline" style="color: #4f46e5"><i class="fas fa-certificate"></i> Open Certificates</a>
                    </div>
                </div>
                <div class="dash-hero-panel">
                    <div class="dash-hero-panel-head">
                        <span>Performance snapshot</span>
                        <span class="dash-hero-panel-badge">Live</span>
                    </div>
                    <div class="dash-ring-card">
                        <span class="dash-ring-value" data-count="<?php echo $averageScore; ?>">0</span>
                        <span class="dash-ring-label">Average score</span>
                        <p>
                            <?php if ($latestActivity): ?>
                                    Latest finish: <?php echo $latestActivityScore; ?>% on <?php echo htmlspecialchars($latestActivity['title']); ?>
                            <?php else: ?>
                                    Complete your first quiz to unlock performance insights.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="dash-mini-stat-grid">
                        <article class="dash-mini-stat">
                            <span class="dash-mini-stat-label">Best run</span>
                            <strong class="dash-mini-stat-value" data-count="<?php echo $bestScore; ?>">0%</strong>
                        </article>
                        <article class="dash-mini-stat">
                            <span class="dash-mini-stat-label">Available quizzes</span>
                            <strong class="dash-mini-stat-value" data-count="<?php echo (int) $totalQuizzes; ?>">0</strong>
                        </article>
                        <article class="dash-mini-stat">
                            <span class="dash-mini-stat-label">Certificates pending</span>
                            <strong class="dash-mini-stat-value" data-count="<?php echo $pendingCertificates; ?>">0</strong>
                        </article>
                    </div>
                </div>
            </section>

        <!-- Stats Grid -->
        <div class="dash-stats-grid dash-stagger">
            <div class="dash-stat-card gradient-border-blue">
                <div class="dash-stat-card-icon"><i class="fas fa-pencil-alt"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $totalAttempts; ?>">0</span>
                    <span class="dash-stat-card-label">Quizzes Taken</span>
                    <span class="dash-stat-footnote"><?php echo $currentMonthAttempts; ?> completed this month</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-gold">
                <div class="dash-stat-card-icon"><i class="fas fa-certificate"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $totalCertificates; ?>">0</span>
                    <span class="dash-stat-card-label">Certificates Earned</span>
                    <span class="dash-stat-footnote"><?php echo $pendingCertificates; ?> waiting to be claimed</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-green">
                <div class="dash-stat-card-icon"><i class="fas fa-star"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $averageScore; ?>">0</span>
                    <span class="dash-stat-card-label">Average Score %</span>
                    <span class="dash-stat-footnote"><?php echo $passedAttempts; ?> strong finishes so far</span>
                </div>
            </div>
            <div class="dash-stat-card gradient-border-rose">
                <div class="dash-stat-card-icon"><i class="fas fa-bolt"></i></div>
                <div class="dash-stat-card-body">
                    <span class="stat-card-dash-value" data-count="<?php echo $totalPoints; ?>">0</span>
                    <span class="dash-stat-card-label">Total Points</span>
                    <span class="dash-stat-footnote">Across <?php echo $categoryCount; ?> different learning tracks</span>
                </div>
            </div>
        </div>

        <!-- Two-Column Layout -->
        <div class="dash-two-col dash-stagger">

            <!-- Left Column -->
            <div class="dash-col-left">

                <!-- Recent Quiz Attempts -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-history"></i> Recent Quiz Attempts</h3>
                        <span class="dash-card-tag">Last 5 results</span>
                    </div>
                    <div class="dash-card-body">
                        <?php if (count($recentAttempts) > 0): ?>
                            <div class="table-responsive">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>Quiz</th>
                                            <th>Score</th>
                                            <th>Date</th>
                                            <th>Certificate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAttempts as $attempt):
                                            $pct = $attempt['total_marks'] > 0 ? round(($attempt['score'] / $attempt['total_marks']) * 100, 1) : 0;
                                            if ($pct >= 70) {
                                                $level = 'high';
                                                $badgeLabel = 'High';
                                            } elseif ($pct >= 40) {
                                                $level = 'medium';
                                                $badgeLabel = 'Medium';
                                            } else {
                                                $level = 'low';
                                                $badgeLabel = 'Low';
                                            }
                                            $hasCert = isset($attemptCertMap[$attempt['attempt_id']]) && $attemptCertMap[$attempt['attempt_id']];
                                            ?>
                                            <tr>
                                                <td data-label="Quiz"><?php echo htmlspecialchars($attempt['title']); ?></td>
                                                <td data-label="Score">
                                                    <div class="score-wrapper">
                                                        <div class="score-progress-bar">
                                                            <div class="score-progress-fill <?php echo $level; ?>" style="width: <?php echo $pct; ?>%"></div>
                                                        </div>
                                                        <span class="score-badge <?php echo $level; ?>"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_marks']; ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Date"><?php echo date('M d, Y', strtotime($attempt['completed_at'])); ?></td>
                                                <td data-label="Certificate">
                                                    <?php if ($hasCert): ?>
                                                            <a href="certificates.php" class="dash-btn-small dash-btn-primary"><i class="fas fa-eye"></i> View</a>
                                                    <?php elseif ($pct >= 70): ?>
                                                            <a href="generate_certificate.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" class="dash-btn-small dash-btn-success"><i class="fas fa-download"></i> Get Certificate</a>
                                                    <?php else: ?>
                                                            <span class="score-badge low">Score too low</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-book-open"></i>
                                <p>No quiz attempts yet. <a href="quiz.php">Take your first quiz!</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-chart-grid dash-chart-grid--spaced">
                    <!-- Performance Trend Chart -->
                    <div class="dash-card glass dash-chart-tile">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-chart-line"></i> Performance Trend</h3>
                            <span class="dash-card-tag">Last 10 Quizzes</span>
                        </div>
                        <div class="dash-card-body dash-chart-body">
                            <div class="dash-chart-canvas-wrap">
                                <?php if (count($chartLabels) > 0): ?>
                                        <canvas id="performanceChart"></canvas>
                                <?php else: ?>
                                        <div class="dash-empty-state dash-chart-empty">
                                            <i class="fas fa-chart-line"></i>
                                            <p>Take a quiz to see trends.</p>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Category Mastery Chart -->
                    <div class="dash-card glass dash-chart-tile">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-chart-bar"></i> Category Mastery</h3>
                            <span class="dash-card-tag">Average Score %</span>
                        </div>
                        <div class="dash-card-body dash-chart-body">
                            <div class="dash-chart-canvas-wrap">
                                <?php if (count($catLabels) > 0): ?>
                                        <canvas id="categoryChart"></canvas>
                                <?php else: ?>
                                        <div class="dash-empty-state dash-chart-empty">
                                            <i class="fas fa-chart-pie"></i>
                                            <p>No category data yet.</p>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dash-chart-grid">
                    <!-- Pass Ratio Chart -->
                    <div class="dash-card glass dash-chart-tile">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-chart-pie"></i> Pass vs Needs Improvement</h3>
                            <span class="dash-card-tag">All Time</span>
                        </div>
                        <div class="dash-card-body dash-chart-body">
                            <div class="dash-chart-canvas-wrap">
                                <?php if ($totalAttempts > 0): ?>
                                        <canvas id="passFailChart"></canvas>
                                <?php else: ?>
                                        <div class="dash-empty-state dash-chart-empty">
                                            <i class="fas fa-chart-pie"></i>
                                            <p>No attempts yet.</p>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Score Distribution Chart -->
                    <div class="dash-card glass dash-chart-tile">
                        <div class="dash-card-header">
                            <h3><i class="fas fa-signal"></i> Score Distribution</h3>
                            <span class="dash-card-tag">Score Ranges</span>
                        </div>
                        <div class="dash-card-body dash-chart-body">
                            <div class="dash-chart-canvas-wrap">
                                <?php if ($totalAttempts > 0): ?>
                                        <canvas id="scoreDistChart"></canvas>
                                <?php else: ?>
                                        <div class="dash-empty-state dash-chart-empty">
                                            <i class="fas fa-chart-bar"></i>
                                            <p>No scores yet.</p>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quiz Categories -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-folder"></i> Quiz Categories</h3>
                        <span class="dash-card-tag"><?php echo $categoryCount; ?> open tracks</span>
                    </div>
                    <div class="dash-card-body">
                        <p class="dash-card-note">Move between categories quickly and keep your progress balanced across fundamentals, problem solving, and practical stacks.</p>
                        <?php if (count($categories) > 0): ?>
                            <div class="dash-category-grid">
                                <?php
                                $iconMap = [
                                    'H' => 'fa-code',
                                    'C' => 'fa-code',
                                    'P' => 'fa-database',
                                    'J' => 'fa-brands fa-js',
                                    'S' => 'fa-database',
                                    'M' => 'fa-calculator',
                                    'E' => 'fa-globe',
                                    'G' => 'fa-globe',
                                    'D' => 'fa-paint-brush',
                                    'N' => 'fa-microchip',
                                    'L' => 'fa-language',
                                    'B' => 'fa-flask',
                                    'A' => 'fa-chart-bar',
                                    'R' => 'fa-random',
                                    'F' => 'fa-film',
                                    'W' => 'fa-pen-fancy',
                                ];
                                foreach ($categories as $cat):
                                    $firstLetter = strtoupper(substr($cat['name'] ?? $cat, 0, 1));
                                    $icon = $iconMap[$firstLetter] ?? 'fa-folder';
                                    ?>
                                    <a href="quiz.php?category=<?php echo urlencode($cat['name'] ?? $cat); ?>" class="dash-category-card">
                                        <div class="dash-category-icon-wrapper"><i class="fas <?php echo $icon; ?>"></i></div>
                                        <span class="dash-category-name"><?php echo htmlspecialchars($cat['name'] ?? $cat); ?></span>
                                        <div class="dash-category-action">
                                            <span>Explore Track</span>
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>No categories available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Column -->
            <div class="dash-col-right">

                <!-- Recent Certificates -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-trophy"></i> Recent Certificates</h3>
                        <span class="dash-card-tag"><?php echo $totalCertificates; ?> total</span>
                    </div>
                    <div class="dash-card-body">
                        <p class="dash-card-note">Every certificate here reflects a 70%+ quiz finish and builds your achievement trail.</p>
                        <?php if (count($certificates) > 0): ?>
                                <?php foreach ($certificates as $cert): ?>
                                    <div class="dash-cert-card">
                                        <div class="dash-cert-icon"><i class="fas fa-trophy gold"></i></div>
                                        <div class="dash-cert-info">
                                            <span class="dash-cert-title"><?php echo htmlspecialchars($cert['title']); ?></span>
                                            <span class="dash-cert-date"><?php echo date('M d, Y', strtotime($cert['downloaded_at'])); ?></span>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($cert['certificate_path']); ?>" class="dash-btn-small dash-btn-primary" download><i class="fas fa-download"></i> Get Certificate</a>
                                    </div>
                                <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-certificate"></i>
                                <p>No certificates earned yet. Score 70%+ on a quiz to earn one!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-satellite-dish"></i> Achievement Radar</h3>
                        <span class="dash-card-tag">Personal pulse</span>
                    </div>
                    <div class="dash-card-body">
                        <div class="dash-signal-grid">
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Leaderboard Rank</span>
                                <strong class="dash-signal-value" data-count="<?php echo $leaderboardRank; ?>">0</strong>
                                <p>Position based on total quiz score.</p>
                            </article>
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Pass Rate</span>
                                <strong class="dash-signal-value" data-count="<?php echo $userPassRate; ?>">0%</strong>
                                <p>Strong finishes across your completed quizzes.</p>
                            </article>
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Top Score</span>
                                <strong class="dash-signal-value" data-count="<?php echo $bestScore; ?>">0%</strong>
                                <p>Your best single-quiz performance so far.</p>
                            </article>
                            <article class="dash-signal-card">
                                <span class="dash-signal-label">Momentum</span>
                                <strong class="dash-signal-value" data-count="<?php echo $currentMonthAttempts; ?>">0</strong>
                                <p>Attempts completed during the current month.</p>
                            </article>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                        <span class="dash-card-tag">Live timeline</span>
                    </div>
                    <div class="dash-card-body">
                        <?php if (count($recentActivity) > 0): ?>
                            <div class="dash-activity-list">
                                <?php foreach ($recentActivity as $act):
                                    $pct = $act['total_marks'] > 0 ? round(($act['score'] / $act['total_marks']) * 100, 1) : 0;
                                    if ($pct >= 70) {
                                        $dotClass = 'green';
                                    } elseif ($pct >= 40) {
                                        $dotClass = 'yellow';
                                    } else {
                                        $dotClass = 'red';
                                    }
                                    ?>
                                    <div class="dash-activity-item">
                                        <span class="dash-activity-dot <?php echo $dotClass; ?>"></span>
                                        <div class="dash-activity-content">
                                            <span class="dash-activity-title"><?php echo htmlspecialchars($act['title']); ?></span>
                                            <span class="dash-activity-meta">Score: <?php echo $act['score']; ?>/<?php echo $act['total_marks']; ?> &middot; <?php echo date('M d, Y', strtotime($act['completed_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-history"></i>
                                <p>No activity yet. Start a quiz to see your progress!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Leaderboard Mini -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-trophy"></i> Leaderboard</h3>
                        <span class="dash-card-tag">Top 5</span>
                    </div>
                    <div class="dash-card-body">
                        <?php if (count($leaderboard) > 0): ?>
                            <div class="dash-lb-list">
                                <?php $rank = 1;
                                foreach ($leaderboard as $lb):
                                    $rankClass = '';
                                    $rankIcon = '';
                                    if ($rank === 1) {
                                        $rankClass = 'gold';
                                        $rankIcon = '<i class="fas fa-crown"></i>';
                                    } elseif ($rank === 2) {
                                        $rankClass = 'silver';
                                    } elseif ($rank === 3) {
                                        $rankClass = 'bronze';
                                    }
                                    ?>
                                    <div class="dash-lb-item <?php echo $rankClass; ?>">
                                        <span class="dash-lb-rank"><?php if ($rankIcon)
                                            echo $rankIcon;
                                        else
                                            echo '#' . $rank; ?></span>
                                        <span class="dash-lb-name"><?php echo htmlspecialchars($lb['username']); ?></span>
                                        <span class="dash-lb-score"><?php echo (int) $lb['total_score']; ?></span>
                                    </div>
                                    <?php $rank++; endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty-state">
                                <i class="fas fa-users"></i>
                                <p>No leaderboard data yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dash-card glass">
                    <div class="dash-card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        <span class="dash-card-tag">Move faster</span>
                    </div>
                    <div class="dash-card-body">
                        <div class="dash-quick-grid">
                            <a href="quiz.php" class="dash-quick-btn blue"><i class="fas fa-pencil-alt"></i> Take Quiz</a>
                            <button type="button" class="dash-quick-btn green" onclick="openJoinModal()"><i class="fas fa-users"></i> Join Live</button>
                            <a href="certificates.php" class="dash-quick-btn gold"><i class="fas fa-certificate"></i> View Certificates</a>
                            <a href="contact.php" class="dash-quick-btn rose"><i class="fas fa-headset"></i> Get Support</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        </div>
    </div>

    <!-- Join Quiz Modal Overlay -->
    <div id="joinQuizModal" class="app-modal-overlay" style="display:none;">
        <div class="app-modal-card">
            <div class="app-modal-head">
                <h3 class="app-modal-title">Join Quiz</h3>
                <button type="button" class="app-modal-close" onclick="closeJoinModal()">&times;</button>
            </div>
            <div class="app-modal-body">
                <p>Enter the unique access code to jump straight into the quiz.</p>
                <form action="dashboard_user.php" method="POST" class="app-form">
                    <input type="hidden" name="action" value="join_by_code">
                    <div class="app-field">
                        <label for="modal_quiz_code" class="app-label">Unique Code</label>
                        <input type="text" name="quiz_code" id="modal_quiz_code" class="app-input" placeholder="E.g. QUIZ-123" required maxlength="20">
                    </div>
                    <div class="app-modal-actions">
                        <button type="submit" class="app-button app-button-primary">Join Quiz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openJoinModal() {
            const modal = document.getElementById('joinQuizModal');
            modal.style.display = 'flex';
            document.getElementById('modal_quiz_code').focus();
        }

        function closeJoinModal() {
            document.getElementById('joinQuizModal').style.display = 'none';
        }

        // Close on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeJoinModal();
        });

        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('joinQuizModal');
            if (event.target == modal) closeJoinModal();
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const chartLabels = <?php echo json_encode($chartLabels); ?>;
            const chartData = <?php echo json_encode($chartData); ?>;
            const isCompactViewport = window.matchMedia('(max-width: 480px)').matches;
            const catLabels = <?php echo json_encode($catLabels); ?>;
            const catData = <?php echo json_encode($catData); ?>;
            const passFailData = <?php echo json_encode($passFailData); ?>;
            const scoreDistData = <?php echo json_encode($scoreDistData); ?>;
            const totalAttempts = <?php echo $totalAttempts; ?>;

            if (document.getElementById('performanceChart') && chartLabels.length > 0) {
                const ctx = document.getElementById('performanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Score %',
                            data: chartData,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 2,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#4f46e5',
                            pointRadius: isCompactViewport ? 3 : 4,
                            pointHoverRadius: isCompactViewport ? 4 : 6,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    autoSkip: true,
                                    maxRotation: 0,
                                    maxTicksLimit: isCompactViewport ? 4 : 6,
                                    font: {
                                        size: isCompactViewport ? 10 : 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    stepSize: 20,
                                    font: {
                                        size: isCompactViewport ? 10 : 11
                                    }
                                }
                            }
                        }
                    }
                });
            }

            if (document.getElementById('categoryChart') && catLabels.length > 0) {
                const ctx2 = document.getElementById('categoryChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: catLabels,
                        datasets: [{
                            label: 'Average Score %',
                            data: catData,
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    maxRotation: 0,
                                    minRotation: 0,
                                    font: {
                                        size: isCompactViewport ? 10 : 11
                                    },
                                    callback: function(value) {
                                        const label = this.getLabelForValue(value);
                                        return isCompactViewport && label.length > 10 ? label.slice(0, 10) + '...' : label;
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    font: {
                                        size: isCompactViewport ? 10 : 11
                                    }
                                }
                            }
                        }
                    }
                });
            }

            if (document.getElementById('passFailChart') && totalAttempts > 0) {
                const ctx3 = document.getElementById('passFailChart').getContext('2d');
                new Chart(ctx3, {
                    type: 'doughnut',
                    data: {
                        labels: ['Passed (70%+)', 'Needs Improvement'],
                        datasets: [{
                            data: passFailData,
                            backgroundColor: [
                                'rgba(16, 185, 129, 0.8)', // Green
                                'rgba(244, 63, 94, 0.8)'   // Rose
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    boxWidth: isCompactViewport ? 10 : 12,
                                    boxHeight: isCompactViewport ? 10 : 12,
                                    padding: isCompactViewport ? 12 : 20,
                                    font: {
                                        size: isCompactViewport ? 10 : 12
                                    }
                                }
                            }
                        },
                        cutout: isCompactViewport ? '64%' : '70%'
                    }
                });
            }

            if (document.getElementById('scoreDistChart') && totalAttempts > 0) {
                const ctx4 = document.getElementById('scoreDistChart').getContext('2d');
                new Chart(ctx4, {
                    type: 'bar',
                    data: {
                        labels: ['0-49%', '50-69%', '70-89%', '90-100%'],
                        datasets: [{
                            label: 'Number of Attempts',
                            data: scoreDistData,
                            backgroundColor: [
                                'rgba(244, 63, 94, 0.8)',   // Rose
                                'rgba(245, 158, 11, 0.8)',  // Amber
                                'rgba(59, 130, 246, 0.8)',  // Blue
                                'rgba(16, 185, 129, 0.8)'   // Green
                            ],
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    maxRotation: 0,
                                    minRotation: 0,
                                    font: {
                                        size: isCompactViewport ? 10 : 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: isCompactViewport ? 10 : 11
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>

<?php include 'includes/footer.php'; ?>
