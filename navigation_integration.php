<?php
// includes/navigation.php - Dynamic Navigation System
function getNavigationMenu() {
    $db = Database::getInstance();
    
    // Get published pages that should show in menu
    $menuPages = $db->fetchAll(
        "SELECT title, slug FROM pages 
         WHERE status = 'published' AND show_in_menu = 1 
         ORDER BY menu_order ASC, title ASC"
    );
    
    // Build navigation array with default items
    $navigation = [
        ['title' => 'Home', 'url' => SITE_URL . '/', 'slug' => 'home'],
        ['title' => 'Rules', 'url' => SITE_URL . '/rules.php', 'slug' => 'rules'],
        ['title' => 'Entry Link', 'url' => getSetting('filmfreeway_url', '#'), 'slug' => 'entry', 'external' => true],
        ['title' => 'Festivals', 'url' => SITE_URL . '/festivals.php', 'slug' => 'festivals']
    ];
    
    // Add dynamic pages
    foreach ($menuPages as $page) {
        // Skip if it's already in default navigation
        $slugs = array_column($navigation, 'slug');
        if (!in_array($page['slug'], $slugs)) {
            $navigation[] = [
                'title' => $page['title'],
                'url' => SITE_URL . '/' . $page['slug'] . '.php',
                'slug' => $page['slug']
            ];
        }
    }
    
    // Add remaining default items
    $navigation[] = ['title' => 'Store', 'url' => SITE_URL . '/store.php', 'slug' => 'store'];
    $navigation[] = ['title' => 'Contact', 'url' => SITE_URL . '/contact.php', 'slug' => 'contact'];
    
    return $navigation;
}

function renderNavigation($currentSlug = '') {
    $navigation = getNavigationMenu();
    $html = '';
    
    foreach ($navigation as $item) {
        $isActive = ($currentSlug === $item['slug']) ? ' active' : '';
        $target = isset($item['external']) ? ' target="_blank"' : '';
        
        $html .= '<a href="' . $item['url'] . '" class="nav-link text-white' . $isActive . '"' . $target . '>';
        $html .= $item['title'];
        if (isset($item['external'])) {
            $html .= ' <i class="fas fa-external-link-alt ms-1"></i>';
        }
        $html .= '</a>';
    }
    
    return $html;
}

