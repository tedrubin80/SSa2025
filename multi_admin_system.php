<?php
// Enhanced Database Schema for Multi-Admin System
-- Update admin_users table with expanded roles and permissions
ALTER TABLE admin_users ADD COLUMN permissions JSON DEFAULT NULL;
ALTER TABLE admin_users MODIFY COLUMN role ENUM('super_admin', 'admin', 'festival_director', 'editor', 'judge_coordinator', 'content_manager', 'readonly') DEFAULT 'readonly';
ALTER TABLE admin_users ADD COLUMN department VARCHAR(100) DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN bio TEXT DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN login_attempts INT DEFAULT 0;
ALTER TABLE admin_users ADD COLUMN locked_until TIMESTAMP NULL DEFAULT NULL;

-- User activity log table
CREATE TABLE admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_action (action),
    INDEX idx_target (target_type, target_id)
);

-- User sessions table for better session management
CREATE TABLE admin_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);

// includes/permissions.php - Permission System
<?php
class PermissionManager {
    
    // Define all available permissions
    const PERMISSIONS = [
        // User Management
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'users.edit' => 'Edit Users',
        'users.delete' => 'Delete Users',
        'users.manage_roles' => 'Manage User Roles',
        
        // Festival Management
        'festivals.view' => 'View Festivals',
        'festivals.create' => 'Create Festivals',
        'festivals.edit' => 'Edit Festivals',
        'festivals.delete' => 'Delete Festivals',
        'festivals.publish' => 'Publish Festivals',
        
        // Awards Management
        'awards.view' => 'View Awards',
        'awards.create' => 'Create Awards',
        'awards.edit' => 'Edit Awards',
        'awards.delete' => 'Delete Awards',
        
        // Page Management
        'pages.view' => 'View Pages',
        'pages.create' => 'Create Pages',
        'pages.edit' => 'Edit Pages',
        'pages.delete' => 'Delete Pages',
        'pages.publish' => 'Publish Pages',
        
        // Content Management
        'content.view' => 'View Content',
        'content.edit' => 'Edit Content',
        'content.publish' => 'Publish Content',
        
        // Settings Management
        'settings.view' => 'View Settings',
        'settings.edit' => 'Edit Settings',
        
        // Reports & Analytics
        'reports.view' => 'View Reports',
        'reports.export' => 'Export Reports',
        
        // System Administration
        'system.backup' => 'System Backup',
        'system.logs' => 'View System Logs',
        'system.maintenance' => 'System Maintenance'
    ];
    
    // Define role-based permission sets
    const ROLE_PERMISSIONS = [
        'super_admin' => 'all', // All permissions
        'admin' => [
            'users.view', 'users.create', 'users.edit',
            'festivals.view', 'festivals.create', 'festivals.edit', 'festivals.publish',
            'awards.view', 'awards.create', 'awards.edit', 'awards.delete',
            'pages.view', 'pages.create', 'pages.edit', 'pages.publish',
            'content.view', 'content.edit', 'content.publish',
            'settings.view', 'settings.edit',
            'reports.view', 'reports.export'
        ],
        'festival_director' => [
            'festivals.view', 'festivals.create', 'festivals.edit', 'festivals.publish',
            'awards.view', 'awards.create', 'awards.edit',
            'pages.view', 'pages.edit',
            'content.view', 'content.edit',
            'reports.view', 'reports.export',
            'users.view'
        ],
        'editor' => [
            'festivals.view', 'festivals.edit',
            'awards.view', 'awards.edit',
            'pages.view', 'pages.create', 'pages.edit',
            'content.view', 'content.edit'
        ],
        'judge_coordinator' => [
            'festivals.view',
            'awards.view', 'awards.create', 'awards.edit',
            'reports.view',
            'users.view'
        ],
        'content_manager' => [
            'pages.view', 'pages.create', 'pages.edit', 'pages.publish',
            'content.view', 'content.edit', 'content.publish',
            'festivals.view'
        ],
        'readonly' => [
            'festivals.view',
            'awards.view',
            'pages.view',
            'content.view'
        ]
    ];
    
    public static function hasPermission($permission, $userPermissions = null, $userRole = null) {
        if (!$userPermissions && !$userRole) {
            if (!isset($_SESSION['admin_user_id'])) {
                return false;
            }
            
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT role, permissions FROM admin_users WHERE id = ?", 
                [$_SESSION['admin_user_id']]
            );
            
            if (!$user) return false;
            
            $userRole = $user['role'];
            $userPermissions = $user['permissions'] ? json_decode($user['permissions'], true) : [];
        }
        
        // Super admin has all permissions
        if ($userRole === 'super_admin') {
            return true;
        }
        
        // Check custom permissions first
        if (is_array($userPermissions) && in_array($permission, $userPermissions)) {
            return true;
        }
        
        // Check role-based permissions
        $rolePermissions = self::ROLE_PERMISSIONS[$userRole] ?? [];
        
