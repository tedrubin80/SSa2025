<?php
// admin/media.php - Media Management System
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();
PermissionManager::requirePermission('media.view');

$db = Database::getInstance();
$errors = [];
$success = '';

// Handle file uploads and actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload':
            if (PermissionManager::hasPermission('media.upload')) {
                $result = handleFileUpload($_FILES['file'] ?? null, $_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'update_media':
            if (PermissionManager::hasPermission('media.upload')) {
                $result = updateMediaFile($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'delete_media':
            if (PermissionManager::hasPermission('media.delete')) {
                $result = deleteMediaFile($_POST['media_id']);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
            }
            break;
    }
}

function handleFileUpload($file, $data) {
    global $db;
    
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'errors' => ['No file uploaded or upload error.']];
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'errors' => ['File size exceeds maximum limit of ' . formatBytes(MAX_FILE_SIZE) . '.']];
    }
    
    // Get file info
    $originalName = $file['name'];
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = $file['type'];
    
    // Determine file type and validate
    $fileType = 'other';
    if (in_array($fileExtension, ALLOWED_IMAGE_TYPES)) {
        $fileType = 'image';
    } elseif (in_array($fileExtension, ALLOWED_VIDEO_TYPES)) {
        $fileType = 'video';
    } elseif (in_array($fileExtension, ALLOWED_DOCUMENT_TYPES)) {
        $fileType = 'document';
    } elseif (in_array($fileExtension, ['mp3', 'wav', 'ogg', 'aac'])) {
        $fileType = 'audio';
    }
    
    if ($fileType === 'other') {
        return ['success' => false, 'errors' => ['File type not allowed. Allowed types: ' . 
                implode(', ', array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOCUMENT_TYPES))]];
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $fileExtension;
    $uploadDir = UPLOAD_PATH . '/' . $fileType . 's';
    $filePath = $uploadDir . '/' . $filename;
    $relativePath = $fileType . 's/' . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'errors' => ['Failed to move uploaded file.']];
    }
    
    // For images, create thumbnail
    if ($fileType === 'image') {
        createThumbnail($filePath, $uploadDir . '/thumb_' . $filename);
    }
    
    try {
        // Save to database
        $db->query(
            "INSERT INTO media_files 
             (filename, original_name, file_path, file_size, mime_type, file_type, alt_text, caption, uploaded_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $filename,
                $originalName,
                $relativePath,
                $file['size'],
                $mimeType,
                $fileType,
                $data['alt_text'] ?? '',
                $data['caption'] ?? '',
                $_SESSION['admin_user_id']
            ]
        );
        
        logActivity('media_uploaded', "Uploaded file: $originalName");
        
        return ['success' => true, 'message' => 'File uploaded successfully.'];
    } catch (Exception $e) {
        // Clean up file if database insert fails
        unlink($filePath);
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function createThumbnail($sourcePath, $thumbnailPath, $maxWidth = 300, $maxHeight = 200) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    // Create source image
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Save thumbnail
    switch ($mimeType) {
        case 'image/jpeg':
            imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case 'image/png':
            imagepng($thumbnail, $thumbnailPath);
            break;
        case 'image/gif':
            imagegif($thumbnail, $thumbnailPath);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return true;
}

function updateMediaFile($data) {
    global $db;
    
    $errors = [];
    
    if (empty($data['media_id'])) {
        $errors[] = 'Media ID is required.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $db->query(
            "UPDATE media_files SET alt_text = ?, caption = ? WHERE id = ?",
            [
                $data['alt_text'] ?? '',
                $data['caption'] ?? '',
                $data['media_id']
            ]
        );
        
        logActivity('media_updated', "Updated media file ID: {$data['media_id']}");
        
        return ['success' => true, 'message' => 'Media file updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function deleteMediaFile($mediaId) {
    global $db;
    
    // Get file info
    $media = $db->fetchOne("SELECT * FROM media_files WHERE id = ?", [$mediaId]);
    if (!$media) {
        return ['success' => false, 'message' => 'Media file not found.'];
    }
    
    // Delete physical file
    $filePath = UPLOAD_PATH . '/' . $media['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Delete thumbnail if it exists
    if ($media['file_type'] === 'image') {
        $thumbPath = dirname($filePath) . '/thumb_' . $media['filename'];
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
    }
    
    // Delete from database
    $db->query("DELETE FROM media_files WHERE id = ?", [$mediaId]);
    
    logActivity('media_deleted', "Deleted media file: {$media['original_name']}");
    
    return ['success' => true, 'message' => 'Media file deleted successfully.'];
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

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get filter parameters
$typeFilter = $_GET['type'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build where clause
$whereConditions = [];
$params = [];

if ($typeFilter) {
    $whereConditions[] = "file_type = ?";
    $params[] = $typeFilter;
}

if ($searchFilter) {
    $whereConditions[] = "(original_name LIKE ? OR alt_text LIKE ? OR caption LIKE ?)";
    $searchParam = "%$searchFilter%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get media files
$mediaFiles = $db->fetchAll(
    "SELECT m.*, u.full_name as uploaded_by_name 
     FROM media_files m
     LEFT JOIN admin_users u ON m.uploaded_by = u.id
     $whereClause
     ORDER BY m.created_at DESC",
    $params
);

// Get media statistics
$stats = $db->fetchOne(
    "SELECT 
     COUNT(*) as total_files,
     SUM(file_size) as total_size,
     SUM(CASE WHEN file_type = 'image' THEN 1 ELSE 0 END) as images,
     SUM(CASE WHEN file_type = 'video' THEN 1 ELSE 0 END) as videos,
     SUM(CASE WHEN file_type = 'document' THEN 1 ELSE 0 END) as documents,
     SUM(CASE WHEN file_type = 'audio' THEN 1 ELSE 0 END) as audio
     FROM media_files"
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
        <h2 class="text-ssa mb-0">Media Library</h2>
        <p class="text-muted">Manage images, videos, and documents</p>
    </div>';

if (PermissionManager::hasPermission('media.upload')) {
    $content .= '
    <button type="button" class="btn btn-ssa" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fas fa-upload me-2"></i>Upload Files
    </button>';
}

$content .= '
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['total_files'] . '</h4>
                <small>Total Files</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . formatBytes($stats['total_size']) . '</h4>
                <small>Total Size</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['images'] . '</h4>
                <small>Images</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['videos'] . '</h4>
                <small>Videos</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['documents'] . '</h4>
                <small>Documents</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">' . $stats['audio'] . '</h4>
                <small>Audio</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="type" class="form-label">File Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="image"' . ($typeFilter === 'image' ? ' selected' : '') . '>Images</option>
                    <option value="video"' . ($typeFilter === 'video' ? ' selected' : '') . '>Videos</option>
                    <option value="document"' . ($typeFilter === 'document' ? ' selected' : '') . '>Documents</option>
                    <option value="audio"' . ($typeFilter === 'audio' ? ' selected' : '') . '>Audio</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="' . escape($searchFilter) . '" placeholder="Search filenames, alt text, captions...">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-ssa">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Media Grid -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-images me-2"></i>Media Files
        </h5>
    </div>
    <div class="card-body">';

if (empty($mediaFiles)) {
    $content .= '
        <div class="text-center py-5">
            <i class="fas fa-images fa-4x text-muted mb-4"></i>
            <h3 class="text-muted">No Media Files</h3>
            <p class="text-muted">Upload some files to get started.</p>';
    
    if (PermissionManager::hasPermission('media.upload')) {
        $content .= '<button type="button" class="btn btn-ssa" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload me-2"></i>Upload Files
                     </button>';
    }
    
    $content .= '</div>';
} else {
    $content .= '<div class="row">';
    
    foreach ($mediaFiles as $media) {
        $fileUrl = UPLOAD_URL . '/' . $media['file_path'];
        $thumbUrl = $fileUrl;
        
        // Use thumbnail for images
        if ($media['file_type'] === 'image') {
            $thumbPath = dirname($media['file_path']) . '/thumb_' . $media['filename'];
            if (file_exists(UPLOAD_PATH . '/' . $thumbPath)) {
                $thumbUrl = UPLOAD_URL . '/' . $thumbPath;
            }
        }
        
        $fileIcon = match($media['file_type']) {
            'video' => 'fas fa-video',
            'audio' => 'fas fa-music',
            'document' => 'fas fa-file-pdf',
            default => 'fas fa-file'
        };
        
        $content .= '
        <div class="col-md-4 col-lg-3 mb-4">
            <div class="card h-100 media-card">
                <div class="media-preview">';
        
        if ($media['file_type'] === 'image') {
            $content .= '<img src="' . $thumbUrl . '" class="card-img-top" alt="' . escape($media['alt_text']) . '">';
        } else {
            $content .= '
                    <div class="media-icon-preview">
                        <i class="' . $fileIcon . ' fa-3x text-muted"></i>
                    </div>';
        }
        
        $content .= '
                    <div class="media-overlay">
                        <div class="btn-group">
                            <a href="' . $fileUrl . '" target="_blank" class="btn btn-light btn-sm" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-light btn-sm" 
                                    onclick="copyUrl(\'' . $fileUrl . '\')" title="Copy URL">
                                <i class="fas fa-copy"></i>
                            </button>';
        
        if (PermissionManager::hasPermission('media.upload')) {
            $content .= '
                            <button type="button" class="btn btn-light btn-sm" 
                                    onclick="editMedia(' . $media['id'] . ')" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>';
        }
        
        if (PermissionManager::hasPermission('media.delete')) {
            $content .= '
                            <button type="button" class="btn btn-danger btn-sm" 
                                    onclick="deleteMedia(' . $media['id'] . ', \'' . escape($media['original_name']) . '\')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>';
        }
        
        $content .= '
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="card-title text-truncate" title="' . escape($media['original_name']) . '">
                        ' . escape($media['original_name']) . '
                    </h6>
                    <p class="card-text small text-muted">
                        <i class="fas fa-file me-1"></i>' . formatBytes($media['file_size']) . '<br>
                        <i class="fas fa-calendar me-1"></i>' . formatDate($media['created_at']) . '<br>
                        <i class="fas fa-user me-1"></i>' . escape($media['uploaded_by_name']) . '
                    </p>';
        
        if ($media['alt_text'] || $media['caption']) {
            $content .= '<p class="card-text small">';
            if ($media['alt_text']) {
                $content .= '<strong>Alt:</strong> ' . escape($media['alt_text']) . '<br>';
            }
            if ($media['caption']) {
                $content .= '<strong>Caption:</strong> ' . escape($media['caption']);
            }
            $content .= '</p>';
        }
        
        $content .= '
                </div>
            </div>
        </div>';
    }
    
    $content .= '</div>';
}

$content .= '
    </div>
</div>';

// Upload Modal
if (PermissionManager::hasPermission('media.upload')) {
    $content .= '
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Media File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file" class="form-label">Choose File *</label>
                        <input type="file" class="form-control" id="file" name="file" required
                               accept="' . implode(',', array_map(fn($ext) => ".$ext", array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOCUMENT_TYPES))) . '">
                        <div class="form-text">
                            Max size: ' . formatBytes(MAX_FILE_SIZE) . '<br>
                            Allowed types: ' . implode(', ', array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOCUMENT_TYPES)) . '
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alt_text" class="form-label">Alt Text</label>
                        <input type="text" class="form-control" id="alt_text" name="alt_text" 
                               placeholder="Descriptive text for accessibility">
                    </div>
                    
                    <div class="mb-3">
                        <label for="caption" class="form-label">Caption</label>
                        <textarea class="form-control" id="caption" name="caption" rows="3"
                                  placeholder="Optional caption or description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ssa">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>';
}

$content .= '
<!-- Edit Media Modal -->
<div class="modal fade" id="editMediaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editMediaForm">
                <input type="hidden" name="action" value="update_media">
                <input type="hidden" name="media_id" id="edit_media_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Media File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_alt_text" class="form-label">Alt Text</label>
                        <input type="text" class="form-control" id="edit_alt_text" name="alt_text">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_caption" class="form-label">Caption</label>
                        <textarea class="form-control" id="edit_caption" name="caption" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ssa">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_media">
    <input type="hidden" name="media_id" id="deleteMediaId">
</form>

<style>
.media-card {
    transition: transform 0.2s;
}

.media-card:hover {
    transform: translateY(-2px);
}

.media-preview {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.media-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-icon-preview {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
}

.media-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.media-card:hover .media-overlay {
    opacity: 1;
}
</style>

<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(function() {
        // You could show a toast notification here
        alert("URL copied to clipboard!");
    });
}

function editMedia(mediaId) {
    // Fetch media details and populate edit form
    fetch(`media_api.php?id=${mediaId}`)
        .then(response => response.json())
        .then(media => {
            document.getElementById("edit_media_id").value = media.id;
            document.getElementById("edit_alt_text").value = media.alt_text || "";
            document.getElementById("edit_caption").value = media.caption || "";
            
            const modal = new bootstrap.Modal(document.getElementById("editMediaModal"));
            modal.show();
        })
        .catch(error => {
            console.error("Error loading media details:", error);
            alert("Error loading media details");
        });
}

function deleteMedia(mediaId, filename) {
    if (confirm("Are you sure you want to delete " + filename + "? This action cannot be undone.")) {
        document.getElementById("deleteMediaId").value = mediaId;
        document.getElementById("deleteForm").submit();
    }
}
</script>';

renderAdminLayout('Media Library', $content);
?>