// includes/layout.php - Updated Layout System
function renderLayout($title, $content, $slug = '', $metaDescription = '') {
    $siteTitle = getSetting('site_title', 'Southern Shorts Awards');
    $fullTitle = $title . ' - ' . $siteTitle;
    
    // Get navigation
    $navigation = renderNavigation($slug);
    
    // Get FilmFreeway URL for submit button
    $filmFreewayUrl = getSetting('filmfreeway_url', '#');
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= escape($fullTitle) ?></title>
        <?php if ($metaDescription): ?>
        <meta name="description" content="<?= escape($metaDescription) ?>">
        <?php endif; ?>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        
        <style>
        :root {
            --ssa-red: #CC3300;
            --ssa-dark-red: #993300;
            --ssa-bg: #f7f7e7;
            --ssa-gold: #FFD700;
        }
        
        body {
            background-color: var(--ssa-bg);
            font-family: Verdana, Arial, Helvetica, sans-serif;
            font-size: 14px;
        }
        
        .text-ssa {
            color: var(--ssa-red) !important;
        }
        
        .bg-ssa {
            background-color: var(--ssa-red) !important;
        }
        
        .btn-ssa {
            background-color: var(--ssa-red);
            border-color: var(--ssa-red);
            color: white;
        }
        
        .btn-ssa:hover {
            background-color: var(--ssa-dark-red);
            border-color: var(--ssa-dark-red);
            color: white;
        }
        
        .btn-outline-ssa {
            border-color: var(--ssa-red);
            color: var(--ssa-red);
        }
        
        .btn-outline-ssa:hover {
            background-color: var(--ssa-red);
            border-color: var(--ssa-red);
            color: white;
        }
        
        .navbar-brand img {
            height: 60px;
        }
        
        .navbar-nav .nav-link {
            font-weight: bold;
            padding: 0.5rem 1rem !important;
            border-right: 1px solid rgba(255,255,255,0.2);
        }
        
        .navbar-nav .nav-link:last-child {
            border-right: none;
        }
        
        .navbar-nav .nav-link.active {
            background-color: rgba(255,255,255,0.1);
        }
        
        .festival-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .festival-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .festival-title {
            color: var(--ssa-red);
            margin-bottom: 1rem;
        }
        
        .submit-button {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .footer {
            background-color: var(--ssa-dark-red);
            color: white;
            margin-top: 4rem;
        }
        
        .content-wrapper {
            min-height: calc(100vh - 200px);
        }
        
        @media (max-width: 768px) {
            .submit-button {
                position: relative;
                right: auto;
                top: auto;
                transform: none;
                margin: 1rem 0;
            }
            
            .navbar-nav {
                text-align: center;
            }
            
            .navbar-nav .nav-link {
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,0.2);
            }
        }
        </style>
    </head>
    <body>
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg bg-ssa">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?= SITE_URL ?>">
                    <img src="<?= SITE_URL ?>/assets/images/SSA-Logo.png" alt="Southern Shorts Awards" class="img-fluid">
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <div class="navbar-nav ms-auto">
                        <?= $navigation ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Tagline -->
        <div class="bg-danger text-white text-center py-2">
            <strong>In Recognition of Quality Filmcraft</strong>
        </div>
        
        <!-- Submit Button (Desktop) -->
        <div class="d-none d-lg-block">
            <a href="<?= escape($filmFreewayUrl) ?>" target="_blank" class="btn btn-ssa btn-lg submit-button">
                <i class="fas fa-upload me-2"></i>Submit Film
            </a>
        </div>
        
        <!-- Main Content -->
        <div class="container-fluid content-wrapper">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-2 d-none d-lg-block p-4">
                    <div class="sticky-top">
                        <h6 class="text-ssa border-bottom border-danger pb-2">Call for Entries</h6>
                        <ul class="list-unstyled small">
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
                        
                        <hr class="border-danger">
                        
                        <h6 class="text-ssa">Mission Statement</h6>
                        <p class="small">Our goal is to recognize filmmakers whose short films demonstrate their ability to produce well-crafted motion pictures.</p>
                        
                        <!-- Mobile Submit Button -->
                        <div class="d-lg-none text-center my-4">
                            <a href="<?= escape($filmFreewayUrl) ?>" target="_blank" class="btn btn-ssa">
                                <i class="fas fa-upload me-2"></i>Submit Your Film
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Area -->
                <div class="col-lg-8 p-4">
                    <?= $content ?>
                </div>
                
                <!-- Right Sidebar -->
                <div class="col-lg-2 d-none d-lg-block p-4">
                    <div class="sticky-top">
                        <!-- Partner/Sponsor Banner -->
                        <div class="text-center mb-4">
                            <a href="http://www.filmmakerbasics.com" target="_blank">
                                <img src="<?= SITE_URL ?>/assets/images/FMB-Banner.png" alt="Filmmaker Basics" class="img-fluid">
                            </a>
                        </div>
                        
                        <!-- Recent News or Updates -->
                        <div class="card">
                            <div class="card-header bg-ssa text-white">
                                <h6 class="mb-0">Latest Updates</h6>
                            </div>
                            <div class="card-body">
                                <p class="small">Festival seasons are updated regularly. Check back for the latest results and upcoming deadlines.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer py-4">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-white">Southern Shorts Awards</h5>
                        <p class="mb-0">Recognizing excellence in short filmmaking since our founding.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="mb-2">
                            <strong>Contact:</strong><br>
                            Email: info@southernshortsawards.com<br>
                            Phone: (678) 310-7192
                        </div>
                    </div>
                </div>
                <hr class="border-light">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; <?= date('Y') ?> Southern Shorts Awards. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="<?= escape($filmFreewayUrl) ?>" target="_blank" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-film me-1"></i>Submit via FilmFreeway
                        </a>
                    </div>
                </div>
            </div>
        </footer>
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

// Menu management in admin
// admin/menu.php - Navigation Menu Management
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_menu') {
        $menuItems = $_POST['menu_items'] ?? [];
        
        foreach ($menuItems as $id => $data) {
            $showInMenu = isset($data['show_in_menu']) ? 1 : 0;
            $menuOrder = (int)($data['menu_order'] ?? 0);
            
            $db->query(
                "UPDATE pages SET show_in_menu = ?, menu_order = ? WHERE id = ?",
                [$showInMenu, $menuOrder, (int)$id]
            );
        }
        
        $_SESSION['success'] = 'Menu updated successfully!';
        header('Location: ' . ADMIN_URL . '/menu.php');
        exit;
    }
}

$pages = $db->fetchAll(
    "SELECT * FROM pages WHERE page_type = 'static' ORDER BY menu_order ASC, title ASC"
);

$content = '';

if (isset($_SESSION['success'])) {
    $content .= '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Navigation Menu</h2>
        <p class="text-muted">Manage which pages appear in the main navigation</p>
    </div>
    <a href="' . ADMIN_URL . '/pages.php" class="btn btn-outline-ssa">
        <i class="fas fa-file-alt me-2"></i>Manage Pages
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Menu Configuration</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_menu">
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> Some navigation items (Home, Entry Link, Festivals) are built-in and cannot be modified here.
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Page Title</th>
                            <th width="100">Show in Menu</th>
                            <th width="100">Menu Order</th>
                            <th width="100">Status</th>
                        </tr>
                    </thead>
                    <tbody>';

foreach ($pages as $page) {
    $statusBadge = $page['status'] === 'published' ? 'bg-success' : 'bg-warning';
    
    $content .= '
                        <tr>
                            <td>
                                <strong>' . escape($page['title']) . '</strong>
                                <br><small class="text-muted">/' . escape($page['slug']) . '.php</small>
                            </td>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="menu_items[' . $page['id'] . '][show_in_menu]" 
                                           value="1"' . ($page['show_in_menu'] ? ' checked' : '') . '>
                                </div>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm" 
                                       name="menu_items[' . $page['id'] . '][menu_order]" 
                                       value="' . $page['menu_order'] . '" min="0">
                            </td>
                            <td>
                                <span class="badge ' . $statusBadge . '">' . ucfirst($page['status']) . '</span>
                            </td>
                        </tr>';
}

$content .= '
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn btn-ssa">
                <i class="fas fa-save me-2"></i>Update Menu
            </button>
        </form>
    </div>
</div>';

renderAdminLayout('Navigation Menu', $content);
?>