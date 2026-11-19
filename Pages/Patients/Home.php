<?php
session_start();
require '../../database/DBconnect.php';
require 'header.php';

// 1. Get the user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "<div class='alert alert-danger'>User not logged in.</div>";
    exit;
}

// 2. Fetch patient_id for this user
$patientStmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$patientStmt->bind_param("i", $user_id);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();

// Handle empty result gracefully
$patientRow = $patientResult->fetch_assoc();
$patient_id = $patientRow['patient_id'] ?? null;

if (!$patient_id) {
    // Create a new patient record if it doesn't exist
    $insertPatientStmt = $conn->prepare("
        INSERT INTO patients (user_id, date_of_birth, height_cm, weight_kg, blood_type, bmi, profile_picture)
        VALUES (?, NULL, NULL, NULL, NULL, NULL, NULL)
    ");
    $insertPatientStmt->bind_param("i", $user_id);
    
    if ($insertPatientStmt->execute()) {
        $patient_id = $conn->insert_id;
        error_log("New patient record created with patient_id: $patient_id");
    } else {
        echo "<div class='alert alert-danger'>Error creating patient record. Please contact support.</div>";
        exit;
    }
    $insertPatientStmt->close();
}

error_log("Home.php: user_id = $user_id, patient_id = $patient_id");

// 3. Fetch patient details
$sql = "SELECT u.first_name, u.last_name, u.gender, u.email,
               p.date_of_birth, p.height_cm, p.weight_kg, p.blood_type, p.bmi, p.profile_picture
        FROM users u
        LEFT JOIN patients p ON u.user_id = p.user_id
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "<div class='alert alert-warning'>Patient data not found.</div>";
    exit;
}
$patient = $result->fetch_assoc();

// Calculate age safely
if (!empty($patient['date_of_birth'])) {
    $dob = new DateTime($patient['date_of_birth']);
    $today = new DateTime();
    $age = $dob->diff($today)->y;
} else {
    $age = 'N/A';
}

// Profile picture logic
$defaultPic = '/Healthcare_portal/Pages/Patients/uploads/default_profile.png';
$profilePic = $patient['profile_picture'] ?? '';
if ($profilePic && file_exists($_SERVER['DOCUMENT_ROOT'] . $profilePic)) {
    $displayPic = $profilePic;
} else {
    $displayPic = $defaultPic;
}

$conn->set_charset("utf8mb4");

