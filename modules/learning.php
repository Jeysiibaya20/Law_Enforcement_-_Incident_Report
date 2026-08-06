<?php
$base_url = '../';
require_once __DIR__ . '/../includes/user_auth.php';
$page_title = 'Learning & Awareness Guide';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/header.php';

// --- MOCK PROGRESS STATE (SIMULATING DB FETCH) ---
// This array simulates the initial default state.
$default_mock_progress = [
    // Recommended courses start at 0%
    101 => ['progress' => 0, 'status' => 'Not Started'],
    102 => ['progress' => 0, 'status' => 'Not Started'],
    103 => ['progress' => 0, 'status' => 'Not Started'],
    // General courses have existing progress
    201 => ['progress' => 65, 'status' => 'In Progress'],
    202 => ['progress' => 100, 'status' => 'Completed'], // Completed course for review mock
    203 => ['progress' => 20, 'status' => 'In Progress'],
    301 => ['progress' => 0, 'status' => 'Not Started'],
    302 => ['progress' => 0, 'status' => 'Not Started'],
];

// Load progress from session, merging it with defaults. This ensures persistence.
if (!isset($_SESSION['training_progress'])) {
    $_SESSION['training_progress'] = $default_mock_progress;
}
$employee_training_progress = $_SESSION['training_progress'];


// --- ACTION HANDLER: START/CONTINUE/CANCEL COURSE ---
if (isset($_GET['action']) && isset($_GET['course_id'])) {
    $action = $_GET['action'];
    $course_id = (int)$_GET['course_id'];

    if (isset($employee_training_progress[$course_id])) {
        
        $current_progress = $employee_training_progress[$course_id]['progress'];
        $current_status = $employee_training_progress[$course_id]['status'];
        
        $new_progress = $current_progress;
        $new_status = $current_status;

        $redirect_needed = false;
        
        if ($action === 'start_course') {
            if ($current_status === 'Not Started') {
                // Starting a new course, set progress to 10%
                $new_progress = 10;
                $new_status = 'In Progress';
                $redirect_needed = true;
            } elseif ($current_status === 'In Progress' && $current_progress < 100) {
                // Continuing a course, advance it by 20%
                $new_progress = min(100, $current_progress + 20);
                $new_status = ($new_progress === 100) ? 'Completed' : 'In Progress';
                $redirect_needed = true;
            }
        } elseif ($action === 'cancel_course') {
            if ($current_status !== 'Completed') {
                // Reset course if it's not completed
                $new_progress = 0;
                $new_status = 'Not Started';
                $redirect_needed = true;
            }
        }

        if ($redirect_needed) {
            // Apply changes to the session data
            $_SESSION['training_progress'][$course_id]['progress'] = $new_progress;
            $_SESSION['training_progress'][$course_id]['status'] = $new_status;

            // Redirect to clear the GET parameters and display updated data
            header("Location: learning.php?status=updated&cid=" . $course_id);
            exit();
        }
    }
}

// Use the session data for all subsequent processing logic
$mock_progress_db_defaults = $employee_training_progress;


// Mock Competency Gaps: Data retrieved from a performance/competency module
$competencies = [
    [
        'competency_id' => 1,
        'name' => 'Effective Communication',
        'required_level' => 4,
        'current_level' => 3,
        'gap' => 1,
    ],
    [
        'competency_id' => 2,
        'name' => 'Analytical Problem Solving',
        'required_level' => 5,
        'current_level' => 2,
        'gap' => 3,
    ],
    [
        'competency_id' => 3,
        'name' => 'Strategic Financial Planning',
        'required_level' => 4,
        'current_level' => 4,
        'gap' => 0,
    ],
    [
        'competency_id' => 4,
        'name' => 'Advanced Team Leadership',
        'required_level' => 5,
        'current_level' => 4,
        'gap' => 1,
    ],
];

