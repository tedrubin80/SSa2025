<?php
// admin/includes/admin_layout.php - Admin layout template
require_once __DIR__ . '/../../config.php';
requireLogin();

function renderAdminLayout($title, $content, $pageClass = '') {
    $user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($title) ?> - Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
    <!-- Custom Admin CSS -->
    <style>
        :root {
            --ssa-red: #CC3300;
            --ssa-dark-red: #993300;
            --ssa-burgundy: #A33900;
            --ssa-cream: #f7f7e7;
            --ssa-yellow: #FFFF00;
            --ssa-pink: #FF0066;
        }
        
        body {
            font-family: Verdana, Arial, Helvetica, sans-serif;
            background-color: #f8f9fa;
        }
        
        .admin-sidebar {
            background: linear-gradient(180deg, var(--ssa-red) 0%, var(--ssa-dark-red) 100%);
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .admin-sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: var(--ssa-yellow);
            transform: translateX(5px);
        }
        
        .admin-sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        .admin-header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 30px;
            margin-bottom: 30px;
        }
        
        .admin-header h1 {
            color: var(--ssa-red);
            margin: 0;
            font-size: 24px;
        }
        
        .admin-content {
            padding: 0 30px 30px;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .card-header {
            background: var(--ssa-cream);
            border-bottom: 2px solid var(--ssa-red);
            font-weight: bold;
            color: var(--ssa-red);
        }
        
        .btn-ssa {
            background-color: var(--ssa-red);
            border-color: var(--ssa-red);
            color: white;
        }
        
        .btn-ssa:hover {
            background-color: var(--ssa-burgundy);
            border-color: var(--ssa-burgundy);
            color: white;
        }
        
        .btn-outline-ssa {
            border-color: var(--ssa-red);
            color: var(--ssa-red);
        }
        
        .btn-outline-ssa:hover {
            background-color: var(--ssa-red);
            border-color: var(--ssa-red);
            color: white;
        }
        
        .table th {
            background-color: var(--ssa-cream);
            color: var(--ssa-red);
            font-weight: bold;
            border: none;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(204, 51, 0, 0.05);
        }
        
        .badge-published {
            background-color: #28a745;
        }
        
        .badge-draft {
            background-color: #ffc107;
        }
        
        .badge-archived {
            background-color: #6c757d;
        }
        
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin: 20px;
            text-align: center;
            color: white;
        }
        
        .sidebar-brand {
            padding: 30px 20px;
            text-align: center;
            color: white;
            border-bottom: 2px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-brand h4 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        
        .sidebar-brand small {
            opacity: 0.8;
        }
        
        .form-control:focus {
            border-color: var(--ssa-red);
            box-shadow: 0 0 0 0.2rem rgba(204, 51, 0, 0.25);
        }
        
        .alert-ssa {
            background-color: var(--ssa-cream);
            border-color: var(--ssa-red);
            color: var(--ssa-red);
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                min-height: auto;
            }
            
            .admin-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body class="<?= escape($pageClass) ?>">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block admin-sidebar collapse">
                <div class="sidebar-brand">
                    <h4>SSA Admin</h4>
                    <small>Content Management</small>
                </div>
                
                <div class="user-info">
                    <i class="fas fa-user-circle fa-2x mb-2"></i>
                    <div><strong><?= escape($user['username']) ?></strong></div>
                    <small><?= escape(ucfirst($user['role'])) ?></small>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/festivals.php">
                            <i class="fas fa-film"></i>Festivals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/import_festival.php">
                            <i class="fas fa-file-import"></i>Import Festival
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/films.php">
                            <i class="fas fa-video"></i>Films
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/awards.php">
                            <i class="fas fa-trophy"></i>Awards
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/categories.php">
                            <i class="fas fa-tags"></i>Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/pages.php">
                            <i class="fas fa-file-alt"></i>Pages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/import_pages.php">
                            <i class="fas fa-file-import"></i>Import Pages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/settings.php">
                            <i class="fas fa-cog"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link" href="<?= SITE_URL ?>" target="_blank">
                            <i class="fas fa-external-link-alt"></i>View Website
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= ADMIN_URL ?>/logout.php">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="admin-header d-flex justify-content-between align-items-center">
                    <h1><?= escape($title) ?></h1>
                    <button class="btn btn-outline-ssa d-md-none" type="button" data-bs-toggle="collapse" data-bs-target=".admin-sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <div class="admin-content">
                    <?= $content ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- TinyMCE Configuration -->
    <script>
        tinymce.init({
            selector: '.tinymce',
            height: 400,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            content_style: 'body { font-family: Verdana, Arial, Helvetica, sans-serif; font-size:14px }'
        });
    </script>
    
    <!-- Custom Admin JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Confirm delete actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-delete')) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
<?php
}
?>