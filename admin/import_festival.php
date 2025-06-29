<?php
// admin/import_festival.php - Import historical festival pages
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';
$previewData = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'preview') {
        // Preview mode - parse HTML and show extracted data
        $htmlContent = $_POST['html_content'] ?? '';
        $previewData = parseHtmlContent($htmlContent);
        
        if (empty($previewData)) {
            $errors[] = 'Could not extract festival data from the provided HTML. Please check the format.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import') {
        // Import mode - save to database
        $festivalData = json_decode($_POST['festival_data'], true);
        
        if ($festivalData) {
            try {
                // Insert festival
                $db->query(
                    "INSERT INTO festivals (title, season, year, description, status, created_at) 
                     VALUES (?, ?, ?, ?, 'published', NOW())",
                    [
                        $festivalData['title'],
                        $festivalData['season'],
                        $festivalData['year'],
                        $festivalData['description']
                    ]
                );
                
                $festivalId = $db->lastInsertId();
                
                // Insert awards
                if (!empty($festivalData['awards'])) {
                    foreach ($festivalData['awards'] as $award) {
                        // Get or create award type
                        $awardType = $db->fetchOne(
                            "SELECT id FROM award_types WHERE name = ?",
                            [$award['type']]
                        );
                        
                        if (!$awardType) {
                            $db->query(
                                "INSERT INTO award_types (name, description) VALUES (?, ?)",
                                [$award['type'], $award['type'] . ' award']
                            );
                            $awardTypeId = $db->lastInsertId();
                        } else {
                            $awardTypeId = $awardType['id'];
                        }
                        
                        // Get category if specified
                        $categoryId = null;
                        if (!empty($award['category'])) {
                            $category = $db->fetchOne(
                                "SELECT id FROM categories WHERE name = ?",
                                [$award['category']]
                            );
                            if ($category) {
                                $categoryId = $category['id'];
                            }
                        }
                        
                        // Insert award
                        $db->query(
                            "INSERT INTO awards (festival_id, award_type_id, category_id, recipient_name, recipient_role, film_title, placement) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [
                                $festivalId,
                                $awardTypeId,
                                $categoryId,
                                $award['recipient'],
                                $award['role'] ?? '',
                                $award['film_title'] ?? '',
                                $award['placement']
                            ]
                        );
                    }
                }
                
                $success = 'Festival imported successfully! <a href="' . ADMIN_URL . '/festivals_edit.php?id=' . $festivalId . '">Edit Festival</a>';
                
            } catch (Exception $e) {
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Invalid festival data for import.';
        }
    }
}

function parseHtmlContent($html) {
    if (empty($html)) return null;
    
    // Create DOMDocument to parse HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    
    $data = [
        'title' => '',
        'season' => '',
        'year' => '',
        'description' => '',
        'awards' => []
    ];
    
    // Extract title - look for patterns like "Summer 2017", "Fall 2019", etc.
    $titleElement = $dom->getElementsByTagName('title');
    if ($titleElement->length > 0) {
        $title = $titleElement->item(0)->textContent;
        if (preg_match('/(Spring|Summer|Fall|Winter)\s+(\d{4})/i', $title, $matches)) {
            $data['season'] = ucfirst(strtolower($matches[1]));
            $data['year'] = $matches[2];
            $data['title'] = $matches[1] . ' ' . $matches[2] . ' Season';
        }
    }
    
    // If not found in title, look in page content
    if (empty($data['season']) || empty($data['year'])) {
        $xpath = new DOMXPath($dom);
        $textNodes = $xpath->query('//text()');
        
        foreach ($textNodes as $node) {
            if (preg_match('/(Spring|Summer|Fall|Winter)\s+(\d{4})/i', $node->textContent, $matches)) {
                $data['season'] = ucfirst(strtolower($matches[1]));
                $data['year'] = $matches[2];
                $data['title'] = $matches[1] . ' ' . $matches[2] . ' Season';
                break;
            }
        }
    }
    
    // Extract awards - look for common patterns
    $xpath = new DOMXPath($dom);
    $textNodes = $xpath->query('//text()');
    
    foreach ($textNodes as $node) {
        $text = trim($node->textContent);
        
        // Look for "BEST [CATEGORY]:" patterns
        if (preg_match('/BEST\s+([^:]+):\s*"([^"]+)"\s*-\s*(.+)/i', $text, $matches)) {
            $data['awards'][] = [
                'type' => 'Best ' . trim($matches[1]),
                'category' => trim($matches[1]),
                'film_title' => trim($matches[2]),
                'recipient' => trim($matches[3]),
                'placement' => 'Winner'
            ];
        }
        
        // Look for "BEST [ROLE]:" patterns (like BEST DIRECTOR)
        elseif (preg_match('/BEST\s+(DIRECTOR|ACTOR|ACTRESS|CINEMATOGRAPHER|EDITOR|SCREENPLAY|SOUND|MUSIC|PRODUCTION\s+DESIGN):\s*([^-]+?)(?:\s*-\s*"([^"]+)")?/i', $text, $matches)) {
            $data['awards'][] = [
                'type' => 'Best ' . trim($matches[1]),
                'recipient' => trim($matches[2]),
                'film_title' => isset($matches[3]) ? trim($matches[3]) : '',
                'placement' => 'Best of Show'
            ];
        }
        
        // Look for "Award of Excellence/Merit:" patterns
        elseif (preg_match('/Award\s+of\s+(Excellence|Merit):\s*"([^"]+)"\s*-\s*(.+)/i', $text, $matches)) {
            $data['awards'][] = [
                'type' => 'Film Award',
                'film_title' => trim($matches[2]),
                'recipient' => trim($matches[3]),
                'placement' => $matches[1]
            ];
        }
    }
    
    // Generate description from content
    $contentDivs = $xpath->query('//div[contains(@class, "style5") or contains(@class, "content")]');
    if ($contentDivs->length > 0) {
        $data['description'] = '<p>Historical festival data imported from archived website.</p>';
    }
    
    return !empty($data['season']) && !empty($data['year']) ? $data : null;
}