// --- MOCK COURSE CATALOG (Combined with Review Data) ---
$all_courses = [
    // Recommended (High Gap-based) Courses
    [
        'id' => 101,
        'title' => 'Mastering Problem Solving Techniques',
        'category' => 'Analytical Skills',
        'duration' => '6h 30m',
        'description' => 'A deep dive into root cause analysis and creative problem resolution. Directly addresses a critical competency gap.',
        'target_competency' => 'Analytical Problem Solving',
        'recommended' => true,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['Introduction to RCA', '5-Why Analysis Template'],
    ],
    [
        'id' => 102,
        'title' => 'Advanced Data Interpretation',
        'category' => 'Analytical Skills',
        'duration' => '4h 0m',
        'description' => 'Learn to extract actionable insights from complex data sets.',
        'target_competency' => 'Analytical Problem Solving',
        'recommended' => true,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['Statistical Methods Guide', 'Advanced Pivot Tables'],
    ],
    [
        'id' => 103,
        'title' => 'Critical Thinking for Business',
        'category' => 'Analytical Skills',
        'duration' => '3h 45m',
        'description' => 'Develop foundational skills to evaluate arguments and make sound judgments.',
        'target_competency' => 'Analytical Problem Solving',
        'recommended' => true,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['Cognitive Bias Handbook'],
    ],
    
    // General Courses
    [
        'id' => 201,
        'title' => 'Effective Team Leadership in a Remote World',
        'category' => 'Management',
        'duration' => '8h 15m',
        'description' => 'Strategies for leading and motivating diverse, remote teams.',
        'target_competency' => 'Advanced Team Leadership',
        'recommended' => false,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['Remote Team Checklists'],
    ],
    [
        'id' => 202,
        'title' => 'Conflict Resolution and Mediation',
        'category' => 'Communication',
        'duration' => '2h 0m',
        'description' => 'Tools and techniques for turning workplace conflict into productive collaboration.',
        'target_competency' => 'Effective Communication',
        'recommended' => false,
        // --- REVIEW DATA CONSOLIDATED HERE ---
        'completion_date' => '2024-05-15',
        'instructor' => 'Dr. Eleanor Vance',
        'resources' => ['Summary PDF', 'Mediation Checklist', 'Case Studies'],
    ],
    [
        'id' => 203,
        'title' => 'Introduction to Project Management (PMP Prep)',
        'category' => 'Project Skills',
        'duration' => '12h 0m',
        'description' => 'A comprehensive introduction to the fundamentals of project management.',
        'target_competency' => 'N/A',
        'recommended' => false,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['PMP Glossary', 'Phase Gates Summary'],
    ],
    [
        'id' => 301,
        'title' => 'Cybersecurity Fundamentals',
        'category' => 'Technical',
        'duration' => '5h 30m',
        'description' => 'Essential knowledge for protecting corporate and personal data.',
        'target_competency' => 'N/A',
        'recommended' => false,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['Phishing Quiz', 'Security Policy Guide'],
    ],
    [
        'id' => 302,
        'title' => 'Advanced Spreadsheet Modeling',
        'category' => 'Technical',
        'duration' => '7h 15m',
        'description' => 'Go beyond the basics with advanced functions and data visualization.',
        'target_competency' => 'N/A',
        'recommended' => false,
        'completion_date' => 'N/A',
        'instructor' => 'N/A',
        'resources' => ['Excel Functions Cheatsheet'],
    ],
];

// --- DATA PROCESSING LOGIC ---

// 1. Merge Course Catalog with Employee Progress
$courses_with_progress = [];
$total_completed = 0;
$total_in_progress = 0;
$total_courses = count($all_courses);
$course_map = []; // Used for easy lookup later

foreach ($all_courses as $course) {
    $course_id = $course['id'];
    // Use the potentially updated progress data
    $progress_data = $mock_progress_db_defaults[$course_id] ?? ['progress' => 0, 'status' => 'Not Started'];

    $course['progress'] = $progress_data['progress'];
    $course['status'] = $progress_data['status'];
    $courses_with_progress[] = $course;
    $course_map[$course_id] = $course; // Map for quick lookup

    if ($course['status'] === 'Completed') {
        $total_completed++;
    } elseif ($course['status'] === 'In Progress') {
        $total_in_progress++;
    }
}

