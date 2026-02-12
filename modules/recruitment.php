<?php
$page_title = 'Recruitment & Applicant Tracking';
$base_url = '../';
// Start session for status messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Assuming these files exist in the includes directory
require_once '../includes/header.php';
require_once '../includes/navbar.php';
// Include database connection configuration
require_once $base_url . '../config/db_connect.php'; // $pdo variable is assumed to be available

// --- HELPER FUNCTION: Status Badge Renderer ---
function render_status_badge($status) {
    $class = '';
    switch ($status) {
        case 'Active':
        case 'Hired':
        case 'Interview Scheduled':
        case 'Offer Accepted':
            $class = 'bg-success-subtle text-success';
            break;
        case 'Screening':
        case 'Reviewing':
        case 'Pending':
        case 'Draft':
            $class = 'bg-primary-subtle text-primary';
            break;
        case 'Hiring Freeze':
        case 'Rejected':
            $class = 'bg-danger-subtle text-danger';
            break;
        case 'Interviewing':
            $class = 'bg-warning-subtle text-warning';
            break;
        default:
            $class = 'bg-secondary-subtle text-secondary';
            break;
    }
    return "<span class='badge {$class}'>{$status}</span>";
}

// Function to display and clear session messages
function display_session_messages() {
    if (isset($_SESSION['message'])) {
        $message_type = $_SESSION['message']['type'];
        $message_text = $_SESSION['message']['text'];
        echo "<div class='alert alert-{$message_type}' role='alert'>
                <i class='bi bi-info-circle-fill me-2'></i>{$message_text}
              </div>";
        unset($_SESSION['message']); // Clear the message after display
    }
}

// --- POST JOB LOGIC (FUNCTIONAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
    $title = trim($_POST['job_title'] ?? '');
    $department_id = filter_var($_POST['department_id'] ?? '', FILTER_VALIDATE_INT);
    $description = trim($_POST['job_description'] ?? '');
    
    // Simple validation
    if (empty($title) || empty($department_id) || empty($description)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Job Title, Department, and Description are required.'];
    } elseif ($department_id === false) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid Department selected.'];
    } else {
        try {
            // Prepare the INSERT statement
            $sql = "INSERT INTO job_postings 
                    (title, department_id, description, status, stage, posted_date) 
                    VALUES (?, ?, ?, 'Active', 'Draft', NOW())"; // Default status and stage
            
            $stmt = $pdo->prepare($sql);
            
            // Execute the statement
            $success = $stmt->execute([
                $title, 
                $department_id, 
                $description
            ]);
            
            if ($success) {
                $_SESSION['message'] = ['type' => 'success', 'text' => "Successfully posted new job: '{$title}'."];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to post job due to a database error.'];
            }
            
        } catch (PDOException $e) {
            // Log the error (optional)
            // error_log("New Job Post DB Error: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error while posting job. Please check logs.'];
        }
    }
    
    // Redirect to prevent form resubmission and display the message
    header("Location: recruitment.php");
    exit();
}
// --- END POST JOB LOGIC ---


// --- DYNAMIC DATA FETCHING ---
$recruitment_stats = [
    'open_jobs' => 0,
    'new_applicants_this_month' => 0,
    'avg_time_to_hire_days' => '42',
    'offer_acceptance_rate' => '85%',
];
$job_openings = [];
$job_applicants = [];
// FIX 1: Initializing as plural $departments for consistency
$departments = []; 
$error_message = null;

