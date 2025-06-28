<?php
// festival.php - Public festival page
require_once 'config.php';
require_once 'includes/layout.php';

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get festival data
$festival = $db->fetchOne(
    "SELECT * FROM festivals WHERE id = ? AND status = 'published'",
    [$id]
);

if (!$festival) {
    // Redirect to festivals list if festival not found
    redirect(SITE_URL . '/festivals.php');
}

// Get films for this festival grouped by category
$films = $db->fetchAll(
    "SELECT f.*, c.name as category_name 
     FROM films f 
     JOIN categories c ON f.category_id = c.id 
     WHERE f.festival_id = ? 
     ORDER BY c.sort_order, f.title",
    [$id]
);

// Get awards for this festival
$awards = $db->fetchAll(
    "SELECT a.*, at.name as award_name, c.name as category_name,
     CASE a.placement
         WHEN 'Best of Show' THEN 1
         WHEN 'Winner' THEN 2
         WHEN 'Excellence' THEN 3
         WHEN 'Merit' THEN 4
         WHEN 'Distinction' THEN 5
         ELSE 6
     END as sort_order
     FROM awards a
     JOIN award_types at ON a.award_type_id = at.id
     LEFT JOIN categories c ON a.category_id = c.id
     WHERE a.festival_id = ?
     ORDER BY sort_order, at.sort_order, a.recipient_name",
    [$id]
);

// Group awards by type
$bestOfShowAwards = array_filter($awards, fn($a) => $a['placement'] === 'Best of Show');
$categoryAwards = [];
$filmAwards = [];

foreach ($awards as $award) {
    if ($award['placement'] === 'Best of Show') {
        continue; // Already handled above
    }
    
    if ($award['category_id']) {
        $categoryAwards[$award['category_name']][] = $award;
    } else {
        $filmAwards[$award['placement']][] = $award;
    }
}

$title = escape($festival['title']);
$seasonYear = formatSeason($festival['season'], $festival['year']);

// Build content
$content = '';

// Festival header with video if available
if (!empty($festival['featured_video_url'])) {
    $embedUrl = '';
    
    // Convert various video URLs to embed format
    if (strpos($festival['featured_video_url'], 'vimeo.com') !== false) {
        if (preg_match('/vimeo\.com\/(\d+)/', $festival['featured_video_url'], $matches)) {
            $embedUrl = 'https://player.vimeo.com/video/' . $matches[1];
        }
    } elseif (strpos($festival['featured_video_url'], 'youtube.com') !== false || strpos($festival['featured_video_url'], 'youtu.be') !== false) {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $festival['featured_video_url'], $matches)) {
            $embedUrl = 'https://www.youtube.com/embed/' . $matches[1];
        }
    } else {
        $embedUrl = $festival['featured_video_url']; // Assume it's already an embed URL
    }
    
    if ($embedUrl) {
        $content .= '
        <div class="video-container mb-4">
            <iframe src="' . escape($embedUrl) . '" 
                    frameborder="0" allow="autoplay; fullscreen; encrypted-media" allowfullscreen>
            </iframe>
        </div>';
    }
}

// Program PDF link if available
if (!empty($festival['program_pdf_url'])) {
    $content .= '
    <div class="text-center mb-4">
        <a href="' . escape($festival['program_pdf_url']) . '" target="_blank" class="btn btn-ssa">
            <i class="fas fa-file-pdf me-2"></i>Download Festival Program
        </a>
    </div>';
}

$content .= '
<div class="text-center mb-4">
    <h1 class="festival-title">Best of Show ' . escape($seasonYear) . ' Season</h1>
</div>';

// Best of Show Awards
if (!empty($bestOfShowAwards)) {
    $content .= '<div class="award-section">
        <h2 class="text-ssa mb-3">
            <i class="fas fa-crown me-2"></i>Best of Show Awards
        </h2>';
    
    foreach ($bestOfShowAwards as $award) {
        $content .= '
        <div class="award-winner">
            <strong>' . escape($award['award_name']) . ':</strong> 
            ' . escape($award['recipient_name']);
        
        if (!empty($award['film_title'])) {
            $content .= ' - "' . escape($award['film_title']) . '"';
        }
        
        if (!empty($award['recipient_role'])) {
            $content .= ' <em>(' . escape($award['recipient_role']) . ')</em>';
        }
        
        $content .= '</div>';
    }
    
    $content .= '</div>';
}

