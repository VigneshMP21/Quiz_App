<?php
session_start();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

require_once 'includes/functions.php';

if ($userId !== null) {
    $stmt = $pdo->prepare('UPDATE users SET remember_selector = NULL, remember_token_hash = NULL, remember_expires_at = NULL WHERE id = ?');
    $stmt->execute([$userId]);
}

setcookie('quizpro_remember', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_unset();
session_destroy();
header("Location: index.php");
exit;
?>
