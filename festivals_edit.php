<?php
// admin/festivals_edit.php - Festival create/edit form
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Initialize festival data
$festival = [
    'title' => '',
    'season' => 'Spring',
    'year' => date('Y'),
    'start_date' => '',
    'end_date' => '',
    'description' => '',
    'featured_video_url' => '',
    'program_pdf_url' => '',
    'status' => 'draft'
];

// Load existing festival data for editing
if ($isEdit) {
    $existingFestival = $db->fetchOne("SELECT * FROM festivals WHERE id = ?", [$id]);
    if (!$existingFestival) {
        $_SESSION['error_message'] = 'Festival not found.';
        redirect(ADMIN_URL . '/festivals.php');
    }
    $festival = array_merge($festival, $existingFestival);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $festival['title'] = trim($_POST['title'] ?? '');
    $festival['season'] = $_POST['season'] ?? 'Spring';
    $festival['year'] = (int)($_POST['year'] ?? date('Y'));
    $festival['start_date'] = $_POST['start_date'] ?? null;
    $festival['end_date'] = $_POST['end_date'] ?? null;
    $festival['description'] = trim($_POST['description'] ?? '');
    $festival['featured_video_url'] = trim($_POST['featured_video_url'] ?? '');
    $festival['program_pdf_url'] = trim($_POST['program_pdf_url'] ?? '');
    $festival['status'] = $_POST['status'] ?? 'draft';
    
    // Validation
    $errors = [];
    
    if (empty($festival['title'])) {
        $errors[] = 'Title is required.';
    }
    
    if ($festival['year'] < 2000 || $festival['year'] > 2100) {
        $errors[] = 'Please enter a valid year.';
    }
    
    // Check for duplicate season/year combination
    $duplicateCheck = $db->fetchOne(
        "SELECT id FROM festivals WHERE season = ? AND year = ? AND id != ?",
        [$festival['season'], $festival['year'], $id]
    );
    if ($duplicateCheck) {
        $errors[] = 'A festival for ' . $festival['season'] . ' ' . $festival['year'] . ' already exists.';
    }
    
    // Validate dates
    if (!empty($festival['start_date']) && !empty($festival['end_date'])) {
        if (strtotime($festival['start_date']) > strtotime($festival['end_date'])) {
            $errors[] = 'End date must be after start date.';
        }
    }
    
    // If no errors, save the festival
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $db->query(
                    "UPDATE festivals SET 
                     title = ?, season = ?, year = ?, start_date = ?, end_date = ?, 
                     description = ?, featured_video_url = ?, program_pdf_url = ?, status = ?, 
                     updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?",
                    [
                        $festival['title'], $festival['season'], $festival['year'],
                        $festival['start_date'] ?: null, $festival['end_date'] ?: null,
                        $festival['description'], $festival['featured_video_url'],
                        $festival['program_pdf_url'], $festival['status'], $id
                    ]
                );
                $_SESSION['success_message'] = 'Festival updated successfully.';
            } else {
                $db->query(
                    "INSERT INTO festivals 
                     (title, season, year, start_date, end_date, description, featured_video_url, program_pdf_url, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $festival['title'], $festival['season'], $festival['year'],
                        $festival['start_date'] ?: null, $festival['end_date'] ?: null,
                        $festival['description'], $festival['featured_video_url'],
                        $festival['program_pdf_url'], $festival['status']
                    ]
                );
                $_SESSION['success_message'] = 'Festival created successfully.';
            }
            redirect(ADMIN_URL . '/festivals.php');
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

$pageTitle = $isEdit ? 'Edit Festival' : 'Create New Festival';

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">' . $pageTitle . '</h2>
        <p class="text-muted">Configure festival details and content</p>
    </div>
    <a href="' . ADMIN_URL . '/festivals.php" class="btn btn-outline-ssa">
        <i class="fas fa-arrow-left me-2"></i>Back to Festivals
    </a>
</div>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Festival Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Festival Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="' . escape($festival['title']) . '" required>
                            <div class="form-text">Example: "Summer 2024 Season" or "Fall 2023 Awards"</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="season" class="form-label">Season *</label>
                            <select class="form-select" id="season" name="season" required>
                                <option value="Spring"' . ($festival['season'] === 'Spring' ? ' selected' : '') . '>Spring</option>
                                <option value="Summer"' . ($festival['season'] === 'Summer' ? ' selected' : '') . '>Summer</option>
                                <option value="Fall"' . ($festival['season'] === 'Fall' ? ' selected' : '') . '>Fall</option>
                                <option value="Winter"' . ($festival['season'] === 'Winter' ? ' selected' : '') . '>Winter</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="year" class="form-label">Year *</label>
                            <input type="number" class="form-control" id="year" name="year" 
                                   value="' . escape($festival['year']) . '" min="2000" max="2100" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="' . escape($festival['start_date']) . '">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="' . escape($festival['end_date']) . '">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control tinymce" id="description" name="description" rows="8">' . escape($festival['description']) . '</textarea>
                            <div class="form-text">Provide a detailed description of the festival, awards ceremony, and highlights.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Media & Resources</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="featured_video_url" class="form-label">Featured Video URL</label>
                            <input type="url" class="form-control" id="featured_video_url" name="featured_video_url" 
                                   value="' . escape($festival['featured_video_url']) . '">
                            <div class="form-text">Vimeo or YouTube embed URL for the festival highlights video</div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="program_pdf_url" class="form-label">Program PDF URL</label>
                            <input type="url" class="form-control" id="program_pdf_url" name="program_pdf_url" 
                                   value="' . escape($festival['program_pdf_url']) . '">
                            <div class="form-text">Direct link to downloadable festival program PDF</div>
                        </div>
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
                            <option value="draft"' . ($festival['status'] === 'draft' ? ' selected' : '') . '>Draft</option>
                            <option value="published"' . ($festival['status'] === 'published' ? ' selected' : '') . '>Published</option>
                            <option value="archived"' . ($festival['status'] === 'archived' ? ' selected' : '') . '>Archived</option>
                        </select>
                        <div class="form-text">
                            <strong>Draft:</strong> Only visible to admins<br>
                            <strong>Published:</strong> Visible to public<br>
                            <strong>Archived:</strong> Hidden but preserved
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-ssa">
                            <i class="fas fa-save me-2"></i>' . ($isEdit ? 'Update Festival' : 'Create Festival') . '
                        </button>
                        
                        <a href="' . ADMIN_URL . '/festivals.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>';

if ($isEdit && $festival['status'] === 'published') {
    $content .= '
                        <a href="' . SITE_URL . '/festival.php?id=' . $id . '" target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>View Festival
                        </a>';
}

$content .= '
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">';

if ($isEdit) {
    $content .= '
                        <a href="' . ADMIN_URL . '/films.php?festival_id=' . $id . '" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-video me-2"></i>Manage Films
                        </a>
                        <a href="' . ADMIN_URL . '/awards.php?festival_id=' . $id . '" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-trophy me-2"></i>Manage Awards
                        </a>';
}

$content .= '
                        <a href="' . ADMIN_URL . '/categories.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-tags me-2"></i>Manage Categories
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>';

renderAdminLayout($pageTitle, $content);
?>