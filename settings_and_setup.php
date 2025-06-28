<?php
// config.php - Main Configuration File
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'southern_shorts_cms');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

define('SITE_URL', 'https://yourdomain.com');
define('ADMIN_URL', SITE_URL . '/admin');

// Start session
session_start();

// Database class
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
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
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
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
        return $this->pdo->lastInsertId();
    }
}

// Helper functions
function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

function requireLogin() {
    if (!isset($_SESSION['admin_user_id'])) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $db = Database::getInstance();
        $results = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}

function setSetting($key, $value) {
    $db = Database::getInstance();
    $db->query(
        "INSERT INTO site_settings (setting_key, setting_value, updated_at) 
         VALUES (?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
        [$key, $value]
    );
}

// admin/settings.php - Site Settings Management
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Default settings
$defaultSettings = [
    'site_title' => 'Southern Shorts Awards',
    'site_description' => 'A quarterly short film competition recognizing excellence in filmmaking.',
    'filmfreeway_url' => 'https://filmfreeway.com/festivals/8879',
    'contact_email' => 'info@southernshortsawards.com',
    'contact_phone' => '(678) 310-7192',
    'awards_description' => 'Recognizing excellence in short filmmaking with professional industry judging.',
    'submission_guidelines' => 'Films must be under 30 minutes and submitted via FilmFreeway.',
    'festival_address' => 'Atlanta, GA',
    'social_facebook' => '',
    'social_twitter' => '',
    'social_instagram' => '',
    'social_youtube' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($defaultSettings as $key => $defaultValue) {
        $value = $_POST[$key] ?? $defaultValue;
        setSetting($key, $value);
    }
    
    $success = 'Settings updated successfully!';
}

// Get current settings
$currentSettings = [];
foreach ($defaultSettings as $key => $defaultValue) {
    $currentSettings[$key] = getSetting($key, $defaultValue);
}

$content = '';

if ($success) {
    $content .= '<div class="alert alert-success">' . $success . '</div>';
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Site Settings</h2>
        <p class="text-muted">Configure your website settings and information</p>
    </div>
</div>

<form method="POST">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_title" class="form-label">Site Title</label>
                                <input type="text" class="form-control" id="site_title" name="site_title" 
                                       value="' . escape($currentSettings['site_title']) . '">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="festival_address" class="form-label">Festival Location</label>
                                <input type="text" class="form-control" id="festival_address" name="festival_address" 
                                       value="' . escape($currentSettings['festival_address']) . '">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea class="form-control" id="site_description" name="site_description" 
                                  rows="3">' . escape($currentSettings['site_description']) . '</textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="awards_description" class="form-label">Awards Description</label>
                        <textarea class="form-control" id="awards_description" name="awards_description" 
                                  rows="3">' . escape($currentSettings['awards_description']) . '</textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="submission_guidelines" class="form-label">Submission Guidelines Summary</label>
                        <textarea class="form-control" id="submission_guidelines" name="submission_guidelines" 
                                  rows="3">' . escape($currentSettings['submission_guidelines']) . '</textarea>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                       value="' . escape($currentSettings['contact_email']) . '">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="' . escape($currentSettings['contact_phone']) . '">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">External Links</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="filmfreeway_url" class="form-label">FilmFreeway Submission URL</label>
                        <input type="url" class="form-control" id="filmfreeway_url" name="filmfreeway_url" 
                               value="' . escape($currentSettings['filmfreeway_url']) . '">
                        <div class="form-text">This URL will be used for all "Submit Film" buttons</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Social Media</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="social_facebook" class="form-label">Facebook URL</label>
                        <input type="url" class="form-control" id="social_facebook" name="social_facebook" 
                               value="' . escape($currentSettings['social_facebook']) . '">
                    </div>
                    
                    <div class="mb-3">
                        <label for="social_twitter" class="form-label">Twitter URL</label>
                        <input type="url" class="form-control" id="social_twitter" name="social_twitter" 
                               value="' . escape($currentSettings['social_twitter']) . '">
                    </div>
                    
                    <div class="mb-3">
                        <label for="social_instagram" class="form-label">Instagram URL</label>
                        <input type="url" class="form-control" id="social_instagram" name="social_instagram" 
                               value="' . escape($currentSettings['social_instagram']) . '">
                    </div>
                    
                    <div class="mb-3">
                        <label for="social_youtube" class="form-label">YouTube URL</label>
                        <input type="url" class="form-control" id="social_youtube" name="social_youtube" 
                               value="' . escape($currentSettings['social_youtube']) . '">
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-ssa">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>';

renderAdminLayout('Site Settings', $content);

// Database setup script - setup.sql
?>

-- Complete Database Schema for Southern Shorts Awards CMS
CREATE DATABASE IF NOT EXISTS southern_shorts_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE southern_shorts_cms;

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Static pages table
CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    meta_description TEXT,
    page_type ENUM('static', 'system') DEFAULT 'static',
    template VARCHAR(50) DEFAULT 'default',
    status ENUM('draft', 'published') DEFAULT 'draft',
    menu_order INT DEFAULT 0,
    show_in_menu BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_menu_order (menu_order)
);

-- Festivals table
CREATE TABLE festivals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    season ENUM('Spring', 'Summer', 'Fall', 'Winter') NOT NULL,
    year YEAR NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description LONGTEXT,
    content LONGTEXT,
    meta_description TEXT,
    featured_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    start_date DATE,
    end_date DATE,
    submission_deadline DATE,
    awards_date DATE,
    program_pdf_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_season_year (season, year),
    INDEX idx_status (status),
    INDEX idx_slug (slug)
);

