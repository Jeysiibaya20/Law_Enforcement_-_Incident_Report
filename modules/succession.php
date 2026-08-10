<?php
$page_title = 'Succession Planning Dashboard';
$base_url = '../';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// --- HELPER FUNCTION ---
/**
 * Renders a color-coded badge based on the candidate's readiness status.
 * @param string $readiness The readiness status string.
 * @return string The HTML badge element.
 */
function render_readiness_badge($readiness) {
    $class = '';
    switch ($readiness) {
        case 'Ready Now':
            $class = 'bg-success text-white';
            break;
        case '1 Year':
        case 'High Potential':
            $class = 'bg-warning text-dark';
            break;
        case '2-3 Years':
        case 'Medium Potential':
            $class = 'bg-info text-white';
            break;
        default:
            $class = 'bg-secondary text-white';
            break;
    }
    return "<span class='badge {$class} fw-normal'>{$readiness}</span>";
}

// --- MOCK DATA FOR SUCCESSION MODULE ---
$succession_stats = [
    'critical_roles' => 5,
    'high_potential_employees' => 22,
    'ready_now_candidates' => 8,
    'total_candidates_in_pipeline' => 45,
];

$key_positions = [
    [
        'id' => 1,
        'position' => 'Chief Technology Officer (CTO)',
        'incumbent' => 'Alan Turing',
        'risk_level' => 'High',
        'candidates_count' => 3,
        'candidates' => [
            ['name' => 'Dr. Grace Hopper', 'readiness' => 'Ready Now', 'potential' => 'High'],
            ['name' => 'Nikola Tesla', 'readiness' => '1 Year', 'potential' => 'High'],
            ['name' => 'Ada Lovelace', 'readiness' => '2-3 Years', 'potential' => 'Medium'],
        ]
    ],
    [
        'id' => 2,
        'position' => 'VP of Global Sales',
        'incumbent' => 'Marie Curie',
        'risk_level' => 'Medium',
        'candidates_count' => 2,
        'candidates' => [
            ['name' => 'Thomas Edison', 'readiness' => 'Ready Now', 'potential' => 'Medium'],
            ['name' => 'Rosalind Franklin', 'readiness' => '2-3 Years', 'potential' => 'Medium'],
        ]
    ],
    [
        'id' => 3,
        'position' => 'Director of Product Development',
        'incumbent' => 'Galileo Galilei',
        'risk_level' => 'Low',
        'candidates_count' => 5,
        'candidates' => [
            ['name' => 'George Washington Carver', 'readiness' => '1 Year', 'potential' => 'High'],
            ['name' => 'Katherine Johnson', 'readiness' => '2-3 Years', 'potential' => 'High'],
            ['name' => 'Mae C. Jemison', 'readiness' => '1 Year', 'potential' => 'Medium'],
        ]
    ],
];
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2">Succession Planning</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Succession Planning</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button type="button" class="btn btn-sm btn-primary" onclick="alert('Mock: Opening form to identify a new Critical Role.')">
                    <i class="bi bi-person-plus me-2"></i>Identify Critical Role
                </button>
            </div>
        </div>

        <!-- SUCCESSION METRICS (KPI CARDS) -->
        <div class="row g-3 mb-4">
            <!-- Critical Roles -->
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-danger h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Critical Roles</span>
                        <span class="dashboard-analytics-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $succession_stats['critical_roles']; ?></div>
                    <div class="dashboard-analytics-sub">Key leadership positions</div>
                </article>
            </div>

            <!-- High Potential Employees -->
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">High-Potential Employees</span>
                        <span class="dashboard-analytics-icon"><i class="bi bi-star-fill"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $succession_stats['high_potential_employees']; ?></div>
                    <div class="dashboard-analytics-sub">Identified top talent</div>
                </article>
            </div>

            <!-- Ready Now Candidates -->
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Ready Now Candidates</span>
                        <span class="dashboard-analytics-icon"><i class="bi bi-check-circle-fill"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $succession_stats['ready_now_candidates']; ?></div>
                    <div class="dashboard-analytics-sub">Immediate succession readiness</div>
                </article>
            </div>

            <!-- Total Candidates in Pipeline -->
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Pipeline Candidates</span>
                        <span class="dashboard-analytics-icon"><i class="bi bi-diagram-3-fill"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $succession_stats['total_candidates_in_pipeline']; ?></div>
                    <div class="dashboard-analytics-sub">Total leadership pool</div>
                </article>
            </div>
        </div>

        <div class="row">
            <!-- CRITICAL ROLES AND PIPELINE TABLE -->
            <div class="col-lg-12">
                <div class="card enhanced-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-bank me-2"></i>Critical Roles Succession Pipeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Key Position</th>
                                        <th>Incumbent</th>
                                        <th>Risk Level</th>
                                        <th># of Candidates</th>
                                        <th>Top Candidate Readiness</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($key_positions)): ?>
                                        <?php foreach ($key_positions as $position): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($position['position']); ?></td>
                                            <td><?php echo htmlspecialchars($position['incumbent']); ?></td>
                                            <td>
                                                <?php 
                                                    $risk_class = match($position['risk_level']) {
                                                        'High' => 'bg-danger-subtle text-danger',
                                                        'Medium' => 'bg-warning-subtle text-warning',
                                                        'Low' => 'bg-success-subtle text-success',
                                                        default => 'bg-secondary-subtle text-secondary',
                                                    };
                                                    echo "<span class='badge {$risk_class}'>{$position['risk_level']}</span>";
                                                ?>
                                            </td>
                                            <td><span class="fw-bold"><?php echo $position['candidates_count']; ?></span></td>
                                            <td>
                                                <?php 
                                                    $top_candidate = $position['candidates'][0] ?? null;
                                                    if ($top_candidate) {
                                                        echo render_readiness_badge($top_candidate['readiness']);
                                                    } else {
                                                        echo render_readiness_badge('None');
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <button 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    title="View Pipeline"
                                                    onclick="alert('Viewing full candidate pipeline for: <?php echo htmlspecialchars($position['position']); ?>');">
                                                    <i class="bi bi-eye"></i> Pipeline
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No critical roles have been identified yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TALENT READINESS & DEVELOPMENT FOCUS -->
            <div class="col-lg-12">
                <div class="card enhanced-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Talent Readiness & Development Focus</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="text-secondary mb-3">Top Development Needs Identified Across Pipeline</h6>
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Strategic Financial Planning
                                <span class="badge bg-danger">4 Candidates</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Advanced Team Leadership
                                <span class="badge bg-warning text-dark">3 Candidates</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Global Market Entry
                                <span class="badge bg-info">2 Candidates</span>
                            </li>
                        </ul>

                        <div class="d-grid gap-2">
                            <!-- Link to Learning & Development -->
                            <a href="learning.php" class="btn btn-outline-primary">
                                <i class="bi bi-mortarboard me-2"></i>Link to Learning & Development
                            </a>
                            <!-- View 9-Box Grid - Changed from button to anchor tag with a direct link -->
                            <a href="nine_box_grid.php" class="btn btn-outline-secondary">
                                <i class="bi bi-grid-3x3-gap-fill me-2"></i>View 9-Box Grid
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
