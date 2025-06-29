<?php
// admin/users_edit.php - Edit user profile and permissions
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$userId = $_GET['id'] ?? null;
$isCurrentUser = $userId == $_SESSION['admin_user_id'];
$errors = [];
$success = '';

// Check permissions
if (!$isCurrentUser) {
    PermissionManager::requirePermission('users.edit');
}

// Get user data
if ($userId) {
    $user = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$userId]);
    if (!$user) {
        $_SESSION['error_message'] = 'User not found.';
        redirect(ADMIN_URL . '/users.php');
    }
} else {
    $_SESSION['error_message'] = 'Invalid user ID.';
    redirect(ADMIN_URL . '/users.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $result = updateUserProfile($_POST, $userId, $isCurrentUser);
            if ($result['success']) {
                $success = $result['message'];
                // Refresh user data
                $user = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$userId]);
            } else {
                $errors = $result['errors'];
            }
            break;
            
        case 'change_password':
            $result = changeUserPassword($_POST, $userId, $isCurrentUser);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $errors = $result['errors'];
            }
            break;
            
        case 'update_permissions':
            if (!$isCurrentUser && PermissionManager::hasPermission('users.edit')) {
                $result = updateUserPermissions($_POST, $userId);
                if ($result['success']) {
                    $success = $result['message'];
                    $user = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$userId]);
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
    }
}

