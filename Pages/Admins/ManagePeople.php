<?php 
session_start();
include 'header.php'; 
include '../../database/DBconnect.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Fetch Doctors with user info
$sqlDoctors = "SELECT d.doctor_id, d.user_id, u.first_name, u.last_name, u.email, u.gender, d.specialty, d.years_experience, d.clinic_address, d.profile_picture, u.role
               FROM doctors d
               JOIN users u ON d.user_id = u.user_id
               WHERE u.role = 'doctor'
               ORDER BY u.last_name, u.first_name";
$resultDoctors = $conn->query($sqlDoctors);

// Fetch Patients
$sqlPatients = "SELECT p.patient_id, p.user_id, u.first_name, u.last_name, u.gender, u.email, p.date_of_birth, p.blood_type, p.height_cm, p.weight_kg, p.bmi, p.profile_picture
                FROM patients p
                JOIN users u ON p.user_id = u.user_id
                WHERE u.role = 'patient'
                ORDER BY u.last_name, u.first_name";
$resultPatients = $conn->query($sqlPatients);

// Helper function to calculate age
function calculateAge($dob) {
    $dobDate = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($dobDate)->y;
}
?>

<div class="container py-5">
  <h2 class="mb-4">Manage People</h2>

  <!-- Success and Error Messages -->
  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo $_GET['success'] === 'add_doctor' ? 'New doctor added successfully!' : 'New patient added successfully!'; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['edit_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Information updated successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php
        $errors = [
            'invalid_input' => 'Please fill all required fields correctly.',
            'database_error' => 'A database error occurred: ' . htmlspecialchars($_GET['error_message'] ?? 'Please try again.'),
            'email_exists' => 'Email already exists.',
            'file_upload_error' => 'Failed to upload profile picture.'
        ];
        echo $errors[$_GET['error']] ?? 'An error occurred.';
      ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Tabs Navigation -->
  <ul class="nav nav-tabs mb-4" id="peopleTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="doctors-tab" data-bs-toggle="tab" data-bs-target="#doctors" type="button" role="tab" aria-controls="doctors" aria-selected="true">
        Doctors
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="patients-tab" data-bs-toggle="tab" data-bs-target="#patients" type="button" role="tab" aria-controls="patients" aria-selected="false">
        Patients
      </button>
    </li>
  </ul>

  <div class="tab-content" id="peopleTabContent">
    <!-- Doctors Tab -->
    <div class="tab-pane fade show active" id="doctors" role="tabpanel" aria-labelledby="doctors-tab">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Doctors</h4>
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
          <i class="bi bi-plus-lg"></i> Add New Doctor
        </button>
      </div>
      <div class="card shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th scope="col" class="ps-4" style="width: 25%;">Name</th>
                <th scope="col" class="text-center" style="width: 25%;">Email</th>
                <th scope="col" class="text-center" style="width: 20%;">Specialty</th>
                <th scope="col" class="text-center" style="width: 15%;">Experience (Years)</th>
                <th scope="col" class="text-center pe-4" style="width: 15%;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultDoctors && $resultDoctors->num_rows > 0): ?>
                <?php while($doctor = $resultDoctors->fetch_assoc()): ?>
                  <tr data-doctor-id="<?= $doctor['doctor_id'] ?>">
                    <td class="ps-4 fw-semibold">
                      Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>
                    </td>
                    <td class="text-center"><?= htmlspecialchars($doctor['email']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($doctor['specialty']) ?></td>
                    <td class="text-center"><?= (int)$doctor['years_experience'] ?></td>
                    <td class="text-center pe-4">
                      <a href="#" 
                         class="btn btn-sm btn-warning me-2 editDoctorBtn" 
                         data-bs-toggle="modal" 
                         data-bs-target="#editDoctorModal"
                         data-doctor-id="<?= $doctor['doctor_id'] ?>"
                         data-user-id="<?= $doctor['user_id'] ?>"
                         data-first-name="<?= htmlspecialchars($doctor['first_name']) ?>"
                         data-last-name="<?= htmlspecialchars($doctor['last_name']) ?>"
                         data-email="<?= htmlspecialchars($doctor['email']) ?>"
                         data-gender="<?= htmlspecialchars($doctor['gender'] ?? '') ?>"
                         data-specialty="<?= htmlspecialchars($doctor['specialty']) ?>"
                         data-years-experience="<?= (int)$doctor['years_experience'] ?>"
                         data-clinic-address="<?= htmlspecialchars($doctor['clinic_address'] ?? '') ?>"
                         data-profile-picture="<?= htmlspecialchars($doctor['profile_picture'] ?? '') ?>"
                         aria-label="Edit Dr. <?= htmlspecialchars($doctor['last_name']) ?>" 
                         title="Edit">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <button class="btn btn-sm btn-danger" aria-label="Delete Dr. <?= htmlspecialchars($doctor['last_name']) ?>" title="Delete" onclick="confirmDelete('doctor', <?= $doctor['doctor_id'] ?>)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center py-4">No doctors found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Patients Tab -->
    <div class="tab-pane fade" id="patients" role="tabpanel" aria-labelledby="patients-tab">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Patients</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
          <i class="bi bi-plus-lg"></i> Add New Patient
        </button>
      </div>
      <div class="card shadow-sm rounded-3">
        <div class="card-body p-0 table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th scope="col" class="ps-4" style="width: 30%;">Patient Name</th>
                <th scope="col" class="text-center" style="width: 10%;">Age</th>
                <th scope="col" class="text-center" style="width: 15%;">Gender</th>
                <th scope="col" class="text-center" style="width: 25%;">Email</th>
                <th scope="col" class="text-center pe-4" style="width: 20%;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultPatients && $resultPatients->num_rows > 0): ?>
                <?php while($patient = $resultPatients->fetch_assoc()): ?>
                  <tr data-patient-id="<?= $patient['patient_id'] ?>">
                    <td class="ps-4 fw-semibold">
                      <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                    </td>
                    <td class="text-center"><?= calculateAge($patient['date_of_birth']) ?></td>
                    <td class="text-center text-capitalize"><?= htmlspecialchars($patient['gender']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($patient['email']) ?></td>
                    <td class="text-center pe-4">
                      <a href="#" 
                          class="btn btn-sm btn-warning me-2 editPatientBtn" 
                          data-bs-toggle="modal" 
                          data-bs-target="#editPatientModal"
                          data-patient-id="<?= $patient['patient_id'] ?>"
                          data-user-id="<?= $patient['user_id'] ?>"
                          data-first-name="<?= htmlspecialchars($patient['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($patient['last_name']) ?>"
                          data-email="<?= htmlspecialchars($patient['email']) ?>"
                          data-gender="<?= htmlspecialchars($patient['gender']) ?>"
                          data-date-of-birth="<?= htmlspecialchars($patient['date_of_birth']) ?>"
                          data-blood-type="<?= htmlspecialchars($patient['blood_type'] ?? '') ?>"
                          data-height-cm="<?= htmlspecialchars($patient['height_cm'] ?? '') ?>"
                          data-weight-kg="<?= htmlspecialchars($patient['weight_kg'] ?? '') ?>"
                          data-bmi="<?= htmlspecialchars($patient['bmi'] ?? '') ?>"
                          data-profile-picture="<?= htmlspecialchars($patient['profile_picture'] ?? '') ?>"
                          title="Edit Patient">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      <button class="btn btn-sm btn-danger ms-2" title="Delete Patient" onclick="confirmDelete('patient', <?= $patient['patient_id'] ?>)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center py-4">No patients found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add New Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1" aria-labelledby="addDoctorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="addDoctorForm" action="Process/DoctorAddProcess.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="addDoctorModalLabel">Add New Doctor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="first_name" name="first_name" required>
            </div>
            <div class="col-md-6">
              <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="last_name" name="last_name" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-md-6">
              <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="password" name="password" required minlength="6">
            </div>
            <div class="col-md-6">
              <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="gender" name="gender" required>
                <option value="" selected disabled>Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="specialty" class="form-label">Specialty <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="specialty" name="specialty" required>
            </div>
            <div class="col-md-6">
              <label for="years_experience" class="form-label">Years of Experience <span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="years_experience" name="years_experience" min="0" required>
            </div>
            <div class="col-md-6">
              <label for="clinic_address" class="form-label">Clinic Address</label>
              <textarea class="form-control" id="clinic_address" name="clinic_address" rows="2"></textarea>
            </div>
            <div class="col-md-12">
              <label for="profile_picture" class="form-label">Profile Picture</label>
              <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Doctor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Doctor Modal -->
<div class="modal fade" id="editDoctorModal" tabindex="-1" aria-labelledby="editDoctorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="editDoctorForm" action="Process/DoctorEditProcess.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="doctor_id" id="edit_doctor_id">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editDoctorModalLabel">Edit Doctor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
            </div>
            <div class="col-md-6">
              <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
            </div>
            <div class="col-md-6">
              <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="edit_email" name="email" required>
            </div>
            <div class="col-md-6">
              <label for="edit_password" class="form-label">Password <small class="text-muted">(Leave blank to keep current)</small></label>
              <input type="password" class="form-control" id="edit_password" name="password" minlength="6" placeholder="Enter new password if you want to change">
            </div>
            <div class="col-md-6">
              <label for="edit_gender" class="form-label">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="edit_gender" name="gender" required>
                <option value="" disabled>Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="edit_specialty" class="form-label">Specialty <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_specialty" name="specialty" required>
            </div>
            <div class="col-md-6">
              <label for="edit_years_experience" class="form-label">Years of Experience <span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="edit_years_experience" name="years_experience" min="0" required>
            </div>
            <div class="col-md-6">
              <label for="edit_clinic_address" class="form-label">Clinic Address</label>
              <textarea class="form-control" id="edit_clinic_address" name="clinic_address" rows="2"></textarea>
            </div>
            <div class="col-md-12">
              <label for="edit_profile_picture" class="form-label">Profile Picture <small class="text-muted">(Upload to replace current)</small></label>
              <input class="form-control" type="file" id="edit_profile_picture" name="profile_picture" accept="image/*">
              <div id="currentProfilePicture" class="mt-2"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add New Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="addPatientForm" action="./Process/PatientAddProcess.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="addPatientModalLabel">Add New Patient</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" name="first_name" id="first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="last_name" id="last_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" name="password" id="password" class="form-control" required minlength="6">
            </div>
            <div class="col-md-4">
              <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
              <select name="gender" id="gender" class="form-select" required>
                <option value="" selected disabled>Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label for="blood_type" class="form-label">Blood Type</label>
              <input type="text" name="blood_type" id="blood_type" class="form-control" placeholder="e.g. A+, O-">
            </div>
            <div class="col-md-6">
              <label for="height_cm" class="form-label">Height (cm)</label>
              <input type="number" name="height_cm" id="height_cm" class="form-control" min="0" step="0.1">
            </div>
            <div class="col-md-6">
              <label for="weight_kg" class="form-label">Weight (kg)</label>
              <input type="number" name="weight_kg" id="weight_kg" class="form-control" min="0" step="0.1">
            </div>
            <div class="col-md-12">
              <label for="profile_picture" class="form-label">Profile Picture</label>
              <input type="file" name="profile_picture" id="profile_picture" class="form-control" accept="image/*">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Patient</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Patient Modal -->
<div class="modal fade" id="editPatientModal" tabindex="-1" aria-labelledby="editPatientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="editPatientForm" action="Process/PatientEditProcess.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="patient_id" id="edit_patient_id">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editPatientModalLabel">Edit Patient</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
              <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="edit_password" class="form-label">Password <small class="text-muted">(Leave blank to keep current)</small></label>
              <input type="password" name="password" id="edit_password" class="form-control" minlength="6" placeholder="Enter new password if you want to change">
            </div>
            <div class="col-md-4">
              <label for="edit_gender" class="form-label">Gender <span class="text-danger">*</span></label>
              <select name="gender" id="edit_gender" class="form-select" required>
                <option value="" disabled>Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="edit_date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label for="edit_blood_type" class="form-label">Blood Type</label>
              <input type="text" name="blood_type" id="edit_blood_type" class="form-control" placeholder="e.g. A+, O-">
            </div>
            <div class="col-md-6">
              <label for="edit_height_cm" class="form-label">Height (cm)</label>
              <input type="number" name="height_cm" id="edit_height_cm" class="form-control" min="0" step="0.1">
            </div>
            <div class="col-md-6">
              <label for="edit_weight_kg" class="form-label">Weight (kg)</label>
              <input type="number" name="weight_kg" id="edit_weight_kg" class="form-control" min="0" step="0.1">
            </div>
            <div class="col-md-12">
              <label for="edit_profile_picture" class="form-label">Profile Picture <small class="text-muted">(Upload to replace current)</small></label>
              <input type="file" name="profile_picture" id="edit_profile_picture" class="form-control" accept="image/*">
              <div id="currentPatientProfilePicture" class="mt-2"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Confirm delete function
function confirmDelete(type, id) {
  if (confirm(`Are you sure you want to delete this ${type}?`)) {
    fetch(`Process/Delete.php?type=${type}&id=${id}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove the row from the table
          const row = document.querySelector(`tr[data-${type}-id="${id}"]`);
          if (row) {
            row.remove();
          }
          // Show success message
          const alertDiv = document.createElement('div');
          alertDiv.className = 'alert alert-success alert-dismissible fade show';
          alertDiv.innerHTML = `
            ${data.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          `;
          document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
        } else {
          // Show error message
          const alertDiv = document.createElement('div');
          alertDiv.className = 'alert alert-danger alert-dismissible fade show';
          alertDiv.innerHTML = `
            ${data.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          `;
          document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        // Show error message
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
          An error occurred while deleting. Please try again.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
      });
  }
}

// Populate Edit Doctor Modal
document.addEventListener('DOMContentLoaded', function() {
  const editDoctorButtons = document.querySelectorAll('.editDoctorBtn');
  const editDoctorModal = document.getElementById('editDoctorModal');

  editDoctorButtons.forEach(button => {
    button.addEventListener('click', function() {
      const doctorId = this.getAttribute('data-doctor-id');
      const userId = this.getAttribute('data-user-id');
      const firstName = this.getAttribute('data-first-name');
      const lastName = this.getAttribute('data-last-name');
      const email = this.getAttribute('data-email');
      const gender = this.getAttribute('data-gender');
      const specialty = this.getAttribute('data-specialty');
      const yearsExperience = this.getAttribute('data-years-experience');
      const clinicAddress = this.getAttribute('data-clinic-address');
      const profilePicture = this.getAttribute('data-profile-picture');

      editDoctorModal.querySelector('#edit_doctor_id').value = doctorId;
      editDoctorModal.querySelector('#edit_user_id').value = userId;
      editDoctorModal.querySelector('#edit_first_name').value = firstName;
      editDoctorModal.querySelector('#edit_last_name').value = lastName;
      editDoctorModal.querySelector('#edit_email').value = email;
      editDoctorModal.querySelector('#edit_gender').value = gender;
      editDoctorModal.querySelector('#edit_specialty').value = specialty;
      editDoctorModal.querySelector('#edit_years_experience').value = yearsExperience;
      editDoctorModal.querySelector('#edit_clinic_address').value = clinicAddress;
      editDoctorModal.querySelector('#edit_password').value = '';

      const profileDiv = editDoctorModal.querySelector('#currentProfilePicture');
      if (profilePicture) {
        profileDiv.innerHTML = `<img src="../${profilePicture}" alt="Current Profile Picture" class="img-thumbnail" style="max-height: 100px;">`;
      } else {
        profileDiv.innerHTML = '<small class="text-muted">No profile picture uploaded.</small>';
      }
    });
  });
});

// Populate Edit Patient Modal
document.addEventListener('DOMContentLoaded', function() {
  const editPatientButtons = document.querySelectorAll('.editPatientBtn');
  const editPatientModal = document.getElementById('editPatientModal');

  editPatientButtons.forEach(button => {
    button.addEventListener('click', function() {
      const patientId = this.getAttribute('data-patient-id');
      const userId = this.getAttribute('data-user-id');
      const firstName = this.getAttribute('data-first-name');
      const lastName = this.getAttribute('data-last-name');
      const email = this.getAttribute('data-email');
      const gender = this.getAttribute('data-gender');
      const dob = this.getAttribute('data-date-of-birth');
      const bloodType = this.getAttribute('data-blood-type');
      const height = this.getAttribute('data-height-cm');
      const weight = this.getAttribute('data-weight-kg');
      const bmi = this.getAttribute('data-bmi');
      const profilePicture = this.getAttribute('data-profile-picture');

      editPatientModal.querySelector('#edit_patient_id').value = patientId;
      editPatientModal.querySelector('#edit_user_id').value = userId;
      editPatientModal.querySelector('#edit_first_name').value = firstName;
      editPatientModal.querySelector('#edit_last_name').value = lastName;
      editPatientModal.querySelector('#edit_email').value = email;
      editPatientModal.querySelector('#edit_gender').value = gender;
      editPatientModal.querySelector('#edit_date_of_birth').value = dob;
      editPatientModal.querySelector('#edit_blood_type').value = bloodType;
      editPatientModal.querySelector('#edit_height_cm').value = height;
      editPatientModal.querySelector('#edit_weight_kg').value = weight;
      editPatientModal.querySelector('#edit_password').value = '';

      const profileDiv = editPatientModal.querySelector('#currentPatientProfilePicture');
      if (profilePicture) {
        profileDiv.innerHTML = `<img src="../${profilePicture}" alt="Profile Picture" class="img-thumbnail" style="max-height: 100px;">`;
      } else {
        profileDiv.innerHTML = '<small class="text-muted">No profile picture uploaded.</small>';
      }
    });
  });
});
</script>

<?php include 'footer.php'; ?>