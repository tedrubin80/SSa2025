<?php
// index.php - Public homepage
require_once 'config.php';
require_once 'includes/layout.php';

$db = Database::getInstance();

// Get latest published festival
$latestFestival = $db->fetchOne(
    "SELECT * FROM festivals 
     WHERE status = 'published' 
     ORDER BY year DESC, 
     CASE season 
         WHEN 'Winter' THEN 1 
         WHEN 'Spring' THEN 2 
         WHEN 'Summer' THEN 3 
         WHEN 'Fall' THEN 4 
     END DESC 
     LIMIT 1"
);

// Get recent festivals for showcase
$recentFestivals = $db->fetchAll(
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
     END DESC 
     LIMIT 3"
);

// Get total statistics
$stats = [
    'total_festivals' => $db->fetchOne("SELECT COUNT(*) as count FROM festivals WHERE status = 'published'")['count'],
    'total_films' => $db->fetchOne("SELECT COUNT(*) as count FROM films f JOIN festivals fest ON f.festival_id = fest.id WHERE fest.status = 'published'")['count'],
    'total_awards' => $db->fetchOne("SELECT COUNT(*) as count FROM awards a JOIN festivals fest ON a.festival_id = fest.id WHERE fest.status = 'published'")['count']
];

$content = '';

// Hero section with latest festival
if ($latestFestival && !empty($latestFestival['featured_video_url'])) {
    $embedUrl = '';
    
    // Convert video URL to embed format
    if (strpos($latestFestival['featured_video_url'], 'vimeo.com') !== false) {
        if (preg_match('/vimeo\.com\/(\d+)/', $latestFestival['featured_video_url'], $matches)) {
            $embedUrl = 'https://player.vimeo.com/video/' . $matches[1];
        }
    } elseif (strpos($latestFestival['featured_video_url'], 'youtube.com') !== false || strpos($latestFestival['featured_video_url'], 'youtu.be') !== false) {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $latestFestival['featured_video_url'], $matches)) {
            $embedUrl = 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=1&mute=1';
        }
    } else {
        $embedUrl = $latestFestival['featured_video_url'];
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

// Main intro section
$content .= '
<div class="text-center mb-5">
    <h1 class="text-ssa mb-3">Enter your short film at our festival, because:</h1>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="row text-start">
                        <div class="col-md-6">
                            <p class="fs-5"><em><strong>1.</strong> It\'s a quarterly Competition & Festival for films under 30 minutes.</em></p>
                            <p class="fs-5"><em><strong>2.</strong> ALL entries receive a scoresheet.</em></p>
                            <p class="fs-5"><em><strong>3.</strong> The Festival is online and the Awards Presentation is live-streamed on Zoom!</em></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-5"><em><strong>4.</strong> ALL films are scored by 3 judges.</em></p>
                            <p class="fs-5"><em><strong>5.</strong> Cast & Crew may receive individual awards.</em></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';

// Latest festival highlight
if ($latestFestival) {
    $content .= '
    <div class="card mb-5 border-0" style="background: linear-gradient(135deg, var(--ssa-cream) 0%, #ffffff 100%);">
        <div class="card-body text-center py-5">
            <h2 class="text-ssa mb-3">Latest Festival Season</h2>
            <h3 class="festival-title">' . escape($latestFestival['title']) . '</h3>
            <p class="lead">' . escape($latestFestival['season'] . ' ' . $latestFestival['year']) . ' Awards & Results</p>
            
            <div class="row justify-content-center mt-4">
                <div class="col-md-8">
                    <a href="' . SITE_URL . '/festival.php?id=' . $latestFestival['id'] . '" class="btn btn-ssa btn-lg">
                        <i class="fas fa-trophy me-2"></i>View Award Winners
                    </a>';
    
    if (!empty($latestFestival['program_pdf_url'])) {
        $content .= '
                    <a href="' . escape($latestFestival['program_pdf_url']) . '" target="_blank" class="btn btn-outline-ssa btn-lg ms-3">
                        <i class="fas fa-file-pdf me-2"></i>Download Program
                    </a>';
    }
    
    $content .= '
                </div>
            </div>
        </div>
    </div>';
}

// Recent festivals showcase
if (!empty($recentFestivals)) {
    $content .= '
    <div class="mb-5">
        <h2 class="text-ssa text-center mb-4">Recent Festival Seasons</h2>
        <div class="row">';
    
    foreach ($recentFestivals as $festival) {
        $seasonBadge = match($festival['season']) {
            'Spring' => 'bg-success',
            'Summer' => 'bg-warning',
            'Fall' => 'bg-danger',
            'Winter' => 'bg-info',
            default => 'bg-secondary'
        };
        
        $content .= '
        <div class="col-md-4 mb-4">
            <div class="festival-card h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <span class="badge ' . $seasonBadge . '">' . escape($festival['season']) . '</span>
                    <span class="badge bg-secondary">' . escape($festival['year']) . '</span>
                </div>
                
                <h4 class="festival-title">' . escape($festival['title']) . '</h4>
                
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
                
                <a href="' . SITE_URL . '/festival.php?id=' . $festival['id'] . '" class="btn btn-outline-ssa w-100">
                    View Results
                </a>
            </div>
        </div>';
    }
    
    $content .= '
        </div>
        
        <div class="text-center">
            <a href="' . SITE_URL . '/festivals.php" class="btn btn-ssa">
                <i class="fas fa-list me-2"></i>View All Festivals
            </a>
        </div>
    </div>';
}

// Statistics section
$content .= '
<div class="card border-0 mb-5" style="background: var(--ssa-red); color: white;">
    <div class="card-body text-center py-5">
        <h2 class="mb-4">Southern Shorts Awards By The Numbers</h2>
        <div class="row">
            <div class="col-md-4 mb-3">
                <i class="fas fa-film fa-3x mb-3"></i>
                <h3>' . $stats['total_festivals'] . '</h3>
                <p class="fs-5">Festival Seasons</p>
            </div>
            <div class="col-md-4 mb-3">
                <i class="fas fa-video fa-3x mb-3"></i>
                <h3>' . $stats['total_films'] . '</h3>
                <p class="fs-5">Films Showcased</p>
            </div>
            <div class="col-md-4 mb-3">
                <i class="fas fa-trophy fa-3x mb-3"></i>
                <h3>' . $stats['total_awards'] . '</h3>
                <p class="fs-5">Awards Given</p>
            </div>
        </div>
    </div>
</div>';

// Call to action section
$filmFreewayUrl = getSetting('filmfreeway_url', '#');
$content .= '
<div class="text-center mb-5">
    <h2 class="text-ssa mb-4">Ready to Enter Your Film?</h2>
    <p class="lead mb-4">Join our community of award-winning filmmakers. Submit your short film today and get professional feedback from industry judges.</p>
    
    <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="' . escape($filmFreewayUrl) . '" target="_blank" class="btn btn-ssa btn-lg">
            <i class="fas fa-upload me-2"></i>Submit via FilmFreeway
        </a>
        <a href="' . SITE_URL . '/rules.php" class="btn btn-outline-ssa btn-lg">
            <i class="fas fa-file-alt me-2"></i>View Submission Rules
        </a>
    </div>
    
    <div class="mt-4">
        <p class="text-muted">Questions? Check our <a href="' . SITE_URL . '/faq.php" class="text-ssa">FAQ</a> or <a href="' . SITE_URL . '/contact.php" class="text-ssa">contact us</a></p>
    </div>
</div>';

renderLayout('Home', $content, 'homepage');
?>