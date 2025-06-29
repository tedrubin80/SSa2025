<?php
// festivals.php - Public festivals listing page
require_once 'config.php';
require_once 'includes/layout.php';

$db = Database::getInstance();

// Get all published festivals in chronological order
$festivals = $db->fetchAll(
    "SELECT f.*, 
     (SELECT COUNT(*) FROM films WHERE festival_id = f.id) as film_count,
     (SELECT COUNT(*) FROM awards WHERE festival_id = f.id) as award_count
     FROM festivals f 
     WHERE f.status = 'published'
     ORDER BY f.year DESC, 
     CASE f.season 
         WHEN 'Winter' THEN 1 
         WHEN 'Spring' THEN 2 
         WHEN 'Summer' THEN 3 
         WHEN 'Fall' THEN 4 
     END DESC"
);

// Group festivals by year for better organization
$festivalsByYear = [];
foreach ($festivals as $festival) {
    $festivalsByYear[$festival['year']][] = $festival;
}

$content = '
<div class="text-center mb-5">
    <h1 class="text-ssa mb-3">Festival Seasons</h1>
    <p class="lead">Explore our award-winning short films from past festival seasons</p>
</div>';

if (empty($festivals)) {
    $content .= '
    <div class="text-center py-5">
        <i class="fas fa-film fa-4x text-muted mb-4"></i>
        <h3 class="text-muted">No Festivals Available</h3>
        <p class="text-muted">Festival seasons will be published here when they become available.</p>
    </div>';
} else {
    // Display festivals grouped by year
    foreach ($festivalsByYear as $year => $yearFestivals) {
        $content .= '
        <div class="mb-5">
            <h2 class="text-ssa border-bottom border-danger pb-2 mb-4">
                <i class="fas fa-calendar-alt me-2"></i>' . $year . ' Seasons
            </h2>
            
            <div class="row">';
        
        foreach ($yearFestivals as $festival) {
            // Create excerpt from description
            $excerpt = '';
            if (!empty($festival['description'])) {
                $excerpt = strip_tags($festival['description']);
                if (strlen($excerpt) > 150) {
                    $excerpt = substr($excerpt, 0, 150) . '...';
                }
            }
            
            // Determine season badge color
            $seasonBadge = match($festival['season']) {
                'Spring' => 'bg-success',
                'Summer' => 'bg-warning',
                'Fall' => 'bg-danger',
                'Winter' => 'bg-info',
                default => 'bg-secondary'
            };
            
            $content .= '
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="festival-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge ' . $seasonBadge . ' fs-6">' . escape($festival['season']) . '</span>
                        <span class="badge bg-secondary">' . escape($festival['year']) . '</span>
                    </div>
                    
                    <h4 class="festival-title">' . escape($festival['title']) . '</h4>';
            
            if (!empty($excerpt)) {
                $content .= '<p class="text-muted mb-3">' . escape($excerpt) . '</p>';
            }
            
            // Festival dates if available
            if (!empty($festival['start_date']) && !empty($festival['end_date'])) {
                $content .= '
                    <p class="text-muted small mb-3">
                        <i class="fas fa-calendar me-1"></i>
                        ' . formatDate($festival['start_date']) . ' - ' . formatDate($festival['end_date']) . '
                    </p>';
            }
            
            // Stats
            $content .= '
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="border-end">
                                <h6 class="text-ssa mb-0">' . $festival['film_count'] . '</h6>
                                <small class="text-muted">Films</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h6 class="text-ssa mb-0">' . $festival['award_count'] . '</h6>
                            <small class="text-muted">Awards</small>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="' . SITE_URL . '/festival.php?id=' . $festival['id'] . '" class="btn btn-ssa">
                            <i class="fas fa-trophy me-2"></i>View Festival Results
                        </a>';
            
            // Program PDF link if available
            if (!empty($festival['program_pdf_url'])) {
                $content .= '
                        <a href="' . escape($festival['program_pdf_url']) . '" target="_blank" class="btn btn-outline-ssa btn-sm">
                            <i class="fas fa-file-pdf me-2"></i>Download Program
                        </a>';
            }
            
            $content .= '
                    </div>
                </div>
            </div>';
        }
        
        $content .= '
            </div>
        </div>';
    }
    
    // Statistics section
    $totalFilms = array_sum(array_column($festivals, 'film_count'));
    $totalAwards = array_sum(array_column($festivals, 'award_count'));
    $totalSeasons = count($festivals);
    
    $content .= '
    <div class="card mt-5">
        <div class="card-header text-center">
            <h4 class="text-ssa mb-0">Festival Statistics</h4>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <i class="fas fa-film fa-2x text-ssa mb-2"></i>
                    <h3 class="text-ssa">' . $totalSeasons . '</h3>
                    <p class="text-muted">Festival Seasons</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-video fa-2x text-ssa mb-2"></i>
                    <h3 class="text-ssa">' . $totalFilms . '</h3>
                    <p class="text-muted">Films Showcased</p>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-trophy fa-2x text-ssa mb-2"></i>
                    <h3 class="text-ssa">' . $totalAwards . '</h3>
                    <p class="text-muted">Awards Given</p>
                </div>
            </div>
        </div>
    </div>';
}

// Call to action
$filmFreewayUrl = getSetting('filmfreeway_url', '#');
$content .= '
<div class="card bg-light border-0 mt-5">
    <div class="card-body text-center py-5">
        <h3 class="text-ssa mb-3">Ready to Submit Your Film?</h3>
        <p class="lead mb-4">Join our community of award-winning filmmakers and submit your short film today.</p>
        <a href="' . escape($filmFreewayUrl) . '" target="_blank" class="btn btn-ssa btn-lg">
            <i class="fas fa-upload me-2"></i>Submit via FilmFreeway
        </a>
        <div class="mt-3">
            <a href="' . SITE_URL . '/rules.php" class="btn btn-outline-ssa me-2">
                <i class="fas fa-file-alt me-2"></i>View Rules
            </a>
            <a href="' . SITE_URL . '/faq.php" class="btn btn-outline-ssa">
                <i class="fas fa-question-circle me-2"></i>FAQ
            </a>
        </div>
    </div>
</div>';

renderLayout('Festivals', $content, 'festivals-page');
?>