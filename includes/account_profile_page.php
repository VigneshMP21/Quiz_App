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
    if (empty($errors) && isset($_FILES['profile_image'])) {
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

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker"><?php echo $profileIsAdminView ? 'Admin identity' : 'Learner identity'; ?></span>
                    <h1 class="app-title"><?php echo htmlspecialchars($displayName); ?></h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                    <div class="app-actions">
                        <a href="<?php echo htmlspecialchars($homeLink); ?>" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        <a href="<?php echo htmlspecialchars($changePasswordLink); ?>" class="app-button app-button-primary"><i class="fas fa-key"></i> Change Password</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Account status</span>
                        <span class="app-status-pill"><i class="fas fa-shield-halved"></i> Verified</span>
                    </div>
                    <div class="app-profile-hero-card">
                        <?php if ($displayProfileImage !== ''): ?>
                            <img src="<?php echo htmlspecialchars($displayProfileImage); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="app-account-avatar app-account-avatar-image">
                        <?php else: ?>
                            <span class="app-account-avatar app-account-avatar-fallback"><?php echo htmlspecialchars($profileInitial); ?></span>
                        <?php endif; ?>
                        <div class="app-profile-hero-meta">
                            <strong><?php echo htmlspecialchars($displayName); ?></strong>
                            <p><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'No email set'); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label"><?php echo htmlspecialchars($statOneLabel); ?></span>
                    <strong class="app-metric-value" data-count="<?php echo $statOneValue; ?>">0</strong>
                    <p><?php echo htmlspecialchars($statOneText); ?></p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label"><?php echo htmlspecialchars($statTwoLabel); ?></span>
                    <strong class="app-metric-value" data-count="<?php echo $statTwoValue; ?>">0</strong>
                    <p><?php echo htmlspecialchars($statTwoText); ?></p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label"><?php echo htmlspecialchars($statThreeLabel); ?></span>
                    <strong class="app-metric-value" data-count="<?php echo $statThreeValue; ?>">0</strong>
                    <p><?php echo htmlspecialchars($statThreeText); ?></p>
                </article>
            </section>

            <div class="app-grid app-account-layout">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Profile editor</span>
                            <h2 class="app-panel-title">Update your account details</h2>
                        </div>
                    </div>
                    <p class="app-panel-text">Upload a profile image and keep your name, email, address, and phone number accurate. Your profile image will appear in the shared header after it is saved.</p>

                    <form action="<?php echo htmlspecialchars($profileLink); ?>" method="POST" enctype="multipart/form-data" class="app-form-grid">
                        <div class="app-form-section">
                            <h3 class="app-section-title">Identity</h3>
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
                            <div class="app-field-row-compact">
                                <div class="app-field">
                                    <label for="phone" class="app-label">Phone No.</label>
                                    <input type="text" id="phone" name="phone" class="app-input" value="<?php echo htmlspecialchars($formData['phone']); ?>" placeholder="+91 98765 43210">
                                </div>
                                <div class="app-field">
                                    <label for="profile_image" class="app-label">Profile Image</label>
                                    <input type="file" id="profile_image" name="profile_image" class="app-input" accept=".jpg,.jpeg,.png,.webp,.gif">
                                    <p class="app-helper">Upload JPG, PNG, WEBP, or GIF up to 2MB. Saved images are stored in <code>assets/upload/</code>.</p>
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
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Current identity</span>
                                <h2 class="app-panel-title">Header preview</h2>
                            </div>
                        </div>
                        <div class="app-account-card">
                            <?php if ($displayProfileImage !== ''): ?>
                                <img src="<?php echo htmlspecialchars($displayProfileImage); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="app-account-avatar app-account-avatar-image app-account-avatar-large">
                            <?php else: ?>
                                <span class="app-account-avatar app-account-avatar-fallback app-account-avatar-large"><?php echo htmlspecialchars($profileInitial); ?></span>
                            <?php endif; ?>
                            <div class="app-account-card-meta">
                                <strong><?php echo htmlspecialchars($displayName); ?></strong>
                                <span><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'No email set'); ?></span>
                                <span><?php echo htmlspecialchars($displayPhone !== '' ? $displayPhone : 'No phone number added'); ?></span>
                                <p><?php echo htmlspecialchars($displayAddress !== '' ? $displayAddress : 'No address added yet.'); ?></p>
                            </div>
                        </div>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Guidance</span>
                                <h2 class="app-panel-title">Keep this profile useful</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Use a recognisable image so the header avatar clearly matches your account.</li>
                            <li><i class="fas fa-check-circle"></i> Keep your email current so notifications and certificates stay associated correctly.</li>
                            <li><i class="fas fa-check-circle"></i> Update your password separately from the sidebar when you need a security refresh.</li>
                        </ul>
                    </section>
                </aside>
            </div>
<?php require __DIR__ . '/footer.php'; ?>