// 2. Separate Recommended (High Gap) Courses from All Courses
$recommended_courses = array_filter($courses_with_progress, function($course) {
    // Only recommend courses explicitly marked as such AND not completed
    return $course['recommended'] && $course['status'] !== 'Completed';
});

// 3. Group all courses by category for the main catalog view
$courses_by_category = [];
foreach ($courses_with_progress as $course) {
    $category = $course['category'];
    if (!isset($courses_by_category[$category])) {
        $courses_by_category[$category] = [];
    }
    $courses_by_category[$category][] = $course;
}

// 4. Calculate overall progress summary
$total_not_started = $total_courses - ($total_completed + $total_in_progress);
$overall_progress_percent = $total_courses > 0 ? round(($total_completed + ($total_in_progress / 2)) / $total_courses * 100) : 0;


// --- HELPER FUNCTION: Course Status Badge Renderer ---
/**
 * Renders a Bootstrap badge for the course status.
 */
function render_status_badge($status) {
    $class = '';
    switch ($status) {
        case 'Completed':
            $class = 'bg-success';
            break;
        case 'In Progress':
            $class = 'bg-primary';
            break;
        case 'Not Started':
            $class = 'bg-secondary';
            break;
        default:
            $class = 'bg-light text-dark';
            break;
    }
    return "<span class='badge {$class} fw-normal'>{$status}</span>";
}

// --- HELPER FUNCTION: Course Card Renderer ---
/**
 * Renders an individual course card.
 */
