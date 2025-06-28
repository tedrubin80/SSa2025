<?php
// admin/pages.php - Static Pages Management
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                $id = (int)$_POST['id'];
                $db->query("DELETE FROM pages WHERE id = ? AND page_type = 'static'", [$id]);
                $_SESSION['success'] = 'Page deleted successfully!';
                break;
                
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $db->query("UPDATE pages SET status = IF(status = 'published', 'draft', 'published') WHERE id = ?", [$id]);
                $_SESSION['success'] = 'Page status updated!';
                break;
                
            case 'update_menu_order':
                $pages = $_POST['pages'] ?? [];
                foreach ($pages as $id => $order) {
                    $db->query("UPDATE pages SET menu_order = ? WHERE id = ?", [(int)$order, (int)$id]);
                }
                $_SESSION['success'] = 'Menu order updated!';
                break;
        }
        header('Location: ' . ADMIN_URL . '/pages.php');
        exit;
    }
}

// Get all static pages
$pages = $db->fetchAll(
    "SELECT p.*, u.username as created_by_name 
     FROM pages p 
     LEFT JOIN admin_users u ON p.created_by = u.id 
     WHERE p.page_type = 'static' 
     ORDER BY p.menu_order ASC, p.title ASC"
);

$content = '';

// Display messages
if (isset($_SESSION['success'])) {
    $content .= '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Static Pages</h2>
        <p class="text-muted">Manage your website pages</p>
    </div>
    <div>
        <a href="' . ADMIN_URL . '/pages_edit.php" class="btn btn-ssa">
            <i class="fas fa-plus me-2"></i>Add New Page
        </a>
        <a href="' . ADMIN_URL . '/pages_import.php" class="btn btn-outline-ssa">
            <i class="fas fa-file-import me-2"></i>Import HTML
        </a>
    </div>
</div>';

if (empty($pages)) {
    $content .= '
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-file-alt fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">No Pages Found</h4>
            <p class="text-muted mb-4">Create your first static page or import existing HTML content.</p>
            <a href="' . ADMIN_URL . '/pages_edit.php" class="btn btn-ssa me-2">
                <i class="fas fa-plus me-2"></i>Create Page
            </a>
            <a href="' . ADMIN_URL . '/pages_import.php" class="btn btn-outline-ssa">
                <i class="fas fa-file-import me-2"></i>Import HTML
            </a>
        </div>
    </div>';
} else {
    $content .= '
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>All Pages (' . count($pages) . ')
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" id="menuOrderForm">
                <input type="hidden" name="action" value="update_menu_order">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="60">Order</th>
                                <th>Title</th>
                                <th>Slug</th>
                                <th width="100">Status</th>
                                <th width="120">Menu</th>
                                <th width="120">Updated</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

    foreach ($pages as $page) {
        $statusBadge = $page['status'] === 'published' ? 'bg-success' : 'bg-warning';
        $menuBadge = $page['show_in_menu'] ? 'bg-info' : 'bg-secondary';
        
        $content .= '
                            <tr>
                                <td>
                                    <input type="number" class="form-control form-control-sm" 
                                           name="pages[' . $page['id'] . ']" 
                                           value="' . $page['menu_order'] . '" 
                                           min="0" style="width: 60px;">
                                </td>
                                <td>
                                    <strong>' . escape($page['title']) . '</strong>
                                    <br><small class="text-muted">' . escape($page['created_by_name'] ?? 'Unknown') . '</small>
                                </td>
                                <td>
                                    <code>' . escape($page['slug']) . '</code>
                                    <br><a href="' . SITE_URL . '/' . $page['slug'] . '.php" target="_blank" class="small">
                                        <i class="fas fa-external-link-alt me-1"></i>View
                                    </a>
                                </td>
                                <td>
                                    <span class="badge ' . $statusBadge . '">' . ucfirst($page['status']) . '</span>
                                </td>
                                <td>
                                    <span class="badge ' . $menuBadge . '">' . ($page['show_in_menu'] ? 'Visible' : 'Hidden') . '</span>
                                </td>
                                <td>
                                    <small>' . formatDate($page['updated_at']) . '</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="' . ADMIN_URL . '/pages_edit.php?id=' . $page['id'] . '" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="toggleStatus(' . $page['id'] . ')" title="Toggle Status">
                                            <i class="fas fa-eye' . ($page['status'] === 'published' ? '-slash' : '') . '"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deletePage(' . $page['id'] . ', \'' . escape($page['title']) . '\')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>';
    }

    $content .= '
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-outline-ssa">
                        <i class="fas fa-save me-2"></i>Update Menu Order
                    </button>
                </div>
            </form>
        </div>
    </div>';
}

$content .= '
<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Toggle Status Form -->
<form id="toggleForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="id" id="toggleId">
</form>

<script>
function deletePage(id, title) {
    if (confirm("Are you sure you want to delete the page \\"" + title + "\\"? This action cannot be undone.")) {
        document.getElementById("deleteId").value = id;
        document.getElementById("deleteForm").submit();
    }
}

function toggleStatus(id) {
    document.getElementById("toggleId").value = id;
    document.getElementById("toggleForm").submit();
}
</script>';

renderAdminLayout('Static Pages', $content);
?>

<!-- pages_edit.php - Create/Edit Static Pages -->
<?php
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Check if editing existing page
$pageId = $_GET['id'] ?? null;
$page = null;

if ($pageId) {
    $page = $db->fetchOne("SELECT * FROM pages WHERE id = ? AND page_type = 'static'", [$pageId]);
    if (!$page) {
        header('Location: ' . ADMIN_URL . '/pages.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $showInMenu = isset($_POST['show_in_menu']) ? 1 : 0;
    $menuOrder = (int)($_POST['menu_order'] ?? 0);
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($slug)) {
        $slug = createSlug($title);
    } else {
        $slug = createSlug($slug);
    }
    
    // Check slug uniqueness
    $existingSlug = $db->fetchOne(
        "SELECT id FROM pages WHERE slug = ? AND id != ?", 
        [$slug, $pageId ?: 0]
    );
    if ($existingSlug) {
        $errors[] = 'A page with this slug already exists.';
    }
    
    if (empty($errors)) {
        try {
            if ($pageId) {
                // Update existing page
                $db->query(
                    "UPDATE pages SET title = ?, slug = ?, content = ?, meta_description = ?, 
                     status = ?, show_in_menu = ?, menu_order = ?, updated_at = NOW() 
                     WHERE id = ?",
                    [$title, $slug, $content, $metaDescription, $status, $showInMenu, $menuOrder, $pageId]
                );
                $success = 'Page updated successfully!';
            } else {
                // Create new page
                $db->query(
                    "INSERT INTO pages (title, slug, content, meta_description, status, show_in_menu, 
                     menu_order, page_type, created_by, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'static', ?, NOW(), NOW())",
                    [$title, $slug, $content, $metaDescription, $status, $showInMenu, $menuOrder, $_SESSION['admin_user_id']]
                );
                $pageId = $db->lastInsertId();
                $success = 'Page created successfully!';
                
                // Refresh page data
                $page = $db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Set default values
if (!$page) {
    $page = [
        'title' => '',
        'slug' => '',
        'content' => '',
        'meta_description' => '',
        'status' => 'draft',
        'show_in_menu' => 1,
        'menu_order' => 0
    ];
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

$pageTitle = $pageId ? 'Edit Page' : 'Create New Page';

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">' . $pageTitle . '</h2>
        <p class="text-muted">Create and manage static pages for your website</p>
    </div>
    <a href="' . ADMIN_URL . '/pages.php" class="btn btn-outline-ssa">
        <i class="fas fa-arrow-left me-2"></i>Back to Pages
    </a>
</div>

<form method="POST">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
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
                        <label for="slug" class="form-label">URL Slug</label>
                        <div class="input-group">
                            <span class="input-group-text">' . SITE_URL . '/</span>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="' . escape($page['slug']) . '">
                            <span class="input-group-text">.php</span>
                        </div>
                        <div class="form-text">Leave blank to auto-generate from title</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="20">' . escape($page['content']) . '</textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="meta_description" class="form-label">Meta Description</label>
                        <textarea class="form-control" id="meta_description" name="meta_description" 
                                  rows="3" maxlength="160">' . escape($page['meta_description']) . '</textarea>
                        <div class="form-text">Recommended: 150-160 characters for SEO</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Page Settings</h5>
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
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_in_menu" name="show_in_menu" value="1"' . 
                            ($page['show_in_menu'] ? ' checked' : '') . '>
                            <label class="form-check-label" for="show_in_menu">
                                Show in Navigation Menu
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="menu_order" class="form-label">Menu Order</label>
                        <input type="number" class="form-control" id="menu_order" name="menu_order" 
                               value="' . $page['menu_order'] . '" min="0">
                        <div class="form-text">Lower numbers appear first</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-ssa">
                            <i class="fas fa-save me-2"></i>' . ($pageId ? 'Update' : 'Create') . ' Page
                        </button>';

if ($pageId && $page['status'] === 'published') {
    $content .= '
                        <a href="' . SITE_URL . '/' . $page['slug'] . '.php" target="_blank" class="btn btn-outline-ssa">
                            <i class="fas fa-external-link-alt me-2"></i>View Page
                        </a>';
}

$content .= '
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: "#content",
    height: 500,
    plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount",
    toolbar: "undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help",
    content_style: "body { font-family: Verdana, Arial, sans-serif; font-size: 14px; }",
    menubar: false,
    branding: false
});

// Auto-generate slug from title
document.getElementById("title").addEventListener("input", function() {
    if (!document.getElementById("slug").value) {
        let slug = this.value.toLowerCase()
            .replace(/[^a-z0-9 -]/g, "")
            .replace(/\s+/g, "-")
            .replace(/-+/g, "-")
            .replace(/^-|-$/g, "");
        document.getElementById("slug").value = slug;
    }
});
</script>';

renderAdminLayout($pageTitle, $content);
?>