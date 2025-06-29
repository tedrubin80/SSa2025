<?php
// includes/layout.php - Main layout template
require_once __DIR__ . '/../config.php';

function renderLayout($title, $content, $pageClass = '') {
    $siteTitle = getSetting('site_title', 'Southern Shorts Awards');
    $siteDescription = getSetting('site_description', 'In Recognition of Quality Filmcraft');
    $filmFreewayUrl = getSetting('filmfreeway_url', '#');
    
    // Get navigation pages
    $db = Database::getInstance();
    $pages = $db->fetchAll(
        "SELECT slug, title FROM pages WHERE status = 'published' AND show_in_menu = 1 ORDER BY sort_order"
    );
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($title) ?> - <?= escape($siteTitle) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --ssa-red: #CC3300;
            --ssa-dark-red: #993300;
            --ssa-burgundy: #A33900;
            --ssa-cream: #f7f7e7;
            --ssa-yellow: #FFFF00;
            --ssa-pink: #FF0066;
        }
        
        body {
            font-family: Verdana, Arial, Helvetica, sans-serif;
            background-color: var(--ssa-cream);
            margin: 0;
            padding: 0;
        }
        
        .header-logo {
            background-color: var(--ssa-cream);
            padding: 20px 0;
            text-align: center;
        }
        
        .header-logo img {
            max-width: 100%;
            height: auto;
        }
        
        .tagline {
            background-color: var(--ssa-cream);
            color: var(--ssa-red);
            font-size: 14px;
            font-weight: bold;
            font-style: italic;
            text-align: center;
            padding: 10px;
        }
        
        .main-nav {
            background-color: var(--ssa-dark-red);
            background-image: linear-gradient(135deg, var(--ssa-dark-red) 0%, var(--ssa-burgundy) 100%);
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-nav .nav-link {
            color: white !important;
            font-weight: bold;
            font-size: 16px;
            padding: 15px 20px !important;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--ssa-yellow) !important;
            background-color: rgba(255,255,255,0.1);
        }
        
        .submit-button {
            background-color: var(--ssa-red);
            border: 2px solid var(--ssa-red);
            border-radius: 8px;
            padding: 15px 25px;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        
        .submit-button:hover {
            background-color: var(--ssa-burgundy);
            border-color: var(--ssa-burgundy);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .submit-button img {
            max-width: 160px;
            height: auto;
        }
        
        .sidebar {
            background-color: var(--ssa-cream);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        
        .sidebar h4 {
            color: var(--ssa-red);
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--ssa-red);
            padding-bottom: 5px;
        }
        
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        
        .sidebar ul li {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .mission-statement {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--ssa-red);
        }
        
        .content-area {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .festival-card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .festival-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border-color: var(--ssa-red);
        }
        
        .festival-title {
            color: var(--ssa-red);
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .award-section {
            margin: 30px 0;
        }
        
        .award-section h3 {
            color: var(--ssa-red);
            font-size: 18px;
            font-weight: bold;
            border-bottom: 2px solid var(--ssa-red);
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .award-winner {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 3px solid var(--ssa-red);
        }
        
        .award-winner strong {
            color: var(--ssa-red);
        }
        
        .footer {
            background-color: var(--ssa-dark-red);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
        
        .footer a {
            color: var(--ssa-pink);
            text-decoration: none;
        }
        
        .footer a:hover {
            color: var(--ssa-yellow);
        }
        
        .btn-ssa {
            background-color: var(--ssa-red);
            border-color: var(--ssa-red);
            color: white;
        }
        
        .btn-ssa:hover {
            background-color: var(--ssa-burgundy);
            border-color: var(--ssa-burgundy);
            color: white;
        }
        
        .text-ssa {
            color: var(--ssa-red);
        }
        
        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            margin: 20px 0;
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .navbar-nav .nav-link {
                padding: 10px 15px !important;
                font-size: 14px;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .festival-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body class="<?= escape($pageClass) ?>">
    <!-- Header -->
    <header>
        <div class="header-logo">
            <img src="<?= SITE_URL ?>/assets/images/SSA-Logosm.png" alt="<?= escape($siteTitle) ?>" style="max-width: 802px; height: auto;">
        </div>
        <div class="tagline">
            <?= escape($siteDescription) ?>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg main-nav">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 1%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($pages as $page): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/<?= $page['slug'] ?>.php">
                            <?= escape($page['title']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/festivals.php">Festivals</a>
                    </li>
                </ul>
                
                <div class="submit-button">
                    <a href="<?= escape($filmFreewayUrl) ?>" target="_blank">
                        <img src="<?= SITE_URL ?>/assets/images/submit_btn-red_lg-1x.png" alt="Submit to FilmFreeway" style="max-width: 160px;">
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <h4>Call for Entries</h4>
                    <ul>
                        <li>• Action/Adventure</li>
                        <li>• Animation</li>
                        <li>• Comedy</li>
                        <li>• Documentary</li>
                        <li>• Drama</li>
                        <li>• Fan Films</li>
                        <li>• Horror</li>
                        <li>• Mystery/Thriller</li>
                        <li>• Musicals</li>
                        <li>• Puppetry</li>
                        <li>• Science Fiction/Fantasy</li>
                        <li>• Student</li>
                        <li>• Web Series/Webisode</li>
                        <li>• Western</li>
                        <li>• Made in Georgia</li>
                        <li>• Review Only</li>
                    </ul>
                </div>
                
                <div class="mission-statement">
                    <h4 style="color: var(--ssa-red); margin-bottom: 15px;">Mission Statement</h4>
                    <p style="font-size: 14px; margin: 0;">
                        Our goal is to recognize filmmakers whose short films demonstrate their ability to produce well-crafted motion pictures.
                    </p>
                </div>
            </div>
            
            <!-- Content -->
            <div class="col-lg-9">
                <div class="content-area">
                    <?= $content ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Contact Information</h5>
                    <p>
                        Email: <a href="mailto:<?= escape(getSetting('contact_email')) ?>"><?= escape(getSetting('contact_email')) ?></a><br>
                        Phone: <?= escape(getSetting('contact_phone', '(678) 310-7192')) ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <h5>Follow Us</h5>
                    <p>Stay updated with the latest festival news and submissions.</p>
                </div>
            </div>
            <hr style="border-color: var(--ssa-pink);">
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> <?= escape($siteTitle) ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
?>