-- Award categories table
CREATE TABLE award_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT,
    category_name VARCHAR(100) NOT NULL,
    category_type ENUM('Film Category', 'Technical', 'Individual', 'Special') DEFAULT 'Film Category',
    description TEXT,
    display_order INT DEFAULT 0,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    INDEX idx_festival_display (festival_id, display_order)
);

-- Awards table
CREATE TABLE awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT NOT NULL,
    category_id INT,
    award_type ENUM('Best', 'Excellence', 'Merit', 'Distinction', 'Special', 'Best of Show') NOT NULL,
    award_title VARCHAR(200),
    film_title VARCHAR(200),
    filmmaker_names TEXT,
    recipient_role VARCHAR(100),
    description TEXT,
    placement ENUM('Winner', 'Excellence', 'Merit', 'Distinction', 'Special') DEFAULT 'Winner',
    display_order INT DEFAULT 0,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES award_categories(id) ON DELETE SET NULL,
    INDEX idx_festival_category (festival_id, category_id),
    INDEX idx_display_order (display_order)
);

-- Films table (for detailed film information)
CREATE TABLE films (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT,
    title VARCHAR(200) NOT NULL,
    director VARCHAR(200),
    producer VARCHAR(200),
    runtime_minutes INT,
    genre VARCHAR(50),
    synopsis TEXT,
    trailer_url VARCHAR(500),
    screening_url VARCHAR(500),
    poster_image VARCHAR(255),
    country VARCHAR(100),
    language VARCHAR(50),
    subtitles BOOLEAN DEFAULT FALSE,
    status ENUM('submitted', 'selected', 'awarded', 'screened') DEFAULT 'submitted',
    total_score DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    INDEX idx_festival_status (festival_id, status),
    INDEX idx_genre (genre)
);

-- Site settings table
CREATE TABLE site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json', 'url') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Media/uploads table
CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    alt_text VARCHAR(255),
    caption TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_filename (filename),
    INDEX idx_mime_type (mime_type)
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) 
VALUES ('admin', 'admin@southernshortsawards.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert default site settings
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('site_title', 'Southern Shorts Awards', 'text', 'Main site title'),
('site_description', 'A quarterly short film competition recognizing excellence in filmmaking.', 'text', 'Site description for meta tags'),
('filmfreeway_url', 'https://filmfreeway.com/festivals/8879', 'url', 'FilmFreeway submission URL'),
('contact_email', 'info@southernshortsawards.com', 'text', 'Main contact email'),
('contact_phone', '(678) 310-7192', 'text', 'Main contact phone'),
('awards_description', 'Recognizing excellence in short filmmaking with professional industry judging.', 'text', 'Description of awards program'),
('submission_guidelines', 'Films must be under 30 minutes and submitted via FilmFreeway.', 'text', 'Brief submission guidelines'),
('festival_address', 'Atlanta, GA', 'text', 'Festival location');

-- Installation instructions
/*
INSTALLATION INSTRUCTIONS:

1. Create the database by running this SQL file
2. Update config.php with your database credentials
3. Set up your web server to point to the project directory
4. Create the following directories and make them writable:
   - /uploads/
   - /uploads/images/
   - /uploads/documents/
   - /assets/images/

5. Upload your existing images to /assets/images/:
   - SSA-Logo.png (main logo)
   - FMB-Banner.png (partner banner)
   - Any other images referenced in your HTML

6. Default login credentials:
   Username: admin
   Password: admin123
   (Change this immediately after first login!)

7. For clean URLs, ensure mod_rewrite is enabled and add this .htaccess:

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9_-]+)/?$ page.php?slug=$1 [L,QSA]

8. Import your existing pages using the Import tool in the admin panel

SECURITY NOTES:
- Change default admin password immediately
- Restrict access to /admin/ directory if possible
- Keep the system updated
- Use strong passwords for all admin accounts
- Backup database regularly
*/

<?php
// Quick installation script - install.php
if (file_exists('config.php')) {
    die('Installation already completed. Remove install.php for security.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'southern_shorts_cms';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $siteUrl = $_POST['site_url'] ?? '';
    
    // Create config file
    $configContent = "<?php\n";
    $configContent .= "define('DB_HOST', '" . addslashes($dbHost) . "');\n";
    $configContent .= "define('DB_NAME', '" . addslashes($dbName) . "');\n";
    $configContent .= "define('DB_USER', '" . addslashes($dbUser) . "');\n";
    $configContent .= "define('DB_PASS', '" . addslashes($dbPass) . "');\n\n";
    $configContent .= "define('SITE_URL', '" . addslashes($siteUrl) . "');\n";
    $configContent .= "define('ADMIN_URL', SITE_URL . '/admin');\n\n";
    $configContent .= "// Include the rest of config.php content here...\n";
    
    file_put_contents('config.php', $configContent);
    
    echo '<div class="alert alert-success">Installation completed! Please remove install.php and configure your database.</div>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Southern Shorts CMS Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Southern Shorts CMS Installation</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" class="form-control" name="db_name" value="southern_shorts_cms" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Username</label>
                            <input type="text" class="form-control" name="db_user" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" class="form-control" name="db_pass">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Site URL</label>
                            <input type="url" class="form-control" name="site_url" value="<?= 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Install</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>