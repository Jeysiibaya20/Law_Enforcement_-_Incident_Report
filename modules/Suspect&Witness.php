<?php
$page_title = 'Competency Management';
$base_url = '../'; // Assuming this file is at the root or similar to index.php
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// --- MOCK DATA SETUP ---

// Check for the 'view' GET parameter to determine the page content
$current_view = $_GET['view'] ?? 'dashboard';

// --- MOCK DATA FOR COMPETENCY KPIs (Updated based on list below) ---
$comp_kpis = [
    'total_competencies' => 4, // Updated from 12
    'competencies_met' => 1, // Leadership (Current 4 > Required 3)
    'skill_gaps' => 3, // Communication, Problem Solving, Software Proficiency
    'high_priority_gaps' => 2, // Problem Solving (Gap 3), Software Proficiency (Gap 2)
];

// --- MOCK DATA FOR COMPETENCIES (Aligned with 'employee_competencies' table schema) ---
$competencies = [
    [
        'competency_id' => 1,
        'name' => 'Effective Communication',
        'category' => 'Soft Skills',
        'required_level' => 4,
        'current_level' => 3,
        'last_assessed_date' => '2024-09-01',
        'description' => 'Ability to clearly and concisely convey information.',
        'development_plan' => 'Enroll in the "Active Listening Workshop" to close the gap.',
    ],
    [
        'competency_id' => 2,
        'name' => 'Analytical Problem Solving',
        'category' => 'Technical Skills',
        'required_level' => 5,
        'current_level' => 2,
        'last_assessed_date' => '2024-09-01',
        'description' => 'Capacity to analyze complex situations and devise effective solutions.',
        'development_plan' => 'High priority: Complete "Advanced Logic" course by Q4.',
    ],
    [
        'competency_id' => 3,
        'name' => 'Team Leadership',
        'category' => 'Leadership',
        'required_level' => 3,
        'current_level' => 4,
        'last_assessed_date' => '2024-09-01',
        'description' => 'Guiding and motivating teams to achieve strategic goals.',
        'development_plan' => 'Proficient: Continue mentoring junior staff.',
    ],
    [
        'competency_id' => 4,
        'name' => 'Software Proficiency (CRM)',
        'category' => 'Technical Skills',
        'required_level' => 3,
        'current_level' => 1,
        'last_assessed_date' => '2024-06-15',
        'description' => 'Proficiency in core CRM platform usage and reporting.',
        'development_plan' => 'Overdue: Complete CRM Level 1 Certification by EOM.',
    ],
];

// Helper to determine badge color and gap
function get_level_badge($level) {
    switch ($level) {
        case 5: return '<span class="badge" style="background-color: #7d3c98; color: white;">Expert</span>'; // Custom purple
        case 4: return '<span class="badge bg-success">Proficient</span>';
        case 3: return '<span class="badge bg-info">Intermediate</span>';
        case 2: return '<span class="badge bg-warning text-dark">Beginner</span>';
        case 1:
        default: return '<span class="badge bg-danger">Novice</span>';
    }
}

function calculate_gap_status($required, $current) {
    $gap = $required - $current;
    if ($gap <= 0) {
        return ['label' => 'Met', 'class' => 'success', 'icon' => 'bi-check-circle-fill'];
    } elseif ($gap >= 3) {
        return ['label' => "High Gap ({$gap})", 'class' => 'danger', 'icon' => 'bi-lightning-charge-fill'];
    } else {
        return ['label' => "Gap ({$gap})", 'class' => 'warning', 'icon' => 'bi-exclamation-triangle-fill'];
    }
}
?>

