-- Southern Shorts Awards CMS Database Schema

CREATE DATABASE southern_shorts_cms;
USE southern_shorts_cms;

-- Users table for admin authentication
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Festivals table
CREATE TABLE festivals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    season ENUM('Spring', 'Summer', 'Fall', 'Winter') NOT NULL,
    year YEAR NOT NULL,
    start_date DATE,
    end_date DATE,
    description TEXT,
    featured_video_url VARCHAR(500),
    program_pdf_url VARCHAR(500),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_season_year (season, year)
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1
);

-- Award types table
CREATE TABLE award_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0
);

-- Films table
CREATE TABLE films (
    id INT PRIMARY KEY AUTO_INCREMENT,
    festival_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    directors TEXT,
    producers TEXT,
    description TEXT,
    runtime_minutes INT,
    video_url VARCHAR(500),
    poster_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Awards table
CREATE TABLE awards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    festival_id INT NOT NULL,
    film_id INT,
    award_type_id INT NOT NULL,
    category_id INT,
    recipient_name VARCHAR(200) NOT NULL,
    recipient_role VARCHAR(100),
    film_title VARCHAR(200),
    placement ENUM('Winner', 'Excellence', 'Merit', 'Distinction', 'Best of Show') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE,
    FOREIGN KEY (film_id) REFERENCES films(id) ON DELETE SET NULL,
    FOREIGN KEY (award_type_id) REFERENCES award_types(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Pages table for static content
CREATE TABLE pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    content LONGTEXT,
    meta_description TEXT,
    status ENUM('draft', 'published') DEFAULT 'draft',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT
);

-- Insert default categories
INSERT INTO categories (name, description, sort_order) VALUES
('Action/Adventure', 'Action and Adventure films', 1),
('Animation', 'Animated films', 2),
('Comedy', 'Comedy films', 3),
('Documentary', 'Documentary films', 4),
('Drama', 'Drama films', 5),
('Fan Films', 'Fan-made films', 6),
('Horror', 'Horror films', 7),
('Mystery/Thriller', 'Mystery and Thriller films', 8),
('Musicals', 'Musical films', 9),
('Puppetry', 'Puppetry films', 10),
('Science Fiction/Fantasy', 'Science Fiction and Fantasy films', 11),
('Student', 'Student films', 12),
('Web Series/Webisode', 'Web series and webisodes', 13),
('Western', 'Western films', 14),
('Made in Georgia', 'Films made in Georgia', 15),
('Review Only', 'Review only category', 16);

-- Insert default award types
INSERT INTO award_types (name, description, sort_order) VALUES
('Best Picture', 'Best Picture award', 1),
('Best Director', 'Best Director award', 2),
('Best Screenplay', 'Best Screenplay award', 3),
('Best Actor', 'Best Actor award', 4),
('Best Actress', 'Best Actress award', 5),
('Best Cinematographer', 'Best Cinematographer award', 6),
('Best Editor', 'Best Editor award', 7),
('Best Sound Design', 'Best Sound Design award', 8),
('Best Music', 'Best Music award', 9),
('Best Production Design', 'Best Production Design award', 10),
('Best Visual FX', 'Best Visual Effects award', 11),
('Best Makeup FX', 'Best Makeup Effects award', 12),
('Best Costume Design', 'Best Costume Design award', 13);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@southernshortsawards.com', 'admin');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_title', 'Southern Shorts Awards', 'Site title'),
('site_tagline', 'In Recognition of Quality Filmcraft', 'Site tagline'),
('contact_email', 'info@southernshortsawards.com', 'Contact email'),
('contact_phone', '(678) 310-7192', 'Contact phone'),
('filmfreeway_url', 'https://filmfreeway.com/festivals/8879', 'FilmFreeway submission URL');

-- Insert default pages
INSERT INTO pages (slug, title, content, status, sort_order) VALUES
('home', 'Home', '<p>Welcome to Southern Shorts Awards Film Festival</p>', 'published', 1),
('rules', 'Rules', '<p>Festival rules and guidelines</p>', 'published', 2),
('judges', 'Judges', '<p>Meet our judges</p>', 'published', 3),
('faq', 'FAQ', '<p>Frequently asked questions</p>', 'published', 4),
('scoring', 'Scoring', '<p>How we score films</p>', 'published', 5),
('awards', 'Awards', '<p>About our awards</p>', 'published', 6),
('about', 'About', '<p>About Southern Shorts Awards</p>', 'published', 7),
('contact', 'Contact', '<p>Contact information</p>', 'published', 8);