// Category Awards
if (!empty($categoryAwards)) {
    $content .= '<div class="award-section">
        <h2 class="text-ssa mb-3">
            <i class="fas fa-trophy me-2"></i>Category Awards
        </h2>';
    
    foreach ($categoryAwards as $categoryName => $categoryAwardsList) {
        $content .= '<h3 class="text-ssa">Best ' . escape($categoryName) . '</h3>';
        
        // Group by placement within category
        $placementGroups = [];
        foreach ($categoryAwardsList as $award) {
            $placementGroups[$award['placement']][] = $award;
        }
        
        foreach (['Winner', 'Excellence', 'Merit', 'Distinction'] as $placement) {
            if (isset($placementGroups[$placement])) {
                if ($placement === 'Winner') {
                    $content .= '<div class="mb-3">';
                } else {
                    $content .= '<div class="mb-2"><em>Award of ' . $placement . ':</em><br>';
                }
                
                foreach ($placementGroups[$placement] as $award) {
                    $content .= '<div class="award-winner">';
                    
                    if (!empty($award['film_title'])) {
                        $content .= '"' . escape($award['film_title']) . '" - ';
                    }
                    
                    $content .= escape($award['recipient_name']);
                    
                    if (!empty($award['recipient_role']) && $award['recipient_role'] !== 'Filmmaker') {
                        $content .= ' <em>(' . escape($award['recipient_role']) . ')</em>';
                    }
                    
                    $content .= '</div>';
                }
                
                $content .= '</div>';
            }
        }
    }
    
    $content .= '</div>';
}

// Film Awards by Tier
if (!empty($filmAwards)) {
    $content .= '<div class="award-section">
        <h2 class="text-ssa mb-3">
            <i class="fas fa-medal me-2"></i>Film Awards
        </h2>';
    
    $tierTitles = [
        'Distinction' => 'Awards of Distinction',
        'Excellence' => 'Awards of Excellence',
        'Merit' => 'Awards of Merit'
    ];
    
    foreach (['Distinction', 'Excellence', 'Merit'] as $tier) {
        if (isset($filmAwards[$tier])) {
            $content .= '<h3>' . $tierTitles[$tier] . '</h3>';
            
            foreach ($filmAwards[$tier] as $award) {
                $content .= '<div class="award-winner">';
                
                if (!empty($award['film_title'])) {
                    $content .= '"' . escape($award['film_title']) . '"';
                    if (!empty($award['recipient_name'])) {
                        $content .= ' - ' . escape($award['recipient_name']);
                    }
                } else {
                    $content .= escape($award['recipient_name']);
                }
                
                if (!empty($award['recipient_role']) && $award['recipient_role'] !== 'Filmmaker') {
                    $content .= ' <em>(' . escape($award['recipient_role']) . ')</em>';
                }
                
                $content .= '</div>';
            }
        }
    }
    
    $content .= '</div>';
}

// Festival Description
if (!empty($festival['description'])) {
    $content .= '<div class="award-section">
        <h2 class="text-ssa mb-3">
            <i class="fas fa-info-circle me-2"></i>About This Festival
        </h2>
        <div class="festival-description">
            ' . $festival['description'] . '
        </div>
    </div>';
}

// Festival Info
$content .= '<div class="award-section">
    <h2 class="text-ssa mb-3">
        <i class="fas fa-calendar me-2"></i>Festival Information
    </h2>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Season:</strong> ' . escape($festival['season']) . ' ' . escape($festival['year']) . '</p>';

if (!empty($festival['start_date']) && !empty($festival['end_date'])) {
    $content .= '<p><strong>Dates:</strong> ' . formatDate($festival['start_date']) . ' - ' . formatDate($festival['end_date']) . '</p>';
}

$content .= '
        </div>
        <div class="col-md-6">
            <p><strong>Total Films:</strong> ' . count($films) . '</p>
            <p><strong>Total Awards:</strong> ' . count($awards) . '</p>
        </div>
    </div>
</div>';

// Navigation to other festivals
$otherFestivals = $db->fetchAll(
    "SELECT id, title, season, year FROM festivals 
     WHERE status = 'published' AND id != ? 
     ORDER BY year DESC, 
     CASE season 
         WHEN 'Winter' THEN 1 
         WHEN 'Spring' THEN 2 
         WHEN 'Summer' THEN 3 
         WHEN 'Fall' THEN 4 
     END DESC 
     LIMIT 5",
    [$id]
);

if (!empty($otherFestivals)) {
    $content .= '<div class="award-section">
        <h2 class="text-ssa mb-3">
            <i class="fas fa-film me-2"></i>Other Festivals
        </h2>
        <div class="row">';
    
    foreach ($otherFestivals as $otherFestival) {
        $content .= '
        <div class="col-md-6 mb-3">
            <div class="festival-card">
                <h5 class="text-ssa">' . escape($otherFestival['title']) . '</h5>
                <p class="text-muted">' . escape($otherFestival['season'] . ' ' . $otherFestival['year']) . '</p>
                <a href="' . SITE_URL . '/festival.php?id=' . $otherFestival['id'] . '" class="btn btn-outline-ssa btn-sm">
                    View Festival
                </a>
            </div>
        </div>';
    }
    
    $content .= '</div>
        <div class="text-center mt-3">
            <a href="' . SITE_URL . '/festivals.php" class="btn btn-ssa">
                <i class="fas fa-list me-2"></i>View All Festivals
            </a>
        </div>
    </div>';
}

renderLayout($title, $content, 'festival-page');
?>