<?php
require_once 'db.php';

// Function to redirect with a message
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
        unset($_SESSION['message']);
    }
    header("Location: $url");
    exit();
}

// Function to display messages
function displayMessage() {
    if (!empty($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['message'];
        $type = $_SESSION['flash_message']['type'];
        
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' flash-message-container">' 
             . htmlspecialchars($message) 
             . '</div>';
        
        unset($_SESSION['flash_message']);
        return;
    }

    if (!empty($_SESSION['error'])) {
        echo '<div class="alert alert-error flash-message-container">' 
             . htmlspecialchars($_SESSION['error']) 
             . '</div>';
        
        unset($_SESSION['error']);
        return;
    }

    if (!empty($_SESSION['message'])) {
        echo '<div class="alert alert-info flash-message-container">' 
             . htmlspecialchars($_SESSION['message']) 
             . '</div>';
        
        unset($_SESSION['message']);
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

function getUserById(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT id, username, email, address, phone, profile_image, role, password
                          FROM users
                          WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function getProfilePagePath(bool $isAdminView): string {
    return $isAdminView ? 'admin_profile.php' : 'user_profile.php';
}

function getChangePasswordPagePath(bool $isAdminView): string {
    return $isAdminView ? 'admin_change_password.php' : 'user_change_password.php';
}

function syncSessionUser(array $user): void {
    if (isset($user['id'])) {
        $_SESSION['user_id'] = (int) $user['id'];
    }

    if (isset($user['username'])) {
        $_SESSION['username'] = (string) $user['username'];
    }

    if (isset($user['role'])) {
        $_SESSION['role'] = (string) $user['role'];
    }
}

function storeProfileImageUpload(array $file, int $userId, ?string $currentRelativePath = null): array {
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['path' => $currentRelativePath, 'error' => null];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['path' => $currentRelativePath, 'error' => 'Image upload failed. Please try again.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['path' => $currentRelativePath, 'error' => 'Uploaded image could not be validated.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size > 2 * 1024 * 1024) {
        return ['path' => $currentRelativePath, 'error' => 'Profile image must be 2MB or smaller.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($tmpName);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        return ['path' => $currentRelativePath, 'error' => 'Please upload a JPG, PNG, WEBP, or GIF image.'];
    }

    $uploadDir = dirname(__DIR__) . '/assets/upload';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['path' => $currentRelativePath, 'error' => 'Profile upload folder could not be created.'];
    }

    try {
        $token = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $token = (string) mt_rand(1000, 9999);
    }

    $fileName = 'profile_' . $userId . '_' . time() . '_' . $token . '.' . $allowedTypes[$mimeType];
    $relativePath = 'assets/upload/' . $fileName;
    $destination = dirname(__DIR__) . '/' . $relativePath;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['path' => $currentRelativePath, 'error' => 'Profile image could not be saved.'];
    }

    if (!empty($currentRelativePath) && preg_match('~^assets/upload/~', $currentRelativePath)) {
        $currentAbsolutePath = dirname(__DIR__) . '/' . ltrim($currentRelativePath, '/');
        if (is_file($currentAbsolutePath)) {
            @unlink($currentAbsolutePath);
        }
    }

    return ['path' => $relativePath, 'error' => null];
}
