<?php
session_start();
require_once 'includes/functions.php';

$accountPageConfig = [
    'is_admin' => true,
    'page_title' => 'QuizPro - Admin Change Password',
    'page_body_class' => 'page-admin-change-password',
    'header_context' => 'Admin security',
    'footer_summary' => 'Password rotation and secure account maintenance for protected admin access.',
];

require __DIR__ . '/includes/account_change_password_page.php';
