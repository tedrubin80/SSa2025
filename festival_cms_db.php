-- Film Festival CMS Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS southern_shorts_cms;
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
    last_login TIMESTAMP NULL
);

-- Festivals table
CREATE TABLE festivals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    season ENUM('Spring', 'Summer', 'Fall', 'Winter') NOT NULL,
    year YEAR NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    meta_description TEXT,
    featured_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    festival_date DATE,
    submission_deadline DATE,
    awards_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES admin_users(id),
    INDEX idx_season_year (season, year),
    INDEX idx_status (status),
    INDEX idx_festival_date (festival_date)
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
    status ENUM('draft', 'published') DEFAULT 'published',
    menu_order INT DEFAULT 0,
    show_in_menu BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES admin_users(id),
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_menu_order (menu_order)
);

-- Awards categories table
CREATE TABLE award_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT,
    category_name VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    INDEX idx_festival_display (festival_id, display_order)
);

-- Awards table
CREATE TABLE awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT,
    category_id INT,
    award_type ENUM('Best', 'Excellence', 'Merit', 'Distinction', 'Special') NOT NULL,
    film_title VARCHAR(200) NOT NULL,
    filmmaker_names TEXT,
    description TEXT,
    display_order INT DEFAULT 0,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES award_categories(id) ON DELETE CASCADE,
    INDEX idx_festival_category (festival_id, category_id),
    INDEX idx_display_order (display_order)
);

-- Site settings table
CREATE TABLE site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_