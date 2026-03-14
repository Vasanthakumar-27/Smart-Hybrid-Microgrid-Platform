<?php
/**
 * Page Header & Navigation
 * Include at the top of every page after session check
 */
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$alertCount = 0;
if (isLoggedIn()) {
    try {
        $db = getDB();
        if (isAdmin()) {
            $alertCount = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'")->fetchColumn();
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM alerts WHERE family_id = ? AND status = 'active'");
            $stmt->execute([getCurrentFamilyId()]);
            $alertCount = (int) $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $alertCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-lightning-charge-fill"></i>
        <span><?= APP_NAME ?></span>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>monitor.php" class="nav-link <?= $currentPage === 'monitor' ? 'active' : '' ?>">
            <i class="bi bi-display"></i> <span>Live Monitor</span>
        </a>
        <a href="<?= BASE_URL ?>battery.php" class="nav-link <?= $currentPage === 'battery' ? 'active' : '' ?>">
            <i class="bi bi-battery-charging"></i> <span>Battery</span>
        </a>
        <a href="<?= BASE_URL ?>analytics.php" class="nav-link <?= $currentPage === 'analytics' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> <span>Analytics</span>
        </a>
        <a href="<?= BASE_URL ?>savings.php" class="nav-link <?= $currentPage === 'savings' ? 'active' : '' ?>">
            <i class="bi bi-piggy-bank"></i> <span>Savings</span>
        </a>
        <a href="<?= BASE_URL ?>reports.php" class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph"></i> <span>Reports</span>
        </a>
        <a href="<?= BASE_URL ?>logs.php" class="nav-link <?= $currentPage === 'logs' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> <span>System Logs</span>
        </a>
        <a href="<?= BASE_URL ?>alerts.php" class="nav-link <?= $currentPage === 'alerts' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle"></i> <span>Alerts</span>
            <?php if ($alertCount > 0): ?>
            <span class="badge bg-danger ms-auto"><?= $alertCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>profile.php" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i> <span>My Profile</span>
        </a>

        <?php if (isAdmin()): ?>
        <div class="nav-divider">Administration</div>
        <a href="<?= BASE_URL ?>admin/families.php" class="nav-link <?= $currentPage === 'families' ? 'active' : '' ?>">
            <i class="bi bi-houses"></i> <span>Families</span>
        </a>
        <a href="<?= BASE_URL ?>admin/microgrids.php" class="nav-link <?= $currentPage === 'microgrids' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i> <span>Microgrids</span>
        </a>
        <a href="<?= BASE_URL ?>admin/users.php" class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> <span>Users</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <i class="bi bi-person-circle"></i>
            <div>
                <div class="user-name"><?= h($_SESSION['full_name'] ?? 'User') ?></div>
                <small class="user-role"><?= ucfirst($_SESSION['role'] ?? '') ?><?= isset($_SESSION['family_name']) ? ' • ' . h($_SESSION['family_name']) : '' ?></small>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content Area -->
<div class="main-content" id="mainContent">
    <!-- Top Bar -->
    <div class="top-bar">
        <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h4 class="page-title mb-0"><?= h($pageTitle ?? 'Dashboard') ?></h4>
        <div class="top-bar-right">
            <span class="text-muted small"><?= date('D, d M Y H:i') ?></span>
            <?php if ($alertCount > 0): ?>
            <a href="<?= BASE_URL ?>alerts.php" class="btn btn-sm btn-outline-danger ms-2">
                <i class="bi bi-bell-fill"></i> <?= $alertCount ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page Content Container -->
    <div class="content-wrapper">
