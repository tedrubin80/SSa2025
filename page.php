<?php
// page.php - Dynamic page handler for database-driven pages
require_once 'config.php';
require_once 'includes/layout.php';

$db = Database::getInstance();

// Get the page slug from URL parameter or from the requesting script name
$slug = '';

if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
} else {
    // Extract slug from the current script name
    $scriptName = basename($_SERVER['SCRIPT_NAME'], '.php');
    $slug = $scriptName;
}

// Get page data from database
$page = $db->fetchOne(
    "SELECT * FROM pages WHERE slug = ? AND status = 'published'",
    [$slug]
);

if (!$page) {
    // Page not found - show 404
    http_response_code(404);
    $content = '
    <div class="text-center py-5">
        <i class="fas fa-exclamation-triangle fa-4x text-muted mb-4"></i>
        <h1 class="text-ssa">Page Not Found</h1>
        <p class="lead">The page you are looking for could not be found.</p>
        <a href="' . SITE_URL . '" class="btn btn-ssa">
            <i class="fas fa-home me-2"></i>Return Home
        </a>
    </div>';
    
    renderLayout('Page Not Found', $content);
    exit;
}

// Set page title and render content
$title = $page['title'];
$content = $page['content'];

// Add meta description if available
if (!empty($page['meta_description'])) {
    // This would typically go in the head section, but we'll add it as a comment for SEO
    $content = '<!-- Meta Description: ' . escape($page['meta_description']) . ' -->' . "\n" . $content;
}

renderLayout($title, $content, 'page-' . $slug);
?>