<?php
session_start();
require_once 'includes/functions.php';

$accountPageConfig = [
    'is_admin' => false,
    'page_title' => 'QuizPro - My Profile',
    'page_body_class' => 'page-user-profile',
    'header_context' => 'Learner profile',
    'footer_summary' => 'Account management for learner identity details, profile images, and personal contact information.',
];

require __DIR__ . '/includes/account_profile_page.php';
