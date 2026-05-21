<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($accountPageConfig) || !is_array($accountPageConfig)) {
    http_response_code(500);
    exit('Account profile configuration is missing.');
}

$profileIsAdminView = !empty($accountPageConfig['is_admin']);

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isAdmin() !== $profileIsAdminView) {
    redirect(getProfilePagePath(isAdmin()));
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = getUserById($pdo, $userId);

if (!$user) {
    redirect($profileIsAdminView ? 'dashboard_admin.php' : 'dashboard_user.php', 'Profile could not be loaded.', 'error');
}

$errors = [];
$formData = [
    'username' => (string) ($user['username'] ?? ''),
    'email' => (string) ($user['email'] ?? ''),
    'address' => (string) ($user['address'] ?? ''),
    'phone' => (string) ($user['phone'] ?? ''),
];
$currentProfileImage = trim((string) ($user['profile_image'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim((string) ($_POST['username'] ?? ''));
    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $formData['address'] = trim((string) ($_POST['address'] ?? ''));
    $formData['phone'] = trim((string) ($_POST['phone'] ?? ''));

    if ($formData['username'] === '' || $formData['email'] === '') {
        $errors[] = 'Name and email are required.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($formData['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $formData['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (empty($errors)) {
        $duplicateStmt = $pdo->prepare("SELECT id, username, email
                                        FROM users
                                        WHERE (username = ? OR email = ?)
                                        AND id <> ?");
        $duplicateStmt->execute([$formData['username'], $formData['email'], $userId]);
        $duplicates = $duplicateStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($duplicates as $duplicate) {
            if (strcasecmp((string) $duplicate['username'], $formData['username']) === 0 && !in_array('Username already exists.', $errors, true)) {
                $errors[] = 'Username already exists.';
            }

            if (strcasecmp((string) $duplicate['email'], $formData['email']) === 0 && !in_array('Email address already exists.', $errors, true)) {
                $errors[] = 'Email address already exists.';
            }
        }
    }

    $profileImagePath = $currentProfileImage;
    $croppedImageData = trim((string) ($_POST['cropped_image_data'] ?? ''));

    if (empty($errors) && $croppedImageData !== '') {
        // Prioritize cropped image data
        $uploadResult = storeProfileImageData($croppedImageData, $userId, $currentProfileImage !== '' ? $currentProfileImage : null);
        if (!empty($uploadResult['error'])) {
            $errors[] = (string) $uploadResult['error'];
        } else {
            $profileImagePath = (string) ($uploadResult['path'] ?? $currentProfileImage);
        }
    } elseif (empty($errors) && isset($_FILES['profile_image'])) {
        // Fallback to standard upload if no crop data
        $uploadResult = storeProfileImageUpload($_FILES['profile_image'], $userId, $currentProfileImage !== '' ? $currentProfileImage : null);
        if (!empty($uploadResult['error'])) {
            $errors[] = (string) $uploadResult['error'];
        } else {
            $profileImagePath = (string) ($uploadResult['path'] ?? $currentProfileImage);
        }
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("UPDATE users
                                     SET username = ?, email = ?, address = ?, phone = ?, profile_image = ?
                                     WHERE id = ?");

        if ($updateStmt->execute([
            $formData['username'],
            $formData['email'],
            $formData['address'] !== '' ? $formData['address'] : null,
            $formData['phone'] !== '' ? $formData['phone'] : null,
            $profileImagePath !== '' ? $profileImagePath : null,
            $userId,
        ])) {
            $updatedUser = getUserById($pdo, $userId);
            if ($updatedUser) {
                syncSessionUser($updatedUser);
            }

            redirect(getProfilePagePath($profileIsAdminView), 'Profile updated successfully.');
        }

        $errors[] = 'Profile update failed. Please try again.';
    }
}

$user = getUserById($pdo, $userId) ?: $user;
$displayName = trim((string) ($user['username'] ?? 'User'));
$displayEmail = trim((string) ($user['email'] ?? ''));
$displayAddress = trim((string) ($user['address'] ?? ''));
$displayPhone = trim((string) ($user['phone'] ?? ''));
$displayProfileImage = trim((string) ($user['profile_image'] ?? ''));
$profileInitial = strtoupper(substr($displayName, 0, 1));

if ($profileIsAdminView) {
    $summaryStmt = $pdo->prepare("SELECT
                                    COUNT(*) AS total_quizzes,
                                    COALESCE(SUM(no_of_questions), 0) AS planned_questions,
                                    COALESCE(SUM(total_marks), 0) AS total_marks
                                  FROM quizzes
                                  WHERE created_by = ?");
    $summaryStmt->execute([$userId]);
    $profileStats = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $messageCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();

    $statOneValue = (int) ($profileStats['total_quizzes'] ?? 0);
    $statOneLabel = 'Quizzes built';
    $statOneText = 'Assessments created under your admin account.';
    $statTwoValue = (int) ($profileStats['planned_questions'] ?? 0);
    $statTwoLabel = 'Questions planned';
    $statTwoText = 'Target question volume across authored quizzes.';
    $statThreeValue = $messageCount;
    $statThreeLabel = 'Inbox items';
    $statThreeText = 'Current contact messages visible to admins.';
    $heroSummary = 'Keep your admin identity current, upload a recognisable profile image, and maintain clear account details for platform operations.';
} else {
    $summaryStmt = $pdo->prepare("SELECT
                                    COUNT(*) AS total_attempts,
                                    COALESCE(MAX(score), 0) AS best_score
                                  FROM user_attempts
                                  WHERE user_id = ?");
    $summaryStmt->execute([$userId]);
    $profileStats = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $certificateStmt = $pdo->prepare("SELECT COUNT(*)
                                      FROM certificates c
                                      JOIN user_attempts ua ON c.attempt_id = ua.id
                                      WHERE ua.user_id = ?");
    $certificateStmt->execute([$userId]);
    $certificateCount = (int) $certificateStmt->fetchColumn();

    $statOneValue = (int) ($profileStats['total_attempts'] ?? 0);
    $statOneLabel = 'Quiz attempts';
    $statOneText = 'Recorded attempts completed from your learner account.';
    $statTwoValue = (int) ($profileStats['best_score'] ?? 0);
    $statTwoLabel = 'Best score';
    $statTwoText = 'Highest score captured across all finished quizzes.';
    $statThreeValue = $certificateCount;
    $statThreeLabel = 'Certificates earned';
    $statThreeText = 'Certificates already unlocked from strong finishes.';
    $heroSummary = 'Keep your learner profile polished, upload a recognisable image, and make sure your contact details stay current for certificate delivery and support.';
}

$pageTitle = $accountPageConfig['page_title'] ?? ($profileIsAdminView ? 'QuizPro - Admin Profile' : 'QuizPro - User Profile');
$pageKey = 'profile';
$pageBodyClass = $accountPageConfig['page_body_class'] ?? ($profileIsAdminView ? 'page-admin-profile' : 'page-user-profile');
$headerContext = $accountPageConfig['header_context'] ?? ($profileIsAdminView ? 'Admin profile' : 'Learner profile');
$pageFooterSummary = $accountPageConfig['footer_summary'] ?? 'Structured account management for identity details, profile images, and secure account visibility.';
$homeLink = $profileIsAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$logoutLink = 'logout.php';
$changePasswordLink = getChangePasswordPagePath($profileIsAdminView);
$profileLink = getProfilePagePath($profileIsAdminView);
$isAdminView = $profileIsAdminView;
$profileRoleLabel = $profileIsAdminView ? 'Platform administrator' : 'Learner account';
$profileCompletionItems = 2
    + ($displayPhone !== '' ? 1 : 0)
    + ($displayAddress !== '' ? 1 : 0)
    + ($displayProfileImage !== '' ? 1 : 0);
$profileCompletionPercent = (int) round(($profileCompletionItems / 5) * 100);
$profileImageSrc = $displayProfileImage !== '' ? $displayProfileImage : '';

$headAssets = <<<'HTML'
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<style>
    .profile-command {
        display: grid;
        gap: 22px;
        margin-top: 24px;
    }
    .profile-hero {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: center;
        padding: 26px;
        border-radius: 28px;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96)),
            radial-gradient(circle at top right, rgba(20, 184, 166, 0.12), transparent 42%);
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
    }
    .profile-hero-main {
        display: flex;
        align-items: center;
        gap: 18px;
        min-width: 0;
    }
    .profile-hero-avatar {
        width: 108px;
        height: 108px;
        border-radius: 26px;
        object-fit: cover;
        flex-shrink: 0;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.14);
    }
    .profile-hero-copy {
        min-width: 0;
    }
    .profile-hero-copy h1 {
        margin: 0;
        color: #0f172a;
        font-family: "Poppins", sans-serif;
        font-size: clamp(1.9rem, 4vw, 3rem);
        line-height: 1.05;
        letter-spacing: 0;
    }
    .profile-hero-copy p {
        margin: 10px 0 0;
        max-width: 68ch;
        color: #475569;
        font-size: 14px;
        line-height: 1.75;
    }
    .profile-role-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        padding: 7px 10px;
        border-radius: 999px;
        background: rgba(20, 184, 166, 0.1);
        border: 1px solid rgba(20, 184, 166, 0.18);
        color: #0f766e;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    .profile-completion {
        min-width: 220px;
        display: grid;
        gap: 10px;
        padding: 18px;
        border-radius: 20px;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.07);
    }
    .profile-completion-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
    }
    .profile-completion-top strong {
        color: #0f172a;
        font-size: 18px;
    }
    .profile-completion-track {
        height: 9px;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.18);
    }
    .profile-completion-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #14b8a6, #38bdf8);
    }
    .profile-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .profile-stat-card,
    .profile-side-panel,
    .profile-form-shell {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.07);
    }
    .profile-stat-card {
        display: grid;
        gap: 8px;
        padding: 18px;
        border-radius: 20px;
    }
    .profile-stat-card i {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #0f766e;
        background: rgba(20, 184, 166, 0.1);
    }
    .profile-stat-card strong {
        color: #0f172a;
        font-size: 28px;
        line-height: 1;
    }
    .profile-stat-card span {
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
    }
    .profile-stat-card p {
        margin: 0;
        color: #64748b;
        font-size: 12px;
        line-height: 1.6;
    }
    .profile-layout {
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.75fr);
        align-items: start;
    }
    .profile-form-shell {
        padding: 24px;
        border-radius: 26px;
    }
    .profile-form-section {
        background: #ffffff;
        border-color: rgba(148, 163, 184, 0.16);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }
    .profile-form-section .app-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #0f172a;
    }
    .profile-form-section .app-section-title i {
        color: #0f766e;
    }
    .profile-side-panel {
        padding: 20px;
        border-radius: 24px;
    }
    .profile-preview-card {
        display: grid;
        justify-items: center;
        gap: 14px;
        text-align: center;
    }
    .profile-preview-card .app-account-avatar-large {
        width: 118px;
        height: 118px;
        border-radius: 30px;
    }
    .profile-preview-meta {
        display: grid;
        gap: 7px;
        min-width: 0;
    }
    .profile-preview-meta strong {
        color: #0f172a;
        font-size: 22px;
        font-weight: 800;
    }
    .profile-preview-meta span,
    .profile-preview-meta p {
        margin: 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
        overflow-wrap: anywhere;
    }
    .profile-detail-list {
        display: grid;
        gap: 10px;
        margin-top: 18px;
    }
    .profile-detail-item {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr);
        gap: 10px;
        align-items: start;
        padding: 12px;
        border-radius: 16px;
        background: rgba(248, 250, 252, 0.9);
        border: 1px solid rgba(148, 163, 184, 0.14);
    }
    .profile-detail-item i {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #2563eb;
        background: rgba(37, 99, 235, 0.08);
    }
    .profile-detail-item span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .profile-detail-item strong,
    .profile-detail-item p {
        margin: 3px 0 0;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }
    .app-crop-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 20000;
        padding: 2rem;
    }
    .app-crop-modal.active {
        display: flex;
    }
    .app-crop-container {
        background: var(--app-bg-panel, #ffffff);
        padding: 1.5rem;
        border-radius: 1.5rem;
        max-width: 800px;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .app-crop-wrapper {
        max-height: 60vh;
        overflow: hidden;
        border-radius: 0.75rem;
        background: #f1f5f9;
    }
    .app-crop-wrapper img {
        max-width: 100%;
    }
    @media (max-width: 900px) {
        .profile-hero,
        .profile-layout {
            grid-template-columns: 1fr;
        }
        .profile-completion {
            min-width: 0;
        }
        .profile-stat-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 560px) {
        .profile-hero {
            padding: 18px;
            border-radius: 24px;
        }
        .profile-hero-main {
            align-items: flex-start;
        }
        .profile-hero-avatar {
            width: 76px;
            height: 76px;
            border-radius: 20px;
        }
        .profile-form-shell,
        .profile-side-panel {
            padding: 16px;
            border-radius: 22px;
        }
    }
</style>
<link rel="stylesheet" href="assets/css/mobile_view.css">
HTML;

require __DIR__ . '/header.php';
?>
            <?php displayMessage(); ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>



            <div class="profile-command">
                <section class="profile-hero">
                    <div class="profile-hero-main">
                        <?php if ($profileImageSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($profileImageSrc); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="profile-hero-avatar app-account-avatar-image">
                        <?php else: ?>
                            <span class="profile-hero-avatar app-account-avatar-fallback"><?php echo htmlspecialchars($profileInitial); ?></span>
                        <?php endif; ?>
                        <div class="profile-hero-copy">
                            <span class="profile-role-pill"><i class="fas fa-id-badge"></i><?php echo htmlspecialchars($profileRoleLabel); ?></span>
                            <h1><?php echo htmlspecialchars($displayName); ?></h1>
                            <p><?php echo htmlspecialchars($heroSummary); ?></p>
                        </div>
                    </div>
                    <div class="profile-completion">
                        <div class="profile-completion-top">
                            <span>Profile completion</span>
                            <strong><?php echo $profileCompletionPercent; ?>%</strong>
                        </div>
                        <div class="profile-completion-track" aria-hidden="true">
                            <div class="profile-completion-fill" style="width: <?php echo $profileCompletionPercent; ?>%"></div>
                        </div>
                    </div>
                </section>

                <section class="profile-stat-grid" aria-label="Profile summary">
                    <div class="profile-stat-card">
                        <i class="fas fa-layer-group"></i>
                        <strong><?php echo $statOneValue; ?></strong>
                        <span><?php echo htmlspecialchars($statOneLabel); ?></span>
                        <p><?php echo htmlspecialchars($statOneText); ?></p>
                    </div>
                    <div class="profile-stat-card">
                        <i class="fas fa-chart-line"></i>
                        <strong><?php echo $statTwoValue; ?></strong>
                        <span><?php echo htmlspecialchars($statTwoLabel); ?></span>
                        <p><?php echo htmlspecialchars($statTwoText); ?></p>
                    </div>
                    <div class="profile-stat-card">
                        <i class="fas fa-award"></i>
                        <strong><?php echo $statThreeValue; ?></strong>
                        <span><?php echo htmlspecialchars($statThreeLabel); ?></span>
                        <p><?php echo htmlspecialchars($statThreeText); ?></p>
                    </div>
                </section>

                <div class="app-grid profile-layout">
                    <section class="profile-form-shell">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Profile editor</span>
                                <h2 class="app-panel-title">Account details</h2>
                            </div>
                        </div>
                        <p class="app-panel-text">Keep the information below accurate so certificates, notifications, and support messages can use the right identity details.</p>

                        <form action="<?php echo htmlspecialchars($profileLink); ?>" method="POST" enctype="multipart/form-data" class="app-form-grid">
                            <div class="app-form-section profile-form-section">
                                <h3 class="app-section-title"><i class="fas fa-user-pen"></i> Identity</h3>
                                <div class="app-field-row-compact">
                                    <div class="app-field">
                                        <label for="username" class="app-label">Name*</label>
                                        <input type="text" id="username" name="username" class="app-input" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                                    </div>
                                    <div class="app-field">
                                        <label for="email" class="app-label">Email*</label>
                                        <input type="email" id="email" name="email" class="app-input" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="app-form-section profile-form-section">
                                <h3 class="app-section-title"><i class="fas fa-address-card"></i> Contact and image</h3>
                                <div class="app-field-row-compact">
                                    <div class="app-field">
                                        <label for="phone" class="app-label">Phone No.</label>
                                        <input type="text" id="phone" name="phone" class="app-input" value="<?php echo htmlspecialchars($formData['phone']); ?>" placeholder="+91 98765 43210">
                                    </div>
                                    <div class="app-field">
                                        <label for="profile_image" class="app-label">Profile image</label>
                                        <input type="file" id="profile_image" class="app-input" accept=".jpg,.jpeg,.png,.webp,.gif">
                                        <input type="hidden" name="cropped_image_data" id="cropped_image_data">
                                        <p class="app-helper">Upload JPG, PNG, WEBP, or GIF up to 2MB.</p>
                                    </div>
                                </div>
                                <div class="app-field">
                                    <label for="address" class="app-label">Address</label>
                                    <textarea id="address" name="address" rows="5" class="app-textarea" placeholder="Add your address"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                                </div>
                            </div>

                            <div class="app-actions">
                                <button type="submit" class="app-button app-button-primary"><i class="fas fa-floppy-disk"></i> Save Profile</button>
                                <a href="<?php echo htmlspecialchars($homeLink); ?>" class="app-button app-button-ghost"><i class="fas fa-xmark"></i> Cancel</a>
                            </div>
                        </form>
                    </section>

                    <aside class="app-sidebar">
                        <section class="profile-side-panel">
                            <div class="app-panel-head">
                                <div>
                                    <span class="app-panel-kicker">Current identity</span>
                                    <h2 class="app-panel-title">Profile preview</h2>
                                </div>
                            </div>
                            <div class="profile-preview-card">
                                <?php if ($displayProfileImage !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($displayProfileImage); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="app-account-avatar app-account-avatar-image app-account-avatar-large">
                                <?php else: ?>
                                    <span class="app-account-avatar app-account-avatar-fallback app-account-avatar-large"><?php echo htmlspecialchars($profileInitial); ?></span>
                                <?php endif; ?>
                                <div class="profile-preview-meta">
                                    <strong><?php echo htmlspecialchars($displayName); ?></strong>
                                    <span><?php echo htmlspecialchars($profileRoleLabel); ?></span>
                                    <p><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'No email set'); ?></p>
                                </div>
                            </div>

                            <div class="profile-detail-list">
                                <div class="profile-detail-item">
                                    <i class="fas fa-phone"></i>
                                    <div>
                                        <span>Phone</span>
                                        <strong><?php echo htmlspecialchars($displayPhone !== '' ? $displayPhone : 'Not added'); ?></strong>
                                    </div>
                                </div>
                                <div class="profile-detail-item">
                                    <i class="fas fa-location-dot"></i>
                                    <div>
                                        <span>Address</span>
                                        <p><?php echo htmlspecialchars($displayAddress !== '' ? $displayAddress : 'No address added yet.'); ?></p>
                                    </div>
                                </div>
                                <div class="profile-detail-item">
                                    <i class="fas fa-shield-halved"></i>
                                    <div>
                                        <span>Access level</span>
                                        <strong><?php echo htmlspecialchars($profileRoleLabel); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </div>

            <div id="cropModal" class="app-crop-modal">
                <div class="app-crop-container">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Image refinement</span>
                            <h2 class="app-panel-title">Crop your profile picture</h2>
                        </div>
                    </div>
                    <div class="app-crop-wrapper">
                        <img id="imageToCrop" src="" alt="Crop preview">
                    </div>
                    <div class="app-actions">
                        <button type="button" class="app-button app-button-primary" id="applyCrop"><i class="fas fa-crop-simple"></i> Apply Crop</button>
                        <button type="button" class="app-button app-button-ghost" id="cancelCrop"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </div>
            </div>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
            <script>
                (function() {
                    const profileInput = document.getElementById('profile_image');
                    const cropModal = document.getElementById('cropModal');
                    const imageToCrop = document.getElementById('imageToCrop');
                    const applyBtn = document.getElementById('applyCrop');
                    const cancelBtn = document.getElementById('cancelCrop');
                    const croppedDataInput = document.getElementById('cropped_image_data');
                    let cropper = null;

                    profileInput.addEventListener('change', function(e) {
                        const files = e.target.files;
                        if (files && files.length > 0) {
                            const file = files[0];
                            const reader = new FileReader();
                            reader.onload = function(event) {
                                imageToCrop.src = event.target.result;
                                cropModal.classList.add('active');
                                
                                if (cropper) cropper.destroy();
                                
                                cropper = new Cropper(imageToCrop, {
                                    aspectRatio: 1,
                                    viewMode: 2,
                                    guides: true,
                                    center: true,
                                    highlight: false,
                                    cropBoxMovable: true,
                                    cropBoxResizable: true,
                                    toggleDragModeOnDblclick: false,
                                });
                            };
                            reader.readAsDataURL(file);
                        }
                    });

                    applyBtn.addEventListener('click', function() {
                        if (!cropper) return;
                        
                        const canvas = cropper.getCroppedCanvas({
                            width: 400,
                            height: 400,
                        });
                        
                        croppedDataInput.value = canvas.toDataURL('image/jpeg', 0.9);
                        cropModal.classList.remove('active');
                        
                        // Optional: Show preview on the page
                        const avatarPreviews = document.querySelectorAll('.app-account-avatar-image, .app-topbar-avatar-image');
                        avatarPreviews.forEach(img => {
                            img.src = canvas.toDataURL();
                        });
                    });

                    cancelBtn.addEventListener('click', function() {
                        cropModal.classList.remove('active');
                        profileInput.value = '';
                        if (cropper) cropper.destroy();
                    });
                })();
            </script>
<?php require __DIR__ . '/footer.php'; ?>