$content = '';

// Display messages
if (!empty($errors)) {
    $content .= '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $content .= '<li>' . $error . '</li>';
    }
    $content .= '</ul></div>';
}

if ($success) {
    $content .= '<div class="alert alert-success">' . $success . '</div>';
}

$content .= '
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-ssa mb-0">Import Historical Festival</h2>
        <p class="text-muted">Import festival data from existing HTML pages</p>
    </div>
    <a href="' . ADMIN_URL . '/festivals.php" class="btn btn-outline-ssa">
        <i class="fas fa-arrow-left me-2"></i>Back to Festivals
    </a>
</div>';

if (!$previewData) {
    // Show import form
    $content .= '
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-file-import me-2"></i>Import Festival HTML
            </h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="preview">
                
                <div class="mb-3">
                    <label for="html_content" class="form-label">HTML Content</label>
                    <textarea class="form-control" id="html_content" name="html_content" rows="20" required 
                              placeholder="Paste the complete HTML content of the festival page here...">' . escape($_POST['html_content'] ?? '') . '</textarea>
                    <div class="form-text">
                        Paste the entire HTML content from files like "SUMMER 2017 Festival.html", "FALL 2019 Festival.html", etc.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-ssa">
                    <i class="fas fa-search me-2"></i>Preview Import Data
                </button>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Import Instructions</h6>
        </div>
        <div class="card-body">
            <ol>
                <li>Open one of your existing festival HTML files (e.g., "SUMMER 2017 Festival.html")</li>
                <li>Copy the entire HTML content</li>
                <li>Paste it into the text area above</li>
                <li>Click "Preview Import Data" to see what will be imported</li>
                <li>Review the extracted data and confirm the import</li>
            </ol>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Supported Formats:</strong> The import tool will automatically detect:
                <ul class="mb-0 mt-2">
                    <li>Festival season and year</li>
                    <li>Award categories and winners</li>
                    <li>Film titles and filmmaker names</li>
                    <li>Award levels (Best of Show, Excellence, Merit, etc.)</li>
                </ul>
            </div>
        </div>
    </div>';
} else {
    // Show preview data
    $content .= '
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-eye me-2"></i>Preview Import Data
            </h5>
        </div>
        <div class="card-body">
            <h6>Festival Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Title:</strong></td><td>' . escape($previewData['title']) . '</td></tr>
                <tr><td><strong>Season:</strong></td><td>' . escape($previewData['season']) . '</td></tr>
                <tr><td><strong>Year:</strong></td><td>' . escape($previewData['year']) . '</td></tr>
            </table>
            
            <h6 class="mt-4">Awards Found (' . count($previewData['awards']) . ')</h6>';
    
    if (!empty($previewData['awards'])) {
        $content .= '
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Award Type</th>
                            <th>Category</th>
                            <th>Film Title</th>
                            <th>Recipient</th>
                            <th>Placement</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($previewData['awards'] as $award) {
            $content .= '
                        <tr>
                            <td>' . escape($award['type']) . '</td>
                            <td>' . escape($award['category'] ?? '') . '</td>
                            <td>' . escape($award['film_title'] ?? '') . '</td>
                            <td>' . escape($award['recipient']) . '</td>
                            <td><span class="badge bg-info">' . escape($award['placement']) . '</span></td>
                        </tr>';
        }
        
        $content .= '
                    </tbody>
                </table>
            </div>';
    } else {
        $content .= '<p class="text-muted">No awards detected in the HTML content.</p>';
    }
    
    $content .= '
            <form method="POST" class="mt-4">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="festival_data" value="' . escape(json_encode($previewData)) . '">
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Confirm Import
                    </button>
                    <a href="' . ADMIN_URL . '/import_festival.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Import Form
                    </a>
                </div>
            </form>
        </div>
    </div>';
}

renderAdminLayout('Import Festival', $content);
?>