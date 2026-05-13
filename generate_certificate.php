<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if attempt_id is provided
if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    $_SESSION['error'] = 'Invalid certificate request';
    redirect('dashboard_user.php');
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
        $_SESSION['error'] = 'Attempt not found or unauthorized';
        redirect('dashboard_user.php');
    }

    // Check if score meets certificate criteria (70% or higher)
    $passing_score = $attempt['total_marks'] * 0.7;
    if ($attempt['score'] < $passing_score) {
        $_SESSION['error'] = 'Your score does not meet the certificate criteria (70% or higher)';
        redirect('dashboard_user.php');
    }

    // Check if certificate already exists
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE attempt_id = ?");
    $stmt->execute([$attempt_id]);
    $existing_cert = $stmt->fetch();

    if ($existing_cert) {
        // Certificate already exists, redirect to download
        if (file_exists($existing_cert['certificate_path'])) {
            header('Location: ' . $existing_cert['certificate_path']);
            exit;
        }
    }

    // Generate certificate
    require_once 'vendor/autoload.php';

    // Certificate content
    ob_start();
    include 'certificate_template.php';
    $html = ob_get_clean();

    // Configure dompdf
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans'); // For better Unicode support

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Create certificates directory if it doesn't exist
    $cert_dir = 'certificates/' . $_SESSION['user_id'] . '/';
    if (!file_exists($cert_dir)) {
        if (!mkdir($cert_dir, 0755, true)) {
            throw new Exception('Failed to create certificate directory');
        }
    }

    // Generate unique filename
    $filename = 'certificate_' . $attempt_id . '_' . time() . '.pdf';
    $filepath = $cert_dir . $filename;

    // Save the PDF
    if (!file_put_contents($filepath, $dompdf->output())) {
        throw new Exception('Failed to save certificate file');
    }

    // Record certificate in database
    $stmt = $pdo->prepare("INSERT INTO certificates (attempt_id, certificate_path, downloaded_at) 
                          VALUES (?, ?, NOW())");
    if (!$stmt->execute([$attempt_id, $filepath])) {
        throw new Exception('Failed to record certificate in database');
    }

    // Output the PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    echo $dompdf->output();
    exit;

} catch (Exception $e) {
    // Log the error (you should implement proper error logging)
    error_log('Certificate generation error: ' . $e->getMessage());
    
    $_SESSION['error'] = 'Failed to generate certificate. Please try again.';
    redirect('dashboard_user.php');
}