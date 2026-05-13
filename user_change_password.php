<?php
session_start();
require_once 'includes/functions.php';

$accountPageConfig = [
    'is_admin' => false,
    'page_title' => 'QuizPro - Change Password',
    'page_body_class' => 'page-user-change-password',
    'header_context' => 'Learner security',
    'footer_summary' => 'Password rotation and secure account maintenance for learner access.',
];

require __DIR__ . '/includes/account_change_password_page.php';
