<?php
// admin/festivals.php - Festivals management page
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->query("DELETE FROM festivals WHERE id = ?", [$id]);
        $_SESSION['success_message'] = 'Festival deleted successfully.';
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting festival: ' . $e->getMessage();
    }
    redirect(ADMIN_URL . '/festivals.php');
}

// Get all festivals ordered chronologically
$festivals = $db->fetchAll(
    "SELECT f.*, 
     (SELECT COUNT(*) FROM films WHERE festival_id = f.id) as film_count,
     (SELECT COUNT(*) FROM awards WHERE festival_id = f.id) as award_count
     FROM festivals f 
     ORDER BY f.year DESC, 
     CASE f.season 
         WHEN 'Winter' THEN 1 
         WHEN 'Spring' THEN 2 
         WHEN 'Summer' THEN 3 
         WHEN 'Fall' THEN 4 
     END DESC"
);

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
        <h2 class="text-ssa mb-0">Festivals Management</h2>
        <p class="text-muted">Manage film festival seasons and events</p>
    </div>
    <a href="' . ADMIN_URL . '/festivals_edit.php" class="btn btn-ssa">
        <i class="fas fa-plus me-2"></i>Create New Festival
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-film me-2"></i>All Festivals
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Festival</th>
                        <th>Season & Year</th>
                        <th>Films</th>
                        <th>Awards</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

if (empty($festivals)) {
    $content .= '<tr><td colspan="7" class="text-center text-muted py-4">
        <i class="fas fa-film fa-3x mb-3 d-block"></i>
        No festivals found. <a href="' . ADMIN_URL . '/festivals_edit.php">Create your first festival</a>.
    </td></tr>';
} else {
    foreach ($festivals as $festival) {
        $statusBadge = match($festival['status']) {
            'published' => '<span class="badge badge-published">Published</span>',
            'draft' => '<span class="badge badge-draft">Draft</span>',
            'archived' => '<span class="badge badge-archived">Archived</span>',
            default => '<span class="badge bg-secondary">' . ucfirst($festival['status']) . '</span>'
        };
        
        $content .= '
                    <tr>
                        <td>
                            <strong>' . escape($festival['title']) . '</strong>
                            ' . ($festival['description'] ? '<br><small class="text-muted">' . escape(substr($festival['description'], 0, 100)) . '...</small>' : '') . '
                        </td>
                        <td>
                            <span class="badge bg-info">' . escape($festival['season']) . '</span>
                            <span class="badge bg-secondary">' . escape($festival['year']) . '</span>
                        </td>
                        <td>
                            <span class="badge bg-primary">' . $festival['film_count'] . ' films</span>
                        </td>
                        <td>
                            <span class="badge bg-warning">' . $festival['award_count'] . ' awards</span>
                        </td>
                        <td>' . $statusBadge . '</td>
                        <td>' . escape(date('M j, Y', strtotime($festival['created_at']))) . '</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="' . ADMIN_URL . '/festivals_edit.php?id=' . $festival['id'] . '" 
                                   class="btn btn-sm btn-outline-ssa" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>';
        
        if ($festival['status'] === 'published') {
            $content .= '
                                <a href="' . SITE_URL . '/festival.php?id=' . $festival['id'] . '" 
                                   target="_blank" class="btn btn-sm btn-outline-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>';
        }
        
        $content .= '
                                <a href="' . ADMIN_URL . '/films.php?festival_id=' . $festival['id'] . '" 
                                   class="btn btn-sm btn-outline-primary" title="Manage Films">
                                    <i class="fas fa-video"></i>
                                </a>
                                <a href="' . ADMIN_URL . '/awards.php?festival_id=' . $festival['id'] . '" 
                                   class="btn btn-sm btn-outline-warning" title="Manage Awards">
                                    <i class="fas fa-trophy"></i>
                                </a>
                                <a href="' . ADMIN_URL . '/festivals.php?action=delete&id=' . $festival['id'] . '" 
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
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Festival Statistics</h6>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-4">
                        <h4 class="text-success">' . count(array_filter($festivals, fn($f) => $f['status'] === 'published')) . '</h4>
                        <small>Published</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-warning">' . count(array_filter($festivals, fn($f) => $f['status'] === 'draft')) . '</h4>
                        <small>Drafts</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-secondary">' . count(array_filter($festivals, fn($f) => $f['status'] === 'archived')) . '</h4>
                        <small>Archived</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Festival Management Tips</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-lightbulb text-warning me-2"></i>Use descriptive titles like "Summer 2024 Season" for easy identification</li>
                    <li><i class="fas fa-calendar text-info me-2"></i>Set proper start and end dates for each festival season</li>
                    <li><i class="fas fa-video text-primary me-2"></i>Add featured videos to showcase the best films from each festival</li>
                    <li><i class="fas fa-file-pdf text-danger me-2"></i>Upload program PDFs for attendees to download</li>
                    <li><i class="fas fa-eye text-success me-2"></i>Publish festivals when ready to make them visible to the public</li>
                </ul>
            </div>
        </div>
    </div>
</div>';

renderAdminLayout('Festivals', $content);
?>