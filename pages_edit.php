<?php
// admin/pages_edit.php - Page create/edit form
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Initialize page data
$page = [
    'title' => '',
    'slug' => '',
    'content' => '',
    'meta_description' => '',
    'status' => 'draft',
    'sort_order' => 0
];

// Load existing page data for editing
if ($isEdit) {
    $existingPage = $db->fetchOne("SELECT * FROM pages WHERE id = ?", [$id]);
    if (!$existingPage) {
        $_SESSION['error_message'] = 'Page not found.';
        redirect(ADMIN_URL . '/pages.php');
    }
    $page = array_merge($page, $existingPage);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page['title'] = trim($_POST['title'] ?? '');
    $page['slug'] = trim($_POST['slug'] ?? '');
    $page['content'] = $_POST['content'] ?? '';
    $page['meta_description'] = trim($_POST['meta_description'] ?? '');
    $page['status'] = $_POST['status'] ?? 'draft';
    $page['sort_order'] = (int)($_POST['sort_order'] ?? 0);
    
    // Auto-generate slug if empty
    if (empty($page['slug']) && !empty($page['title'])) {
        $page['slug'] = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $page['title']));
        $page['slug'] = trim($page['slug'], '-');
    }
    
    // Validation
    $errors = [];
    
    if (empty($page['title'])) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($page['slug'])) {
        $errors[] = 'URL slug is required.';
    } elseif (!preg_match('/^[a-z0-9-]+$/', $page['slug'])) {
        $errors[] = 'URL slug can only contain lowercase letters, numbers, and hyphens.';
    }
    
    // Check for duplicate slug
    $duplicateCheck = $db->fetchOne(
        "SELECT id FROM pages WHERE slug = ? AND id != ?",
        [$page['slug'], $id]
    );
    if ($duplicateCheck) {
        $errors[] = 'A page with this URL slug already exists.';
    }
    
    // If no errors, save the page
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $db->query(
                    "UPDATE pages SET 
                     title = ?, slug = ?, content = ?, meta_description = ?, 
                     status = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?",
                    [
                        $page['title'], $page['slug'], $page['content'],
                        $page['meta_description'], $page['status'], $page['sort_order'], $id
                    ]
                );
                $_SESSION['success_message'] = 'Page updated successfully.';
            } else {
                $db->query(
                    "INSERT INTO pages (title, slug, content, meta_description, status, sort_order) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $page['title'], $page['slug'], $page['content'],
                        $page['meta_description'], $page['status'], $page['sort_order']
                    ]
                );
                $_SESSION['success_message'] = 'Page created successfully.';
            }
            redirect(ADMIN_URL . '/pages.php');
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Build the form content
$content = '';

// Display errors
if (!empty($errors)) {
    $content .= '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $content .= '<li>' . escape($error) . '</li>';
    }
    $content .= '</ul></div>';
}

$pageTitle = $isEdit ? 'Edit Page' : 'Create New Page';

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">' . $pageTitle . '</h2>
        <p class="text-muted">Configure page content and settings</p>
    </div>
    <a href="' . ADMIN_URL . '/pages.php" class="btn btn-outline-ssa">
        <i class="fas fa-arrow-left me-2"></i>Back to Pages
    </a>
</div>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Page Content</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Page Title *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="' . escape($page['title']) . '" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">URL Slug *</label>
                        <div class="input-group">
                            <span class="input-group-text">' . SITE_URL . '/</span>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="' . escape($page['slug']) . '" required pattern="[a-z0-9-]+">
                            <span class="input-group-text">.php</span>
                        </div>
                        <div class="form-text">Only lowercase letters, numbers, and hyphens allowed</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Page Content</label>
                        <textarea class="form-control tinymce" id="content" name="content" rows="20">' . escape($page['content']) . '</textarea>
                        <div class="form-text">Use the rich text editor to format your content. You can include HTML, embeds, and special functionality.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="meta_description" class="form-label">Meta Description</label>
                        <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="160">' . escape($page['meta_description']) . '</textarea>
                        <div class="form-text">Brief description for search engines (160 characters max)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Publishing Options</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft"' . ($page['status'] === 'draft' ? ' selected' : '') . '>Draft</option>
                            <option value="published"' . ($page['status'] === 'published' ? ' selected' : '') . '>Published</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" 
                               value="' . escape($page['sort_order']) . '" min="0">
                        <div class="form-text">Lower numbers appear first in navigation</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-ssa">
                            <i class="fas fa-save me-2"></i>' . ($isEdit ? 'Update Page' : 'Create Page') . '
                        </button>
                        
                        <a href="' . ADMIN_URL . '/pages.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>';

if ($isEdit && $page['status'] === 'published') {
    $content .= '
                        <a href="' . SITE_URL . '/' . $page['slug'] . '.php" target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>View Page
                        </a>';
}

$content .= '
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Special Page Types</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <small>
                            <strong>Store Page:</strong> Use Ecwid integration for e-commerce functionality<br>
                            <strong>Contact Page:</strong> Include contact forms and information<br>
                            <strong>Entry Page:</strong> Embed FilmFreeway submission widget
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Auto-generate slug from title
document.getElementById("title").addEventListener("input", function() {
    const title = this.value;
    const slug = title.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "");
    
    if (!document.getElementById("slug").value || document.getElementById("slug").dataset.autoGenerated) {
        document.getElementById("slug").value = slug;
        document.getElementById("slug").dataset.autoGenerated = "true";
    }
});

document.getElementById("slug").addEventListener("input", function() {
    this.dataset.autoGenerated = "false";
});
</script>';

renderAdminLayout($pageTitle, $content);
?>