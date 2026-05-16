<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/certificate_image_service.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if attempt_id is provided
if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    redirect('dashboard_user.php', 'Invalid certificate request', 'error');
}

$attempt_id = (int)$_GET['attempt_id'];

try {
    // Verify the attempt belongs to the user and meets certificate criteria
    $stmt = $pdo->prepare("SELECT ua.*, q.title, q.total_marks, u.username 
                          FROM user_attempts ua
                          JOIN quizzes q ON ua.quiz_id = q.id
                          JOIN users u ON ua.user_id = u.id
                          WHERE ua.id = ? AND ua.user_id = ?");
    $stmt->execute([$attempt_id, $_SESSION['user_id']]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        redirect('dashboard_user.php', 'Attempt not found or unauthorized', 'error');
    }

    // Check if score meets certificate criteria (70% or higher)
    $passing_score = $attempt['total_marks'] * 0.7;
    if ($attempt['score'] < $passing_score) {
        redirect('dashboard_user.php', 'Your score does not meet the certificate criteria (70% or higher)', 'error');
    }

    // Check if certificate already exists
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE attempt_id = ?");
    $stmt->execute([$attempt_id]);
    $existing_cert = $stmt->fetch();

    if ($existing_cert && !empty($existing_cert['certificate_path'])) {
        $existingPath = getCertificateAbsolutePath((string) $existing_cert['certificate_path']);
        if (is_file($existingPath)) {
            outputCertificateDownload($existingPath);
        }
    }

    $absoluteFilepath = getCertificateAbsolutePath(generateAndSaveCertificate($attempt_id, $pdo));

    outputCertificateDownload($absoluteFilepath);
} catch (Exception $e) {
    error_log('Certificate generation error: ' . $e->getMessage());

    redirect('dashboard_user.php', 'Failed to generate certificate: ' . $e->getMessage(), 'error');
}
