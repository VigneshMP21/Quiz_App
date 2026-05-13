<?php
require_once 'db.php';

// Function to redirect with a message
function redirect($url, $message = null) {
    if ($message) {
        $_SESSION['message'] = $message;
    }
    header("Location: $url");
    exit();
}

// Function to display messages
function displayMessage() {
    if (!empty($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['message'];
        $type = $_SESSION['flash_message']['type'];
        
        echo '<div class="alert alert-' . htmlspecialchars($type) . '">' 
             . htmlspecialchars($message) 
             . '</div>';
        
        unset($_SESSION['flash_message']);
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to generate random unique code
function generateUniqueCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Function to get quiz categories
function getQuizCategories() {
    return [
        'HTML', 'CSS', 'JavaScript', 'PHP', 'Python',
        'Java', 'C', 'MySql', 'C++',
        'REACT', 'Data Science', 'DSA'
    ];
}

/**
 * Sets a flash message in session
 * 
 * @param string $message The message to display
 * @param string $type The type of message (success, error, warning, info)
 */
function setMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Displays and clears the flash message
 */


