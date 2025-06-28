<?php
// config.php - Configuration file for Southern Shorts CMS

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'southern_shorts_cms');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_URL', 'http://localhost/southern_shorts_cms');
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOADS_DIR', 'uploads/');
define('UPLOADS_PATH', __DIR__ . '/' . UPLOADS_DIR);

// Session settings
ini_set('session.cookie_httponly', 1);
session_start();

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}

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