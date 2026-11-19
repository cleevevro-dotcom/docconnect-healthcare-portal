<?php
session_start();
include '../../../database/DBconnect.php'; // Adjusted for Pages\Doctors\Process

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../../../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($doctor_id);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Doctor profile not found.");
}
$stmt->close();

// Get patient_id from URL
$patient_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch patient details
$sql = "
    SELECT p.patient_id, u.first_name, u.last_name, u.email, p.date_of_birth, 
           u.gender, p.height_cm, p.weight_kg, p.blood_type, p.bmi, p.profile_picture
    FROM patients p
    INNER JOIN users u ON p.user_id = u.user_id
    WHERE p.patient_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die("Patient not found.");
}

// Handle wellness date
$wellness_date = isset($_GET['wellness_date']) ? $_GET['wellness_date'] : date('Y-m-d');
$prev_date = date('Y-m-d', strtotime($wellness_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($wellness_date . ' +1 day'));

// Fetch wellness data
$sql = "
    SELECT water_liters, mood, sleep_hours, exercise_type, exercise_minutes
    FROM patient_daily_wellness
    WHERE patient_id = ? AND DATE(log_date) = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $patient_id, $wellness_date);
$stmt->execute();
$wellness = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch medical history
$sql = "
    SELECT history_id, condition_name, diagnosis_date, recovery_date, notes
    FROM medical_history
    WHERE patient_id = ?
    ORDER BY diagnosis_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medical_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch lifestyle recommendations
$sql = "
    SELECT recommendation_id, recommendation, created_at
    FROM lifestyle_recommendations
    WHERE patient_id = ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$lifestyle_recs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch lab results
$sql = "
    SELECT lab_result_id, test_name, result_summary, result_date, file_path
    FROM lab_results
    WHERE patient_id = ?
    ORDER BY result_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$lab_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch inherited conditions
$sql = "
    SELECT inherited_id, condition_name, gene_name, diagnosis_date, notes
    FROM inherited_conditions
    WHERE patient_id = ?
    ORDER BY diagnosis_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$inherited_conditions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch immunizations
$sql = "
    SELECT immunization_id, vaccine_name, date_administered, status, notes
    FROM immunizations
    WHERE patient_id = ?
    ORDER BY date_administered DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$immunizations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch doctor notes
$sql = "
    SELECT dn.note_id, dn.note_text, dn.appointment_id, dn.created_at, dn.updated_at,
           a.appointment_datetime, a.reason
    FROM doctor_notes dn
    LEFT JOIN appointments a ON dn.appointment_id = a.appointment_id
    WHERE dn.patient_id = ? AND dn.doctor_id = ?
    ORDER BY dn.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $patient_id, $doctor_id);
$stmt->execute();
$doctor_notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch appointments for note form dropdown
$sql = "
    SELECT appointment_id, appointment_datetime, reason
    FROM appointments
    WHERE patient_id = ? AND doctor_id = ?
    ORDER BY appointment_datetime DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $patient_id, $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle adding a new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_text = trim($_POST['note_text']);
    $appointment_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
    if (!empty($note_text)) {
        $sql = "INSERT INTO doctor_notes (patient_id, doctor_id, appointment_id, note_text, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiis", $patient_id, $doctor_id, $appointment_id, $note_text);
        $stmt->execute();
        $stmt->close();
        header("Location: PatientProfile.php?id=$patient_id&tab=notes");
        exit;
    }
}

// Handle editing a note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id = (int)$_POST['note_id'];
    $note_text = trim($_POST['note_text']);
    $appointment_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
    if (!empty($note_text)) {
        $sql = "UPDATE doctor_notes SET note_text = ?, appointment_id = ?, updated_at = NOW() 
                WHERE note_id = ? AND patient_id = ? AND doctor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siiii", $note_text, $appointment_id, $note_id, $patient_id, $doctor_id);
        $stmt->execute();
        $stmt->close();
        header("Location: PatientProfile.php?id=$patient_id&tab=notes");
        exit;
    }
}

