<?php
// admin/pages_import.php - Import HTML Pages
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';
$previewData = null;

// Pre-defined page mappings based on existing HTML files
$predefinedPages = [
    'rules' => [
        'title' => 'Rules & Guidelines',
        'meta_description' => 'Complete rules and guidelines for submitting to the Southern Shorts Awards Film Festival.',
        'menu_order' => 2,
        'show_in_menu' => true
    ],
    'judges' => [
        'title' => 'Our Judges',
        'meta_description' => 'Meet the industry professional judges who review and score all film submissions.',
        'menu_order' => 8,
        'show_in_menu' => true
    ],
    'faq' => [
        'title' => 'Frequently Asked Questions',
        'meta_description' => 'Answers to frequently asked questions about the festival submission process.',
        'menu_order' => 4,
        'show_in_menu' => true
    ],
    'scoring' => [
        'title' => 'How We Score',
        'meta_description' => 'Learn how Southern Shorts Awards scores and evaluates film submissions.',
        'menu_order' => 5,
        'show_in_menu' => true
    ],
    'awards' => [
        'title' => 'Awards & Recognition',
        'meta_description' => 'Information about awards, certificates, and recognition given to winning films.',
        'menu_order' => 6,
        'show_in_menu' => true
    ],
    'about' => [
        'title' => 'About Southern Shorts',
        'meta_description' => 'Learn about the Southern Shorts Awards Film Festival and our mission.',
        'menu_order' => 9,
        'show_in_menu' => true
    ],
    'contact' => [
        'title' => 'Contact Us',
        'meta_description' => 'Get in touch with the Southern Shorts Awards team.',
        'menu_order' => 10,
        'show_in_menu' => true
    ],
    'store' => [
        'title' => 'Awards Store',
        'meta_description' => 'Purchase certificates, plaques, and trophies for your awards.',
        'menu_order' => 7,
        'show_in_menu' => true
    ]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'preview') {
        $htmlContent = $_POST['html_content'] ?? '';
        $pageType = $_POST['page_type'] ?? 'custom';
        
        $previewData = parseHtmlContent($htmlContent, $pageType);
        
        if (empty($previewData)) {
            $errors[] = 'Could not extract page content from the provided HTML.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
        $pageData = json_decode($_POST['page_data'], true);
        
        if ($pageData) {
            try {
                // Check if page already exists
                $existing = $db->fetchOne("SELECT id FROM pages WHERE slug = ?", [$pageData['slug']]);
                
                if ($existing) {
                    $errors[] = 'A page with slug "' . $pageData['slug'] . '" already exists.';
                } else {
                    $db->query(
                        "INSERT INTO pages (title, slug, content, meta_description, status, show_in_menu, menu_order, page_type, created_by, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, 'published', ?, ?, 'static', ?, NOW(), NOW())",
                        [
                            $pageData['title'],
                            $pageData['slug'],
                            $pageData['content'],
                            $pageData['meta_description'],
                            $pageData['show_in_menu'] ? 1 : 0,
                            $pageData['menu_order'],
                            $_SESSION['admin_user_id']
                        ]
                    );
                    
                    $pageId = $db->lastInsertId();
                    $success = 'Page imported successfully! <a href="' . ADMIN_URL . '/pages_edit.php?id=' . $pageId . '">Edit Page</a>';
                }
            } catch (Exception $e) {
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import_predefined') {
        $selectedPages = $_POST['selected_pages'] ?? [];
        $imported = 0;
        
        foreach ($selectedPages as $slug) {
            if (isset($predefinedPages[$slug])) {
                // Check if already exists
                $existing = $db->fetchOne("SELECT id FROM pages WHERE slug = ?", [$slug]);
                if (!$existing) {
                    $pageData = $predefinedPages[$slug];
                    $content = generateContentFromSlug($slug);
                    
                    $db->query(
                        "INSERT INTO pages (title, slug, content, meta_description, status, show_in_menu, menu_order, page_type, created_by, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, 'published', ?, ?, 'static', ?, NOW(), NOW())",
                        [
                            $pageData['title'],
                            $slug,
                            $content,
                            $pageData['meta_description'],
                            $pageData['show_in_menu'] ? 1 : 0,
                            $pageData['menu_order'],
                            $_SESSION['admin_user_id']
                        ]
                    );
                    $imported++;
                }
            }
        }
        
        if ($imported > 0) {
            $success = "Successfully imported {$imported} page(s)!";
        } else {
            $errors[] = 'No pages were imported. They may already exist.';
        }
    }
}

function parseHtmlContent($html, $pageType = 'custom') {
    if (empty($html)) return null;
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    
    $data = [
        'title' => '',
        'slug' => '',
        'content' => '',
        'meta_description' => '',
        'show_in_menu' => true,
        'menu_order' => 0
    ];
    
    // Extract title
    $titleElement = $dom->getElementsByTagName('title');
    if ($titleElement->length > 0) {
        $title = $titleElement->item(0)->textContent;
        $data['title'] = str_replace(['Southern Shorts ', ' - Southern Shorts'], '', $title);
        $data['slug'] = createSlug($data['title']);
    }
    
    // If it's a predefined page type, get the settings
    global $predefinedPages;
    if ($pageType !== 'custom' && isset($predefinedPages[$pageType])) {
        $predefined = $predefinedPages[$pageType];
        $data['title'] = $predefined['title'];
        $data['slug'] = $pageType;
        $data['meta_description'] = $predefined['meta_description'];
        $data['menu_order'] = $predefined['menu_order'];
        $data['show_in_menu'] = $predefined['show_in_menu'];
    }
    
    // Extract main content
    $xpath = new DOMXPath($dom);
    
    // Look for main content div (based on the HTML structure in documents)
    $contentDivs = $xpath->query('//div[contains(@class, "style5") or contains(@id, "apDiv6")]');
    
    if ($contentDivs->length > 0) {
        $contentDiv = $contentDivs->item(0);
        $data['content'] = cleanHtmlContent($dom->saveHTML($contentDiv));
    } else {
        // Fallback: get body content
        $body = $dom->getElementsByTagName('body');
        if ($body->length > 0) {
            $data['content'] = cleanHtmlContent($dom->saveHTML($body->item(0)));
        }
    }
    
    return !empty($data['title']) ? $data : null;
}

function cleanHtmlContent($html) {
    // Remove inline styles, absolute positioning, and clean up HTML
    $html = preg_replace('/style="[^"]*"/', '', $html);
    $html = preg_replace('/class="[^"]*"/', '', $html);
    $html = preg_replace('/<div[^>]*>/', '<div>', $html);
    $html = preg_replace('/<span[^>]*>/', '<span>', $html);
    
    // Convert to Bootstrap-friendly structure
    $html = str_replace(['<p class="style', '<h'], ['<p', '<h'], $html);
    
    // Remove empty tags and clean up
    $html = preg_replace('/<[^>]*>[\s]*<\/[^>]*>/', '', $html);
    $html = preg_replace('/\s+/', ' ', $html);
    
    return trim($html);
}

function generateContentFromSlug($slug) {
    // Generate basic content based on slug (this would contain the actual content from your documents)
    $content = [
        'rules' => '<h2>Rules & Guidelines</h2><p>Complete submission guidelines and rules for the Southern Shorts Awards Film Festival.</p>',
        'judges' => '<h2>Our Judges</h2><p>Meet our panel of industry professional judges who review and score film submissions.</p>',
        'faq' => '<h2>Frequently Asked Questions</h2><p>Common questions and answers about the festival submission process.</p>',
        'scoring' => '<h2>How We Score</h2><p>Learn about our professional 3-judge scoring system and award criteria.</p>',
        'awards' => '<h2>Awards & Recognition</h2><p>Information about certificates, plaques, trophies, and laurels for winning films.</p>',
        'about' => '<h2>About Southern Shorts Awards</h2><p>Learn about our mission to recognize quality filmcraft in short films.</p>',
        'contact' => '<h2>Contact Us</h2><p>Get in touch with the Southern Shorts Awards team.</p>',
        'store' => '<h2>Awards Store</h2><p>Purchase physical awards and recognition items for your achievements.</p>'
    ];
    
    return $content[$slug] ?? '<p>Content needs to be added for this page.</p>';
}

$content = '';

// Display messages
if (!empty($errors)) {
    $content .= '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $content .= '<li>' . $error . '</li>';
    }
    $content .= '</ul></div>';
}

if ($success) {
    $content .= '<div class="alert alert-success">' . $success . '</div>';
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Import Pages</h2>
        <p class="text-muted">Import existing HTML pages or create from templates</p>
    </div>
    <a href="' . ADMIN_URL . '/pages.php" class="btn btn-outline-ssa">
        <i class="fas fa-arrow-left me-2"></i>Back to Pages
    </a>
</div>';

if (!$previewData) {
    // Show import options
    $content .= '
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-magic me-2"></i>Import Standard Pages
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Quickly create standard festival pages with pre-configured content and settings.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="import_predefined">
                        
                        <div class="row">';
    
    foreach ($predefinedPages as $slug => $pageData) {
        // Check if page already exists
        $exists = $db->fetchOne("SELECT id FROM pages WHERE slug = ?", [$slug]);
        $disabled = $exists ? ' disabled' : '';
        $checkboxClass = $exists ? 'text-muted' : '';
        
        $content .= '
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selected_pages[]" 
                                           value="' . $slug . '" id="page_' . $slug . '"' . $disabled . '>
                                    <label class="form-check-label ' . $checkboxClass . '" for="page_' . $slug . '">
                                        ' . escape($pageData['title']) . 
                                        ($exists ? ' <small>(exists)</small>' : '') . '
                                    </label>
                                </div>
                            </div>';
    }
    
    $content .= '
                        </div>
                        
                        <button type="submit" class="btn btn-ssa mt-3">
                            <i class="fas fa-download me-2"></i>Import Selected Pages
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-import me-2"></i>Import HTML Content
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Import content from existing HTML files by pasting the HTML code.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="preview">
                        
                        <div class="mb-3">
                            <label for="page_type" class="form-label">Page Type</label>
                            <select class="form-select" id="page_type" name="page_type">
                                <option value="custom">Custom Page</option>';
    
    foreach ($predefinedPages as $slug => $pageData) {
        $content .= '<option value="' . $slug . '">' . escape($pageData['title']) . '</option>';
    }
    
    $content .= '
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="html_content" class="form-label">HTML Content</label>
                            <textarea class="form-control" id="html_content" name="html_content" rows="10" required 
                                      placeholder="Paste the HTML content here...">' . escape($_POST['html_content'] ?? '') . '</textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-outline-ssa">
                            <i class="fas fa-search me-2"></i>Preview Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>';
} else {
    // Show preview
    $content .= '
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-eye me-2"></i>Preview Import
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Page Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Title:</strong></td><td>' . escape($previewData['title']) . '</td></tr>
                        <tr><td><strong>Slug:</strong></td><td><code>' . escape($previewData['slug']) . '</code></td></tr>
                        <tr><td><strong>Menu Order:</strong></td><td>' . $previewData['menu_order'] . '</td></tr>
                        <tr><td><strong>Show in Menu:</strong></td><td>' . ($previewData['show_in_menu'] ? 'Yes' : 'No') . '</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Meta Description</h6>
                    <p class="text-muted">' . escape($previewData['meta_description']) . '</p>
                </div>
            </div>
            
            <h6 class="mt-4">Content Preview</h6>
            <div class="border p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                ' . $previewData['content'] . '
            </div>
            
            <form method="POST" class="mt-4">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="page_data" value="' . escape(json_encode($previewData)) . '">
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Confirm Import
                    </button>
                    <a href="' . ADMIN_URL . '/pages_import.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Import
                    </a>
                </div>
            </form>
        </div>
    </div>';
}

renderAdminLayout('Import Pages', $content);
?>

<!-- Public page display system -->
<?php
// page.php - Dynamic page display (single file for all static pages)
require_once 'config.php';
require_once 'includes/layout.php';

$db = Database::getInstance();

// Get slug from URL (Apache rewrite or manual parameter)
$slug = $_GET['slug'] ?? basename($_SERVER['REQUEST_URI'], '.php');

// Remove .php extension if present
$slug = str_replace('.php', '', $slug);

// Fetch page from database
$page = $db->fetchOne(
    "SELECT * FROM pages WHERE slug = ? AND status = 'published'", 
    [$slug]
);

if (!$page) {
    // Page not found
    http_response_code(404);
    $content = '
    <div class="text-center py-5">
        <i class="fas fa-exclamation-triangle fa-4x text-muted mb-4"></i>
        <h1 class="display-4 text-muted">Page Not Found</h1>
        <p class="lead">The page you are looking for could not be found.</p>
        <a href="' . SITE_URL . '" class="btn btn-ssa">
            <i class="fas fa-home me-2"></i>Return Home
        </a>
    </div>';
    
    renderLayout('Page Not Found', $content);
    exit;
}

// Set meta description if available
$metaDescription = !empty($page['meta_description']) ? $page['meta_description'] : '';

renderLayout($page['title'], $page['content'], $slug, $metaDescription);
?>

<!-- .htaccess for clean URLs -->
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9_-]+)/?$ page.php?slug=$1 [L,QSA]

<!-- Helper function to create slugs -->
<?php
function createSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9 -]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}
?>