// Get date from GET or default to today
$displayDate = $_GET['date'] ?? date('Y-m-d');
$prevDate = date('Y-m-d', strtotime($displayDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($displayDate . ' +1 day'));

// 4. Fetch wellness data for this patient and date
$wellnessStmt = $conn->prepare("
    SELECT water_liters, mood, sleep_hours, exercise_type, exercise_minutes
    FROM patient_daily_wellness
    WHERE patient_id = ? AND log_date = ?
");
$wellnessStmt->bind_param("is", $patient_id, $displayDate);
$wellnessStmt->execute();
$wellnessResult = $wellnessStmt->get_result();
$wellness = $wellnessResult->fetch_assoc() ?? [
    'water_liters' => 0,
    'mood' => 'Feeling Good',
    'sleep_hours' => null,
    'exercise_type' => 'N/A',
    'exercise_minutes' => 0
];

// Debugging: Log wellness data retrieval
if ($wellness) {
    error_log("Home.php: Wellness data found for patient_id $patient_id on $displayDate: " . json_encode($wellness));
} else {
    error_log("Home.php: No wellness data found for patient_id $patient_id on $displayDate");
}

$waterLiters = $wellness['water_liters'] ?? 0;
$water_goal = 2.5;
$water_percent = min(100, ($waterLiters / $water_goal) * 100);

// Assuming $conn is your MySQLi connection and $patient_id is set

// 1. Fetch Upcoming Appointment
$apptSql = "
    SELECT a.*, u.first_name AS doctor_first, u.last_name AS doctor_last
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users u ON d.user_id = u.user_id
    WHERE a.patient_id = ? AND a.appointment_datetime >= NOW() AND a.status = 'scheduled'
    ORDER BY a.appointment_datetime ASC
    LIMIT 1
";

$apptStmt = $conn->prepare($apptSql);

if ($apptStmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

$apptStmt->bind_param("i", $patient_id);
$apptStmt->execute();
$apptResult = $apptStmt->get_result();
$upcomingAppt = $apptResult->fetch_assoc();
// 2. Fetch Doctor Notes
$notesStmt = $conn->prepare("
    SELECT n.*, u.first_name AS doctor_first, u.last_name AS doctor_last, a.appointment_datetime
    FROM doctor_notes n
    JOIN doctors d ON n.doctor_id = d.doctor_id
    JOIN users u ON d.user_id = u.user_id
    JOIN appointments a ON n.appointment_id = a.appointment_id
    WHERE n.patient_id = ?
    ORDER BY n.created_at DESC
    LIMIT 5
");
$notesStmt->bind_param("i", $patient_id);
$notesStmt->execute();
$notesResult = $notesStmt->get_result();

// 3. Fetch Lifestyle Recommendations
$recStmt = $conn->prepare("
    SELECT * FROM lifestyle_recommendations
    WHERE patient_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$recStmt->bind_param("i", $patient_id);
$recStmt->execute();
$recResult = $recStmt->get_result();

?>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap" rel="stylesheet">
<style>
    body {
        background: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #2d3748;
    }
    .container {
        max-width: 1200px;
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }
    .card-header {
        padding: 1.25rem 1.5rem;
        border-radius: 20px 20px 0 0;
        color: white;
        font-weight: 700;
        font-size: 1.25rem;
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        border: none;
    }
    .profile-pic {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        object-fit: cover;
        transition: all 0.3s ease;
    }
    .profile-pic:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }
    .profile-info h3 {
        font-weight: 800;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
        color: #1e40af;
    }
    .profile-info p {
        margin-bottom: 0.5rem;
        color: #4b5563;
        font-size: 1.1rem;
    }
    .btn-primary {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        font-size: 1rem;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        transition: all 0.3s ease;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
    }
    .progress {
        height: 1rem;
        border-radius: 1rem;
        background: #e5e7eb;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    .progress-bar {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        border-radius: 1rem;
        transition: width 1s ease-in-out;
    }
    .form-label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }
    .form-control, .form-select {
        border-radius: 12px;
        border: 2px solid #e5e7eb;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f9fafb;
    }
    .form-control:focus, .form-select:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        background: white;
    }
    .alert {
        border-radius: 12px;
        font-weight: 600;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        animation: slideIn 0.5s ease-out;
    }
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .section-title {
        font-weight: 800;
        font-size: 2rem;
        margin-bottom: 2rem;
        color: #1e40af;
        text-align: center;
        position: relative;
        padding-bottom: 1rem;
    }
    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 4px;
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        border-radius: 2px;
    }
    .wellness-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }
    .wellness-card .card-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        font-weight: 600;
    }
    .appointment-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }
    .appointment-card .card-header {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    .notes-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }
    .notes-card .card-header {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }
    .list-group-item {
        border: none;
        background: #f8fafc;
        border-radius: 12px;
        margin-bottom: 1rem;
        padding: 1.25rem;
        transition: all 0.3s ease;
    }
    .list-group-item:hover {
        transform: translateX(5px);
        background: #f1f5f9;
    }
    .badge {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
    }
    .badge.bg-info {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    }
    .lifestyle-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    .lifestyle-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    .lifestyle-header {
        font-weight: 700;
        font-size: 1.25rem;
        color: #1e40af;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e5e7eb;
    }
    .recommendation-title {
        font-weight: 600;
        color: #4f46e5;
        margin-bottom: 0.5rem;
    }
    .recommendation-text {
        color: #4b5563;
        font-size: 1rem;
        line-height: 1.6;
    }
    .btn-group {
        display: flex;
        gap: 1rem;
    }
    .btn-outline-secondary {
        border: 2px solid #e5e7eb;
        color: #6b7280;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-outline-secondary:hover {
        background: #f3f4f6;
        color: #4b5563;
        transform: translateY(-2px);
    }
    .date-navigation {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 12px;
        margin-top: 1.5rem;
    }
    .date-navigation .current-date {
        font-weight: 600;
        color: #1e40af;
    }
    .date-navigation .btn-outline-secondary {
        color: #1e40af;
        border-color: #1e40af;
    }
    .date-navigation .btn-outline-secondary:hover {
        background-color: #1e40af;
        color: white;
    }
    .date-navigation .btn-outline-secondary:disabled {
        color: #94a3b8;
        border-color: #94a3b8;
        background-color: transparent;
    }
    @media (max-width: 768px) {
        .profile-card .card-body {
            flex-direction: column;
            text-align: center;
        }
        .btn-group {
            justify-content: center;
            margin-top: 1rem;
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 1.75rem;
        }
}
</style>

