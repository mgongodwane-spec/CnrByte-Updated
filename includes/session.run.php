<?php

// Session configuration: use secure cookie flags when on HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$cookieParams = [
    'lifetime' => 60 * 60 * 24, // 1 day
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax'
];

// Deliberately omit the Domain attribute: host-only session cookies reduce cross-host cookie risks.

// Use the options array (PHP 7.3+). If unavailable, this will still work in modern XAMPP.
session_set_cookie_params($cookieParams);
session_start();

// Simple CSRF token for forms
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // fallback
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// Periodic session id regeneration (every 15 minutes)
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} else {
    $interval = 60 * 15; // 15 minutes
    if (time() - $_SESSION['last_regeneration'] >= $interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
