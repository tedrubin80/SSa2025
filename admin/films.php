<?php
// admin/films.php - Films Management System
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();
PermissionManager::requirePermission('films.view');

$db = Database::getInstance();
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_film':
            if (PermissionManager::hasPermission('films.create')) {
                $result = createFilm($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'update_film':
            if (PermissionManager::hasPermission('films.edit')) {
                $result = updateFilm($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'delete_film':
            if (PermissionManager::hasPermission('films.delete')) {
                deleteFilm($_POST['film_id']);
                $success = 'Film deleted successfully.';
            }
            break;
            
        case 'update_status':
            if (PermissionManager::hasPermission('films.edit')) {
                updateFilmStatus($_POST['film_id'], $_POST['status']);
                $success = 'Film status updated successfully.';
            }
            break;
            
        case 'bulk_import':
            if (PermissionManager::hasPermission('films.create')) {
                $result = bulkImportFilms($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
    }
}

function createFilm($data) {
    global $db;
    
    $errors = [];
    
    // Validation
    if (empty($data['festival_id'])) {
        $errors[] = 'Festival is required.';
    }
    
    if (empty($data['title'])) {
        $errors[] = 'Film title is required.';
    }
    
    if (empty($data['director'])) {
        $errors[] = 'Director is required.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $db->query(
            "INSERT INTO films 
             (festival_id, category_id, title, director, producer, writer, cinematographer, 
              editor, cast, duration_minutes, production_year, country, language, synopsis, 
              director_statement, video_url, trailer_url, poster_image, submission_date, 
              review_status, score, notes, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['festival_id'],
                $data['category_id'] ?: null,
                $data['title'],
                $data['director'],
                $data['producer'] ?: null,
                $data['writer'] ?: null,
                $data['cinematographer'] ?: null,
                $data['editor'] ?: null,
                $data['cast'] ?: null,
                $data['duration_minutes'] ?: null,
                $data['production_year'] ?: null,
                $data['country'] ?: null,
                $data['language'] ?: null,
                $data['synopsis'] ?: null,
                $data['director_statement'] ?: null,
                $data['video_url'] ?: null,
                $data['trailer_url'] ?: null,
                $data['poster_image'] ?: null,
                $data['submission_date'] ?: null,
                $data['review_status'] ?: 'pending',
                $data['score'] ?: null,
                $data['notes'] ?: null,
                $_SESSION['admin_user_id']
            ]
        );
        
        logActivity('film_created', "Created film: {$data['title']}");
        
        return ['success' => true, 'message' => 'Film created successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function updateFilm($data) {
    global $db;
    
    $errors = [];
    
    if (empty($data['film_id'])) {
        $errors[] = 'Film ID is required.';
    }
    
    if (empty($data['title'])) {
        $errors[] = 'Film title is required.';
    }
    
    if (empty($data['director'])) {
        $errors[] = 'Director is required.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $db->query(
            "UPDATE films SET 
             category_id = ?, title = ?, director = ?, producer = ?, writer = ?, 
             cinematographer = ?, editor = ?, cast = ?, duration_minutes = ?, 
             production_year = ?, country = ?, language = ?, synopsis = ?, 
             director_statement = ?, video_url = ?, trailer_url = ?, poster_image = ?, 
             submission_date = ?, review_status = ?, score = ?, notes = ?
             WHERE id = ?",
            [
                $data['category_id'] ?: null,
                $data['title'],
                $data['director'],
                $data['producer'] ?: null,
                $data['writer'] ?: null,
                $data['cinematographer'] ?: null,
                $data['editor'] ?: null,
                $data['cast'] ?: null,
                $data['duration_minutes'] ?: null,
                $data['production_year'] ?: null,
                $data['country'] ?: null,
                $data['language'] ?: null,
                $data['synopsis'] ?: null,
                $data['director_statement'] ?: null,
                $data['video_url'] ?: null,
                $data['trailer_url'] ?: null,
                $data['poster_image'] ?: null,
                $data['submission_date'] ?: null,
                $data['review_status'] ?: 'pending',
                $data['score'] ?: null,
                $data['notes'] ?: null,
                $data['film_id']
            ]
        );
        
        logActivity('film_updated', "Updated film ID: {$data['film_id']}");
        
        return ['success' => true, 'message' => 'Film updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function deleteFilm($filmId) {
    global $db;
    
    $film = $db->fetchOne("SELECT title FROM films WHERE id = ?", [$filmId]);
    
    $db->query("DELETE FROM films WHERE id = ?", [$filmId]);
    
    logActivity('film_deleted', "Deleted film: {$film['title']}");
}

function updateFilmStatus($filmId, $status) {
    global $db;
    
    $db->query("UPDATE films SET review_status = ? WHERE id = ?", [$status, $filmId]);
    
    logActivity('film_status_updated', "Updated film status ID: $filmId to $status");
}

function bulkImportFilms($data) {
    global $db;
    
    $errors = [];
    $imported = 0;
    
    if (empty($data['festival_id'])) {
        return ['success' => false, 'errors' => ['Festival is required for bulk import.']];
    }
    
    if (empty($data['bulk_films'])) {
        return ['success' => false, 'errors' => ['No film data provided.']];
    }
    
    $lines = explode("\n", trim($data['bulk_films']));
    
    try {
        $db->beginTransaction();
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Expected format: Title|Director|Category|Duration|Country|Synopsis
            $parts = array_map('trim', explode('|', $line));
            
            if (count($parts) < 2) {
                $errors[] = "Line " . ($lineNum + 1) . ": Invalid format. Expected: Title|Director|Category|Duration|Country|Synopsis";
                continue;
            }
            
            // Find category (optional)
            $categoryId = null;
            if (!empty($parts[2])) {
                $category = $db->fetchOne("SELECT id FROM categories WHERE name LIKE ?", ["%{$parts[2]}%"]);
                if ($category) {
                    $categoryId = $category['id'];
                }
            }
            
            // Insert film
            $db->query(
                "INSERT INTO films 
                 (festival_id, category_id, title, director, duration_minutes, country, synopsis, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['festival_id'],
                    $categoryId,
                    $parts[0], // title
                    $parts[1], // director
                    !empty($parts[3]) ? intval($parts[3]) : null, // duration
                    $parts[4] ?? null, // country
                    $parts[5] ?? null, // synopsis
                    $_SESSION['admin_user_id']
                ]
            );
            
            $imported++;
        }
        
        if (empty($errors) || $imported > 0) {
            $db->commit();
            logActivity('bulk_films_imported', "Imported $imported films to festival ID: {$data['festival_id']}");
            
            $message = "Successfully imported $imported films.";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " items had errors.";
            }
            
            return ['success' => true, 'message' => $message, 'errors' => $errors];
        } else {
            $db->rollback();
            return ['success' => false, 'errors' => $errors];
        }
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function logActivity($action, $description) {
    global $db;
    
    $db->query(
        "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
         VALUES (?, ?, ?, ?, NOW())",
        [
            $_SESSION['admin_user_id'],
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    );
}

// Get filter parameters
$festivalFilter = $_GET['festival'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build where clause
$whereConditions = [];
$params = [];

if ($festivalFilter) {
    $whereConditions[] = "f.festival_id = ?";
    $params[] = $festivalFilter;
}

if ($categoryFilter) {
    $whereConditions[] = "f.category_id = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter) {
    $whereConditions[] = "f.review_status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get films with details
$films = $db->fetchAll(
    "SELECT f.*, 
     c.name as category_name,
     fest.title as festival_title,
     fest.season,
     fest.year,
     u.full_name as created_by_name,
     (SELECT COUNT(*) FROM awards WHERE film_id = f.id) as award_count
     FROM films f
     LEFT JOIN categories c ON f.category_id = c.id
     LEFT JOIN festivals fest ON f.festival_id = fest.id
     LEFT JOIN admin_users u ON f.created_by = u.id
     $whereClause
     ORDER BY f.created_at DESC",
    $params
);

// Get filter options
$festivals = $db->fetchAll(
    "SELECT id, title, season, year FROM festivals ORDER BY year DESC, season DESC"
);

$categories = $db->fetchAll(
    "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order"
);

// Get film statistics
$stats = $db->fetchOne(
    "SELECT 
     COUNT(*) as total_films,
     COUNT(DISTINCT festival_id) as festivals_with_films,
     SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) as approved_films,
     SUM(CASE WHEN review_status = 'pending' THEN 1 ELSE 0 END) as pending_films,
     SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) as rejected_films,
     AVG(duration_minutes) as avg_duration
     FROM films"
);

$content = '';

// Display messages
if (!empty($errors)) {
    $content .= '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $content .= '<li>' . escape($error) . '</li>';
    }
    $content .= '</ul></div>';
}

if ($success) {
    $content .= '<div class="alert alert-success">' . escape($success) . '</div>';
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Films Management</h2>
        <p class="text-muted">Manage film submissions and entries</p>
    </div>
    <div class="btn-group">';

if (PermissionManager::hasPermission('films.create')) {
    $content .= '
        <a href="' . ADMIN_URL . '/films_edit.php" class="btn btn-ssa">
            <i class="fas fa-plus me-2"></i>Add Film
        </a>
        <button type="button" class="btn btn-outline-ssa" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
            <i class="fas fa-upload me-2"></i>Bulk Import
        </button>';
}

$content .= '
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['total_films'] . '</h4>
                <small>Total Films</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['approved_films'] . '</h4>
                <small>Approved</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['pending_films'] . '</h4>
                <small>Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['rejected_films'] . '</h4>
                <small>Rejected</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . round($stats['avg_duration']) . 'min</h4>
                <small>Avg Duration</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['festivals_with_films'] . '</h4>
                <small>Festivals</small>
            </div>
        </div>
    </div>
</div>

<!-- Films Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-film me-2"></i>Films
        </h5>
        
        <!-- Quick Filters -->
        <div class="btn-group btn-group-sm">
            <a href="?status=" class="btn ' . (empty($statusFilter) ? 'btn-ssa' : 'btn-outline-secondary') . '">All</a>
            <a href="?status=pending" class="btn ' . ($statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning') . '">Pending</a>
            <a href="?status=approved" class="btn ' . ($statusFilter === 'approved' ? 'btn-success' : 'btn-outline-success') . '">Approved</a>
            <a href="?status=rejected" class="btn ' . ($statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger') . '">Rejected</a>
        </div>
    </div>
    <div class="card-body">
        <!-- Advanced Filters -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <select class="form-select" name="festival">
                    <option value="">All Festivals</option>';

foreach ($festivals as $festival) {
    $selected = $festivalFilter == $festival['id'] ? ' selected' : '';
    $content .= '<option value="' . $festival['id'] . '"' . $selected . '>' . 
                escape($festival['title']) . ' (' . $festival['season'] . ' ' . $festival['year'] . ')</option>';
}

$content .= '
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>';

foreach ($categories as $category) {
    $selected = $categoryFilter == $category['id'] ? ' selected' : '';
    $content .= '<option value="' . $category['id'] . '"' . $selected . '>' . escape($category['name']) . '</option>';
}

$content .= '
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending"' . ($statusFilter === 'pending' ? ' selected' : '') . '>Pending</option>
                    <option value="approved"' . ($statusFilter === 'approved' ? ' selected' : '') . '>Approved</option>
                    <option value="rejected"' . ($statusFilter === 'rejected' ? ' selected' : '') . '>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-ssa w-100">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Film</th>
                        <th>Director</th>
                        <th>Festival</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Awards</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

if (empty($films)) {
    $content .= '<tr><td colspan="7" class="text-center text-muted py-4">
        <i class="fas fa-film fa-3x mb-3 d-block"></i>
        No films found. <a href="' . ADMIN_URL . '/films_edit.php">Add your first film</a>.
    </td></tr>';
} else {
    foreach ($films as $film) {
        $statusBadge = match($film['review_status']) {
            'approved' => '<span class="badge bg-success">Approved</span>',
            'rejected' => '<span class="badge bg-danger">Rejected</span>',
            'pending' => '<span class="badge bg-warning">Pending</span>',
            default => '<span class="badge bg-secondary">' . ucfirst($film['review_status']) . '</span>'
        };
        
        $content .= '
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-film text-primary me-2"></i>
                                <div>
                                    <strong>' . escape($film['title']) . '</strong>';
        
        if ($film['duration_minutes']) {
            $content .= '<br><small class="text-muted">' . $film['duration_minutes'] . ' minutes</small>';
        }
        
        if ($film['production_year']) {
            $content .= '<span class="badge bg-light text-dark ms-2">' . $film['production_year'] . '</span>';
        }
        
        $content .= '
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong>' . escape($film['director']) . '</strong>';
        
        if ($film['country']) {
            $content .= '<br><small class="text-muted">' . escape($film['country']) . '</small>';
        }
        
        $content .= '
                        </td>
                        <td>
                            <span class="badge bg-info">' . escape($film['season']) . ' ' . escape($film['year']) . '</span>
                            <br><small>' . escape($film['festival_title']) . '</small>
                        </td>
                        <td>';
        
        if ($film['category_name']) {
            $content .= '<span class="badge bg-secondary">' . escape($film['category_name']) . '</span>';
        } else {
            $content .= '<span class="text-muted">Uncategorized</span>';
        }
        
        $content .= '
                        </td>
                        <td>' . $statusBadge . '</td>
                        <td>';
        
        if ($film['award_count'] > 0) {
            $content .= '<span class="badge bg-warning"><i class="fas fa-trophy me-1"></i>' . $film['award_count'] . '</span>';
        } else {
            $content .= '<span class="text-muted">None</span>';
        }
        
        $content .= '
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">';
        
        if (PermissionManager::hasPermission('films.edit')) {
            $content .= '
                                <a href="' . ADMIN_URL . '/films_edit.php?id=' . $film['id'] . '" 
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>';
        }
        
        if ($film['video_url']) {
            $content .= '
                                <a href="' . escape($film['video_url']) . '" target="_blank" 
                                   class="btn btn-outline-info" title="Watch">
                                    <i class="fas fa-play"></i>
                                </a>';
        }
        
        if (PermissionManager::hasPermission('films.delete')) {
            $content .= '
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteFilm(' . $film['id'] . ', \'' . escape($film['title']) . '\')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>';
        }
        
        $content .= '
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
</div>';

// Bulk Import Modal
if (PermissionManager::hasPermission('films.create')) {
    $content .= '
<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="bulk_import">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Import Films</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="bulk_festival_id" class="form-label">Festival *</label>
                        <select class="form-select" id="bulk_festival_id" name="festival_id" required>
                            <option value="">Select Festival...</option>';

    foreach ($festivals as $festival) {
        $content .= '<option value="' . $festival['id'] . '">' . 
                    escape($festival['title']) . ' (' . $festival['season'] . ' ' . $festival['year'] . ')</option>';
    }

    $content .= '
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bulk_films" class="form-label">Films Data *</label>
                        <textarea class="form-control" id="bulk_films" name="bulk_films" rows="10" 
                                  placeholder="Enter one film per line in format:
Title|Director|Category|Duration|Country|Synopsis

Example:
My Amazing Short|John Smith|Narrative|15|USA|A compelling story about...
Another Film|Jane Doe|Documentary|22|Canada|An exploration of..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Format:</strong> Title|Director|Category|Duration|Country|Synopsis<br>
                        <strong>Note:</strong> Only Title and Director are required. Other fields are optional.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ssa">Import Films</button>
                </div>
            </form>
        </div>
    </div>
</div>';
}

$content .= '
<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_film">
    <input type="hidden" name="film_id" id="deleteFilmId">
</form>

<script>
function deleteFilm(filmId, filmTitle) {
    if (confirm("Are you sure you want to delete \\"" + filmTitle + "\\"? This action cannot be undone and will also remove any associated awards.")) {
        document.getElementById("deleteFilmId").value = filmId;
        document.getElementById("deleteForm").submit();
    }
}
</script>';

renderAdminLayout('Films Management', $content);