-- Complete Database Schema for Film Festival CMS
-- Drop existing tables if they exist for fresh installation
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS admin_activity_log, admin_sessions, admin_users, awards, films, festival_pages, categories, award_types, festivals, site_settings;
SET FOREIGN_KEY_CHECKS = 1;

-- Site Settings Table
CREATE TABLE site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO site_settings (setting_key, setting_value, description) VALUES
('site_title', 'Film Festival CMS', 'Main site title'),
('site_description', 'Award-winning short film festival', 'Site description for meta tags'),
('contact_email', 'info@filmfestival.com', 'Main contact email'),
('filmfreeway_url', '#', 'FilmFreeway submission URL'),
('primary_color', '#CC3300', 'Primary theme color'),
('secondary_color', '#993300', 'Secondary theme color'),
('festival_active', '1', 'Whether submissions are currently active');

-- Admin Users Table with Enhanced Multi-User Support
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'festival_director', 'editor', 'judge_coordinator', 'content_manager', 'readonly') DEFAULT 'readonly',
    permissions JSON DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP NULL DEFAULT NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default super admin (password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role, is_active) VALUES
('admin', 'admin@filmfestival.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', TRUE);

-- User Sessions Table for Better Session Management
CREATE TABLE admin_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);

-- User Activity Log Table
CREATE TABLE admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_action (action),
    INDEX idx_target (target_type, target_id)
);

-- Festivals Table
CREATE TABLE festivals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    season ENUM('Spring', 'Summer', 'Fall', 'Winter') NOT NULL,
    year INT NOT NULL,
    description TEXT,
    content LONGTEXT,
    start_date DATE,
    end_date DATE,
    submission_deadline DATE,
    program_pdf_url VARCHAR(500),
    banner_image VARCHAR(500),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    meta_title VARCHAR(200),
    meta_description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_season_year (season, year),
    INDEX idx_status_year (status, year),
    INDEX idx_featured (featured)
);

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (name, description, sort_order) VALUES
('Drama', 'Dramatic short films', 1),
('Comedy', 'Comedy short films', 2),
('Horror', 'Horror short films', 3),
('Documentary', 'Documentary short films', 4),
('Animation', 'Animated short films', 5),
('Student', 'Student-produced films', 6),
('Made in Georgia', 'Films produced in Georgia', 7),
('Review Only', 'Films for review only', 8);

-- Award Types Table
CREATE TABLE award_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-trophy',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default award types
INSERT INTO award_types (name, description, sort_order) VALUES
('Best Picture', 'Best overall film in category', 1),
('Best Director', 'Outstanding direction', 2),
('Best Actor', 'Outstanding male performance', 3),
('Best Actress', 'Outstanding female performance', 4),
('Best Supporting Actor', 'Outstanding supporting male performance', 5),
('Best Supporting Actress', 'Outstanding supporting female performance', 6),
('Best Screenplay', 'Outstanding writing', 7),
('Best Cinematography', 'Outstanding camera work', 8),
('Best Editing', 'Outstanding editing', 9),
('Best Sound Design', 'Outstanding sound work', 10),
('Best Original Score', 'Outstanding musical score', 11),
('Best Production Design', 'Outstanding art direction', 12),
('Best Visual Effects', 'Outstanding visual effects', 13),
('Best Makeup', 'Outstanding makeup design', 14),
('Best Costume Design', 'Outstanding costume design', 15),
('Audience Choice', 'Voted by audience', 16),
('Honorable Mention', 'Special recognition', 17);

-- Films Table
CREATE TABLE films (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    director VARCHAR(200),
    producer VARCHAR(200),
    runtime VARCHAR(20),
    country VARCHAR(100),
    language VARCHAR(100),
    year_produced INT,
    genre VARCHAR(100),
    category_id INT,
    synopsis TEXT,
    cast_crew TEXT,
    video_url VARCHAR(500),
    poster_image VARCHAR(500),
    screened BOOLEAN DEFAULT FALSE,
    score DECIMAL(5,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_festival_category (festival_id, category_id),
    INDEX idx_screened (screened)
);

-- Awards Table
CREATE TABLE awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT NOT NULL,
    film_id INT,
    award_type_id INT NOT NULL,
    category_id INT,
    recipient_name VARCHAR(200),
    recipient_type ENUM('film', 'person') DEFAULT 'film',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    FOREIGN KEY (film_id) REFERENCES films(id) ON DELETE SET NULL,
    FOREIGN KEY (award_type_id) REFERENCES award_types(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_festival_category (festival_id, category_id),
    INDEX idx_award_type (award_type_id)
);

-- Pages Table for CMS Content
CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    content LONGTEXT,
    excerpt TEXT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    page_type ENUM('page', 'post', 'custom') DEFAULT 'page',
    template VARCHAR(100) DEFAULT 'default',
    meta_title VARCHAR(200),
    meta_description TEXT,
    featured_image VARCHAR(500),
    sort_order INT DEFAULT 0,
    show_in_menu BOOLEAN DEFAULT TRUE,
    parent_id INT DEFAULT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE SET NULL,
    INDEX idx_status_type (status, page_type),
    INDEX idx_slug (slug),
    INDEX idx_menu (show_in_menu, sort_order)
);

-- Insert default pages
INSERT INTO pages (title, slug, content, status, show_in_menu, sort_order, created_by) VALUES
('Home', 'home', '<h1>Welcome to Our Film Festival</h1><p>Discover award-winning short films from talented filmmakers.</p>', 'published', FALSE, 0, 1),
('About', 'about', '<h1>About Our Festival</h1><p>Learn about our mission and history.</p>', 'published', TRUE, 1, 1),
('Rules & Guidelines', 'rules', '<h1>Rules & Guidelines</h1><p>Important submission guidelines and requirements.</p>', 'published', TRUE, 2, 1),
('Submit Your Film', 'submit', '<h1>Submit Your Film</h1><p>Ready to submit your masterpiece?</p>', 'published', TRUE, 3, 1),
('Past Festivals', 'festivals', '<h1>Past Festivals</h1><p>Explore our festival archive.</p>', 'published', TRUE, 4, 1),
('Contact', 'contact', '<h1>Contact Us</h1><p>Get in touch with our team.</p>', 'published', TRUE, 5, 1);

-- Media Files Table
CREATE TABLE media_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    file_type ENUM('image', 'video', 'audio', 'document', 'other') DEFAULT 'other',
    alt_text VARCHAR(200),
    caption TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_file_type (file_type),
    INDEX idx_uploaded_by (uploaded_by)
);

-- Create views for easier data access
CREATE VIEW festival_summary AS
SELECT 
    f.*,
    COUNT(DISTINCT films.id) as film_count,
    COUNT(DISTINCT awards.id) as award_count,
    u.full_name as created_by_name
FROM festivals f
LEFT JOIN films ON f.id = films.festival_id
LEFT JOIN awards ON f.id = awards.festival_id
LEFT JOIN admin_users u ON f.created_by = u.id
GROUP BY f.id;

CREATE VIEW award_summary AS
SELECT 
    a.*,
    f.title as film_title,
    f.director as film_director,
    at.name as award_name,
    at.icon as award_icon,
    c.name as category_name,
    fest.title as festival_title,
    fest.season,
    fest.year
FROM awards a
LEFT JOIN films f ON a.film_id = f.id
LEFT JOIN award_types at ON a.award_type_id = at.id
LEFT JOIN categories c ON a.category_id = c.id
LEFT JOIN festivals fest ON a.festival_id = fest.id;