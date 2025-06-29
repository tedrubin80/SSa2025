<?php
// admin/awards.php - Festival Awards Management System
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();
PermissionManager::requirePermission('awards.view');

$db = Database::getInstance();
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_award':
            if (PermissionManager::hasPermission('awards.create')) {
                $result = createAward($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'update_award':
            if (PermissionManager::hasPermission('awards.edit')) {
                $result = updateAward($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'delete_award':
            if (PermissionManager::hasPermission('awards.delete')) {
                deleteAward($_POST['award_id']);
                $success = 'Award deleted successfully.';
            }
            break;
            
        case 'bulk_import':
            if (PermissionManager::hasPermission('awards.create')) {
                $result = bulkImportAwards($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
    }
}

function createAward($data) {
    global $db;
    
    $errors = [];
    
    // Validation
    if (empty($data['festival_id'])) {
        $errors[] = 'Festival is required.';
    }
    
    if (empty($data['award_type_id'])) {
        $errors[] = 'Award type is required.';
    }
    
    if (empty($data['recipient_name']) && empty($data['film_id'])) {
        $errors[] = 'Either recipient name or film selection is required.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $db->query(
            "INSERT INTO awards 
             (festival_id, film_id, award_type_id, category_id, recipient_name, recipient_role, 
              award_title, description, placement, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['festival_id'],
                $data['film_id'] ?: null,
                $data['award_type_id'],
                $data['category_id'] ?: null,
                $data['recipient_name'] ?: null,
                $data['recipient_role'] ?: null,
                $data['award_title'] ?: null,
                $data['description'] ?: null,
                $data['placement'] ?: 1,
                $_SESSION['admin_user_id']
            ]
        );
        
        logActivity('award_created', "Created award ID: " . $db->lastInsertId());
        
        return ['success' => true, 'message' => 'Award created successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function updateAward($data) {
    global $db;
    
    $errors = [];
    
    if (empty($data['award_id'])) {
        $errors[] = 'Award ID is required.';
    }
    
    if (empty($data['recipient_name']) && empty($data['film_id'])) {
        $errors[] = 'Either recipient name or film selection is required.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $db->query(
            "UPDATE awards SET 
             film_id = ?, award_type_id = ?, category_id = ?, recipient_name = ?, 
             recipient_role = ?, award_title = ?, description = ?, placement = ?
             WHERE id = ?",
            [
                $data['film_id'] ?: null,
                $data['award_type_id'],
                $data['category_id'] ?: null,
                $data['recipient_name'] ?: null,
                $data['recipient_role'] ?: null,
                $data['award_title'] ?: null,
                $data['description'] ?: null,
                $data['placement'] ?: 1,
                $data['award_id']
            ]
        );
        
        logActivity('award_updated', "Updated award ID: {$data['award_id']}");
        
        return ['success' => true, 'message' => 'Award updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function deleteAward($awardId) {
    global $db;
    
    $award = $db->fetchOne("SELECT * FROM awards WHERE id = ?", [$awardId]);
    
    $db->query("DELETE FROM awards WHERE id = ?", [$awardId]);
    
    logActivity('award_deleted', "Deleted award ID: $awardId");
}

function bulkImportAwards($data) {
    global $db;
    
    $errors = [];
    $imported = 0;
    
    if (empty($data['festival_id'])) {
        return ['success' => false, 'errors' => ['Festival is required for bulk import.']];
    }
    
    if (empty($data['bulk_awards'])) {
        return ['success' => false, 'errors' => ['No award data provided.']];
    }
    
    $lines = explode("\n", trim($data['bulk_awards']));
    
    try {
        $db->beginTransaction();
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Expected format: Award Type|Category|Recipient|Role|Film Title|Description
            $parts = array_map('trim', explode('|', $line));
            
            if (count($parts) < 3) {
                $errors[] = "Line " . ($lineNum + 1) . ": Invalid format. Expected: Award Type|Category|Recipient|Role|Film Title|Description";
                continue;
            }
            
            // Find award type
            $awardType = $db->fetchOne("SELECT id FROM award_types WHERE name LIKE ?", ["%{$parts[0]}%"]);
            if (!$awardType) {
                $errors[] = "Line " . ($lineNum + 1) . ": Award type '{$parts[0]}' not found";
                continue;
            }
            
            // Find category (optional)
            $categoryId = null;
            if (!empty($parts[1])) {
                $category = $db->fetchOne("SELECT id FROM categories WHERE name LIKE ?", ["%{$parts[1]}%"]);
                if ($category) {
                    $categoryId = $category['id'];
                }
            }
            
            // Find film (optional)
            $filmId = null;
            if (!empty($parts[4])) {
                $film = $db->fetchOne(
                    "SELECT id FROM films WHERE festival_id = ? AND title LIKE ?",
                    [$data['festival_id'], "%{$parts[4]}%"]
                );
                if ($film) {
                    $filmId = $film['id'];
                }
            }
            
            // Insert award
            $db->query(
                "INSERT INTO awards 
                 (festival_id, film_id, award_type_id, category_id, recipient_name, recipient_role, description, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['festival_id'],
                    $filmId,
                    $awardType['id'],
                    $categoryId,
                    $parts[2] ?: null,
                    $parts[3] ?: null,
                    $parts[5] ?: null,
                    $_SESSION['admin_user_id']
                ]
            );
            
            $imported++;
        }
        
        if (empty($errors) || $imported > 0) {
            $db->commit();
            logActivity('bulk_awards_imported', "Imported $imported awards to festival ID: {$data['festival_id']}");
            
            $message = "Successfully imported $imported awards.";
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
$awardTypeFilter = $_GET['award_type'] ?? '';

// Build where clause
$whereConditions = [];
$params = [];

if ($festivalFilter) {
    $whereConditions[] = "a.festival_id = ?";
    $params[] = $festivalFilter;
}

if ($categoryFilter) {
    $whereConditions[] = "a.category_id = ?";
    $params[] = $categoryFilter;
}

if ($awardTypeFilter) {
    $whereConditions[] = "a.award_type_id = ?";
    $params[] = $awardTypeFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get awards with details
$awards = $db->fetchAll(
    "SELECT a.*, 
     f.title as film_title,
     f.director as film_director,
     at.name as award_name,
     at.icon as award_icon,
     c.name as category_name,
     fest.title as festival_title,
     fest.season,
     fest.year,
     u.full_name as created_by_name
     FROM awards a
     LEFT JOIN films f ON a.film_id = f.id
     LEFT JOIN award_types at ON a.award_type_id = at.id
     LEFT JOIN categories c ON a.category_id = c.id
     LEFT JOIN festivals fest ON a.festival_id = fest.id
     LEFT JOIN admin_users u ON a.created_by = u.id
     $whereClause
     ORDER BY fest.year DESC, fest.season DESC, a.created_at DESC",
    $params
);

// Get filter options
$festivals = $db->fetchAll(
    "SELECT id, title, season, year FROM festivals ORDER BY year DESC, season DESC"
);

$categories = $db->fetchAll(
    "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order"
);

$awardTypes = $db->fetchAll(
    "SELECT id, name FROM award_types WHERE is_active = 1 ORDER BY sort_order"
);

// Get award statistics
$stats = $db->fetchOne(
    "SELECT 
     COUNT(*) as total_awards,
     COUNT(DISTINCT festival_id) as festivals_with_awards,
     COUNT(DISTINCT film_id) as awarded_films,
     COUNT(DISTINCT award_type_id) as award_types_used
     FROM awards"
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
        <h2 class="text-ssa mb-0">Awards Management</h2>
        <p class="text-muted">Manage festival awards and recognition</p>
    </div>
    <div class="btn-group">';

if (PermissionManager::hasPermission('awards.create')) {
    $content .= '
        <button type="button" class="btn btn-ssa" data-bs-toggle="modal" data-bs-target="#createAwardModal">
            <i class="fas fa-plus me-2"></i>Add Award
        </button>
        <button type="button" class="btn btn-outline-ssa" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
            <i class="fas fa-upload me-2"></i>Bulk Import
        </button>';
}

$content .= '
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $stats['total_awards'] . '</h4>
                        <small>Total Awards</small>
                    </div>
                    <i class="fas fa-trophy fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $stats['festivals_with_awards'] . '</h4>
                        <small>Festivals</small>
                    </div>
                    <i class="fas fa-film fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $stats['awarded_films'] . '</h4>
                        <small>Awarded Films</small>
                    </div>
                    <i class="fas fa-video fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $stats['award_types_used'] . '</h4>
                        <small>Award Types</small>
                    </div>
                    <i class="fas fa-award fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="festival" class="form-label">Festival</label>
                <select class="form-select" id="festival" name="festival">
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
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>';

foreach ($categories as $category) {
    $selected = $categoryFilter == $category['id'] ? ' selected' : '';
    $content .= '<option value="' . $category['id'] . '"' . $selected . '>' . escape($category['name']) . '</option>';
}

$content .= '
                </select>
            </div>
            <div class="col-md-3">
                <label for="award_type" class="form-label">Award Type</label>
                <select class="form-select" id="award_type" name="award_type">
                    <option value="">All Award Types</option>';

foreach ($awardTypes as $awardType) {
    $selected = $awardTypeFilter == $awardType['id'] ? ' selected' : '';
    $content .= '<option value="' . $awardType['id'] . '"' . $selected . '>' . escape($awardType['name']) . '</option>';
}

$content .= '
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-ssa">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Awards Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-trophy me-2"></i>Awards
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Award</th>
                        <th>Recipient</th>
                        <th>Film</th>
                        <th>Festival</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

if (empty($awards)) {
    $content .= '<tr><td colspan="6" class="text-center text-muted py-4">
        <i class="fas fa-trophy fa-3x mb-3 d-block"></i>
        No awards found. <a href="#" data-bs-toggle="modal" data-bs-target="#createAwardModal">Create your first award</a>.
    </td></tr>';
} else {
    foreach ($awards as $award) {
        $content .= '
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-' . ($award['award_icon'] ?: 'trophy') . ' text-warning me-2"></i>
                                <div>
                                    <strong>' . escape($award['award_name']) . '</strong>';
        
        if ($award['award_title']) {
            $content .= '<br><small class="text-muted">' . escape($award['award_title']) . '</small>';
        }
        
        if ($award['placement'] > 1) {
            $content .= '<br><span class="badge bg-secondary">Place ' . $award['placement'] . '</span>';
        }
        
        $content .= '
                                </div>
                            </div>
                        </td>
                        <td>';
        
        if ($award['recipient_name']) {
            $content .= '<strong>' . escape($award['recipient_name']) . '</strong>';
            if ($award['recipient_role']) {
                $content .= '<br><small class="text-muted">' . escape($award['recipient_role']) . '</small>';
            }
        } else {
            $content .= '<span class="text-muted">Film Award</span>';
        }
        
        $content .= '
                        </td>
                        <td>';
        
        if ($award['film_title']) {
            $content .= '<strong>' . escape($award['film_title']) . '</strong>';
            if ($award['film_director']) {
                $content .= '<br><small class="text-muted">Dir: ' . escape($award['film_director']) . '</small>';
            }
        } else {
            $content .= '<span class="text-muted">No film</span>';
        }
        
        $content .= '
                        </td>
                        <td>
                            <span class="badge bg-info">' . escape($award['season']) . ' ' . escape($award['year']) . '</span>
                            <br><small>' . escape($award['festival_title']) . '</small>
                        </td>
                        <td>';
        
        if ($award['category_name']) {
            $content .= '<span class="badge bg-secondary">' . escape($award['category_name']) . '</span>';
        } else {
            $content .= '<span class="text-muted">General</span>';
        }
        
        $content .= '
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">';
        
        if (PermissionManager::hasPermission('awards.edit')) {
            $content .= '
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="editAward(' . $award['id'] . ')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>';
        }
        
        if (PermissionManager::hasPermission('awards.delete')) {
            $content .= '
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteAward(' . $award['id'] . ', \'' . escape($award['award_name']) . '\')" title="Delete">
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

// Create Award Modal
if (PermissionManager::hasPermission('awards.create')) {
    $content .= '
<!-- Create Award Modal -->
<div class="modal fade" id="createAwardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_award">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Award</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="festival_id" class="form-label">Festival *</label>
                                <select class="form-select" id="festival_id" name="festival_id" required>
                                    <option value="">Select Festival...</option>';

    foreach ($festivals as $festival) {
        $content .= '<option value="' . $festival['id'] . '">' . 
                    escape($festival['title']) . ' (' . $festival['season'] . ' ' . $festival['year'] . ')</option>';
    }

    $content .= '
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="award_type_id" class="form-label">Award Type *</label>
                                <select class="form-select" id="award_type_id" name="award_type_id" required>
                                    <option value="">Select Award Type...</option>';

    foreach ($awardTypes as $awardType) {
        $content .= '<option value="' . $awardType['id'] . '">' . escape($awardType['name']) . '</option>';
    }

    $content .= '
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category...</option>';

    foreach ($categories as $category) {
        $content .= '<option value="' . $category['id'] . '">' . escape($category['name']) . '</option>';
    }

    $content .= '
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="film_id" class="form-label">Film</label>
                                <select class="form-select" id="film_id" name="film_id">
                                    <option value="">Select Film...</option>
                                    <!-- Films will be loaded via JavaScript based on festival selection -->
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_name" class="form-label">Recipient Name</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_role" class="form-label">Recipient Role</label>
                                <input type="text" class="form-control" id="recipient_role" name="recipient_role" 
                                       placeholder="e.g. Director, Producer, Actor">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="award_title" class="form-label">Custom Award Title</label>
                                <input type="text" class="form-control" id="award_title" name="award_title" 
                                       placeholder="Optional custom title for this specific award">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="placement" class="form-label">Placement</label>
                                <select class="form-select" id="placement" name="placement">
                                    <option value="1">1st Place</option>
                                    <option value="2">2nd Place</option>
                                    <option value="3">3rd Place</option>
                                    <option value="4">Honorable Mention</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ssa">Create Award</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="bulk_import">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Import Awards</h5>
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
                        <label for="bulk_awards" class="form-label">Awards Data *</label>
                        <textarea class="form-control" id="bulk_awards" name="bulk_awards" rows="10" 
                                  placeholder="Enter one award per line in format:
Award Type|Category|Recipient|Role|Film Title|Description

Example:
Best Film|Narrative|John Smith|Director|My Amazing Short|Outstanding storytelling
Best Actor|Drama|Jane Doe|Lead Actor|Another Film|Compelling performance"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Format:</strong> Award Type|Category|Recipient|Role|Film Title|Description<br>
                        <strong>Note:</strong> Category, Role, Film Title, and Description are optional. 
                        Either Recipient or Film Title must be provided.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ssa">Import Awards</button>
                </div>
            </form>
        </div>
    </div>
</div>';
}

$content .= '
<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_award">
    <input type="hidden" name="award_id" id="deleteAwardId">
</form>

<script>
function deleteAward(awardId, awardName) {
    if (confirm("Are you sure you want to delete the " + awardName + " award? This action cannot be undone.")) {
        document.getElementById("deleteAwardId").value = awardId;
        document.getElementById("deleteForm").submit();
    }
}

function editAward(awardId) {
    // This would open an edit modal - similar implementation to create modal
    alert("Edit functionality will be implemented in the next version.");
}

// Load films when festival is selected
document.getElementById("festival_id")?.addEventListener("change", function() {
    const festivalId = this.value;
    const filmSelect = document.getElementById("film_id");
    
    // Clear existing options
    filmSelect.innerHTML = \'<option value="">Select Film...</option>\';
    
    if (festivalId) {
        // Fetch films for selected festival via AJAX
        fetch(`films_api.php?festival_id=${festivalId}`)
            .then(response => response.json())
            .then(films => {
                films.forEach(film => {
                    const option = document.createElement("option");
                    option.value = film.id;
                    option.textContent = film.title + (film.director ? ` (Dir: ${film.director})` : "");
                    filmSelect.appendChild(option);
                });
            })
            .catch(error => console.error("Error loading films:", error));
    }
});
</script>';

renderAdminLayout('Awards Management', $content);
?>