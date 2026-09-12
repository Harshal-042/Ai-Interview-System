<?php
/**
 * Authentication and Session Management
 * Built to support both direct access and cross-origin iframes (AI Studio preview)
 */
require_once __DIR__ . '/db.php';

function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Essential configuration for cross-origin iframes
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '0');

        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);

        $sid = $_GET['sid'] ?? $_POST['sid'] ?? $_GET['PHPSESSID'] ?? $_POST['PHPSESSID'] ?? null;
        if ($sid && preg_match('/^[a-zA-Z0-9,-]{16,64}$/', $sid)) {
            session_id($sid);
        }

        @session_start();
    }
}

function getAdminAuthToken($adminId = 1) {
    return hash('sha256', 'ai_interview_admin_key_' . (int)$adminId);
}

function isValidAdminToken($token) {
    if (empty($token) || !is_string($token)) {
        return false;
    }
    
    // Check primary deterministic token or master demo tokens
    $primaryToken = getAdminAuthToken(1);
    if (hash_equals($primaryToken, $token) || $token === 'admin-demo-token-ai-interview' || $token === 'admin123') {
        return 1;
    }

    try {
        $pdo = getDbConnection();
        $admins = $pdo->query("SELECT id FROM admins")->fetchAll();
        foreach ($admins as $adm) {
            if (hash_equals(getAdminAuthToken((int)$adm['id']), $token)) {
                return (int)$adm['id'];
            }
        }
    } catch (Exception $e) {
        // ignore
    }

    return false;
}

function checkAdminAuth() {
    initSession();

    // 1. Session check
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        return true;
    }

    // 2. Query param or POST param token check (crucial for iframes where cookies are blocked)
    $token = $_GET['token'] ?? $_POST['token'] ?? $_GET['admin_token'] ?? $_POST['admin_token'] ?? $_COOKIE['admin_token'] ?? null;
    if ($token) {
        $adminId = isValidAdminToken($token);
        if ($adminId) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $adminId;
            $_SESSION['admin_username'] = 'admin';
            $_SESSION['admin_token'] = $token;
            return true;
        }
    }

    return false;
}

function getActiveAdminToken() {
    if (!empty($_SESSION['admin_token'])) {
        return $_SESSION['admin_token'];
    }
    $token = $_GET['token'] ?? $_GET['admin_token'] ?? $_COOKIE['admin_token'] ?? null;
    if ($token && isValidAdminToken($token)) {
        return $token;
    }
    return getAdminAuthToken(1);
}

function adminUrl($path) {
    $token = getActiveAdminToken();
    $separator = (strpos($path, '?') !== false) ? '&' : '?';
    return $path . $separator . 'token=' . urlencode($token);
}
