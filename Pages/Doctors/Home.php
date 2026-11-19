<?php
session_start();
require '../../database/DBconnect.php';
require 'header.php';

function displayValue($value, $fallback = 'N/A') {
    return (isset($value) && trim($value) !== '') ? htmlspecialchars($value) : $fallback;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<div class='alert alert-danger'>User not logged in.</div>";
    exit;
}

// Role check
$roleCheck = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$roleCheck->bind_param("i", $user_id);
$roleCheck->execute();
$roleResult = $roleCheck->get_result();
$roleRow = $roleResult->fetch_assoc();

if (!$roleRow || $roleRow['role'] !== 'doctor') {
    echo "<div class='alert alert-warning'>Access denied. This page is for doctors only.</div>";
    exit;
}

// Fetch doctor and their clinic info
$sql = "SELECT 
            u.user_id, u.first_name, u.last_name, u.email,
            d.doctor_id, d.specialty, d.years_experience, d.clinic_address, d.profile_picture,
            dc.clinic_id, dc.clinic_name, dc.short_bio, dc.description, dc.clinic_image
        FROM users u
        LEFT JOIN doctors d ON u.user_id = d.user_id
        LEFT JOIN doctors_clinic dc ON d.doctor_id = dc.doctor_id
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$doctor = null;
$clinics = [];
while ($row = $result->fetch_assoc()) {
    if (!$doctor) {
        $doctor = $row;
    }
    if ($row['clinic_id']) {
        $clinics[] = [
            'clinic_id' => $row['clinic_id'],
            'clinic_name' => $row['clinic_name'],
            'short_bio' => $row['short_bio'],
            'description' => $row['description'],
            'clinic_image' => $row['clinic_image'],
        ];
    }
}
$stmt->close();

// Debug clinic data
error_log("Doctor data: " . print_r($doctor, true));
error_log("Clinics data: " . print_r($clinics, true));

$defaultPic = 'Uploads/default_profile.png';
$profilePic = $doctor['profile_picture'] ?? '';
$displayPic = ($profilePic && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($profilePic, '/'))) ? $profilePic : $defaultPic;

// Clinic image logic
$clinic = !empty($clinics) ? $clinics[0] : null;
$defaultClinicImg = 'Uploads/default_clinic.jpg';
$displayClinicImg = $defaultClinicImg;

if ($clinic && !empty($clinic['clinic_image'])) {
    $clinicImagePath = $clinic['clinic_image'];
    // Ensure the path is relative to the web root
    if (strpos($clinicImagePath, 'Uploads/') === false) {
        // If the path is not relative, fix it (assume filename only)
        $clinicImagePath = 'Uploads/clinic_images/' . basename($clinicImagePath);
    }
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($clinicImagePath, '/');
    error_log("Checking clinic image at: " . $fullPath);
    if (file_exists($fullPath)) {
        $displayClinicImg = $clinicImagePath;
        error_log("Using uploaded clinic image: " . $displayClinicImg);
    } else {
        error_log("Clinic image not found at: " . $fullPath);
        $displayClinicImg = $defaultClinicImg;
    }
} else {
    error_log("No clinic image set in database");
    $displayClinicImg = $defaultClinicImg;
}

// Debug clinic image path
error_log("Clinic Image Path from DB: " . ($clinic['clinic_image'] ?? 'not set'));
error_log("Display Clinic Image Path: " . $displayClinicImg);
error_log("Document Root: " . $_SERVER['DOCUMENT_ROOT']);

// Fetch Appointments Overview data
$doctor_id = $doctor['doctor_id'];

