<?php
// config.php - Main configuration file
session_start();

// Database Configuration
define('DB_HOST', 'self.theorubin.com');
define('DB_NAME', 'stevecmssite');
define('DB_USER', 'stevedemotest');
define('DB_PASS', 'faNvy4-niqqer-kymker');

// Site Configuration
define('SITE_URL', 'http://localhost/film_festival_cms');
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Security
define('SECRET_KEY', 'your-secret-key-change-this-in-production');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Site Settings
define('SITE_TITLE', 'Film Festival CMS');
define('ADMIN_EMAIL', 'admin@filmfestival.com');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload includes
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/permissions.php';

// Initialize database
$db = Database::getInstance();

/**
 * Enhanced Database Class with Connection Pooling
 */
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
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

/**
 * Enhanced Permission Management System
 */
class PermissionManager {
    
    // Define all available permissions
    const PERMISSIONS = [
        // User Management
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'users.edit' => 'Edit Users',
        'users.delete' => 'Delete Users',
        'users.manage_roles' => 'Manage User Roles',
        
        // Festival Management
        'festivals.view' => 'View Festivals',
        'festivals.create' => 'Create Festivals',
        'festivals.edit' => 'Edit Festivals',
        'festivals.delete' => 'Delete Festivals',
        'festivals.publish' => 'Publish Festivals',
        
        // Film Management
        'films.view' => 'View Films',
        'films.create' => 'Create Films',
        'films.edit' => 'Edit Films',
        'films.delete' => 'Delete Films',
        
        // Awards Management
        'awards.view' => 'View Awards',
        'awards.create' => 'Create Awards',
        'awards.edit' => 'Edit Awards',
        'awards.delete' => 'Delete Awards',
        
        // Page Management
        'pages.view' => 'View Pages',
        'pages.create' => 'Create Pages',
        'pages.edit' => 'Edit Pages',
        'pages.delete' => 'Delete Pages',
        'pages.publish' => 'Publish Pages',
        
        // Content Management
        'content.view' => 'View Content',
        'content.edit' => 'Edit Content',
        'content.publish' => 'Publish Content',
        
        // Media Management
        'media.view' => 'View Media',
        'media.upload' => 'Upload Media',
        'media.delete' => 'Delete Media',
        
        // Settings Management
        'settings.view' => 'View Settings',
        'settings.edit' => 'Edit Settings',
        
        // Reports & Analytics
        'reports.view' => 'View Reports',
        'reports.export' => 'Export Reports',
        
        // Import Tools
        'import.html' => 'Import HTML',
        'import.festivals' => 'Import Festivals',
        
        // System Administration
        'system.backup' => 'System Backup',
        'system.logs' => 'View System Logs',
        'system.maintenance' => 'System Maintenance'
    ];
    
    // Define role-based permission sets
    const ROLE_PERMISSIONS = [
        'super_admin' => 'all', // All permissions
        'admin' => [
            'users.view', 'users.create', 'users.edit',
            'festivals.view', 'festivals.create', 'festivals.edit', 'festivals.publish',
            'films.view', 'films.create', 'films.edit', 'films.delete',
            'awards.view', 'awards.create', 'awards.edit', 'awards.delete',
            'pages.view', 'pages.create', 'pages.edit', 'pages.publish',
            'content.view', 'content.edit', 'content.publish',
            'media.view', 'media.upload', 'media.delete',
            'settings.view', 'settings.edit',
            'reports.view', 'reports.export',
            'import.html', 'import.festivals'
        ],
        'festival_director' => [
            'festivals.view', 'festivals.create', 'festivals.edit', 'festivals.publish',
            'films.view', 'films.create', 'films.edit',
            'awards.view', 'awards.create', 'awards.edit',
            'pages.view', 'pages.edit',
            'content.view', 'content.edit',
            'media.view', 'media.upload',
            'reports.view', 'reports.export',
            'users.view'
        ],
        'editor' => [
            'festivals.view', 'festivals.edit',
            'films.view', 'films.edit',
            'awards.view', 'awards.edit',
            'pages.view', 'pages.create', 'pages.edit',
            'content.view', 'content.edit',
            'media.view', 'media.upload'
        ],
        'judge_coordinator' => [
            'festivals.view',
            'films.view',
            'awards.view', 'awards.create', 'awards.edit',
            'reports.view',
            'users.view'
        ],
        'content_manager' => [
            'pages.view', 'pages.create', 'pages.edit', 'pages.publish',
            'content.view', 'content.edit', 'content.publish',
            'media.view', 'media.upload',
            'festivals.view'
        ],
        'readonly' => [
            'festivals.view',
            'films.view',
            'awards.view',
            'pages.view',
            'content.view',
            'media.view'
        ]
    ];
    
