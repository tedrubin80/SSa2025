<?php
// admin/dashboard.php - Admin dashboard
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();

// Get statistics
$stats = [
    'festivals' => $db->fetchOne("SELECT COUNT(*) as count FROM festivals")['count'],
    'published_festivals' => $db->fetchOne("SELECT COUNT(*) as count FROM festivals WHERE status = 'published'")['count'],
    'films' => $db->fetchOne("SELECT COUNT(*) as count FROM films")['count'],
    'awards' => $db->fetchOne("SELECT COUNT(*) as count FROM awards")['count'],
    'categories' => $db->fetchOne("SELECT COUNT(*) as count FROM categories WHERE active = 1")['count'],
    'pages' => $db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE status = 'published'")['count']
];

// Get recent festivals
$recentFestivals = $db->fetchAll(
    "SELECT id, title, season, year, status, created_at 
     FROM festivals 
     ORDER BY year DESC, 
     CASE season 
         WHEN 'Winter' THEN 1 
         WHEN 'Spring' THEN 2 
         WHEN 'Summer' THEN 3 
         WHEN 'Fall' THEN 4 
     END DESC 
     LIMIT 5"
);

// Get recent awards
$recentAwards = $db->fetchAll(
    "SELECT a.*, f.title as festival_title, f.season, f.year, at.name as award_name
     FROM awards a
     JOIN festivals f ON a.festival_id = f.id
     JOIN award_types at ON a.award_type_id = at.id
     ORDER BY a.created_at DESC
     LIMIT 10"
);

$content = '
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="alert alert-ssa">
            <i class="fas fa-info-circle me-2"></i>
            Welcome to the Southern Shorts Awards Content Management System. Use the navigation menu to manage festivals, films, awards, and website content.
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-2 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-film fa-2x text-ssa mb-2"></i>
                <h3 class="text-ssa">' . $stats['festivals'] . '</h3>
                <p class="card-text">Total Festivals</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-eye fa-2x text-success mb-2"></i>
                <h3 class="text-success">' . $stats['published_festivals'] . '</h3>
                <p class="card-text">Published</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-video fa-2x text-info mb-2"></i>
                <h3 class="text-info">' . $stats['films'] . '</h3>
                <p class="card-text">Films</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                <h3 class="text-warning">' . $stats['awards'] . '</h3>
                <p class="card-text">Awards</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-tags fa-2x text-secondary mb-2"></i>
                <h3 class="text-secondary">' . $stats['categories'] . '</h3>
                <p class="card-text">Categories</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                <h3 class="text-primary">' . $stats['pages'] . '</h3>
                <p class="card-text">Pages</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-film me-2"></i>Recent Festivals
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Festival</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

foreach ($recentFestivals as $festival) {
    $statusBadge = match($festival['status']) {
        'published' => '<span class="badge badge-published">Published</span>',
        'draft' => '<span class="badge badge-draft">Draft</span>',
        'archived' => '<span class="badge badge-archived">Archived</span>',
        default => '<span class="badge bg-secondary">' . ucfirst($festival['status']) . '</span>'
    };
    
    $content .= '
                            <tr>
                                <td>
                                    <strong>' . escape($festival['title']) . '</strong><br>
                                    <small class="text-muted">' . escape($festival['season'] . ' ' . $festival['year']) . '</small>
                                </td>
                                <td>' . $statusBadge . '</td>
                                <td>
                                    <a href="' . ADMIN_URL . '/festivals_edit.php?id=' . $festival['id'] . '" class="btn btn-sm btn-outline-ssa">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>';
}

$content .= '
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="' . ADMIN_URL . '/festivals.php" class="btn btn-ssa">
                        <i class="fas fa-film me-2"></i>Manage All Festivals
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Recent Awards
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Award</th>
                                <th>Recipient</th>
                                <th>Festival</th>
                            </tr>
                        </thead>
                        <tbody>';

foreach ($recentAwards as $award) {
    $placementBadge = match($award['placement']) {
        'Best of Show' => '<span class="badge bg-danger">Best of Show</span>',
        'Winner' => '<span class="badge bg-success">Winner</span>',
        'Excellence' => '<span class="badge bg-info">Excellence</span>',
        'Merit' => '<span class="badge bg-warning">Merit</span>',
        'Distinction' => '<span class="badge bg-primary">Distinction</span>',
        default => '<span class="badge bg-secondary">' . escape($award['placement']) . '</span>'
    };
    
    $content .= '
                            <tr>
                                <td>
                                    <strong>' . escape($award['award_name']) . '</strong><br>
                                    ' . $placementBadge . '
                                </td>
                                <td>' . escape($award['recipient_name']) . '</td>
                                <td>
                                    <small>' . escape($award['season'] . ' ' . $award['year']) . '</small>
                                </td>
                            </tr>';
}

$content .= '
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="' . ADMIN_URL . '/awards.php" class="btn btn-ssa">
                        <i class="fas fa-trophy me-2"></i>Manage All Awards
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="' . ADMIN_URL . '/festivals_edit.php" class="btn btn-ssa w-100">
                            <i class="fas fa-plus me-2"></i>New Festival
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="' . ADMIN_URL . '/films_edit.php" class="btn btn-outline-ssa w-100">
                            <i class="fas fa-plus me-2"></i>Add Film
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="' . ADMIN_URL . '/awards_edit.php" class="btn btn-outline-ssa w-100">
                            <i class="fas fa-plus me-2"></i>Add Award
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="' . ADMIN_URL . '/pages_edit.php" class="btn btn-outline-ssa w-100">
                            <i class="fas fa-plus me-2"></i>New Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';

renderAdminLayout('Dashboard', $content);
?>