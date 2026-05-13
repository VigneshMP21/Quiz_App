<?php
session_start();
require_once 'includes/functions.php';

$accountPageConfig = [
    'is_admin' => true,
    'page_title' => 'QuizPro - Admin Profile',
    'page_body_class' => 'page-admin-profile',
    'header_context' => 'Admin profile',
    'footer_summary' => 'Account management for admin identity details, profile images, and secure platform ownership.',
];

require __DIR__ . '/includes/account_profile_page.php';