// Handle adding a new recommendation
$add_recommendation_error = '';
$add_recommendation_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recommendation'])) {
    $recommendation = trim($_POST['recommendation']);
    if (empty($recommendation)) {
        $add_recommendation_error = 'Recommendation cannot be empty.';
    } else {
        try {
            $sql = "INSERT INTO lifestyle_recommendations (patient_id, recommendation, created_at, updated_at) 
                    VALUES (?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("is", $patient_id, $recommendation);
            if ($stmt->execute()) {
                $add_recommendation_success = 'Recommendation added successfully.';
            } else {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            $stmt->close();
            header("Location: PatientProfile.php?id=$patient_id&tab=lifestyle");
            exit;
        } catch (Exception $e) {
            $add_recommendation_error = 'Error adding recommendation: ' . $e->getMessage();
        }
    }
}

// Handle editing a recommendation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_recommendation'])) {
    $recommendation_id = (int)$_POST['recommendation_id'];
    $recommendation = trim($_POST['recommendation']);
    if (!empty($recommendation)) {
        $sql = "UPDATE lifestyle_recommendations SET recommendation = ?, updated_at = NOW() 
                WHERE recommendation_id = ? AND patient_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $recommendation, $recommendation_id, $patient_id);
        $stmt->execute();
        $stmt->close();
        header("Location: PatientProfile.php?id=$patient_id&tab=lifestyle");
        exit;
    }
}

