<?php
require_once __DIR__ . '/include/config.php';
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . SITE_URL . '/admin/login.php');
}
exit;