// Total appointments
$stmt = $conn->prepare("SELECT COUNT(*) as total_appointments 
                        FROM appointments 
                        WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['total_appointments'] ?? 0;
$stmt->close();

// New appointments this week
$week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
$week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT COUNT(*) as new_appointments
                        FROM appointments
                        WHERE doctor_id = ?
                        AND appointment_datetime BETWEEN ? AND ?");
$stmt->bind_param("iss", $doctor_id, $week_start, $week_end);
$stmt->execute();
$new_appointments = $stmt->get_result()->fetch_assoc()['new_appointments'] ?? 0;
$stmt->close();

// Pending appointments
$stmt = $conn->prepare("SELECT COUNT(*) as pending_appointments 
                        FROM appointments 
                        WHERE doctor_id = ? AND status = 'pending'");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$pending_appointments = $stmt->get_result()->fetch_assoc()['pending_appointments'] ?? 0;
$stmt->close();

// Fetch Today's Appointments
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$stmt = $conn->prepare("SELECT a.appointment_id, a.appointment_datetime, a.status, 
                               u.first_name, u.last_name
                        FROM appointments a
                        JOIN patients p ON a.patient_id = p.patient_id
                        JOIN users u ON p.user_id = u.user_id
                        WHERE a.doctor_id = ?
                        AND a.appointment_datetime BETWEEN ? AND ?
                        ORDER BY a.appointment_datetime");
$stmt->bind_param("iss", $doctor_id, $today_start, $today_end);
$stmt->execute();
$appointments_result = $stmt->get_result();
$today_appointments = [];
while ($row = $appointments_result->fetch_assoc()) {
    $today_appointments[] = $row;
}
$stmt->close();
?>

<style>
  body {
    background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
  }
  .background-accent {
    position: absolute;
    top: -80px;
    left: -80px;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, #6366f1 0%, #a5b4fc 80%, transparent 100%);
    opacity: 0.15;
    z-index: 0;
    pointer-events: none;
  }
  .container {
    position: relative;
    z-index: 1;
  }
  .card {
    background: rgba(255,255,255,0.85);
    border-radius: 2rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
    border: none;
    transition: box-shadow 0.3s, transform 0.3s;
  }
  .card:hover {
    box-shadow: 0 16px 40px 0 rgba(31, 38, 135, 0.18);
    transform: translateY(-2px) scale(1.01);
  }
  .card-header {
    border-bottom: none;
    font-size: 1.2rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: none;
    background-clip: padding-box;
    padding-top: 1.2rem;
    padding-bottom: 1.2rem;
  }
  .primary-gradient {
    background: linear-gradient(90deg, #6366f1, #3b82f6);
    color: #fff !important;
  }
  .success-gradient {
    background: linear-gradient(90deg, #22c55e, #16a34a);
    color: #fff !important;
  }
  .info-gradient {
    background: linear-gradient(90deg, #0ea5e9, #38bdf8);
    color: #fff !important;
  }
  .profile-img-ring {
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
    padding: 5px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 2px 12px rgba(59, 130, 246, 0.15);
  }
  .profile-img-ring img {
    border-radius: 50%;
    border: 4px solid #fff;
    width: 150px;
    height: 150px;
    object-fit: cover;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.10);
  }
  .clinic-image {
    border: 3px solid #6366f1;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.10);
    border-radius: 1.5rem;
  }
  .section-divider {
    height: 4px;
    width: 60px;
    background: linear-gradient(90deg, #6366f1, #3b82f6);
    border-radius: 2px;
    margin: 1.5rem 0 2rem 0;
  }
  .stat-card {
    background: linear-gradient(135deg, #f1f5f9 60%, #e0e7ff 100%);
    border-radius: 1.5rem;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.08);
    padding: 1.5rem 1.2rem;
    display: flex;
    align-items: center;
    gap: 1.2rem;
    margin-bottom: 1.2rem;
    transition: box-shadow 0.2s;
  }
  .stat-icon {
    font-size: 2.2rem;
    color: #6366f1;
    background: #e0e7ff;
    border-radius: 1rem;
    padding: 0.7rem;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.10);
  }
  .stat-label {
    font-size: 1.1rem;
    color: #64748b;
    font-weight: 500;
  }
  .stat-value {
    font-size: 1.7rem;
    font-weight: 700;
    color: #1e40af;
  }
  .badge {
    font-size: 0.95rem;
    padding: 0.5em 1em;
    border-radius: 1rem;
    font-weight: 500;
    letter-spacing: 0.5px;
  }
  .timeline {
    border-left: 3px solid #6366f1;
    margin-left: 1.2rem;
    padding-left: 1.5rem;
  }
  .timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
  }
  .timeline-item:last-child {
    margin-bottom: 0;
  }
  .timeline-dot {
    position: absolute;
    left: -2.1rem;
    top: 0.3rem;
    width: 1.2rem;
    height: 1.2rem;
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.10);
  }
  .timeline-time {
    font-size: 1rem;
    color: #6366f1;
    font-weight: 600;
    margin-bottom: 0.2rem;
  }
  .timeline-patient {
    font-size: 1.1rem;
    color: #1e293b;
    font-weight: 500;
  }
  .timeline-status {
    margin-left: 0.5rem;
    font-size: 0.95rem;
    font-weight: 500;
  }
  .btn-gradient {
    background: linear-gradient(90deg, #6366f1, #3b82f6);
    color: #fff;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    transition: background 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.10);
  }
  .btn-gradient:hover {
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    color: #fff;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.15);
  }
  .modal-content {
    animation: scaleIn 0.4s ease;
    backdrop-filter: blur(5px);
    background: rgba(255, 255, 255, 0.95);
    border-radius: 2rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
  }
  @keyframes scaleIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
  }
  .form-control:focus, .form-check-input:focus {
    box-shadow: 0 0 0 0.3rem rgba(99, 102, 241, 0.15);
    border-color: #6366f1;
  }
  @media (max-width: 991px) {
    .profile-img-ring img {
      width: 110px;
      height: 110px;
    }
    .clinic-image {
      height: 140px !important;
    }
  }
  @media (max-width: 767px) {
    .profile-img-ring img {
      width: 80px;
      height: 80px;
    }
    .clinic-image {
      height: 100px !important;
    }
    .timeline {
      margin-left: 0.5rem;
      padding-left: 0.7rem;
    }
  }
</style>
<div class="background-accent"></div>

<div class="container py-5">
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

<!-- Top Section: Doctor Profile -->
<div class="row justify-content-center mb-5">
  <div class="col-lg-10">
    <div class="card shadow-sm rounded-4 border-0">
      <div class="card-header primary-gradient text-white fw-bolder rounded-top-4">Doctor Profile</div>
      <div class="card-body d-flex flex-wrap align-items-start justify-content-between px-4 py-4">
        <div class="d-flex align-items-start ms-md-4" style="gap: 1.5rem;">
          <img src="<?= htmlspecialchars($displayPic) ?>" alt="Doctor Profile" class="rounded-circle border border-4 border-primary" width="140" height="140" style="object-fit: cover;">
          <div style="line-height: 1.4; font-size: 1.05rem;"> <!-- Adjusted line spacing and font size -->
            <h3 class="mb-2 fw-bolder text-primary">Dr. <?= displayValue($doctor['first_name'] . ' ' . $doctor['last_name']) ?></h3>
            <p class="mb-1 text-muted"><i class="bi bi-briefcase-fill me-2 icon-lg text-primary"></i><span class="fw-medium">Specialty:</span> <?= displayValue($doctor['specialty']) ?></p>
            <p class="mb-1 text-muted"><i class="bi bi-clock-fill me-2 icon-lg text-primary"></i><span class="fw-medium">Experience:</span> <?= is_numeric($doctor['years_experience']) ? $doctor['years_experience'] . ' years' : 'N/A' ?></p>
            <p class="mb-1 text-muted"><i class="bi bi-envelope-fill me-2 icon-lg text-primary"></i><span class="fw-medium">Email:</span> <?= displayValue($doctor['email']) ?></p>
            <p class="mb-0 text-muted"><i class="bi bi-geo-alt-fill me-2 icon-lg text-primary"></i><span class="fw-medium">Clinic Address:</span> <?= displayValue($doctor['clinic_address']) ?></p>
          </div>
        </div>
        <div class="mt-3 mt-md-0">
          <button type="button" class="btn btn-primary rounded-3 px-4" data-bs-toggle="modal" data-bs-target="#editProfileModal" aria-label="Edit Profile">
            <i class="bi bi-pencil-square me-1"></i> Edit Profile
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

  <!-- Middle Section: Appointments Overview and Today's Appointments -->
  <div class="row justify-content-center g-4 mb-5 middle-section">
    <!-- Appointments Overview -->
    <div class="col-lg-5">
      <div class="card shadow-sm rounded-4 border-0 h-100">
        <div class="card-header success-gradient text-white fw-bolder rounded-top-4">Appointments Overview</div>
        <div class="card-body d-flex flex-column">
          <div class="mb-3">
            <p class="mb-1 small"><i class="bi bi-calendar-check-fill me-2 icon-lg text-success"></i><span class="fw-medium">Total Appointments:</span> <strong><?= $total_appointments ?></strong></p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: <?= min($total_appointments / 100 * 100, 100) ?>%" aria-valuenow="<?= $total_appointments ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
          <div class="mb-3">
            <p class="mb-1 small"><i class="bi bi-calendar-plus-fill me-2 icon-lg text-success"></i><span class="fw-medium">New This Week:</span> <strong><?= $new_appointments ?></strong></p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: <?= min($new_appointments / 20 * 100, 100) ?>%" aria-valuenow="<?= $new_appointments ?>" aria-valuemin="0" aria-valuemax="20"></div>
            </div>
          </div>
          <div class="mb-3">
            <p class="mb-1 small"><i class="bi bi-hourglass-split me-2 icon-lg text-success"></i><span class="fw-medium">Pending Appointments:</span> <strong><?= $pending_appointments ?></strong></p>
            <div class="progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: <?= min($pending_appointments / 20 * 100, 100) ?>%" aria-valuenow="<?= $pending_appointments ?>" aria-valuemin="0" aria-valuemax="20"></div>
            </div>
          </div>
          <a href="Appointment.php" class="btn btn-outline-success btn-sm rounded-3 mt-auto">View All Appointments</a>
        </div>
      </div>
    </div>

    <!-- Today's Appointments -->
    <div class="col-lg-5">
      <div class="card shadow-sm rounded-4 border-0 h-100">
        <div class="card-header info-gradient text-white fw-bolder rounded-top-4">Today's Appointments</div>
        <div class="card-body d-flex flex-column">
          <?php if (!empty($today_appointments)): ?>
            <ul class="list-unstyled mb-3">
              <?php foreach ($today_appointments as $appt): ?>
                <li class="mb-2 d-flex align-items-center">
                  <span class="fw-medium me-2 text-primary"><?= date('h:i A', strtotime($appt['appointment_datetime'])) ?></span>
                  <span class="flex-grow-1"><?= displayValue($appt['first_name'] . ' ' . $appt['last_name']) ?></span>
                  <span class="badge bg-<?= $appt['status'] === 'pending' ? 'warning' : ($appt['status'] === 'confirmed' ? 'success' : ($appt['status'] === 'cancelled' ? 'danger' : 'secondary')) ?> rounded-pill">
                    <?= ucfirst($appt['status']) ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-muted small">No appointments scheduled for today.</p>
          <?php endif; ?>
          <a href="Appointment.php" class="btn btn-outline-info btn-sm rounded-3 mt-auto">View All</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Section: Clinic Information -->
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="card shadow-sm rounded-4 border-0 glass-card position-relative overflow-hidden">
        <div class="clinic-header-bar primary-gradient position-absolute top-0 start-0 w-100" style="height: 8px; border-radius: 2rem 2rem 0 0;"></div>
        <div class="card-header primary-gradient text-white fw-bolder rounded-top-4 d-flex align-items-center gap-2" style="position: relative; z-index: 2;">
          <i class="bi bi-hospital fs-4 me-2"></i> Clinic Information
        </div>
        <div class="card-body py-4 px-4">
          <?php if ($clinic): ?>
            <div class="row align-items-center flex-md-row flex-column-reverse">
              <div class="col-md-8 mt-4 mt-md-0">
                <div class="mb-3">
                  <span class="badge bg-gradient-primary text-white mb-2" style="font-size:1.1rem; background: linear-gradient(90deg, #6366f1, #3b82f6); padding: 0.7em 1.2em; border-radius: 1.2em;">
                    <i class="bi bi-building me-2"></i><?= displayValue($clinic['clinic_name']) ?>
                  </span>
                </div>
                <p class="text-muted mb-2 small"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><span class="fw-medium">Address:</span> <?= displayValue($doctor['clinic_address']) ?></p>
                <p class="text-muted mb-2 small"><i class="bi bi-info-circle-fill me-2 text-primary"></i><span class="fw-medium">Short Bio:</span> <?= displayValue($clinic['short_bio']) ?></p>
                <p class="small"><i class="bi bi-card-text me-2 text-primary"></i><span class="fw-medium">Description:</span> <?= displayValue($clinic['description']) ?></p>
                <button type="button" class="btn btn-gradient btn-sm rounded-3 mt-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                  <i class="bi bi-pencil-square me-1"></i> Edit Clinic Info
                </button>
              </div>
              <div class="col-md-4 d-flex justify-content-center align-items-center mb-4 mb-md-0">
                <div class="clinic-img-ring p-2" style="background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%); border-radius: 1.5rem; display: inline-block; box-shadow: 0 2px 12px rgba(59, 130, 246, 0.15);">
                  <img src="<?= htmlspecialchars($displayClinicImg) ?>" alt="Clinic Image" class="clinic-image" style="height: 220px; width: 100%; object-fit: cover; border-radius: 1.2rem; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.10);" onerror="this.onerror=null; this.src='<?= htmlspecialchars($defaultClinicImg) ?>';" loading="lazy">
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="bi bi-hospital fs-1 text-primary mb-3"></i>
              <p class="text-muted small">No clinic information available. Update your profile to add clinic details.</p>
              <button type="button" class="btn btn-gradient btn-sm rounded-3 mt-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                <i class="bi bi-plus-circle me-1"></i> Add Clinic Info
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header primary-gradient text-white rounded-top-4">
        <h5 class="modal-title fw-bolder" id="editProfileModalLabel">Edit Profile & Clinic</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="Process/edit_doctor_profile.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="doctor_id" value="<?= htmlspecialchars($doctor['doctor_id'] ?? '') ?>">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($doctor['user_id'] ?? '') ?>">
        <input type="hidden" name="existing_profile_picture" value="<?= htmlspecialchars($doctor['profile_picture'] ?? '') ?>">
        <input type="hidden" name="existing_clinic_image" value="<?= htmlspecialchars($doctor['clinic_image'] ?? '') ?>">
        <div class="modal-body p-4">
          <!-- Doctor Information -->
          <h6 class="mb-3 fw-bolder text-primary">Doctor Information</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="first_name" class="form-label fw-medium text-dark">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control rounded-3" name="first_name" id="first_name" placeholder="Enter first name" value="<?= htmlspecialchars($doctor['first_name'] ?? '') ?>" required>
              <div class="invalid-feedback">Please enter your first name.</div>
            </div>
            <div class="col-md-6">
              <label for="last_name" class="form-label fw-medium text-dark">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control rounded-3" name="last_name" id="last_name" placeholder="Enter last name" value="<?= htmlspecialchars($doctor['last_name'] ?? '') ?>" required>
              <div class="invalid-feedback">Please enter your last name.</div>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label fw-medium text-dark">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control rounded-3" name="email" id="email" placeholder="Enter email address" value="<?= htmlspecialchars($doctor['email'] ?? '') ?>" required>
              <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>
            <div class="col-md-6">
              <label for="specialty" class="form-label fw-medium text-dark">Specialty <span class="text-danger">*</span></label>
              <input type="text" class="form-control rounded-3" name="specialty" id="specialty" placeholder="e.g., Cardiology" value="<?= htmlspecialchars($doctor['specialty'] ?? '') ?>" required>
              <div class="invalid-feedback">Please enter your specialty.</div>
            </div>
            <div class="col-md-6">
              <label for="years_experience" class="form-label fw-medium text-dark">Years of Experience <span class="text-danger">*</span></label>
              <input type="number" class="form-control rounded-3" name="years_experience" id="years_experience" min="0" placeholder="e.g., 5" value="<?= htmlspecialchars($doctor['years_experience'] ?? '') ?>" required>
              <div class="invalid-feedback">Please enter valid years of experience.</div>
            </div>
            <div class="col-12">
              <label for="clinic_address" class="form-label fw-medium text-dark">Clinic Address</label>
              <input type="text" class="form-control rounded-3" name="clinic_address" id="clinic_address" placeholder="Full clinic address" value="<?= htmlspecialchars($doctor['clinic_address'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label for="profile_picture" class="form-label fw-medium text-dark">Profile Picture</label>
              <input type="file" class="form-control rounded-3" name="profile_picture" id="profile_picture" accept="image/*" aria-describedby="profilePictureHelp">
              <div id="profilePictureHelp" class="form-text small">JPG, PNG, GIF. Max 2MB.</div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="remove_profile_picture" name="remove_profile_picture">
                <label class="form-check-label small" for="remove_profile_picture">Remove current picture</label>
              </div>
            </div>
            <?php if ($displayPic !== $defaultPic): ?>
              <div class="col-12">
                <img src="<?= htmlspecialchars($displayPic) ?>" alt="Current Profile Picture" class="rounded-circle" width="80" height="80" style="object-fit: cover;">
              </div>
            <?php endif; ?>
          </div>

          <!-- Clinic Information -->
          <h6 class="mb-3 fw-bolder text-primary">Clinic Information</h6>
          <div class="row g-3">
            <div class="col-12">
              <label for="clinic_name" class="form-label fw-medium text-dark">Clinic Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control rounded-3" name="clinic_name" id="clinic_name" placeholder="Your clinic's name" value="<?= htmlspecialchars($clinic['clinic_name'] ?? '') ?>" required>
              <div class="invalid-feedback">Please enter your clinic name.</div>
            </div>
            <div class="col-12">
              <label for="short_bio" class="form-label fw-medium text-dark">Short Bio</label>
              <textarea class="form-control rounded-3" name="short_bio" id="short_bio" rows="3" placeholder="Brief introduction or summary"><?= htmlspecialchars($clinic['short_bio'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label for="description" class="form-label fw-medium text-dark">Description</label>
              <textarea class="form-control rounded-3" name="description" id="description" rows="5" placeholder="Detailed clinic description"><?= htmlspecialchars($clinic['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label for="clinic_image" class="form-label fw-medium text-dark">Clinic Image</label>
              <input type="file" class="form-control rounded-3" name="clinic_image" id="clinic_image" accept="image/*" aria-describedby="clinicImageHelp">
              <div id="clinicImageHelp" class="form-text small">JPG, PNG, GIF. Max 2MB.</div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="remove_clinic_image" name="remove_clinic_image">
                <label class="form-check-label small" for="remove_clinic_image">Remove current image</label>
              </div>
            </div>
            <?php if ($displayClinicImg !== $defaultClinicImg): ?>
              <div class="col-12">
                <img src="<?= htmlspecialchars($displayClinicImg) ?>" alt="Current Clinic Image" class="rounded" width="120" height="80" style="object-fit: cover;">
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-3 px-4">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
</script>

<?php include 'footer.php'; ?>