<body>
    <div class="container">
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Patient Profile Card -->
        <div class="card profile-card mb-5">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center gap-4">
                <img src="<?= htmlspecialchars($displayPic) ?>" alt="Profile Picture" class="profile-pic" />
                    <div class="profile-info flex-grow-1">
                    <h3><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h3>
                        <p><i class="bi bi-person-badge me-2"></i>Age: <?= $age ?> | Gender: <?= htmlspecialchars(ucfirst($patient['gender'])) ?></p>
                        <p><i class="bi bi-rulers me-2"></i>Height: <?= htmlspecialchars($patient['height_cm'] ?? 'N/A') ?> cm | Weight: <?= htmlspecialchars($patient['weight_kg'] ?? 'N/A') ?> kg</p>
                        <p><i class="bi bi-droplet me-2"></i>Blood Type: <?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?> | BMI: <?= htmlspecialchars($patient['bmi'] ?? 'N/A') ?></p>
                </div>
                    <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-pencil-square me-2"></i>Edit Profile
                    </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Title -->
        <h2 class="section-title">Health Dashboard</h2>

        <div class="row g-4">
            <!-- Top Row: Wellness and Appointment -->
            <div class="col-md-8">
                <div class="card wellness-card h-100">
                    <div class="card-header">
                        <i class="bi bi-heart-pulse me-2"></i>Daily Wellness
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Water Intake</h6>
                                <span class="text-primary fw-bold"><?= htmlspecialchars($wellness['water_liters'] ?? 0) ?>L / <?= $water_goal ?>L</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?= $water_percent ?>%" aria-valuenow="<?= $water_percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <form method="POST" action="Process/process_log_wellness.php" id="wellnessForm" class="needs-validation" novalidate>
                            <input type="hidden" name="log_date" value="<?= htmlspecialchars($displayDate) ?>" />
                            
                            <div class="row">
                                <!-- Left side: Water Intake and Mood -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="water_liters" class="form-label">
                                            <i class="bi bi-droplet me-2"></i>Water Intake (L)
                                        </label>
                                        <input type="number" step="0.1" min="0" name="water_liters" id="water_liters" class="form-control" value="<?= htmlspecialchars($wellness['water_liters'] ?? '') ?>" required />
                                    </div>

                                    <div class="mb-3">
                                        <label for="mood" class="form-label">
                                            <i class="bi bi-emoji-smile me-2"></i>Mood
                                        </label>
                                        <select name="mood" id="mood" class="form-select" required>
                                            <?php
                                            $moods = ['Feeling Good', 'Feeling Bad', 'Pain', 'Other'];
                                            foreach ($moods as $m) {
                                                $selected = ($m === ($wellness['mood'] ?? 'Feeling Good')) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($m) . "\" $selected>" . htmlspecialchars($m) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Right side: Exercise and Sleep -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sleep_hours" class="form-label">
                                            <i class="bi bi-moon-stars me-2"></i>Sleep (hours)
                                        </label>
                                        <input type="number" step="0.1" min="0" max="24" name="sleep_hours" id="sleep_hours" class="form-control" value="<?= htmlspecialchars($wellness['sleep_hours'] ?? '') ?>" />
                                    </div>

                                    <div class="mb-3">
                                        <label for="exercise_type" class="form-label">
                                            <i class="bi bi-activity me-2"></i>Exercise Type
                                        </label>
                                        <select name="exercise_type" id="exercise_type" class="form-select">
                                            <?php
                                            $exercises = ['N/A', 'Gym', 'Walk', 'Other'];
                                            foreach ($exercises as $e) {
                                                $selected = ($e === ($wellness['exercise_type'] ?? 'N/A')) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($e) . "\" $selected>" . htmlspecialchars($e) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="exercise_minutes" class="form-label">
                                            <i class="bi bi-clock me-2"></i>Exercise Duration (minutes)
                                        </label>
                                        <input type="number" min="0" name="exercise_minutes" id="exercise_minutes" class="form-control" value="<?= htmlspecialchars($wellness['exercise_minutes'] ?? 0) ?>" />
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                        <span id="submitText"><i class="bi bi-arrow-repeat me-2"></i>Update Wellness</span>
                                        <span id="spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="date-navigation mt-3">
                            <a href="?date=<?= htmlspecialchars($prevDate) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                            <span class="current-date">
                                <?= (new DateTime($displayDate))->format('F j, Y') ?>
                            </span>
                            <?php
                            $today = new DateTime('today');
                            $nextDateObj = new DateTime($nextDate);
                            if ($nextDateObj > $today) {
                                echo '<button class="btn btn-outline-secondary" disabled>Next <i class="bi bi-chevron-right"></i></button>';
                            } else {
                                echo '<a href="?date=' . htmlspecialchars($nextDate) . '" class="btn btn-outline-secondary">Next <i class="bi bi-chevron-right"></i></a>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointment Card -->
            <div class="col-md-4">
                <div class="card appointment-card h-100">
                    <div class="card-header">
                        <i class="bi bi-calendar-check me-2"></i>Upcoming Appointment
                    </div>
                    <div class="card-body p-4">
                        <?php if ($upcomingAppt): ?>
                            <div class="appointment-details">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-calendar-event me-2"></i>Date & Time</h6>
                                    <p class="mb-0"><?= (new DateTime($upcomingAppt['appointment_datetime']))->format('F j, Y - g:i A') ?></p>
                                </div>
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-person-badge me-2"></i>Doctor</h6>
                                    <p class="mb-0">Dr. <?= htmlspecialchars($upcomingAppt['doctor_first'] . ' ' . $upcomingAppt['doctor_last']) ?></p>
                                </div>
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-clipboard2-pulse me-2"></i>Type</h6>
                                    <p class="mb-0"><?= htmlspecialchars($upcomingAppt['reason']) ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">No upcoming appointments scheduled.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($upcomingAppt): ?>
                    <div class="card-footer bg-transparent border-0 p-4">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary">
                                <i class="bi bi-eye me-2"></i>View Details
                            </button>
                            <button class="btn btn-warning">
                                <i class="bi bi-calendar-plus me-2"></i>Reschedule
                            </button>
                            <button class="btn btn-danger">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom Row: Notes and Recommendations -->
            <div class="col-md-6">
                <div class="card notes-card h-100">
                    <div class="card-header">
                        <i class="bi bi-chat-left-text me-2"></i>Doctor Notes
                    </div>
                    <div class="card-body p-4" style="max-height: 300px; overflow-y: auto;">
            <?php if ($notesResult->num_rows > 0): ?>
                            <div class="list-group">
                    <?php while ($note = $notesResult->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-primary">Dr. <?= htmlspecialchars($note['doctor_first'] . ' ' . $note['doctor_last']) ?></span>
                                            <span class="badge bg-info"><?= (new DateTime($note['created_at']))->format('M j, Y') ?></span>
                                        </div>
                                        <p class="mb-2"><?= nl2br(htmlspecialchars($note['note_text'])) ?></p>
                                        <?php if (!empty($note['appointment_datetime'])): ?>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                <?= (new DateTime($note['appointment_datetime']))->format('M j, Y g:i A') ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>No doctor notes available.
                            </div>
                        <?php endif; ?>
                    </div>
                                </div>
</div>

            <!-- Lifestyle Recommendations Card -->
            <div class="col-md-6">
                <div class="card recommendations-card h-100">
                    <div class="card-header">
                        <i class="bi bi-heart-pulse me-2"></i>Lifestyle Recommendations
                    </div>
                    <div class="card-body p-4" style="max-height: 300px; overflow-y: auto;">
<?php if ($recResult->num_rows > 0): ?>
    <?php while ($rec = $recResult->fetch_assoc()): ?>
        <div class="lifestyle-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="recommendation-title">Recommendation</span>
                                        <span class="badge bg-primary">
                    <?= (new DateTime($rec['created_at']))->format('M j, Y') ?>
                </span>
            </div>
                                    <p class="recommendation-text mb-0">
                <?= nl2br(htmlspecialchars($rec['recommendation'])) ?>
                                    </p>
        </div>
    <?php endwhile; ?>
<?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>No lifestyle recommendations available.
    </div>
<?php endif; ?>
                    </div>
                </div>
    </div>
</div>
</div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="Process/process_edit_profile.php" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editProfileModalLabel">
                        <i class="bi bi-person-circle me-2"></i>Edit Profile
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Profile Picture Preview -->
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <img id="profilePicPreview" src="<?= htmlspecialchars($displayPic) ?>" alt="Profile Picture" class="rounded-circle border shadow" style="width: 150px; height: 150px; object-fit: cover;">
                            </div>
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label fw-bold">
                                    <i class="bi bi-image me-2"></i>Change Picture
                                </label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewProfilePic(event)">
                            </div>
                        </div>
                        <!-- Profile Info Fields -->
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label fw-bold">
                                        <i class="bi bi-person-fill me-2"></i>First Name
                                    </label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($patient['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label fw-bold">
                                        <i class="bi bi-person-fill me-2"></i>Last Name
                                    </label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($patient['last_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-bold">
                                        <i class="bi bi-envelope-fill me-2"></i>Email
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($patient['email'] ?? '') ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid email address.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label fw-bold">
                                        <i class="bi bi-gender-ambiguous me-2"></i>Gender
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="male" <?= $patient['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= $patient['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= $patient['gender'] == 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label fw-bold">
                                        <i class="bi bi-calendar-date me-2"></i>Date of Birth
                                    </label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($patient['date_of_birth']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="height_cm" class="form-label fw-bold">
                                        <i class="bi bi-arrows-vertical me-2"></i>Height (cm)
                                    </label>
                                    <input type="number" class="form-control" id="height_cm" name="height_cm" value="<?= htmlspecialchars($patient['height_cm']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="weight_kg" class="form-label fw-bold">
                                        <i class="bi bi-bar-chart-fill me-2"></i>Weight (kg)
                                    </label>
                                    <input type="number" class="form-control" id="weight_kg" name="weight_kg" value="<?= htmlspecialchars($patient['weight_kg']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="blood_type" class="form-label fw-bold">
                                        <i class="bi bi-droplet-half me-2"></i>Blood Type
                                    </label>
                                    <input type="text" class="form-control" id="blood_type" name="blood_type" value="<?= htmlspecialchars($patient['blood_type']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                        <span id="saveProfileText"><i class="bi bi-save me-2"></i>Save Changes</span>
                        <span id="saveSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Profile Picture Preview Script -->
    <script>
    function previewProfilePic(event) {
        const [file] = event.target.files;
        if (file) {
            document.getElementById('profilePicPreview').src = URL.createObjectURL(file);
        }
    }
    </script>

    <script>
    function confirmDateChange() {
        // Check if there are unsaved changes
        const form = document.getElementById('wellnessForm');
        const formData = new FormData(form);
        const currentData = {
            water_liters: formData.get('water_liters'),
            mood: formData.get('mood'),
            sleep_hours: formData.get('sleep_hours'),
            exercise_type: formData.get('exercise_type'),
            exercise_minutes: formData.get('exercise_minutes')
        };

        // Compare with initial values
        const hasChanges = 
            currentData.water_liters !== '<?= htmlspecialchars($wellness['water_liters'] ?? '') ?>' ||
            currentData.mood !== '<?= htmlspecialchars($wellness['mood'] ?? 'Feeling Good') ?>' ||
            currentData.sleep_hours !== '<?= htmlspecialchars($wellness['sleep_hours'] ?? '') ?>' ||
            currentData.exercise_type !== '<?= htmlspecialchars($wellness['exercise_type'] ?? 'N/A') ?>' ||
            currentData.exercise_minutes !== '<?= htmlspecialchars($wellness['exercise_minutes'] ?? '0') ?>';

        if (hasChanges) {
            return confirm('You have unsaved changes. Do you want to save before changing the date?');
        }
        return true;
    }

    // Add form submission handling
    document.getElementById('wellnessForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const spinner = document.getElementById('spinner');

        // Show loading state
        submitBtn.disabled = true;
        submitText.classList.add('d-none');
        spinner.classList.remove('d-none');

        // Submit the form
        fetch(this.action, {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show';
                alert.innerHTML = `
                    <i class="bi bi-check-circle-fill me-2"></i>
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container').insertBefore(alert, document.querySelector('.card'));

                // Reload the page after a short delay to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to save wellness data');
            }
        })
        .catch(error => {
            // Show error message
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                ${error.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('.container').insertBefore(alert, document.querySelector('.card'));

            // Reset form state
            submitBtn.disabled = false;
            submitText.classList.remove('d-none');
            spinner.classList.add('d-none');
        });
    });
    </script>

    <!-- Add this script before the closing body tag -->
    <script>
    // Form validation
    document.querySelector('form[action="Process/process_edit_profile.php"]').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        const form = this;
        const email = form.querySelector('#email');
        const firstName = form.querySelector('#first_name');
        const lastName = form.querySelector('#last_name');
        let isValid = true;
        let errorMessage = '';

        // Reset previous validation states
        form.classList.remove('was-validated');
        [email, firstName, lastName].forEach(field => {
            field.classList.remove('is-invalid');
        });

        // Validate required fields
        if (!firstName.value.trim()) {
            firstName.classList.add('is-invalid');
            errorMessage += 'First name is required.<br>';
            isValid = false;
        }
        if (!lastName.value.trim()) {
            lastName.classList.add('is-invalid');
            errorMessage += 'Last name is required.<br>';
            isValid = false;
        }
        if (!email.value.trim()) {
            email.classList.add('is-invalid');
            errorMessage += 'Email is required.<br>';
            isValid = false;
        }

        // Validate email format
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (email.value.trim() && !emailRegex.test(email.value)) {
            email.classList.add('is-invalid');
            errorMessage += 'Invalid email format.<br>';
            isValid = false;
        }

        if (!isValid) {
            // Show error message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                ${errorMessage}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.card'));
            return;
        }

        // If validation passes, submit the form
        form.submit();
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