function render_course_card($course, $is_recommended = false) {
    $progress = $course['progress'];
    $status = $course['status'];
    $icon = 'bi-book';
    $button_text = 'Start Course';
    $button_class = 'btn-primary';

    $show_cancel_button = false;
    $cancel_url = "learning.php?action=cancel_course&course_id=" . $course['id'];

    // Determine the URL for the mock action handler (Start/Continue)
    $button_url = "learning.php?action=start_course&course_id=" . $course['id'];


    if ($status === 'Completed') {
        $icon = 'bi-check2-circle';
        $button_text = 'Review Course';
        $button_class = 'btn-success';
        // Link to the review view within learning.php
        $button_url = "learning.php?view=review&course_id=" . $course['id']; 
    } elseif ($status === 'In Progress') {
        $icon = 'bi-arrow-right-circle';
        $button_text = 'Continue (P.' . $progress . '%)';
        $button_class = 'btn-primary';
        $show_cancel_button = true; // Show cancel for in progress
    } else { // Not Started
        $icon = 'bi-play-circle';
        $show_cancel_button = true; // Show cancel for not started (as 'withdraw')
    }

    $card_class = $is_recommended ? 'border-primary shadow-sm' : 'shadow-sm';
    $icon_class = $is_recommended ? 'text-primary' : 'text-secondary';
    
    // Determine progress bar color
    $progress_bar_class = 'bg-primary';
    if ($progress == 100) {
        $progress_bar_class = 'bg-success';
    } elseif ($progress > 0) {
        $progress_bar_class = 'bg-info';
    }
    
    ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 <?php echo $card_class; ?> rounded-3">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($course['title']); ?></h5>
                    <div class="ms-3"><?php echo render_status_badge($status); ?></div>
                </div>
                <h6 class="card-subtitle mb-2 text-muted fw-light"><?php echo htmlspecialchars($course['category']); ?></h6>
                <p class="card-text text-sm mb-3 text-muted"><?php echo htmlspecialchars($course['description']); ?></p>
                
                <div class="mt-auto">
                    <div class="d-flex justify-content-between text-sm text-muted mb-1">
                        <span><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($course['duration']); ?></span>
                        <span><?php echo htmlspecialchars($progress); ?>% Complete</span>
                    </div>
                    
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar <?php echo $progress_bar_class; ?> rounded-pill" role="progressbar" style="width: <?php echo htmlspecialchars($progress); ?>%;" aria-valuenow="<?php echo htmlspecialchars($progress); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <!-- Button Group for Actions -->
                    <div class="d-flex gap-2 mt-2">
                        <!-- Primary Action Button (Start/Continue/Review) -->
                        <a href="<?php echo $button_url; ?>" class="btn <?php echo $button_class; ?> btn-sm flex-grow-1">
                            <i class="bi <?php echo htmlspecialchars($icon); ?> me-2"></i><?php echo htmlspecialchars($button_text); ?>
                        </a>
                        <!-- New Cancel Button -->
                        <?php if ($show_cancel_button): ?>
                            <a href="<?php echo $cancel_url; ?>" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// --- COURSE REVIEW VIEW RENDERER ---
/**
 * Renders the dedicated course review page content.
 */
function render_review_view($course) {
    ?>
    <div class="container-fluid py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="learning.php" class="text-primary">Learning Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Course Review</li>
                        </ol>
                    </nav>
                    <h1 class="display-5 text-success mb-2"><i class="bi bi-award-fill me-2"></i>Course Completed!</h1>
                    <p class="lead text-muted">You have successfully mastered **<?php echo htmlspecialchars($course['title']); ?>**.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="row">
            <!-- Course Details -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm h-100 rounded-3">
                    <div class="card-body">
                        <h3 class="card-title text-primary border-bottom pb-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p class="text-muted mb-4">Category: <?php echo htmlspecialchars($course['category']); ?> | Duration: <?php echo htmlspecialchars($course['duration']); ?></p>

                        <h4 class="h5 mt-4"><i class="bi bi-journal-text me-2"></i>Available Resources</h4>
                        <ul class="list-group list-group-flush mb-4">
                            <?php foreach ($course['resources'] as $resource): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><?php echo htmlspecialchars($resource); ?></span>
                                    <a href="#" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Download Mock</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <h4 class="h5 mt-4"><i class="bi bi-patch-check-fill me-2"></i>Certification Status</h4>
                        <div class="alert alert-success d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                            <div>
                                **Certificate Earned!** Completed on **<?php echo htmlspecialchars($course['completion_date']); ?>**.
                            </div>
                        </div>
                        <a href="#" class="btn btn-primary mt-2"><i class="bi bi-printer me-2"></i>Print Certificate (Mock)</a>
                    </div>
                </div>
            </div>

            <!-- Feedback & Instructor -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm h-100 rounded-3">
                    <div class="card-body">
                        <h4 class="h5 border-bottom pb-2 mb-3"><i class="bi bi-person-circle me-2"></i>Instructor Details</h4>
                        <p><strong><?php echo htmlspecialchars($course['instructor']); ?></strong></p>
                        <p class="text-muted small">Thank you for your engaging instruction!</p>
                        
                        <h4 class="h5 border-bottom pb-2 mt-4 mb-3"><i class="bi bi-chat-dots-fill me-2"></i>Leave Feedback</h4>
                        <form action="#" method="POST">
                            <div class="mb-3">
                                <label for="rating" class="form-label">Course Rating (1-5)</label>
                                <select id="rating" name="rating" class="form-select">
                                    <option>5 Stars - Excellent</option>
                                    <option>4 Stars - Good</option>
                                    <option>3 Stars - Average</option>
                                    <option>2 Stars - Fair</option>
                                    <option>1 Star - Poor</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="feedback" class="form-label">Comments</label>
                                <textarea id="feedback" name="feedback" class="form-control" rows="3" placeholder="What did you like or what could be improved?"></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning w-100"><i class="bi bi-send-fill me-2"></i>Submit Feedback</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// --- MAIN PAGE RENDERING LOGIC ---

$view = $_GET['view'] ?? 'dashboard';
$course_id = (int)($_GET['course_id'] ?? 0);

if ($view === 'review' && $course_id && isset($course_map[$course_id])) {
    // RENDER COURSE REVIEW VIEW
    $course_data = $course_map[$course_id];
    render_review_view($course_data);
} else {
    // RENDER DASHBOARD VIEW
    ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="container-fluid px-md-4">
                <h1 class="mb-2 text-primary font-weight-bold"><i class="fas fa-book-open me-3"></i>Awareness & Learning Guide</h1>
                <p class="lead text-muted mb-4">Community safety awareness guides, emergency procedures, and law enforcement training paths.</p>
            
            <!-- ROW 1: Competency Gaps & Overall Progress -->
            <div class="row mb-5">
                <!-- Competency Gaps Card -->
                <div class="col-lg-5 mb-4">
                    <div class="card shadow-lg h-100 rounded-4">
                        <div class="card-header bg-white border-bottom-0 pt-4">
                            <h4 class="card-title text-danger"><i class="bi bi-person-exclamation me-2"></i>My Competency Gaps</h4>
                            <p class="text-muted mb-0">Areas where your current skill level is below the required level for your role.</p>
                        </div>
                        <ul class="list-group list-group-flush border-top">
                            <?php 
                            $has_gap = false;
                            foreach ($competencies as $comp): 
                                if ($comp['gap'] > 0):
                                    $has_gap = true;
                                    $gap_class = $comp['gap'] >= 2 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning';
                                    $gap_label = $comp['gap'] >= 2 ? 'High Priority Gap' : 'Medium Gap';
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-medium"><?php echo htmlspecialchars($comp['name']); ?></span>
                                        <small class="d-block text-muted">Required: Lvl <?php echo $comp['required_level']; ?> | Current: Lvl <?php echo $comp['current_level']; ?></small>
                                    </div>
                                    <span class="badge <?php echo $gap_class; ?> rounded-pill p-2"><?php echo htmlspecialchars($gap_label); ?></span>
                                </li>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$has_gap):
                            ?>
                                <li class="list-group-item">
                                    <div class="alert alert-success m-0 border-0">
                                        <i class="bi bi-check-circle-fill me-2"></i>No critical competency gaps identified.
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div class="card-footer bg-white border-top-0 pt-3 pb-4">
                            <small class="text-muted">Gaps of 2 or more trigger **Recommended Courses**.</small>
                        </div>
                    </div>
                </div>

                <!-- Overall Progress Card -->
                <div class="col-lg-7 mb-4">
                    <div class="card shadow-lg h-100 rounded-4">
                        <div class="card-body p-4">
                            <h4 class="card-title mb-3"><i class="bi bi-graph-up me-2"></i>My Learning Progress</h4>
                            
                            <!-- Progress Circle Placeholder -->
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center p-4 shadow-sm" style="width: 140px; height: 140px; border: 8px solid #0d6efd; background-color: #e9f0fe;">
                                        <div class="text-center">
                                            <div class="fw-bold fs-3 text-primary"><?php echo $overall_progress_percent; ?>%</div>
                                            <div class="text-muted text-sm">Overall Goal</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            Total Courses Enrolled
                                            <span class="badge bg-primary rounded-pill fs-6"><?php echo $total_courses; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            Courses Completed
                                            <span class="badge bg-success rounded-pill fs-6"><?php echo $total_completed; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            Courses In Progress
                                            <span class="badge bg-info rounded-pill fs-6"><?php echo $total_in_progress; ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            Courses Not Started
                                            <span class="badge bg-secondary rounded-pill fs-6"><?php echo $total_not_started; ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECOMMENDED COURSES SECTION (High Priority) -->
            <h2 class="mb-4 text-danger"><i class="bi bi-stars me-2"></i>Recommended Courses (High Priority)</h2>
            <p class="lead text-muted">These courses are highly recommended to immediately address your most critical competency needs.</p>

            <div class="row">
                <?php if (!empty($recommended_courses)): ?>
                    <?php foreach ($recommended_courses as $course): ?>
                        <?php render_course_card($course, true); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-success shadow-sm rounded-3">
                            <i class="bi bi-hand-thumbs-up-fill me-2"></i>
                            You have no high priority gaps! Excellent! Check out the other courses below for continuous development.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-5">

            <!-- ALL COURSES SECTION (All Courses) -->
            <h2 class="mb-4"><i class="bi bi-grid-fill me-2"></i>All Available Courses</h2>
            
            <?php foreach ($courses_by_category as $category => $courses): ?>
                <h4 class="mb-3 mt-4 text-secondary border-bottom pb-2 fw-bold"><?php echo htmlspecialchars($category); ?></h4>
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                        <?php render_course_card($course); ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($all_courses)): ?>
                <div class="alert alert-warning">No courses are available at this time.</div>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<?php require_once '../includes/footer.php'; ?>