<div class="main-content">
    <div class="content-container">

        <?php if ($current_view === 'dashboard'): ?>

            <!-- Dashboard Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Competency Management</h1>
                    <p class="text-secondary">Your personalized skill profile and development goals</p>
                </div>
            </div>
            
            <!-- KPI Cards Row (Matching index.php layout) -->
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="stats-card enhanced-card" style="background: var(--gradient-primary-dark);">
                        <div class="card-header-icon"><i class="bi bi-layers"></i></div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo htmlspecialchars((string)$comp_kpis['total_competencies']); ?></div>
                            <div class="stats-label">Total Competencies</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card enhanced-card" style="background: var(--gradient-success);">
                        <div class="card-header-icon"><i class="bi bi-check2-square"></i></div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo htmlspecialchars((string)$comp_kpis['competencies_met']); ?></div>
                            <div class="stats-label">Competencies Met</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card enhanced-card" style="background: var(--gradient-warning);">
                        <div class="card-header-icon"><i class="bi bi-trending-down"></i></div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo htmlspecialchars((string)$comp_kpis['skill_gaps']); ?></div>
                            <div class="stats-label">Skill Gaps Identified</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card enhanced-card" style="background: var(--gradient-danger);">
                        <div class="card-header-icon"><i class="bi bi-lightning-charge"></i></div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo htmlspecialchars((string)$comp_kpis['high_priority_gaps']); ?></div>
                            <div class="stats-label">High Priority Gaps</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row (8-column list, 4-column sidebar) -->
            <div class="row g-3 mt-3">
                
                <!-- Left Side: Competency List (col-lg-8) -->
                <div class="col-lg-8">
                    <div class="card enhanced-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-list-check me-2"></i>Core Role Competencies</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($competencies)): ?>
                                <div class="text-center py-4 text-muted">No competencies currently assigned.</div>
                            <?php else: ?>
                                <?php foreach ($competencies as $comp): 
                                    $gap_status = calculate_gap_status($comp['required_level'], $comp['current_level']);
                                ?>
                                    <div class="card mb-3 shadow-sm border-start border-<?php echo $gap_status['class']; ?> border-4">
                                        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                            <div class="flex-grow-1 mb-3 mb-md-0">
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($comp['name']); ?></h6>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($comp['description']); ?></p>

                                                <div class="d-flex flex-wrap gap-3 align-items-center">
                                                    <span class="text-dark small">Required: <?php echo get_level_badge($comp['required_level']); ?></span>
                                                    <span class="text-dark small">Current: <?php echo get_level_badge($comp['current_level']); ?></span>
                                                    <span class="badge bg-<?php echo $gap_status['class']; ?> d-flex align-items-center">
                                                        <i class="bi <?php echo $gap_status['icon']; ?> me-1"></i>
                                                        <?php echo $gap_status['label']; ?>
                                                    </span>
                                                </div>
                                                <!-- Additional schema-driven details -->
                                                <p class="text-muted small mt-2 mb-2">
                                                    <span class="badge bg-secondary-subtle text-secondary me-3"><?php echo htmlspecialchars($comp['category']); ?></span>
                                                    <i class="bi bi-calendar-check me-1"></i>Last Assessed: <?php echo htmlspecialchars($comp['last_assessed_date']); ?>
                                                </p>
                                                <!-- Development Plan (Mapped from 'development_plan' field in schema) -->
                                                <p class="small text-primary mt-2 mb-0"><i class="bi bi-lightbulb me-1"></i><?php echo htmlspecialchars($comp['development_plan']); ?></p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <a href="<?php echo $base_url; ?>learning.php" class="btn btn-sm btn-outline-info">Find Training</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Sidebar (col-lg-4) -->
                <div class="col-lg-4">
                    
                    <!-- Quick Actions Card -->
                    <div class="card enhanced-card mb-3">
                        <h5 class="card-title mb-3"><i class="bi bi-zap me-2"></i>Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <!-- Direct reference to the learning module is key here -->
                            <a href="<?php echo $base_url; ?>learning.php" class="btn btn-primary"><i class="bi bi-mortarboard me-2"></i>Go to Learning Module</a>
                            <!-- Link to switch to Schedule Review view -->
                            <a href="?view=schedule" class="btn btn-outline-secondary"><i class="bi bi-calendar-check me-2"></i>Schedule Review</a>
                            <!-- UPDATED LINK to switch to Career Goals view -->
                            <a href="?view=goals" class="btn btn-outline-secondary"><i class="bi bi-target me-2"></i>Update Career Goals</a>
                        </div>
                    </div>

                    <!-- Profile Status Card (Mocked) -->
                    <div class="card enhanced-card">
                        <h5 class="card-title mb-3"><i class="bi bi-person-circle me-2"></i>Profile Status</h5>
                        <ul class="list-unstyled mb-0 small text-secondary">
                            <li class="d-flex justify-content-between mb-1">
                                <span><i class="bi bi-briefcase me-2 text-primary"></i>Role:</span> 
                                <span class="fw-bold text-dark">Software Engineer II</span>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span><i class="bi bi-star me-2 text-primary"></i>Last Assessment:</span> 
                                <span class="fw-bold text-dark">2024-09-01</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-key me-2 text-primary"></i>Employee ID:</span> 
                                <span class="fw-bold text-dark">E10293</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        <?php elseif ($current_view === 'schedule'): ?>
            
            <!-- Schedule Review Page -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Schedule Competency Review</h1>
                    <p class="text-secondary">Request a formal review session with your manager.</p>
                </div>
                <nav aria-label="breadcrumb" class="d-none d-md-block">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/HOTEL-MANAGEMENT-SYSTEM/HR-1&2-REVAMPED/MERGED_HR_SYSTEM/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="competency.php">Competencies</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Schedule Review</li>
                    </ol>
                </nav>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card enhanced-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-calendar-event me-2"></i>Review Request Form</h5>
                        </div>
                        <div class="card-body">
                            <form action="competency.php?view=dashboard" method="POST">
                                <div class="mb-3">
                                    <label for="reviewDate" class="form-label">Preferred Date</label>
                                    <input type="date" class="form-control" id="reviewDate" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reviewTime" class="form-label">Preferred Time Slot</label>
                                    <select class="form-select" id="reviewTime" required>
                                        <option value="">Select a time...</option>
                                        <option value="morning">Morning (9:00 AM - 12:00 PM)</option>
                                        <option value="afternoon">Afternoon (1:00 PM - 4:00 PM)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="competenciesToDiscuss" class="form-label">Competencies to Discuss (Optional)</label>
                                    <select class="form-select" id="competenciesToDiscuss" multiple>
                                        <?php foreach ($competencies as $comp): ?>
                                            <option value="<?php echo htmlspecialchars($comp['competency_id']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Hold Ctrl or Cmd to select multiple.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Review Notes / Objectives</label>
                                    <textarea class="form-control" id="notes" rows="3" placeholder="e.g., Focus on Analytical Problem Solving gap and Q4 goals."></textarea>
                                </div>
                                <div class="d-flex justify-content-between pt-3">
                                    <a href="competency.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-send me-2"></i>Submit Review Request
                                    </button>
                                </div>
                                <div class="alert alert-info mt-3 small">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Submitting this form will mock the sending of a calendar invite to your manager.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($current_view === 'goals'): ?>
            
            <!-- Career Goals Page -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Update Career Goals</h1>
                    <p class="text-secondary">Define your long-term career aspirations and necessary skills.</p>
                </div>
                <nav aria-label="breadcrumb" class="d-none d-md-block">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/HOTEL-MANAGEMENT-SYSTEM/HR-1&2-REVAMPED/MERGED_HR_SYSTEM/index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="competency.php">Competencies</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Career Goals</li>
                    </ol>
                </nav>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card enhanced-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-target me-2"></i>Career Aspiration Form</h5>
                        </div>
                        <div class="card-body">
                            <form action="competency.php?view=dashboard" method="POST">
                                <div class="mb-3">
                                    <label for="targetRole" class="form-label">Target Role / Position</label>
                                    <input type="text" class="form-control" id="targetRole" placeholder="e.g., Senior Software Engineer, Team Lead" required>
                                </div>
                                <div class="mb-3">
                                    <label for="timeframe" class="form-label">Target Timeframe</label>
                                    <select class="form-select" id="timeframe" required>
                                        <option value="">Select a timeframe...</option>
                                        <option value="12m">Next 12 Months</option>
                                        <option value="24m">1-2 Years</option>
                                        <option value="36m">2-3 Years</option>
                                        <option value="longterm">Long Term (5+ Years)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="skillFocus" class="form-label">Skills to Develop</label>
                                    <select class="form-select" id="skillFocus" multiple>
                                        <option value="">Select competencies to focus on...</option>
                                        <?php foreach ($competencies as $comp): ?>
                                            <option value="<?php echo htmlspecialchars($comp['competency_id']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                                        <?php endforeach; ?>
                                        <option value="other">Other (Specify in comments)</option>
                                    </select>
                                    <div class="form-text">Hold Ctrl or Cmd to select multiple competencies relevant to your target role.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="actionPlan" class="form-label">Development Action Plan / Next Steps</label>
                                    <textarea class="form-control" id="actionPlan" rows="3" placeholder="e.g., Complete AWS certification, start leading design reviews."></textarea>
                                </div>
                                <div class="d-flex justify-content-between pt-3">
                                    <a href="competency.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save me-2"></i>Save Career Goals
                                    </button>
                                </div>
                                <div class="alert alert-info mt-3 small">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Saving this form mocks the update of your career profile for future review.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
