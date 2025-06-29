<?php
// admin/login.php - Admin login page
require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM admin_users WHERE username = ? AND is_active = 1",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Update last activity
            $db->query(
                "UPDATE admin_users SET last_activity = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            redirect(ADMIN_URL . '/dashboard.php');
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please enter both username and password';
    }
}

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(ADMIN_URL . '/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Southern Shorts Awards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --ssa-red: #CC3300;
            --ssa-dark-red: #993300;
            --ssa-cream: #f7f7e7;
        }
        
        body {
            background: linear-gradient(135deg, var(--ssa-red) 0%, var(--ssa-dark-red) 100%);
            font-family: Verdana, Arial, Helvetica, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: var(--ssa-cream);
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid var(--ssa-red);
        }
        
        .login-header h2 {
            color: var(--ssa-red);
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .form-control:focus {
            border-color: var(--ssa-red);
            box-shadow: 0 0 0 0.2rem rgba(204, 51, 0, 0.25);
        }
        
        .btn-login {
            background: var(--ssa-red);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: bold;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--ssa-dark-red);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Admin Login</h2>
            <p class="mb-0 text-muted">Southern Shorts Awards CMS</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-login text-white">Login</button>
            </form>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    <a href="<?= SITE_URL ?>" style="color: var(--ssa-red);">← Back to Website</a>
                </small>
            </div>
        </div>
    </div>
</body>
</html>