        if ($rolePermissions === 'all') {
            return true;
        }
        
        return in_array($permission, $rolePermissions);
    }
    
    public static function requirePermission($permission) {
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            die('Access denied. Required permission: ' . $permission);
        }
    }
    
    public static function getRolePermissions($role) {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }
    
    public static function getAllPermissions() {
        return self::PERMISSIONS;
    }
    
    public static function logActivity($action, $targetType = null, $targetId = null, $details = []) {
        if (!isset($_SESSION['admin_user_id'])) return;
        
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO admin_activity_log (user_id, action, target_type, target_id, details, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $_SESSION['admin_user_id'],
                $action,
                $targetType,
                $targetId,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );
    }
}

// admin/users.php - User Management Interface
require_once '../config.php';
require_once 'includes/admin_layout.php';
require_once 'includes/permissions.php';

requireLogin();
PermissionManager::requirePermission('users.view');

$db = Database::getInstance();
$errors = [];
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                PermissionManager::requirePermission('users.delete');
                $id = (int)$_POST['id'];
                
                // Don't allow deleting yourself
                if ($id === $_SESSION['admin_user_id']) {
                    $errors[] = 'You cannot delete your own account.';
                } else {
                    $db->query("DELETE FROM admin_users WHERE id = ?", [$id]);
                    PermissionManager::logActivity('user_deleted', 'user', $id);
                    $_SESSION['success'] = 'User deleted successfully!';
                }
                break;
                
            case 'toggle_status':
                PermissionManager::requirePermission('users.edit');
                $id = (int)$_POST['id'];
                
                if ($id === $_SESSION['admin_user_id']) {
                    $errors[] = 'You cannot deactivate your own account.';
                } else {
                    $db->query(
                        "UPDATE admin_users SET is_active = !is_active WHERE id = ?", 
                        [$id]
                    );
                    PermissionManager::logActivity('user_status_toggled', 'user', $id);
                    $_SESSION['success'] = 'User status updated!';
                }
                break;
                
            case 'unlock_user':
                PermissionManager::requirePermission('users.edit');
                $id = (int)$_POST['id'];
                $db->query(
                    "UPDATE admin_users SET login_attempts = 0, locked_until = NULL WHERE id = ?", 
                    [$id]
                );
                PermissionManager::logActivity('user_unlocked', 'user', $id);
                $_SESSION['success'] = 'User unlocked successfully!';
                break;
        }
        
        if (empty($errors)) {
            header('Location: ' . ADMIN_URL . '/users.php');
            exit;
        }
    }
}