// Handle profile picture path
$finalPicPath = !empty($patient['profile_picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Patients/uploads/' . $patient['profile_picture'])
    ? '/Healthcare_portal/Pages/Patients/uploads/' . $patient['profile_picture']
    : '/Healthcare_portal/Pages/Patients/uploads/default_profile.png';

// Include header with correct path
include '../header.php';
?>
<style>
:root {
  --primary:rgb(94, 137, 231);
  --primary-dark:rgb(77, 113, 212);
  --secondary: #64748b;
  --accent: #3b82f6;
  --success: #22c55e;
  --info: #0ea5e9;
  --warning: #fbbf24;
  --danger: #ef4444;
  --light:rgb(176, 216, 255);
  --dark: #1e293b;
  --bg-gradient: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
  --card-radius: 1.5rem;
  --card-shadow: 0 8px 32px rgba(37,99,235,0.10);
  --transition: all 0.2s cubic-bezier(.4,0,.2,1);
  --font-main: 'Poppins', 'Inter', Arial, sans-serif;
}
body {
  font-family: var(--font-main);
  background: var(--bg-gradient);
  color: var(--dark);
}
.container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 2rem 1rem;
}
.profile-card {
  background: linear-gradient(120deg, var(--primary) 0%, var(--info) 100%);
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  padding: 2.5rem 2rem 2rem 2rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
  color: #fff;
  text-align: center;
  transition: var(--transition);
  z-index: 1;
}
.profile-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 10px;
  background: linear-gradient(90deg, var(--success), var(--primary), var(--info));
  border-radius: var(--card-radius) var(--card-radius) 0 0;
  z-index: 2;
}
.profile-img {
  width: 140px;
  height: 140px;
  object-fit: cover;
  border-radius: 50%;
  border: 6px solid #fff;
  margin-bottom: 1rem;
  box-shadow: 0 4px 24px rgba(37,99,235,0.18);
  transition: var(--transition);
  background: #fff;
  z-index: 3;
}
.profile-img:hover {
  transform: scale(1.07);
  box-shadow: 0 8px 32px rgba(37,99,235,0.22);
}
.profile-card h3 {
  font-weight: 700;
  font-size: 2rem;
  margin-bottom: 0.5rem;
  letter-spacing: 0.5px;
}
.profile-card .badge {
  font-size: 1.05rem;
  padding: 0.5rem 1.1rem;
  border-radius: 1rem;
  margin-bottom: 0.25rem;
  background: rgba(255,255,255,0.15);
  color: #fff;
  font-weight: 500;
}
.profile-card .text-muted {
  color: #e0e7ef !important;
}
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 1rem;
  padding: 0.85rem 1.7rem;
  font-weight: 600;
  font-size: 1.1rem;
  box-shadow: 0 2px 8px rgba(37,99,235,0.10);
  transition: var(--transition);
  margin-bottom: 1.5rem;
}
.btn-back:hover {
  background: var(--primary-dark);
  transform: translateY(-2px) scale(1.04);
  color: #fff;
}
.wellness-card {
  background: #fff;
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  transition: var(--transition);
}
.wellness-card .card-header {
  background: var(--info);
  color: white;
  border-radius: var(--card-radius) var(--card-radius) 0 0;
  padding: 1.25rem 1.5rem;
  font-size: 1.2rem;
  font-weight: 700;
  display: flex;
  justify-content: space-between;
  align-items: center;
  letter-spacing: 0.5px;
}
.wellness-nav {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.wellness-nav .btn {
  background: rgba(14,165,233,0.12);
  border: none;
  color: var(--info);
  border-radius: 50%;
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  transition: var(--transition);
}
.wellness-nav .btn:hover {
  background: var(--info);
  color: #fff;
}
.wellness-nav input[type="date"] {
  border-radius: 0.7rem;
  border: 1px solid rgba(14,165,233,0.25);
  background: rgba(14,165,233,0.08);
  color: var(--info);
  font-size: 1rem;
  padding: 0.25rem 0.75rem;
  width: 150px;
}
.wellness-nav input[type="date"]:focus {
  outline: none;
  border-color: var(--info);
}
.wellness-card .row.text-center .col-6 {
  margin-bottom: 1.2rem;
}
.wellness-card .fs-2 {
  font-size: 2.2rem !important;
  margin-bottom: 0.2rem;
}
.wellness-card .text-primary { color: var(--primary) !important; }
.wellness-card .text-warning { color: var(--warning) !important; }
.wellness-card .text-secondary { color: var(--secondary) !important; }
.wellness-card .text-success { color: var(--success) !important; }
.card {
  border: none;
  border-radius: var(--card-radius);
  box-shadow: var(--card-shadow);
  background: #fff;
  margin-bottom: 2rem;
}
.card-header {
  border-radius: var(--card-radius) var(--card-radius) 0 0 !important;
  padding: 1.25rem 1.5rem;
  font-weight: 700;
  font-size: 1.15rem;
  letter-spacing: 0.5px;
}
.list-group-item {
  border: none;
  padding: 1.1rem 1.5rem;
  background: transparent;
  font-size: 1.08rem;
  transition: background 0.2s;
  border-radius: 0.7rem;
  margin-bottom: 0.3rem;
}
.list-group-item:hover {
  background: var(--light);
}
.badge {
  font-size: 1rem;
  padding: 0.45rem 1rem;
  border-radius: 0.7rem;
  font-weight: 500;
}
.btn-action {
  border-radius: 0.7rem;
  padding: 0.5rem 1.1rem;
  font-size: 1rem;
  transition: var(--transition);
  font-weight: 600;
}
.btn-action:hover {
  transform: translateY(-2px) scale(1.05);
  background: var(--primary);
  color: #fff;
}
.modal-content {
  border-radius: var(--card-radius);
  box-shadow: 0 8px 32px rgba(37,99,235,0.18);
}
.modal-header {
  background: var(--primary);
  color: white;
  border-radius: var(--card-radius) var(--card-radius) 0 0;
}
.modal-footer {
  border-top: none;
}
.row {
  display: flex;
  flex-wrap: wrap;
  margin-right: -15px;
  margin-left: -15px;
}
.col-left, .col-right {
  padding-right: 15px;
  padding-left: 15px;
}
.col-left {
  flex: 0 0 33.333333%;
  max-width: 33.333333%;
}
.col-right {
  flex: 0 0 66.666667%;
  max-width: 66.666667%;
}
@media (max-width: 992px) {
  .col-left, .col-right {
    flex: 0 0 100%;
    max-width: 100%;
  }
  .profile-card, .wellness-card, .card {
    margin-bottom: 1.5rem;
  }
  .nav-tabs {
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .nav-tabs::-webkit-scrollbar {
    display: none;
  }
}
@media (max-width: 576px) {
  .profile-card {
    padding: 1.2rem;
  }
  .profile-img {
    width: 80px;
    height: 80px;
  }
  .wellness-nav input[type="date"] {
    width: 100px;
  }
  .row.text-center .col-6 {
    flex: 0 0 50%;
    max-width: 50%;
  }
}
.nav-tabs {
  border: none;
  gap: 0.7rem;
  flex-wrap: nowrap;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  background: transparent;
}
.nav-tabs .nav-link {
  border: none;
  border-radius: 1.2rem;
  padding: 1rem 2rem;
  color: #222e3a;
  font-weight: 700;
  font-size: 1.12rem;
  transition: background 0.2s, color 0.2s, transform 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
  background: var(--light);
  margin-bottom: 0.2rem;
  box-shadow: 0 2px 8px rgba(0, 65, 206, 0.06);
}
.nav-tabs .nav-link:hover {
  background: var(--primary);
  color: #fff;
  transform: translateY(-2px) scale(1.03);
}
.nav-tabs .nav-link.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 16px rgba(37,99,235,0.13);
}
</style>
<div class="container">
  <!-- Back Button -->
  <a href="../Patients.php" class="btn btn-back mb-4">
    <i class="bi bi-arrow-left"></i> Back to Patients
  </a>

  <div class="row">
    <!-- Left Column: Profile and Wellness -->
    <div class="col-left">
      <!-- Profile Card -->
      <div class="profile-card text-center mb-4">
        <img src="<?= htmlspecialchars($finalPicPath) ?>" alt="Profile Picture"
             class="rounded-circle mb-3 profile-img shadow-sm"
             style="width: 120px; height: 120px; object-fit: cover;">
        <h3 class="mb-2"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h3>
        <p class="text-muted mb-3"><?= htmlspecialchars($patient['email']) ?></p>
        <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
          <span class="badge bg-info text-dark"><i class="bi bi-gender-ambiguous"></i> <?= htmlspecialchars(ucfirst($patient['gender'] ?? 'Not specified')) ?></span>
          <span class="badge bg-secondary"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars(date('M j, Y', strtotime($patient['date_of_birth']))) ?></span>
          <span class="badge bg-primary"><i class="bi bi-droplet-half"></i> <?= htmlspecialchars($patient['blood_type']) ?></span>
        </div>
        <div class="row text-center g-3">
          <div class="col-4">
            <div class="fw-bold fs-5"><?= htmlspecialchars($patient['height_cm']) ?></div>
            <div class="small text-muted">Height (cm)</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-5"><?= htmlspecialchars($patient['weight_kg']) ?></div>
            <div class="small text-muted">Weight (kg)</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-5"><?= htmlspecialchars($patient['bmi']) ?></div>
            <div class="small text-muted">BMI</div>
          </div>
        </div>
      </div>

      <!-- Daily Wellness Section -->
      <div class="wellness-card">
        <div class="card-header">
          <h5 class="mb-0"><i class="bi bi-activity me-2"></i> Daily Wellness</h5>
          <form method="get" class="wellness-nav">
            <input type="hidden" name="id" value="<?= $patient_id ?>">
            <a href="?id=<?= $patient_id ?>&wellness_date=<?= $prev_date ?>" class="btn" title="Previous Day">
              <i class="bi bi-chevron-left"></i>
            </a>
            <input type="date" name="wellness_date" value="<?= $wellness_date ?>" onchange="this.form.submit()">
            <a href="?id=<?= $patient_id ?>&wellness_date=<?= $next_date ?>" class="btn" title="Next Day">
              <i class="bi bi-chevron-right"></i>
            </a>
          </form>
        </div>
        <div class="card-body py-4">
          <?php if ($wellness): ?>
            <div class="row text-center g-4">
              <div class="col-6">
                <div class="fs-2 text-primary"><i class="bi bi-droplet-half"></i></div>
                <div class="fw-bold"><?= htmlspecialchars($wellness['water_liters']) ?> <span class="small text-muted">L</span></div>
                <div class="small text-muted">Water</div>
              </div>
              <div class="col-6">
                <div class="fs-2 text-warning"><i class="bi bi-emoji-smile"></i></div>
                <div class="fw-bold"><?= htmlspecialchars($wellness['mood']) ?></div>
                <div class="small text-muted">Mood</div>
              </div>
              <div class="col-6">
                <div class="fs-2 text-secondary"><i class="bi bi-moon-stars"></i></div>
                <div class="fw-bold"><?= htmlspecialchars($wellness['sleep_hours']) ?> <span class="small text-muted">hrs</span></div>
                <div class="small text-muted">Sleep</div>
              </div>
              <div class="col-6">
                <div class="fs-2 text-success"><i class="bi bi-bicycle"></i></div>
                <div class="fw-bold"><?= htmlspecialchars($wellness['exercise_type']) ?></div>
                <div class="small text-muted">Exercise</div>
                <div class="fw-bold"><?= htmlspecialchars($wellness['exercise_minutes']) ?> <span class="small text-muted">min</span></div>
              </div>
            </div>
          <?php else: ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-emoji-frown fs-1"></i>
              <div>No wellness data recorded for this day.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right Column: Tabs and Content -->
    <div class="col-right">
      <!-- Tabs Navigation -->
      <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= !isset($_GET['tab']) || $_GET['tab'] === 'appointments' ? 'active' : '' ?>" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab" aria-controls="appointments" aria-selected="true">
            <i class="bi bi-calendar-check"></i> Appointments
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'medical' ? 'active' : '' ?>" id="medical-tab" data-bs-toggle="tab" data-bs-target="#medical" type="button" role="tab" aria-controls="medical" aria-selected="false">
            <i class="bi bi-clipboard-pulse"></i> Medical History
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'lifestyle' ? 'active' : '' ?>" id="lifestyle-tab" data-bs-toggle="tab" data-bs-target="#lifestyle" type="button" role="tab" aria-controls="lifestyle" aria-selected="false">
            <i class="bi bi-heart-pulse"></i> Lifestyle
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'lab' ? 'active' : '' ?>" id="lab-tab" data-bs-toggle="tab" data-bs-target="#lab" type="button" role="tab" aria-controls="lab" aria-selected="false">
            <i class="bi bi-file-earmark-medical"></i> Lab Results
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'inherited' ? 'active' : '' ?>" id="inherited-tab" data-bs-toggle="tab" data-bs-target="#inherited" type="button" role="tab" aria-controls="inherited" aria-selected="false">
            <i class="bi bi-diagram-3"></i> Inherited
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'immun' ? 'active' : '' ?>" id="immun-tab" data-bs-toggle="tab" data-bs-target="#immun" type="button" role="tab" aria-controls="immun" aria-selected="false">
            <i class="bi bi-shield-plus"></i> Immunizations
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'notes' ? 'active' : '' ?>" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
            <i class="bi bi-journal-medical"></i> Doctor Notes
          </button>
        </li>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content" id="profileTabsContent">
        <!-- Appointments Tab -->
        <div class="tab-pane fade <?= !isset($_GET['tab']) || $_GET['tab'] === 'appointments' ? 'show active' : '' ?>" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
          <div class="card">
            <div class="card-header bg-dark text-white"><i class="bi bi-calendar-check me-2"></i> Previous Appointments</div>
            <div class="card-body">
              <?php
              $sql = "
                SELECT appointment_datetime, status, reason
                FROM appointments
                WHERE patient_id = ? AND doctor_id = ?
                ORDER BY appointment_datetime DESC
              ";
              $stmt = $conn->prepare($sql);
              $stmt->bind_param("ii", $patient_id, $doctor_id);
              $stmt->execute();
              $appointments_result = $stmt->get_result();
              $stmt->close();
              ?>
              <?php if ($appointments_result->num_rows === 0): ?>
                <p class="text-muted">No previous appointments found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php while ($appt = $appointments_result->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <div>
                        <strong><?= htmlspecialchars(date('M j, Y H:i', strtotime($appt['appointment_datetime']))) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($appt['reason']) ?></small>
                      </div>
                      <span class="badge 
                        <?= $appt['status'] === 'pending' ? 'bg-warning text-dark' : 
                            ($appt['status'] === 'confirmed' ? 'bg-success' : 'bg-secondary') ?>">
                        <?= ucfirst($appt['status']) ?>
                      </span>
                    </li>
                  <?php endwhile; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Medical History Tab -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'medical' ? 'show active' : '' ?>" id="medical" role="tabpanel" aria-labelledby="medical-tab">
          <div class="card">
            <div class="card-header bg-primary text-white"><i class="bi bi-clipboard-pulse me-2"></i> Medical History</div>
            <div class="card-body">
              <?php if (empty($medical_history)): ?>
                <p class="text-muted">No medical history records found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php foreach ($medical_history as $history): ?>
                    <li class="list-group-item">
                      <strong><?= htmlspecialchars($history['condition_name']) ?></strong>
                      <span class="badge bg-light text-dark ms-2">Diagnosed: <?= htmlspecialchars(date('M j, Y', strtotime($history['diagnosis_date']))) ?></span>
                      <?php if (!empty($history['recovery_date'])): ?>
                        <span class="badge bg-success ms-2">Recovered: <?= htmlspecialchars(date('M j, Y', strtotime($history['recovery_date']))) ?></span>
                      <?php endif; ?>
                      <br>
                      <small><?= nl2br(htmlspecialchars($history['notes'])) ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Lifestyle Recommendations Tab -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'lifestyle' ? 'show active' : '' ?>" id="lifestyle" role="tabpanel" aria-labelledby="lifestyle-tab">
          <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
              <span><i class="bi bi-heart-pulse me-2"></i> Lifestyle Recommendations</span>
              <button class="btn btn-light btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#addRecommendationModal">
                <i class="bi bi-plus-circle"></i> Add Recommendation
              </button>
            </div>
            <div class="card-body">
              <?php if (!empty($add_recommendation_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <?= htmlspecialchars($add_recommendation_success) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              <?php if (!empty($add_recommendation_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?= htmlspecialchars($add_recommendation_error) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              <?php if (empty($lifestyle_recs)): ?>
                <p class="text-muted">No lifestyle recommendations found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php foreach ($lifestyle_recs as $rec): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                      <div>
                        <?= nl2br(htmlspecialchars($rec['recommendation'])) ?>
                        <br><small class="text-muted">Added on <?= htmlspecialchars(date('M j, Y', strtotime($rec['created_at']))) ?></small>
                      </div>
                      <button class="btn btn-outline-primary btn-sm btn-action" 
                              data-bs-toggle="modal" 
                              data-bs-target="#editRecommendationModal"
                              data-id="<?= $rec['recommendation_id'] ?>"
                              data-text="<?= htmlspecialchars($rec['recommendation']) ?>">
                        <i class="bi bi-pencil"></i> Edit
                      </button>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Lab Results Tab -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'lab' ? 'show active' : '' ?>" id="lab" role="tabpanel" aria-labelledby="lab-tab">
          <div class="card">
            <div class="card-header bg-info text-white"><i class="bi bi-file-earmark-medical me-2"></i> Lab Results</div>
            <div class="card-body">
              <?php if (empty($lab_results)): ?>
                <p class="text-muted">No lab results found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php foreach ($lab_results as $lab): ?>
                    <li class="list-group-item">
                      <strong><?= htmlspecialchars($lab['test_name']) ?></strong>
                      <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars(date('M j, Y', strtotime($lab['result_date']))) ?></span>
                      <br>
                      <?= nl2br(htmlspecialchars($lab['result_summary'])) ?>
                      <?php if (!empty($lab['file_path'])): ?>
                        <br><a href="<?= htmlspecialchars($lab['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-file-earmark-arrow-down"></i> View Report</a>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Inherited Conditions Tab -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'inherited' ? 'show active' : '' ?>" id="inherited" role="tabpanel" aria-labelledby="inherited-tab">
          <div class="card">
            <div class="card-header bg-warning text-dark"><i class="bi bi-diagram-3 me-2"></i> Inherited Conditions</div>
            <div class="card-body">
              <?php if (empty($inherited_conditions)): ?>
                <p class="text-muted">No inherited conditions found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php foreach ($inherited_conditions as $cond): ?>
                    <li class="list-group-item">
                      <strong><?= htmlspecialchars($cond['condition_name']) ?></strong>
                      <span class="badge bg-light text-dark ms-2">Gene: <?= htmlspecialchars($cond['gene_name']) ?></span>
                      <span class="badge bg-light text-dark ms-2">Diagnosed: <?= htmlspecialchars(date('M j, Y', strtotime($cond['diagnosis_date']))) ?></span>
                      <br>
                      <small><?= nl2br(htmlspecialchars($cond['notes'])) ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Immunizations Tab -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'immun' ? 'show active' : '' ?>" id="immun" role="tabpanel" aria-labelledby="immun-tab">
          <div class="card">
            <div class="card-header bg-secondary text-white"><i class="bi bi-shield-plus me-2"></i> Immunizations</div>
            <div class="card-body">
              <?php if (empty($immunizations)): ?>
                <p class="text-muted">No immunizations found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php foreach ($immunizations as $imm): ?>
                    <li class="list-group-item">
                      <strong><?= htmlspecialchars($imm['vaccine_name']) ?></strong>
                      <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars(date('M j, Y', strtotime($imm['date_administered']))) ?></span>
                      <span class="badge bg-light text-dark ms-2">Status: <?= htmlspecialchars($imm['status']) ?></span>
                      <br>
                      <small><?= nl2br(htmlspecialchars($imm['notes'])) ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Doctor Notes Tab -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'notes' ? 'show active' : '' ?>" id="notes" role="tabpanel" aria-labelledby="notes-tab">
          <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
              <span><i class="bi bi-journal-medical me-2"></i> Doctor Notes</span>
              <button class="btn btn-light btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                <i class="bi bi-plus-circle"></i> Add Note
              </button>
            </div>
            <div class="card-body">
              <?php if (empty($doctor_notes)): ?>
                <p class="text-muted">No notes found.</p>
              <?php else: ?>
                <ul class="list-group">
                  <?php foreach ($doctor_notes as $note): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                      <div>
                        <?= nl2br(htmlspecialchars($note['note_text'])) ?>
                        <?php if ($note['appointment_id']): ?>
                          <br><small class="text-muted">Appointment: <?= htmlspecialchars(date('M j, Y H:i', strtotime($note['appointment_datetime']))) ?> - <?= htmlspecialchars($note['reason']) ?></small>
                        <?php endif; ?>
                        <br><small class="text-muted">Added on <?= htmlspecialchars(date('M j, Y H:i', strtotime($note['created_at']))) ?></small>
                        <?php if ($note['updated_at'] !== $note['created_at']): ?>
                          <br><small class="text-muted">Updated on <?= htmlspecialchars(date('M j, Y H:i', strtotime($note['updated_at']))) ?></small>
                        <?php endif; ?>
                      </div>
                      <button class="btn btn-outline-primary btn-sm btn-action" 
                              data-bs-toggle="modal" 
                              data-bs-target="#editNoteModal"
                              data-id="<?= $note['note_id'] ?>"
                              data-text="<?= htmlspecialchars($note['note_text']) ?>"
                              data-appointment-id="<?= $note['appointment_id'] ?: '' ?>">
                        <i class="bi bi-pencil"></i> Edit
                      </button>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addNoteModalLabel">Add New Note</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="PatientProfile.php?id=<?= $patient_id ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="note_text" class="form-label">Note Text</label>
            <textarea class="form-control" id="note_text" name="note_text" rows="5" required></textarea>
          </div>
          <div class="mb-3">
            <label for="appointment_id" class="form-label">Link to Appointment (Optional)</label>
            <select class="form-select" id="appointment_id" name="appointment_id">
              <option value="">None</option>
              <?php foreach ($appointments as $appt): ?>
                <option value="<?= $appt['appointment_id'] ?>">
                  <?= htmlspecialchars(date('M j, Y H:i', strtotime($appt['appointment_datetime']))) ?> - <?= htmlspecialchars($appt['reason']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_note" class="btn btn-primary">Save Note</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editNoteModalLabel">Edit Note</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="PatientProfile.php?id=<?= $patient_id ?>">
        <div class="modal-body">
          <input type="hidden" name="note_id" id="edit_note_id">
          <div class="mb-3">
            <label for="edit_note_text" class="form-label">Note Text</label>
            <textarea class="form-control" id="edit_note_text" name="note_text" rows="5" required></textarea>
          </div>
          <div class="mb-3">
            <label for="edit_appointment_id" class="form-label">Link to Appointment (Optional)</label>
            <select class="form-select" id="edit_appointment_id" name="appointment_id">
              <option value="">None</option>
              <?php foreach ($appointments as $appt): ?>
                <option value="<?= $appt['appointment_id'] ?>">
                  <?= htmlspecialchars(date('M j, Y H:i', strtotime($appt['appointment_datetime']))) ?> - <?= htmlspecialchars($appt['reason']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_note" class="btn btn-primary">Update Note</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Recommendation Modal -->
<div class="modal fade" id="addRecommendationModal" tabindex="-1" aria-labelledby="addRecommendationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRecommendationModalLabel">Add New Recommendation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="PatientProfile.php?id=<?= $patient_id ?>">
        <div class="modal-body">
          <?php if (!empty($add_recommendation_error)): ?>
            <div class="alert alert-danger" role="alert">
              <?= htmlspecialchars($add_recommendation_error) ?>
            </div>
          <?php endif; ?>
          <div class="mb-3">
            <label for="recommendation" class="form-label">Recommendation</label>
            <textarea class="form-control" id="recommendation" name="recommendation" rows="5" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_recommendation" class="btn btn-primary">Save Recommendation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Recommendation Modal -->
<div class="modal fade" id="editRecommendationModal" tabindex="-1" aria-labelledby="editRecommendationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editRecommendationModalLabel">Edit Recommendation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="PatientProfile.php?id=<?= $patient_id ?>">
        <div class="modal-body">
          <input type="hidden" name="recommendation_id" id="edit_recommendation_id">
          <div class="mb-3">
            <label for="edit_recommendation" class="form-label">Recommendation</label>
            <textarea class="form-control" id="edit_recommendation" name="recommendation" rows="5" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_recommendation" class="btn btn-primary">Update Recommendation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap Icons and Bootstrap JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Google Fonts for Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- JavaScript for Interactivity -->
<script>
  // Smooth scroll to tabs on click
  document.querySelectorAll('.nav-tabs .nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = link.getAttribute('data-bs-target');
      const targetElement = document.querySelector(targetId);
      targetElement.scrollIntoView({ behavior: 'smooth' });
      new bootstrap.Tab(link).show();
    });
  });

  // Fade-in animation for cards
  document.querySelectorAll('.profile-card, .wellness-card, .card').forEach(card => {
    card.style.opacity = 0;
    card.style.transform = 'translateY(20px)';
    setTimeout(() => {
      card.style.transition = 'opacity 0.5s, transform 0.5s';
      card.style.opacity = 1;
      card.style.transform = 'translateY(0)';
    }, 100);
  });

  // Populate edit note modal
  document.querySelectorAll('[data-bs-target="#editNoteModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.querySelector('#editNoteModal');
      modal.querySelector('#edit_note_id').value = btn.dataset.id;
      modal.querySelector('#edit_note_text').value = btn.dataset.text;
      modal.querySelector('#edit_appointment_id').value = btn.dataset.appointmentId || '';
    });
  });

  // Populate edit recommendation modal
  document.querySelectorAll('[data-bs-target="#editRecommendationModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.querySelector('#editRecommendationModal');
      modal.querySelector('#edit_recommendation_id').value = btn.dataset.id;
      modal.querySelector('#edit_recommendation').value = btn.dataset.text;
    });
  });
</script>

<?php include '../footer.php'; ?>