function updateUserProfile($data, $userId, $isCurrentUser) {
    global $db;
    
    $errors = [];
    
    // Validation
    if (empty($data['username'])) {
        $errors[] = 'Username is required.';
    } elseif (strlen($data['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    
    if (empty($data['email'])) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($data['full_name'])) {
        $errors[] = 'Full name is required.';
    }
    
    // Check for duplicate username/email (excluding current user)
    if (!$errors) {
        $existing = $db->fetchOne(
            "SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?",
            [$data['username'], $data['email'], $userId]
        );
        
        if ($existing) {
            $errors[] = 'Username or email already exists.';
        }
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $db->query(
            "UPDATE admin_users SET 
             username = ?, email = ?, full_name = ?, bio = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $data['username'],
                $data['email'],
                $data['full_name'],
                $data['bio'] ?? '',
                $userId
            ]
        );
        
        logActivity('profile_updated', $isCurrentUser ? 'Updated own profile' : "Updated profile for user ID: $userId");
        
        return ['success' => true, 'message' => 'Profile updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function changeUserPassword($data, $userId, $isCurrentUser) {
    global $db;
    
    $errors = [];
    
    // If current user, require current password
    if ($isCurrentUser) {
        if (empty($data['current_password'])) {
            $errors[] = 'Current password is required.';
        } else {
            $user = $db->fetchOne("SELECT password_hash FROM admin_users WHERE id = ?", [$userId]);
            if (!password_verify($data['current_password'], $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }
    
    if (empty($data['new_password'])) {
        $errors[] = 'New password is required.';
    } elseif (strlen($data['new_password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    
    if ($data['new_password'] !== $data['confirm_password']) {
        $errors[] = 'Password confirmation does not match.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $passwordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        
        $db->query(
            "UPDATE admin_users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?",
            [$passwordHash, $userId]
        );
        
        logActivity('password_changed', $isCurrentUser ? 'Changed own password' : "Changed password for user ID: $userId");
        
        return ['success' => true, 'message' => 'Password updated successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function updateUserPermissions($data, $userId) {
    global $db;
    
    $errors = [];
    
    if (empty($data['role']) || !in_array($data['role'], ['super_admin', 'admin', 'festival_director', 'editor'])) {
        $errors[] = 'Please select a valid role.';
    }
    
    // Only super_admin can create other super_admins
    if ($data['role'] === 'super_admin' && PermissionManager::getCurrentUser()['role'] !== 'super_admin') {
        $errors[] = 'Only super administrators can assign super admin role.';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    try {
        $isActive = isset($data['is_active']) ? 1 : 0;
        
        $db->query(
            "UPDATE admin_users SET role = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
            [$data['role'], $isActive, $userId]
        );
        
        logActivity('permissions_updated', "Updated permissions for user ID: $userId - Role: {$data['role']}, Active: $isActive");
        
        return ['success' => true, 'message' => 'User permissions updated successfully.'];
    } catch (Exception $e) {
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

// Get user's recent activity
$recentActivity = $db->fetchAll(
    "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$userId]
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
        <h2 class="text-ssa mb-0">' . ($isCurrentUser ? 'My Profile' : 'Edit User') . '</h2>
        <p class="text-muted">' . escape($user['full_name']) . ' (' . escape($user['username']) . ')</p>
    </div>
    <a href="' . ADMIN_URL . '/users.php" class="btn btn-outline-ssa">
        <i class="fas fa-arrow-left me-2"></i>Back to Users
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Profile Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>Profile Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="' . escape($user['username']) . '" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="' . escape($user['email']) . '" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="' . escape($user['full_name']) . '" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3">' . escape($user['bio']) . '</textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-ssa">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-key me-2"></i>Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">';

if ($isCurrentUser) {
    $content .= '
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>';
}

$content .= '
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <small class="form-text text-muted">Minimum 8 characters</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>';

// User permissions (only if not current user and has permission)
if (!$isCurrentUser && PermissionManager::hasPermission('users.edit')) {
    $content .= '
        <!-- User Permissions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-2"></i>Permissions & Access
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_permissions">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">User Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role...</option>';

    $currentUserRole = PermissionManager::getCurrentUser()['role'];
    if ($currentUserRole === 'super_admin') {
        $content .= '<option value="super_admin"' . ($user['role'] === 'super_admin' ? ' selected' : '') . '>Super Admin</option>';
    }
    
    $content .= '
                                    <option value="admin"' . ($user['role'] === 'admin' ? ' selected' : '') . '>Admin</option>
                                    <option value="festival_director"' . ($user['role'] === 'festival_director' ? ' selected' : '') . '>Festival Director</option>
                                    <option value="editor"' . ($user['role'] === 'editor' ? ' selected' : '') . '>Editor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Account Status</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"' . 
                                    ($user['is_active'] ? ' checked' : '') . '>
                                    <label class="form-check-label" for="is_active">
                                        Account is active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-cog me-2"></i>Update Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>';
}

$content .= '
    </div>
    
    <div class="col-lg-4">
        <!-- User Info Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">User Information</h5>
            </div>
            <div class="card-body text-center">
                <div class="avatar-circle-large mb-3">
                    ' . strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) . '
                </div>
                <h5>' . escape($user['full_name']) . '</h5>
                <p class="text-muted">@' . escape($user['username']) . '</p>
                
                <div class="row text-center">
                    <div class="col-6">
                        <h6 class="text-ssa mb-0">' . ucfirst(str_replace('_', ' ', $user['role'])) . '</h6>
                        <small class="text-muted">Role</small>
                    </div>
                    <div class="col-6">
                        <h6 class="' . ($user['is_active'] ? 'text-success' : 'text-muted') . ' mb-0">
                            ' . ($user['is_active'] ? 'Active' : 'Inactive') . '
                        </h6>
                        <small class="text-muted">Status</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="row text-center small">
                    <div class="col-12 mb-2">
                        <strong>Joined:</strong> ' . formatDate($user['created_at']) . '
                    </div>
                    <div class="col-12">
                        <strong>Last Login:</strong> ' . ($user['last_login'] ? formatDateTime($user['last_login']) : 'Never') . '
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="timeline">';

if (empty($recentActivity)) {
    $content .= '<p class="text-muted text-center">No recent activity</p>';
} else {
    foreach ($recentActivity as $activity) {
        $iconMap = [
            'login' => 'fas fa-sign-in-alt text-success',
            'logout' => 'fas fa-sign-out-alt text-muted',
            'profile_updated' => 'fas fa-user-edit text-info',
            'password_changed' => 'fas fa-key text-warning',
            'user_created' => 'fas fa-user-plus text-success',
            'festival_created' => 'fas fa-film text-primary',
            'page_created' => 'fas fa-file-alt text-info'
        ];
        
        $icon = $iconMap[$activity['action']] ?? 'fas fa-circle text-secondary';
        
        $content .= '
                    <div class="timeline-item mb-3">
                        <div class="d-flex">
                            <div class="timeline-icon me-3">
                                <i class="' . $icon . '"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small class="text-muted">' . formatDateTime($activity['created_at']) . '</small>
                                <div>' . escape($activity['description']) . '</div>
                            </div>
                        </div>
                    </div>';
    }
}

$content .= '
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 24px;
    margin: 0 auto;
}

.timeline-icon {
    width: 24px;
    display: flex;
    justify-content: center;
}

.timeline-item:not(:last-child)::after {
    content: "";
    position: absolute;
    left: 11px;
    top: 24px;
    height: 20px;
    width: 2px;
    background-color: #dee2e6;
}

.timeline {
    position: relative;
}
</style>';

renderAdminLayout($isCurrentUser ? 'My Profile' : 'Edit User', $content);
?>