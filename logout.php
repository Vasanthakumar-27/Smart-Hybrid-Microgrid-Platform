<?php
/**
 * Logout handler
 */
require_once __DIR__ . '/includes/session.php';
logout();
header('Location: ' . BASE_URL . 'index.php');
exit;