    public static function hasPermission($permission, $userPermissions = null, $userRole = null) {
        if (!$userPermissions && !$userRole) {
            if (!isset($_SESSION['admin_user_id'])) {
                return false;
            }
            
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT role, permissions FROM admin_users WHERE id = ?", 
                [$_SESSION['admin_user_id']]
            );
            
            if (!$user) return false;
            
            $userRole = $user['role'];
            $userPermissions = $user['permissions'] ? json_decode($user['permissions'], true) : [];
        }
        
        // Super admin has all permissions
        if ($userRole === 'super_admin') {
            return true;
        }
        
        // Check custom permissions first
        if (is_array($userPermissions) && in_array($permission, $userPermissions)) {
            return true;
        }
        
        // Check role-based permissions
        $rolePermissions = self::ROLE_PERMISSIONS[$userRole] ?? [];
        
        if ($rolePermissions === 'all') {
            return true;
        }
        
        return in_array($permission, $rolePermissions);
    }
    
    public static function requirePermission($permission) {
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            die('<div class="alert alert-danger">Access denied. Required permission: ' . $permission . '</div>');
        }
    }
    
    public static function getRolePermissions($role) {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }
    
    public static function getAllPermissions() {
        return self::PERMISSIONS;
    }
    
    public static function logActivity($action, $targetType = null, $targetId = null, $details = []) {
        if (!isset($_SESSION['admin_user_id'])) return;
        
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO admin_activity_log (user_id, action, target_type, target_id, details, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $_SESSION['admin_user_id'],
                $action,
                $targetType,
                $targetId,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );
    }
    
    public static function getCurrentUser() {
        if (!isset($_SESSION['admin_user_id'])) {
            return null;
        }
        
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM admin_users WHERE id = ? AND is_active = 1", 
            [$_SESSION['admin_user_id']]
        );
    }
}

/**
 * Helper Functions
 */
function requireLogin() {
    if (!isset($_SESSION['admin_user_id'])) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . ADMIN_URL . '/login.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
    
    // Update last activity in database
    $db = Database::getInstance();
    $db->query(
        "UPDATE admin_users SET last_activity = NOW() WHERE id = ?",
        [$_SESSION['admin_user_id']]
    );
}

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $db = Database::getInstance();
        $results = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
        $settings = [];
        foreach ($results as $result) {
            $settings[$result['setting_key']] = $result['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}

function setSetting($key, $value) {
    $db = Database::getInstance();
    $db->query(
        "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value]
    );
}

function uploadFile($fileInput, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']) {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $file = $_FILES[$fileInput];
    
    // Check file type
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = UPLOAD_PATH . $filename;
    
    // Create upload directory if it doesn't exist
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save to database
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO media_files (filename, original_name, file_path, file_size, mime_type, file_type, uploaded_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $filename,
                $file['name'],
                $filepath,
                $file['size'],
                $file['type'],
                strpos($file['type'], 'image/') === 0 ? 'image' : 'document',
                $_SESSION['admin_user_id'] ?? null
            ]
        );
        
        return UPLOAD_URL . $filename;
    }
    
    return false;
}

// Auto-clean expired sessions
function cleanExpiredSessions() {
    $db = Database::getInstance();
    $db->query("DELETE FROM admin_sessions WHERE expires_at < NOW()");
}

// Call session cleanup periodically (10% chance)
if (rand(1, 10) === 1) {
    cleanExpiredSessions();
}
?>