try {
    // 1. Fetch Key Recruitment Stats
    $current_month = date('Y-m-01');

    // Total Open Jobs (Active)
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM job_postings WHERE status = 'Active'");
    $recruitment_stats['open_jobs'] = (int)($stmt->fetchColumn() ?? 0);

    // New Applicants This Month
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM job_applications WHERE application_date >= ?");
    $stmt->execute([$current_month]);
    $recruitment_stats['new_applicants_this_month'] = (int)($stmt->fetchColumn() ?? 0);

    // 2. Fetch Active Job Openings
    $job_sql = "
        SELECT 
            jp.posting_id AS id,
            jp.title,
            d.department_name AS department,
            jp.status,
            jp.posted_date,
            jp.stage,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_posting_id = jp.posting_id) AS applicants
        FROM 
            job_postings jp
        LEFT JOIN 
            departments d ON jp.department_id = d.department_id
        WHERE 
            jp.status = 'Active' OR jp.status = 'Hiring Freeze'
        ORDER BY 
            jp.posted_date DESC
        LIMIT 5";
    $stmt = $pdo->query($job_sql);
    $job_openings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Recent Applicants
    $applicant_sql = "
        SELECT 
            ja.application_id AS id,
            ja.applicant_name AS name,
            ja.status,
            ja.application_date,
            jp.title AS job_title
        FROM 
            job_applications ja
        JOIN
            job_postings jp ON ja.job_posting_id = jp.posting_id
        ORDER BY 
            ja.application_date DESC
        LIMIT 8";
    $stmt = $pdo->query($applicant_sql);
    $job_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Fetch Departments for the Post New Job Modal (THIS IS THE DYNAMIC DROPDOWN DATA)
    // FIX 2: Changed table name from 'department' to 'departments'
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    // FIX 3: Storing result in the plural variable $departments
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log the error (optional) and set a friendly message
    // error_log("Recruitment DB Error: " . $e->getMessage());
    $error_message = "Database connection error. Could not fetch recruitment data.";
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2">Recruitment & Applicant Tracking</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/HOTEL-MANAGEMENT-SYSTEM/HR-1&2-REVAMPED/MERGED_HR_SYSTEM/index.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Recruitment & Applicants</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button 
                    class="btn btn-primary" 
                    data-bs-toggle="modal" 
                    data-bs-target="#addNewJobModal">
                    <i class="bi bi-briefcase-fill me-2"></i>Post New Job
                </button>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-x-octagon-fill me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php display_session_messages(); // Display success/error messages from job posting ?>

        <h2 class="h4 mb-3"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Recruitment Metrics</h2>
        <div class="row mb-5">
            <?php 
            $kpi_cards = [
                ['title' => 'Open Jobs', 'value' => $recruitment_stats['open_jobs'], 'icon' => 'bi-briefcase-fill', 'color' => 'primary', 'unit' => 'jobs'],
                ['title' => 'New Applicants (Mo.)', 'value' => $recruitment_stats['new_applicants_this_month'], 'icon' => 'bi-person-badge-fill', 'color' => 'success', 'unit' => 'applicants'],
                ['title' => 'Avg. Time to Hire', 'value' => $recruitment_stats['avg_time_to_hire_days'], 'icon' => 'bi-hourglass-split', 'color' => 'warning', 'unit' => 'days'],
                ['title' => 'Offer Acceptance Rate', 'value' => $recruitment_stats['offer_acceptance_rate'], 'icon' => 'bi-hand-thumbs-up-fill', 'color' => 'info', 'unit' => ''],
            ];
            foreach ($kpi_cards as $kpi):
            ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card enhanced-card shadow-sm border-start border-<?php echo $kpi['color']; ?> border-5 h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-<?php echo $kpi['color']; ?> text-uppercase mb-1">
                                    <?php echo $kpi['title']; ?>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo htmlspecialchars($kpi['value']); ?> <?php echo $kpi['unit']; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi <?php echo $kpi['icon']; ?> display-6 text-<?php echo $kpi['color']; ?>-subtle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card enhanced-card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-list-task me-2"></i>Active Job Openings
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Applicants</th>
                                        <th>Posted</th>
                                        <th>Current Stage</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($job_openings)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No active job openings found in the database.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($job_openings as $job): ?>
                                            <tr>
                                                <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($job['title']); ?></span></td>
                                                <td><?php echo htmlspecialchars($job['department']); ?></td>
                                                <td><span class="fw-bold text-primary"><?php echo $job['applicants']; ?></span></td>
                                                <td><?php echo date('Y-m-d', strtotime($job['posted_date'])); ?></td>
                                                <td><?php echo render_status_badge($job['stage']); ?></td>
                                                <td>
                                                    <button 
                                                        class="btn btn-sm btn-outline-primary" 
                                                        title="View Job"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#jobDetailsModal"
                                                        onclick="showJobDetails(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['title'], ENT_QUOTES); ?>', '<?php echo $job['applicants']; ?>', '<?php echo htmlspecialchars($job['department'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($job['stage'], ENT_QUOTES); ?>')">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card enhanced-card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-people-fill me-2"></i>Recent Applicants
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Applicant</th>
                                        <th>Job</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($job_applicants)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No recent applicants found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($job_applicants as $applicant): ?>
                                            <tr>
                                                <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($applicant['name']); ?></span></td>
                                                <td><small><?php echo htmlspecialchars($applicant['job_title']); ?></small></td>
                                                <td><?php echo render_status_badge($applicant['status']); ?></td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button 
                                                            class="btn btn-outline-primary" 
                                                            title="View Profile"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#applicantDetailsModal"
                                                            onclick="showApplicantDetails(<?php echo $applicant['id']; ?>, '<?php echo htmlspecialchars($applicant['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($applicant['job_title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($applicant['status'], ENT_QUOTES); ?>', '<?php echo date('Y-m-d', strtotime($applicant['application_date'])); ?>')">
                                                            <i class="bi bi-person-lines-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jobDetailsModal" tabindex="-1" aria-labelledby="jobDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="jobDetailsModalLabel"><i class="bi bi-info-circle me-2"></i>Job Details (Mock View)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>This is a mock-up of the detailed job posting view.</p>
        <p>Viewing details for Job ID: <strong id="detail-job-id"></strong> - <strong id="detail-job-title"></strong></p>
        
        <dl class="row mt-3">
            <dt class="col-sm-4">Department</dt><dd class="col-sm-8" id="detail-job-department"></dd>
            <dt class="col-sm-4">Applicant Count</dt><dd class="col-sm-8"><span class="badge bg-primary-subtle text-primary" id="detail-job-applicants"></span></dd>
            <dt class="col-sm-4">Current Stage</dt><dd class="col-sm-8"><span id="detail-job-stage"></span></dd>
        </dl>
        
        <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            In a real application, the full job description, requirements, and editing options would load here from the database.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success"><i class="bi bi-pencil-fill me-1"></i>Edit Job</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="applicantDetailsModal" tabindex="-1" aria-labelledby="applicantDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="applicantDetailsModalLabel"><i class="bi bi-person-lines-fill me-2"></i>Applicant Profile (Mock View)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>This is a mock-up of the applicant profile.</p>
        <p>Applicant: <strong id="applicant-name-detail"></strong> (ID: <strong id="applicant-id-detail"></strong>)</p>
        
        <dl class="row mt-3">
            <dt class="col-sm-4">Applied For</dt><dd class="col-sm-8" id="applicant-job-title"></dd>
            <dt class="col-sm-4">Application Date</dt><dd class="col-sm-8" id="applicant-date"></dd>
            <dt class="col-sm-4">Current Status</dt><dd class="col-sm-8"><span id="applicant-status-badge"></span></dd>
        </dl>
        
        <h6 class="mt-4"><i class="bi bi-paperclip me-1"></i>Supporting Documents</h6>
        <div class="d-grid gap-2">
            <button class="btn btn-outline-secondary"><i class="bi bi-file-earmark-person me-2"></i>View Resume/CV</button>
            <button class="btn btn-outline-secondary"><i class="bi bi-file-earmark-text me-2"></i>View Cover Letter</button>
        </div>

        <div class="alert alert-warning small mt-4 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            In a real application, the full details, interview notes, and status management forms would load here from the database.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success"><i class="bi bi-arrow-right-circle me-1"></i>Update Status</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addNewJobModal" tabindex="-1" aria-labelledby="addNewJobModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="recruitment.php">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="addNewJobModalLabel"><i class="bi bi-plus-circle-fill me-2"></i>Post New Job</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Please fill out the details for the new job posting.</p>
            <div class="mb-3">
                <label for="jobTitle" class="form-label">Job Title</label>
                <input type="text" class="form-control" id="jobTitle" name="job_title" placeholder="e.g., Marketing Specialist" required>
            </div>
            <div class="mb-3">
                <label for="department_name" class="form-label">Department</label>
                <select class="form-select" id="department_name" name="department_id" required>
                    <option value="" disabled selected>Select Department</option>
                    <?php 
                    // FIX 4: Check and loop over the corrected plural variable $departments
                    if (!empty($departments)): 
                        foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department_id']); ?>">
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php 
                        endforeach; 
                    else: ?>
                        <option value="" disabled>No departments found. Check your `departments` table.</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="jobDescription" class="form-label">Job Description</label>
                <textarea class="form-control" id="jobDescription" name="job_description" rows="5" required></textarea>
            </div>
          <div class="alert alert-info small mt-3">
              <i class="bi bi-info-circle-fill me-1"></i>
              This will set the job status to 'Active' and stage to 'Draft' upon submission.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="post_job" class="btn btn-primary"><i class="bi bi-send-fill me-1"></i>Post Job</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    // Functions to show the modals with dynamic content
    function showJobDetails(id, title, applicants, department, stage) {
        document.getElementById('detail-job-id').textContent = id;
        document.getElementById('detail-job-title').textContent = title;
        document.getElementById('detail-job-department').textContent = department;
        document.getElementById('detail-job-applicants').textContent = applicants + ' applicants';
        document.getElementById('detail-job-stage').innerHTML = render_status_badge(stage);
    }
    
    function showApplicantDetails(id, name, job_title, status, application_date) {
        document.getElementById('applicant-id-detail').textContent = id;
        document.getElementById('applicant-name-detail').textContent = name;
        document.getElementById('applicant-job-title').textContent = job_title;
        document.getElementById('applicant-date').textContent = application_date;
        document.getElementById('applicant-status-badge').innerHTML = render_status_badge(status);
        
        // This is a client-side version of the PHP render_status_badge function for the mock modals
        function render_status_badge(status) {
            let class_name = '';
            switch (status) {
                case 'Active':
                case 'Hired':
                case 'Interview Scheduled':
                case 'Offer Accepted':
                    class_name = 'bg-success-subtle text-success';
                    break;
                case 'Screening':
                case 'Reviewing':
                case 'Pending':
                case 'Draft': 
                    class_name = 'bg-primary-subtle text-primary';
                    break;
                case 'Hiring Freeze':
                case 'Rejected':
                    class_name = 'bg-danger-subtle text-danger';
                    break;
                case 'Interviewing':
                    class_name = 'bg-warning-subtle text-warning';
                    break;
                default:
                    class_name = 'bg-secondary-subtle text-secondary';
                    break;
            }
            return `<span class='badge ${class_name}'>${status}</span>`;
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>