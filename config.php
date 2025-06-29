<?php
// config.php - Configuration file for Southern Shorts CMS

// Database configuration
define('DB_HOST', 'self.theorubin.com');
define('DB_NAME', 'stevecmssite');
define('DB_USER', 'stevedemotest');
define('DB_PASS', 'faNvy4-niqqer-kymker');

// Site configuration
define('SITE_URL', 'http://localhost/southern_shorts_cms');
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOADS_DIR', 'uploads/');
define('UPLOADS_PATH', __DIR__ . '/' . UPLOADS_DIR);

// Session settings
ini_set('session.cookie_httponly', 1);
session_start();

// Include database class
require_once __DIR__ . '/database.php';

// Helper functions
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(ADMIN_URL . '/login.php');
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = Database::getInstance();
    return $db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

function getSetting($key, $default = '') {
    $db = Database::getInstance();
    $setting = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = ?",
        [$key]
    );
    return $setting ? $setting['setting_value'] : $default;
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatSeason($season, $year) {
    return $season . ' ' . $year;
}

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
    mkdir(UPLOADS_PATH . 'festivals/', 0755, true);
    mkdir(UPLOADS_PATH . 'films/', 0755, true);
    mkdir(UPLOADS_PATH . 'programs/', 0755, true);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>