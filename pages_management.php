<?php
// admin/pages.php - Pages management
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->query("DELETE FROM pages WHERE id = ?", [$id]);
        $_SESSION['success_message'] = 'Page deleted successfully.';
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting page: ' . $e->getMessage();
    }
    redirect(ADMIN_URL . '/pages.php');
}

// Get all pages
$pages = $db->fetchAll("SELECT * FROM pages ORDER BY sort_order, title");

$content = '';

// Display messages
if (isset($_SESSION['success_message'])) {
    $content .= '<div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>' . escape($_SESSION['success_message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $content .= '<div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i>' . escape($_SESSION['error_message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['error_message']);
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Pages Management</h2>
        <p class="text-muted">Manage website pages and content</p>
    </div>
    <div class="btn-group">
        <a href="' . ADMIN_URL . '/pages_edit.php" class="btn btn-ssa">
            <i class="fas fa-plus me-2"></i>Create New Page
        </a>
        <a href="' . ADMIN_URL . '/import_pages.php" class="btn btn-outline-ssa">
            <i class="fas fa-file-import me-2"></i>Import Existing Pages
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-file-alt me-2"></i>All Pages
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>URL Slug</th>
                        <th>Status</th>
                        <th>Sort Order</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

if (empty($pages)) {
    $content .= '<tr><td colspan="6" class="text-center text-muted py-4">
        <i class="fas fa-file-alt fa-3x mb-3 d-block"></i>
        No pages found. <a href="' . ADMIN_URL . '/pages_edit.php">Create your first page</a>.
    </td></tr>';
} else {
    foreach ($pages as $page) {
        $statusBadge = $page['status'] === 'published' 
            ? '<span class="badge badge-published">Published</span>'
            : '<span class="badge badge-draft">Draft</span>';
        
        $content .= '
                    <tr>
                        <td>
                            <strong>' . escape($page['title']) . '</strong>';
        
        if (!empty($page['meta_description'])) {
            $content .= '<br><small class="text-muted">' . escape(substr($page['meta_description'], 0, 100)) . '...</small>';
        }
        
        $content .= '
                        </td>
                        <td>
                            <code>/' . escape($page['slug']) . '.php</code>
                        </td>
                        <td>' . $statusBadge . '</td>
                        <td>
                            <span class="badge bg-secondary">' . $page['sort_order'] . '</span>
                        </td>
                        <td>' . escape(date('M j, Y', strtotime($page['updated_at']))) . '</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="' . ADMIN_URL . '/pages_edit.php?id=' . $page['id'] . '" 
                                   class="btn btn-sm btn-outline-ssa" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>';
        
        if ($page['status'] === 'published') {
            $content .= '
                                <a href="' . SITE_URL . '/' . $page['slug'] . '.php" 
                                   target="_blank" class="btn btn-sm btn-outline-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>';
        }
        
        $content .= '
                                <a href="' . ADMIN_URL . '/pages.php?action=delete&id=' . $page['id'] . '" 
                                   class="btn btn-sm btn-outline-danger btn-delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>';
    }
}

$content .= '
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Page Statistics</h6>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-6">
                        <h4 class="text-success">' . count(array_filter($pages, fn($p) => $p['status'] === 'published')) . '</h4>
                        <small>Published</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning">' . count(array_filter($pages, fn($p) => $p['status'] === 'draft')) . '</h4>
                        <small>Drafts</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="' . ADMIN_URL . '/pages_edit.php" class="btn btn-ssa btn-sm">
                        <i class="fas fa-plus me-2"></i>Create New Page
                    </a>
                    <a href="' . ADMIN_URL . '/import_pages.php" class="btn btn-outline-ssa btn-sm">
                        <i class="fas fa-file-import me-2"></i>Import Existing Pages
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>';

renderAdminLayout('Pages', $content);
?>