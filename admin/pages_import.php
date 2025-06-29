<?php
// admin/import_pages.php - Import existing pages from HTML files
require_once '../config.php';
require_once 'includes/admin_layout.php';

requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Pre-defined page data based on the existing HTML files
$predefinedPages = [
    'rules' => [
        'title' => 'Rules & Guidelines',
        'content' => '
<h2>ENTRY FAQ</h2>
<p>Films must be less than 30 minutes in length to enter this festival.<br>
<strong>Preferred:</strong> An H264 or MP4 file compressed from the original 1080p or 720P master.<br>
<strong>Minimum Acceptable:</strong> An H264 or MP4 file at 480i. Do not rip from a DVD unless you have no other option.</p>

<ul>
<li>We prefer Vimeo to YouTube as the hosting platform.</li>
<li>Do NOT enable ads on your film or it will be disqualified without refund.</li>
<li>Do NOT put logo bugs or any other overlays on the film.</li>
<li>Do NOT start your film with a countdown slate, they are a waste of time.</li>
<li>We prefer end credits to be 60 seconds or less. (ALL film festivals hate long end credits BTW)</li>
<li>Experimental films will be reclassified as Review Only, regardless which category they are entered under.</li>
<li>All films are scored and may receive an award for the film and/or cast or crew.</li>
<li>We ONLY screen the top 3 award-winning films in each genre and films scoring 70 or higher. So your film may receive an award yet NOT be accepted for the festival.</li>
<li>Laurels are ONLY awarded to those films which are shown at the festival and are not sent until AFTER the festival.</li>
</ul>

<h3>PLEASE NOTE!</h3>
<p><strong>PARTICIPATION IS MANDATORY FOR ALL SELECTED FILMS.</strong><br>
If your film is selected for screening, the Producer and/or Director must either attend the online Awards Presentation or send in an acceptance speech video prior to the festival. If no one represents your film at the Awards Presentation and you do not send in an acceptance speech video you will forfeit all awards and you will not receive laurels.</p>

<h3>STUDENT ENTRIES</h3>
<p>PLEASE NOTE: Films must be produced by those under 21. This category is not for filmmakers with professional experience. We reserve the right to reclassify any entry into the Student category. If rejected, you will be refunded and must re-enter under the proper genre at the non-Student entry fee.</p>

<h3>MADE IN GEORGIA</h3>
<p>To qualify for this category, the majority of your film must have been produced in the state of Georgia and you must supply a Georgia address. The sole reason this category exists is to increase filmmaker participation and audience attendance. If your film is selected to be screened, the Producer and/or Director MUST attend the festival - this is MANDATORY.</p>

<h3>REVIEW ONLY</h3>
<p>In order to accommodate those films which do not fit into our stated genres, we offer the option of choosing the REVIEW ONLY category. These films will be scored, receive judges comments and can earn awards, but they will NOT be eligible for festival competition or screening. We reserve the right to move any film into the Review Only category at our discretion.</p>

<h2>Rules</h2>
<ol>
<li>This is a seasonal short film competition with the running time limited to 30 minutes, including titles and credits. Entries with a run time longer than 30 minutes will be disqualified.</li>
<li>Non-English speaking films must be sub-titled to be accepted. Please have a native English speaker check your translation for errors. Subtitles must be readable!</li>
<li>All works must be submitted digitally online directly to FilmFreeway. Do NOT use Dropbox, We Transfer or any similar file-sharing service.</li>
<li>We have no completion by deadlines. We will accept any short film regardless of when it was produced.</li>
<li>Works in progress will NOT be accepted for submission.</li>
<li>Make sure you are submitting the final version as you cannot change the link to your film once it has been submitted.</li>
<li>Films may be resubmitted in future competitions IF they have been re-edited. Resubmissions are not discounted as they go through the same judging process.</li>
<li>We do not accept pornographic, propaganda or hate films, films with specific political agendas or those promoting or proselytizing any religious beliefs.</li>
<li>Titles & Credits: Every festival has to consider screening time when selecting films and ALL of them hate long titles and/or credits. It is in your best interest to keep your opening titles to 30 seconds and your end credits to under a minute.</li>
</ol>

<h2>Terms & Conditions</h2>
<ol>
<li>Southern Shorts Awards is hereby granted the right to utilize an excerpt from any film submitted for promotional purposes.</li>
<li>Southern Shorts Awards is hereby granted the right to share your contact information in specific instances when someone inquires about your production and is trying to make contact with you.</li>
<li>The individual or corporation submitting the film hereby warrants that it is authorized to submit the film for consideration, and understands and accepts these requirements and regulations.</li>
<li>By entering your film for consideration to the Southern Shorts Awards Film Festival, you authorize that your work is cleared for festival exhibition and accept full legal responsibility for the intellectual property therein.</li>
<li>Entrants who were not granted awards will be notified by email, which will also include their score sheet. Results may be delayed, dependent upon the volume of entries.</li>
<li>Southern Shorts Awards winners will be emailed their scoresheet, along with a PDF of their Award Certificate(s) and their laurels. Printed certificates, plaques and trophies must be purchased.</li>
</ol>',
        'meta_description' => 'Complete rules and guidelines for submitting to the Southern Shorts Awards Film Festival. Learn about entry requirements, categories, and terms & conditions.',
        'sort_order' => 2
    ],
    
    'judges' => [
        'title' => 'Our Judges',
        'content' => '
<h2>Festival Judges</h2>
<p>Our Judge pool consists of industry professionals who have worked on short films, features and/or TV series and all have IMDB credits or work in video production or education. Judges provide feedback on all entries which are sent to the submitter along with their score sheet. For reasons of security and integrity, we do not disclose which Judges scored which films.</p>

<div class="row mb-4">
    <div class="col-md-3">
        <img src="' . SITE_URL . '/assets/images/My Headshot.jpg" class="img-fluid rounded" alt="Stephen P. Sherwood">
    </div>
    <div class="col-md-9">
        <h4>Stephen P. Sherwood - Festival Director</h4>
        <p>Festival Director Stephen P. Sherwood is an award winning writer, director and editor with more than 70 credits on IMDb, including feature films and TV series. He runs a bi-weekly scriptwriting workshop and has written a three-part sci-fi/fantasy mini-series, three features and more than a dozen shorts to date. He has produced four award-winning short films which have all been shown in multiple festivals and is currently working on a sci-fi fantasy podcast.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <img src="' . SITE_URL . '/assets/images/Kevin-Powers.jpg" class="img-fluid rounded" alt="Kevin L. Powers">
    </div>
    <div class="col-md-9">
        <h4>Kevin L. Powers</h4>
        <p>Kevin L. Powers is the former Program Director for the Gwinnett Center International Film Festival who is also a writer/producer/director of feature and short films. He has been working in the independent film industry for over fifteen years and has worked on various shorts, features, web series and documentary. In addition to the SSA, he is also working on a new feature film to be filmed in Georgia with local cast & crew.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <img src="' . SITE_URL . '/assets/images/Gohike.png" class="img-fluid rounded" alt="Joshua Gohlke">
    </div>
    <div class="col-md-9">
        <h4>Joshua Gohlke</h4>
        <p>Joshua is a local Atlanta filmmaker who has lead commercial, narrative, and documentary projects. He works most often in post-production, editing as his specialty. He got his start in filmmaking a decade ago by accident on a zombie comedy movie and has explored most aspects of film since then. He is the sound editor for Atlanta Film Chat.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <img src="' . SITE_URL . '/assets/images/Steve-Anderson.jpg" class="img-fluid rounded" alt="Steve Anderson">
    </div>
    <div class="col-md-9">
        <h4>Steve Anderson</h4>
        <p>Steve Anderson is a skilled and seasoned Director of Photography and Colorist who has worked on everything from features to TV shows, commercials, industrials and shorts including the series "America\'s Castles" and the documentaries "A Man Named Pearl," and "Restore America" among many others.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <img src="' . SITE_URL . '/assets/images/Ted-Rubin.jpg" class="img-fluid rounded" alt="Ted Rubin">
    </div>
    <div class="col-md-9">
        <h4>Ted Rubin</h4>
        <p>Ted Rubin has been a Producer on many local productions. Having worked in almost every facet of film-production, he has effectively collaborated with cast and crew members on their production and personal needs. Combined with his ability to create efficient and professional sets with hard work and professionalism, he combines Hollywood standards with southern hospitality.</p>
    </div>
</div>',
        'meta_description' => 'Meet the industry professional judges who review and score all film submissions to the Southern Shorts Awards Festival.',
        'sort_order' => 8
    ],
    
    'faq' => [
        'title' => 'Frequently Asked Questions',
        'content' => '
<h2>Frequently Asked Questions</h2>

<div class="mb-4">
    <h4>Q: Do you offer waivers?</h4>
    <p><strong>A:</strong> No. We are a small, unsponsored festival and can only survive if we get enough entry fees to cover our costs.</p>
</div>

<div class="mb-4">
    <h4>Q: What is a seasonal competition festival?</h4>
    <p><strong>A:</strong> We have a Winter, Spring, Summer and Fall season, and each is comprised of a 3 month entry period. Two weeks after the submissions deadline, the scoresheets and judges comments are sent out and a month later we have the actual festival.</p>
</div>

<div class="mb-4">
    <h4>Q: Do I have to live in the South to submit?</h4>
    <p><strong>A:</strong> No. Our headquarters is in the Atlanta area, which is why it is called the Southern Shorts Awards. We accept films from anywhere.</p>
</div>

<div class="mb-4">
    <h4>Q: So even if I don\'t get an award, I\'ll still get feedback?</h4>
    <p><strong>A:</strong> Yes! All entries receive an email showing the composite scores of the three judges who viewed the film. This will show you where you excel and which skills you need to work on.</p>
</div>

<div class="mb-4">
    <h4>Q: Why should I choose Southern Shorts Awards?</h4>
    <p><strong>A:</strong> It\'s a question of value. Our judges are industry professionals who take the time to watch and score your film. Many festivals use judges which may have absolutely no experience in the industry. Most festivals do not list their scoring criteria, and some will not even notify you that you didn\'t make it into their event, much less provide feedback. As far as we know, we are the ONLY competition where you receive a scoresheet at no additional cost.</p>
</div>

<div class="mb-4">
    <h4>Q: I sent my film into a bunch of festivals and didn\'t even get into one. Does that mean I would get a low score from you?</h4>
    <p><strong>A:</strong> Absolutely not. Festivals vary widely in how they staff and judge incoming films, and most use only one person to decide if it makes the cut. Southern Shorts is different. Every film is viewed by three judges which makes your evaluation more objective.</p>
</div>

<div class="mb-4">
    <h4>Q: So I can get an award even if I\'m not chosen to be shown at the festival?</h4>
    <p><strong>A:</strong> Yes, if your film scores a composite of at least 70 out of 100 or your cast and/or crew score a 7 or higher individually. Only the top three scorers of each genre are screened at the festival as that season\'s best. As we grow, we hope to be able to make the festival longer and include more award winners.</p>
</div>

<div class="mb-4">
    <h4>Q: Will the film award winners be listed on your site?</h4>
    <p><strong>A:</strong> Yes. We will set up a page listing each season\'s film winners which will also link to the video of that season\'s Awards Presentation.</p>
</div>

<div class="mb-4">
    <h4>Q: Do you accept experimental films, music/dance videos, commercials, trailers or PSA\'s?</h4>
    <p><strong>A:</strong> We only accept films which tell stories in our main categories. However, we have added a "Review Only" category which will accept those film types listed above. "Review Only" films will receive a scoresheet and Judges Comments, but are not eligible to be screened at the festival.</p>
</div>',
        'meta_description' => 'Answers to frequently asked questions about the Southern Shorts Awards Film Festival submission process, judging, and awards.',
        'sort_order' => 4
    ],
    
    'scoring' => [
        'title' => 'How We Score',
        'content' => '
<h2>How We Score Films</h2>
<p>ALL entries receive a scoresheet like this one:</p>

<div class="text-center mb-4">
    <img src="' . SITE_URL . '/assets/images/Scoresheet Example.png" class="img-fluid" alt="Sample Scoresheet" style="max-width: 500px;">
</div>

<p>In this example, the film earned a total score of 72.33, which means it would receive an Award of Merit. The Writer, Director, Editor and Audio Engineer would also receive Awards of Merit, and the Director of Photography would receive an Award of Excellence. For a Cast or Crewmember to receive an award, all three judges must nominate them with an average score of 7 or higher. So in this case, John Smith would receive an Award of Excellence and Jane Doe would get an Award of Merit.</p>

<h3>SCORING</h3>
<p>Each of the 10 criteria are scored on a scale from 1 to 10. A perfect score would be 100. Films must receive a minimum composite score of 70 to receive an award.</p>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th colspan="2" class="text-center">FILM</th>
                <th colspan="2" class="text-center">CAST & CREW</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center"><strong>70-79</strong></td>
                <td>Award of Merit</td>
                <td class="text-center"><strong>7</strong></td>
                <td>Award of Merit</td>
            </tr>
            <tr>
                <td class="text-center"><strong>80-89</strong></td>
                <td>Award of Excellence</td>
                <td class="text-center"><strong>8</strong></td>
                <td>Award of Excellence</td>
            </tr>
            <tr>
                <td class="text-center"><strong>90-95</strong></td>
                <td>Award of Distinction</td>
                <td class="text-center"><strong>9</strong></td>
                <td>Award of Distinction</td>
            </tr>
            <tr>
                <td class="text-center"><strong>96-100</strong></td>
                <td>The Orson Award</td>
                <td class="text-center"><strong>9.6+</strong></td>
                <td>Master of the Craft</td>
            </tr>
        </tbody>
    </table>
</div>

<p>Judges will also consider standout individual performances in the following positions:</p>
<ul>
    <li>Writer</li>
    <li>Director</li>
    <li>Director of Photography</li>
    <li>Editor</li>
    <li>Sound Design Engineer</li>
    <li>Lead Actor</li>
    <li>Lead Actress</li>
    <li>Supporting Actor</li>
    <li>Supporting Actress</li>
    <li>Make Up Artist</li>
    <li>Costuming</li>
    <li>Visual Effects</li>
</ul>

<p>Individuals must earn a composite score of 7 or higher in order to receive an award. As an example, if a leading actor had an outstanding performance and all three Judges awarded them an average of at least 7 points, they could receive an award even if the film did not.</p>',
        'meta_description' => 'Learn how Southern Shorts Awards scores and evaluates film submissions using our professional 3-judge scoring system.',
        'sort_order' => 5
    ],
    
    'awards' => [
        'title' => 'The Awards',
        'content' => '
<h2>The Awards</h2>
<p>Every award winner will receive a free PDF of their Award Certificate.</p>

<h3>Award Types</h3>
<p>There are two levels of awards:</p>

<div class="mb-4">
    <h4>1. Individual Achievement Awards</h4>
    <p>PDF Certificates which are included with the filmmaker\'s scoresheet and judges comments on the scoring notification day. These are non-competitive and are based on the scores of three judges who screen and score each film, as well as cast and crew individual achievements. Films must receive a minimum averaged score of 70 to receive an award and individuals must receive a minimum averaged score of 7. You will not receive laurels with these awards and are not authorized to use our laurels unless we have sent them to you.</p>
</div>

<div class="mb-4">
    <h4>2. Individual Achievement Awards for Cast & Crew</h4>
    <p>Given to the cast and crew of every film who score an average of 7 or higher on the score sheet. The Individual Achievement Awards are an acknowledgment of each person\'s contribution to their film. These award certificates are independent of the festival awards, which are competitive. Nominees for the Best of Show are the top five highest scores over 8 within each category and you will receive a separate notification if any of your cast or crew have been nominated.</p>
</div>

<div class="mb-4">
    <h4>3. Festival Awards</h4>
    <p>Competitive and are based on the judges scores. The top 3 award-winning films of each genre are then nominated as the best of that genre. This means your film must score at least 70 out of 100 points to be nominated. For example, in the Drama genre, the highest scoring film wins Best Drama of that season, the second highest wins Best Drama Award of Excellence, and the third the Best Drama Award of Merit. Every film screened at the festival will receive and is authorized to use our laurels as provided, without modification.</p>
</div>

<div class="mb-4">
    <h4>4. Laurels</h4>
    <p>Will ONLY be granted to those films which are actually screen at each festival. Even then, you must use the laurels as supplied without making your own modifications. You may only use laurels if we have supplied them to you, however, you may post your Individual Achievement Awards certificates freely.</p>
</div>

<h3>Awards Presentation</h3>
<p>All festival awards are given out at the Awards Presentation at the end of the screenings. This is a black-tie optional event with a host who will show shorts clips from each nominee, then open the envelope and announce the winners. Each winner present can come to the podium to receive their award and the entire event is videoed and available on our website the following week.</p>

<p>We also give Best Of Show trophies for Best Picture, Director, Screenplay, Actor, Actress, Cinematographer, Editor, Sound Design, Music, Production Design, Visual FX, Makeup FX and Costume Design.</p>

<div class="alert alert-info">
    <h5>COVID 19 UPDATE</h5>
    <p>In 2020, we moved to an online model using Zoom for our Awards Presentation. This will continue until further notice. Beginning with the Winter 2021 Season, we can no longer afford to give out free trophies and plaques to award winners. All award winners will be sent certificates free of charge, but trophies and plaques must be purchased from our store. We will provide a discount code to all filmmakers who participate in the live Awards Presentation.</p>
</div>

<h3>Shipping Information</h3>
<p><strong>U.S. NON-ATTENDING FILMMAKER AWARDS:</strong><br>
If you cannot attend or send someone to accept your films\' award during the festival, you may have your award sent to you after the festival by paying a fee. Shipping costs are as follows and are per award: Certificate - $10, Plaque - $20, Trophy - $30.</p>

<p><strong>FOREIGN NON-ATTENDING FILMMAKER AWARDS:</strong><br>
If you cannot attend or send someone to accept your films\' award during the festival, you may have your award sent to you after the festival by paying a fee. Foreign shipping fees vary, depending on destination, and are determined on a case-by-case basis.</p>

<p><strong>ORDERING AWARDS:</strong><br>
Whether your film is screened or not, you may order a copy of your film and individual awards as a Certificate, Plaque or Trophy at our store. Anyone who has ever received an award from our festival is also eligible to order our Award Winner pins.</p>

<div