// Get all users with activity info
$users = $db->fetchAll(
    "SELECT u.*, 
     (SELECT COUNT(*) FROM admin_activity_log WHERE user_id = u.id AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_activity,
     (SELECT created_at FROM admin_activity_log WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as last_activity_date
     FROM admin_users u 
     ORDER BY u.role, u.full_name"
);

$content = '';

// Display messages
if (isset($_SESSION['success'])) {
    $content .= '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

if (!empty($errors)) {
    $content .= '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $content .= '<li>' . $error . '</li>';
    }
    $content .= '</ul></div>';
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Staff Management</h2>
        <p class="text-muted">Manage festival staff and their access permissions</p>
    </div>
    <div>';

if (PermissionManager::hasPermission('users.create')) {
    $content .= '
        <a href="' . ADMIN_URL . '/users_edit.php" class="btn btn-ssa">
            <i class="fas fa-user-plus me-2"></i>Add Staff Member
        </a>';
}

$content .= '
    </div>
</div>';

// Role statistics
$roleStats = [];
foreach ($users as $user) {
    $roleStats[$user['role']] = ($roleStats[$user['role']] ?? 0) + 1;
}

$content .= '
<div class="row mb-4">';

foreach (PermissionManager::ROLE_PERMISSIONS as $role => $permissions) {
    $count = $roleStats[$role] ?? 0;
    $roleLabel = ucwords(str_replace('_', ' ', $role));
    
    $content .= '
    <div class="col-md-3 col-sm-6 mb-2">
        <div class="card text-center">
            <div class="card-body py-3">
                <h4 class="text-ssa mb-1">' . $count . '</h4>
                <small class="text-muted">' . $roleLabel . '</small>
            </div>
        </div>
    </div>';
}

$content .= '
</div>';

if (empty($users)) {
    $content .= '
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-users fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">No Staff Members</h4>
            <p class="text-muted mb-4">Add staff members to manage festival operations.</p>
            <a href="' . ADMIN_URL . '/users_edit.php" class="btn btn-ssa">
                <i class="fas fa-user-plus me-2"></i>Add First Staff Member
            </a>
        </div>
    </div>';
} else {
    $content .= '
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-users me-2"></i>Staff Members (' . count($users) . ')
            </h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="filterByRole(\'all\')">All</button>
                <button class="btn btn-outline-secondary" onclick="filterByRole(\'admin\')">Admins</button>
                <button class="btn btn-outline-secondary" onclick="filterByRole(\'staff\')">Staff</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Staff Member</th>
                            <th>Role & Department</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($users as $user) {
        $roleClass = match($user['role']) {
            'super_admin' => 'text-danger',
            'admin' => 'text-warning',
            'festival_director' => 'text-primary',
            default => 'text-secondary'
        };
        
        $statusBadge = $user['is_active'] ? 'bg-success' : 'bg-danger';
        $statusText = $user['is_active'] ? 'Active' : 'Inactive';
        
        // Check if user is locked
        $isLocked = $user['locked_until'] && strtotime($user['locked_until']) > time();
        if ($isLocked) {
            $statusBadge = 'bg-warning';
            $statusText = 'Locked';
        }
        
        $roleLabel = ucwords(str_replace('_', ' ', $user['role']));
        $lastLogin = $user['last_login'] ? formatDate($user['last_login'], 'M j, g:i A') : 'Never';
        
        $content .= '
                        <tr data-role="' . $user['role'] . '">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3">
                                        ' . strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) . '
                                    </div>
                                    <div>
                                        <strong>' . escape($user['full_name'] ?: $user['username']) . '</strong>
                                        <br><small class="text-muted">@' . escape($user['username']) . '</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary ' . $roleClass . '">' . $roleLabel . '</span>';
        
        if ($user['department']) {
            $content .= '<br><small class="text-muted">' . escape($user['department']) . '</small>';
        }
        
        $content .= '
                            </td>
                            <td>
                                <small>' . escape($user['email']) . '</small>';
        
        if ($user['phone']) {
            $content .= '<br><small class="text-muted">' . escape($user['phone']) . '</small>';
        }
        
        $content .= '
                            </td>
                            <td>
                                <span class="badge ' . $statusBadge . '">' . $statusText . '</span>
                            </td>
                            <td>
                                <small>Last: ' . $lastLogin . '</small>
                                <br><small class="text-muted">' . ($user['recent_activity'] ?? 0) . ' actions (30d)</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">';
        
        if (PermissionManager::hasPermission('users.edit')) {
            $content .= '
                                    <a href="' . ADMIN_URL . '/users_edit.php?id=' . $user['id'] . '" 
                                       class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>';
        }
        
        if (PermissionManager::hasPermission('users.edit') && $user['id'] !== $_SESSION['admin_user_id']) {
            if ($isLocked) {
                $content .= '
                                    <button type="button" class="btn btn-outline-warning" 
                                            onclick="unlockUser(' . $user['id'] . ')" title="Unlock">
                                        <i class="fas fa-unlock"></i>
                                    </button>';
            }
            
            $content .= '
                                    <button type="button" class="btn btn-outline-warning" 
                                            onclick="toggleStatus(' . $user['id'] . ', \'' . escape($user['full_name'] ?: $user['username']) . '\')" title="Toggle Status">
                                        <i class="fas fa-' . ($user['is_active'] ? 'pause' : 'play') . '"></i>
                                    </button>';
        }
        
        if (PermissionManager::hasPermission('users.delete') && $user['id'] !== $_SESSION['admin_user_id']) {
            $content .= '
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="deleteUser(' . $user['id'] . ', \'' . escape($user['full_name'] ?: $user['username']) . '\')" title="Delete">
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
}

$content .= '
<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--ssa-red);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}
</style>

<!-- Action Forms -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<form id="toggleForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="id" id="toggleId">
</form>

<form id="unlockForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="unlock_user">
    <input type="hidden" name="id" id="unlockId">
</form>

<script>
function deleteUser(id, name) {
    if (confirm("Are you sure you want to delete " + name + "? This action cannot be undone and will remove all their data.")) {
        document.getElementById("deleteId").value = id;
        document.getElementById("deleteForm").submit();
    }
}

function toggleStatus(id, name) {
    if (confirm("Are you sure you want to change the status of " + name + "?")) {
        document.getElementById("toggleId").value = id;
        document.getElementById("toggleForm").submit();
    }
}

function unlockUser(id) {
    if (confirm("Unlock this user account?")) {
        document.getElementById("unlockId").value = id;
        document.getElementById("unlockForm").submit();
    }
}

function filterByRole(role) {
    const rows = document.querySelectorAll("tbody tr[data-role]");
    rows.forEach(row => {
        if (role === "all") {
            row.style.display = "";
        } else if (role === "admin") {
            const userRole = row.getAttribute("data-role");
            row.style.display = ["super_admin", "admin", "festival_director"].includes(userRole) ? "" : "none";
        } else if (role === "staff") {
            const userRole = row.getAttribute("data-role");
            row.style.display = !["super_admin", "admin", "festival_director"].includes(userRole) ? "" : "none";
        }
    });
    
    // Update button states
    document.querySelectorAll(".btn-group button").forEach(btn => btn.classList.remove("active"));
    event.target.classList.add("active");
}
</script>';

renderAdminLayout('Staff Management', $content);
?>