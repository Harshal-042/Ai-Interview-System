<?php
require_once __DIR__ . '/../includes/auth.php';

initSession();

if (!checkAdminAuth()) {
    header('Location: login.php');
    exit;
}

$activeToken = getActiveAdminToken();
$tokenQuery = '?token=' . urlencode($activeToken);

$currentPage = basename($_SERVER['REQUEST_URI']);
$currentPagePath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentFile = basename($currentPagePath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Admin Portal | AI Interview System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        // Ensure localStorage token is always preserved
        try {
            const token = <?= json_encode($activeToken) ?>;
            if (token) {
                localStorage.setItem('admin_token', token);
                sessionStorage.setItem('admin_token', token);
            }
        } catch(e) {}

        // Mobile Nav Toggle
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('mobileMenuToggle');
            const navLinks = document.getElementById('mainNavLinks');
            if (menuToggle && navLinks) {
                menuToggle.addEventListener('click', () => {
                    const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
                    menuToggle.setAttribute('aria-expanded', !isExpanded);
                    navLinks.classList.toggle('nav-open');
                });
            }
        });
    </script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="dashboard.php<?= $tokenQuery ?>" class="brand-link">
            <span>AI Interview System</span>
            <span class="brand-badge">Admin</span>
        </a>
        <button type="button" class="mobile-menu-btn" id="mobileMenuToggle" aria-label="Toggle navigation menu" aria-expanded="false">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
        <nav>
            <ul class="nav-links" id="mainNavLinks">
                <li><a href="dashboard.php<?= $tokenQuery ?>" class="nav-link <?= ($currentFile === 'dashboard.php' || empty($currentFile)) ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="create_interview.php<?= $tokenQuery ?>" class="nav-link <?= $currentFile === 'create_interview.php' ? 'active' : '' ?>">Create Interview</a></li>
                <li><a href="candidates.php<?= $tokenQuery ?>" class="nav-link <?= $currentFile === 'candidates.php' ? 'active' : '' ?>">Candidates & Links</a></li>
                <li><a href="logout.php<?= $tokenQuery ?>" class="nav-link btn-logout">Logout</a></li>
            </ul>
        </nav>
    </div>
</header>
<main class="container">
