<?php
// admin/users.php - Multi-user management system
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();
PermissionManager::requirePermission('users.view');

$db = Database::getInstance();
$errors = [];
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            if (PermissionManager::hasPermission('users.create')) {
                $result = createUser($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors = $result['errors'];
                }
            }
            break;
            
        case 'toggle_status':
            if (PermissionManager::hasPermission('users.edit')) {
                toggleUserStatus($_POST['user_id']);
                $success = 'User status updated successfully.';
            }
            break;
            
        case 'delete_user':
            if (PermissionManager::hasPermission('users.delete')) {
                deleteUser($_POST['user_id']);
                $success = 'User deleted successfully.';
            }
            break;
            
        case 'reset_password':
            if (PermissionManager::hasPermission('users.edit')) {
                $result = resetUserPassword($_POST['user_id']);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
            }
            break;
    }
}

function createUser($data) {
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
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required.';
    } elseif (strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    
    if (empty($data['full_name'])) {
        $errors[] = 'Full name is required.';
    }
    
    if (empty($data['role']) || !in_array($data['role'], ['super_admin', 'admin', 'festival_director', 'editor'])) {
        $errors[] = 'Please select a valid role.';
    }
    
    // Check for duplicate username/email
    if (!$errors) {
        $existing = $db->fetchOne(
            "SELECT id FROM admin_users WHERE username = ? OR email = ?",
            [$data['username'], $data['email']]
        );
        
        if ($existing) {
            $errors[] = 'Username or email already exists.';
        }
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Create user
    try {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $db->query(
            "INSERT INTO admin_users (username, email, password_hash, full_name, role, bio, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['username'],
                $data['email'],
                $passwordHash,
                $data['full_name'],
                $data['role'],
                $data['bio'] ?? ''
            ]
        );
        
        // Log activity
        logActivity('user_created', "Created user: {$data['username']}");
        
        return ['success' => true, 'message' => 'User created successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]];
    }
}

function toggleUserStatus($userId) {
    global $db;
    
    $db->query(
        "UPDATE admin_users SET is_active = NOT is_active WHERE id = ?",
        [$userId]
    );
    
    logActivity('user_status_changed', "Toggled status for user ID: $userId");
}

function deleteUser($userId) {
    global $db;
    
    // Don't allow deletion of current user
    if ($userId == $_SESSION['admin_user_id']) {
        return;
    }
    
    $user = $db->fetchOne("SELECT username FROM admin_users WHERE id = ?", [$userId]);
    
    $db->query("DELETE FROM admin_users WHERE id = ?", [$userId]);
    
    logActivity('user_deleted', "Deleted user: {$user['username']}");
}

function resetUserPassword($userId) {
    global $db;
    
    // Generate temporary password
    $tempPassword = bin2hex(random_bytes(8));
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    $db->query(
        "UPDATE admin_users SET password_hash = ?, must_change_password = 1 WHERE id = ?",
        [$passwordHash, $userId]
    );
    
    $user = $db->fetchOne("SELECT email, full_name FROM admin_users WHERE id = ?", [$userId]);
    
    // In a real application, you'd email this password
    // For demo purposes, we'll just return it
    
    logActivity('password_reset', "Reset password for user ID: $userId");
    
    return [
        'success' => true, 
        'message' => "Password reset to: $tempPassword (User must change on next login)"
    ];
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

// Get all users with stats
$users = $db->fetchAll(
    "SELECT u.*, 
     (SELECT COUNT(*) FROM festivals WHERE created_by = u.id) as festivals_created,
     (SELECT COUNT(*) FROM pages WHERE created_by = u.id) as pages_created,
     (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_activity
     FROM admin_users u 
     ORDER BY u.created_at DESC"
);

// Get user statistics
$userStats = $db->fetchOne(
    "SELECT 
     COUNT(*) as total_users,
     SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
     SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admins,
     SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
     SUM(CASE WHEN role = 'festival_director' THEN 1 ELSE 0 END) as festival_directors,
     SUM(CASE WHEN role = 'editor' THEN 1 ELSE 0 END) as editors,
     SUM(CASE WHEN last_login > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_this_month
     FROM admin_users"
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
        <h2 class="text-ssa mb-0">User Management</h2>
        <p class="text-muted">Manage system users and permissions</p>
    </div>';

if (PermissionManager::hasPermission('users.create')) {
    $content .= '
    <button type="button" class="btn btn-ssa" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fas fa-plus me-2"></i>Add New User
    </button>';
}

$content .= '
</div>

<!-- User Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $userStats['total_users'] . '</h4>
                        <small>Total Users</small>
                    </div>
                    <i class="fas fa-users fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $userStats['active_users'] . '</h4>
                        <small>Active Users</small>
                    </div>
                    <i class="fas fa-user-check fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . $userStats['active_this_month'] . '</h4>
                        <small>Active This Month</small>
                    </div>
                    <i class="fas fa-chart-line fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">' . ($userStats['super_admins'] + $userStats['admins']) . '</h4>
                        <small>Administrators</small>
                    </div>
                    <i class="fas fa-user-cog fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>System Users
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Content Created</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($users as $user) {
    $roleLabels = [
        'super_admin' => '<span class="badge bg-danger">Super Admin</span>',
        'admin' => '<span class="badge bg-warning">Admin</span>',
        'festival_director' => '<span class="badge bg-info">Festival Director</span>',
        'editor' => '<span class="badge bg-secondary">Editor</span>'
    ];
    
    $statusBadge = $user['is_active'] ? 
        '<span class="badge bg-success">Active</span>' : 
        '<span class="badge bg-secondary">Inactive</span>';
    
    $isCurrentUser = $user['id'] == $_SESSION['admin_user_id'];
    $canEdit = PermissionManager::hasPermission('users.edit') && !$isCurrentUser;
    $canDelete = PermissionManager::hasPermission('users.delete') && !$isCurrentUser;
    
    $content .= '
                    <tr' . ($isCurrentUser ? ' class="table-warning"' : '') . '>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3">
                                    ' . strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) . '
                                </div>
                                <div>
                                    <strong>' . escape($user['full_name'] ?: $user['username']) . '</strong>
                                    ' . ($isCurrentUser ? '<small class="text-muted">(You)</small>' : '') . '
                                    <br>
                                    <small class="text-muted">' . escape($user['email']) . '</small>
                                </div>
                            </div>
                        </td>
                        <td>' . ($roleLabels[$user['role']] ?? ucfirst($user['role'])) . '</td>
                        <td>' . $statusBadge . '</td>
                        <td>
                            <small>
                                <i class="fas fa-film me-1"></i>' . $user['festivals_created'] . ' festivals<br>
                                <i class="fas fa-file-alt me-1"></i>' . $user['pages_created'] . ' pages
                            </small>
                        </td>
                        <td>
                            <small>' . 
                            ($user['last_activity'] ? formatDateTime($user['last_activity']) : 
                             ($user['last_login'] ? 'Login: ' . formatDateTime($user['last_login']) : 'Never')) . 
                            '</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="' . ADMIN_URL . '/users_edit.php?id=' . $user['id'] . '" 
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>';
    
    if ($canEdit) {
        $content .= '
                                <button type="button" class="btn btn-outline-warning" 
                                        onclick="toggleUserStatus(' . $user['id'] . ', \'' . escape($user['full_name']) . '\')" title="Toggle Status">
                                    <i class="fas fa-' . ($user['is_active'] ? 'pause' : 'play') . '"></i>
                                </button>
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="resetPassword(' . $user['id'] . ', \'' . escape($user['full_name']) . '\')" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>';
    }
    
    if ($canDelete) {
        $content .= '
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteUser(' . $user['id'] . ', \'' . escape($user['full_name']) . '\')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>';
    }
    
    $content .= '
                            </div>
                        </td>
                    </tr>';
}

$content .= '
                </tbody>
            </table>
        </div>
    </div>
</div>';

// Create User Modal
if (PermissionManager::hasPermission('users.create')) {
    $content .= '
<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">Minimum 8 characters</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role...</option>';

    if (PermissionManager::getCurrentUser()['role'] === 'super_admin') {
        $content .= '<option value="super_admin">Super Admin</option>';
    }
    
    $content .= '
                                    <option value="admin">Admin</option>
                                    <option value="festival_director">Festival Director</option>
                                    <option value="editor">Editor</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ssa">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>';
}

$content .= '
<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}
</style>

<!-- Action Forms -->
<form id="toggleForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggleUserId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<form id="resetPasswordForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="user_id" id="resetPasswordUserId">
</form>

<script>
function toggleUserStatus(userId, userName) {
    if (confirm("Are you sure you want to change the status of " + userName + "?")) {
        document.getElementById("toggleUserId").value = userId;
        document.getElementById("toggleForm").submit();
    }
}

function deleteUser(userId, userName) {
    if (confirm("Are you sure you want to delete " + userName + "? This action cannot be undone and will remove all their data.")) {
        document.getElementById("deleteUserId").value = userId;
        document.getElementById("deleteForm").submit();
    }
}

function resetPassword(userId, userName) {
    if (confirm("Reset password for " + userName + "? They will need to change it on next login.")) {
        document.getElementById("resetPasswordUserId").value = userId;
        document.getElementById("resetPasswordForm").submit();
    }
}
</script>';

renderAdminLayout('User Management', $content);
?>