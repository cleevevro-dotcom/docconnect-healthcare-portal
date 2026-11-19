<?php
session_start();
include '../../database/DBconnect.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo '<div class="container py-5"><div class="alert alert-danger text-center">Please log in as a patient.</div></div>';
    include 'footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];

// Get patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo '<div class="container py-5"><div class="alert alert-danger text-center">Patient record not found.</div></div>';
    include 'footer.php';
    exit;
}

$patient_id = $patient['patient_id'];

// Fetch Medical History
$stmt = $conn->prepare("SELECT history_id, condition_name, diagnosis_date, recovery_date FROM medical_history WHERE patient_id = ? ORDER BY diagnosis_date DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medicalHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Immunizations
$stmt = $conn->prepare("SELECT immunization_id, vaccine_name, date_administered, status FROM immunizations WHERE patient_id = ? ORDER BY date_administered DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$immunizations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Past Appointments
$stmt = $conn->prepare("SELECT appointment_id, appointment_datetime, reason FROM appointments WHERE patient_id = ? AND appointment_datetime < NOW() ORDER BY appointment_datetime DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$pastAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Lifestyle Recommendations (read-only)
$stmt = $conn->prepare("SELECT recommendation FROM lifestyle_recommendations WHERE patient_id = ? ORDER BY recommendation_id DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$lifestyleRecommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Doctor's Notes (read-only)
$stmt = $conn->prepare("SELECT dn.note_text, dn.created_at, u.last_name as doctor_last_name 
                        FROM doctor_notes dn
                        JOIN doctors d ON dn.doctor_id = d.doctor_id
                        JOIN users u ON d.user_id = u.user_id
                        WHERE dn.patient_id = ?
                        ORDER BY dn.created_at DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$doctorNotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Lab Results
$stmt = $conn->prepare("SELECT lab_result_id, test_name, result_summary, result_date, file_path, uploaded_at FROM lab_results WHERE patient_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$labResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
  /* Enhanced Base Styles */
  body {
    background: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  .container {
    max-width: 1400px;
    padding: 2rem 1rem;
  }

  /* Enhanced Page Title */
  .page-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e40af;
    margin-bottom: 2rem;
    text-align: center;
    position: relative;
    padding-bottom: 1rem;
  }

  .page-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
    border-radius: 2px;
  }

  /* Enhanced Navigation */
  .nav-pills .nav-link {
    font-weight: 600;
    font-size: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    color:rgb(34, 126, 255);
    background:rgb(52, 69, 143);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    opacity: 1;
  }

  .nav-pills .nav-link:hover {
    background:rgb(95, 169, 255);
    color:rgb(30, 63, 172);
    opacity: 1;
  }

  .nav-pills .nav-link.active {
    background: linear-gradient(135deg,rgb(43, 61, 223) 0%, #3b82f6 100%);
    color: #fff;
    border: none;
    opacity: 1;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.10);
  }

  .nav-pills .nav-link i {
    margin-right: 1rem;
    font-size: 1.25rem;
    color: inherit;
    transition: transform 0.3s ease;
  }

  .nav-pills .nav-link.active i {
    color: #fff;
  }

  /* Enhanced Content Card */
  .content-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
  }

  .content-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  }

  /* Search and Filter Section */
  .search-filter-section {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
  }

  .search-input {
    flex: 1;
    min-width: 200px;
    position: relative;
  }

  .search-input i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
  }

  .search-input input {
    padding-left: 2.5rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    width: 100%;
  }

  .filter-select {
    min-width: 150px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 0.5rem;
  }

  /* Enhanced Record Items */
  .record-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    position: relative;
  }

  .record-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }

  .record-item .actions {
    position: absolute;
    right: 1rem;
    top: 1rem;
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .record-item:hover .actions {
    opacity: 1;
  }

  /* Quick Action Menu */
  .quick-action-menu {
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    padding: 0.5rem;
    display: none;
    z-index: 100;
  }

  .quick-action-menu.show {
    display: block;
  }

  .quick-action-item {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #4b5563;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.2s ease;
  }

  .quick-action-item:hover {
    background: #f3f4f6;
    color: #1e40af;
  }

  /* Empty State */
  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
  }

  .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #9ca3af;
  }

  /* Print Button */
  .print-button {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: #1e40af;
    color: white;
    border-radius: 50%;
    width: 3.5rem;
    height: 3.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    z-index: 1000;
  }

  .print-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
  }

  /* Loading Animation */
  .loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    display: none;
  }

  .loading-spinner {
    width: 3rem;
    height: 3rem;
    border: 3px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  /* Sidebar Scrollbar */
  .nav-pills {
    max-height: 70vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #3b82f6 #f1f5f9;
  }
  .nav-pills::-webkit-scrollbar {
    width: 6px;
    background: #f1f5f9;
    border-radius: 8px;
  }
  .nav-pills::-webkit-scrollbar-thumb {
    background: #3b82f6;
    border-radius: 8px;
  }
  .nav-pills::-webkit-scrollbar-thumb:hover {
    background: #2563eb;
  }
</style>

<!-- Add Loading Overlay -->
<div class="loading-overlay">
  <div class="loading-spinner"></div>
</div>

<!-- Add Print Button -->
<button class="print-button" onclick="printRecords()">
  <i class="bi bi-printer-fill"></i>
</button>

<div class="container py-5">
  <h2 class="page-title">My Health Record</h2>
  <div class="row gx-4">
    <!-- Sidebar -->
    <nav class="col-md-3 mb-4">
      <div class="nav flex-column nav-pills" id="record-tabs" role="tablist" aria-orientation="vertical">
        <button class="nav-link active" id="medical-history-tab" data-bs-toggle="pill" data-bs-target="#medical-history" type="button" role="tab" aria-controls="medical-history" aria-selected="true">
          <i class="bi bi-journal-medical"></i> Medical History
        </button>
        <button class="nav-link" id="immunizations-tab" data-bs-toggle="pill" data-bs-target="#immunizations" type="button" role="tab" aria-controls="immunizations" aria-selected="false">
          <i class="bi bi-shield-check"></i> Immunizations
        </button>
        <button class="nav-link" id="appointments-tab" data-bs-toggle="pill" data-bs-target="#appointments" type="button" role="tab" aria-controls="appointments" aria-selected="false">
          <i class="bi bi-calendar-check"></i> Past Appointments
        </button>
        <button class="nav-link" id="lab-results-tab" data-bs-toggle="pill" data-bs-target="#lab-results" type="button" role="tab" aria-controls="lab-results" aria-selected="false">
          <i class="bi bi-file-earmark-medical"></i> Lab Results
        </button>
        <button class="nav-link" id="doctors-notes-tab" data-bs-toggle="pill" data-bs-target="#doctors-notes" type="button" role="tab" aria-controls="doctors-notes" aria-selected="false">
          <i class="bi bi-pencil-square"></i> Doctor's Notes
        </button>
        <button class="nav-link" id="lifestyle-tab" data-bs-toggle="pill" data-bs-target="#lifestyle" type="button" role="tab" aria-controls="lifestyle" aria-selected="false">
          <i class="bi bi-heart-pulse"></i> Lifestyle Recommendations
        </button>
      </div>
    </nav>

    <!-- Content -->
    <div class="col-md-9">
      <div class="tab-content" id="record-tabs-content">

        <!-- Medical History -->
        <div class="tab-pane fade show active content-card" id="medical-history" role="tabpanel" aria-labelledby="medical-history-tab">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary mb-0">Medical History</h4>
            <div class="search-filter-section">
              <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" placeholder="Search conditions..." onkeyup="filterRecords(this, 'medical-history-list')">
              </div>
              <select class="filter-select" onchange="sortRecords(this, 'medical-history-list')">
                <option value="date-desc">Date (Newest First)</option>
                <option value="date-asc">Date (Oldest First)</option>
                <option value="name-asc">Name (A-Z)</option>
                <option value="name-desc">Name (Z-A)</option>
              </select>
            </div>
          </div>
          <form action="Process/process_edit_medical_history.php" method="post" id="medical-history-form">
            <div id="medical-history-list">
              <?php if (empty($medicalHistory)): ?>
                <div class="medical-history-item border rounded p-3 mb-3">
                  <input type="hidden" name="history_id[]" value="0">
                  <div class="form-group">
                    <label>Condition Name <span class="text-danger">*</span></label>
                    <input type="text" name="condition_name[]" class="form-control" required>
                  </div>
                  <div class="form-group mt-3">
                    <label>Diagnosis Date</label>
                    <input type="date" name="diagnosis_date[]" class="form-control">
                  </div>
                  <div class="form-group mt-3">
                    <label>Recovery Date</label>
                    <input type="date" name="recovery_date[]" class="form-control">
                  </div>
                  <div class="text-end mt-3">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-history">
                      <i class="bi bi-trash"></i> Remove
                    </button>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($medicalHistory as $history): ?>
                  <div class="medical-history-item border rounded p-3 mb-3">
                    <input type="hidden" name="history_id[]" value="<?= $history['history_id'] ?>">
                    <div class="form-group">
                      <label>Condition Name <span class="text-danger">*</span></label>
                      <input type="text" name="condition_name[]" class="form-control" value="<?= htmlspecialchars($history['condition_name']) ?>" required>
                    </div>
                    <div class="form-group mt-3">
                      <label>Diagnosis Date</label>
                      <input type="date" name="diagnosis_date[]" class="form-control" value="<?= $history['diagnosis_date'] ?>">
                    </div>
                    <div class="form-group mt-3">
                      <label>Recovery Date</label>
                      <input type="date" name="recovery_date[]" class="form-control" value="<?= $history['recovery_date'] ?>">
                    </div>
                    <div class="text-end mt-3">
                      <button type="button" class="btn btn-outline-danger btn-sm remove-history">
                        <i class="bi bi-trash"></i> Remove
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-primary mb-3" id="add-medical-history">+ Add Condition</button><br>
            <button type="submit" class="btn btn-primary">Save Medical History</button>
          </form>
        </div>

        <!-- Immunizations -->
        <div class="tab-pane fade content-card" id="immunizations" role="tabpanel" aria-labelledby="immunizations-tab">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary mb-0">Immunizations</h4>
            <div class="search-filter-section">
              <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" placeholder="Search vaccines..." onkeyup="filterRecords(this, 'immunizations-list')">
              </div>
              <select class="filter-select" onchange="sortRecords(this, 'immunizations-list')">
                <option value="date-desc">Date (Newest First)</option>
                <option value="date-asc">Date (Oldest First)</option>
                <option value="name-asc">Vaccine (A-Z)</option>
                <option value="name-desc">Vaccine (Z-A)</option>
                <option value="status">Status</option>
              </select>
            </div>
          </div>
          <form action="Process/process_edit_immunization.php" method="post" id="immunizations-form">
            <div id="immunizations-list">
              <?php if (empty($immunizations)): ?>
                <div class="empty-state">
                  <i class="bi bi-shield-check"></i>
                  <h5 class="mt-3">No Immunizations Recorded</h5>
                  <p class="text-muted">Add your immunization records to keep track of your vaccinations.</p>
                </div>
              <?php else: ?>
                <?php foreach ($immunizations as $imm): ?>
                  <div class="record-item immunization-item">
                    <input type="hidden" name="immunization_id[]" value="<?= $imm['immunization_id'] ?>">
                    <div class="row">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label class="form-label">Vaccine Name <span class="text-danger">*</span></label>
                          <input type="text" name="vaccine_name[]" class="form-control" value="<?= htmlspecialchars($imm['vaccine_name']) ?>" required>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="form-group">
                          <label class="form-label">Date Administered</label>
                          <input type="date" name="date_administered[]" class="form-control" value="<?= $imm['date_administered'] ?>">
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="form-group">
                          <label class="form-label">Status</label>
                          <select name="status[]" class="form-control">
                            <option value="Completed" <?= $imm['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Pending" <?= $imm['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Scheduled" <?= $imm['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                            <option value="Not Required" <?= $imm['status'] === 'Not Required' ? 'selected' : '' ?>>Not Required</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="actions">
                          <button type="button" class="btn btn-outline-danger btn-sm remove-immunization" data-bs-toggle="tooltip" title="Remove">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="d-flex gap-2 mt-4">
              <button type="button" class="btn btn-outline-primary" id="add-immunization">
                <i class="bi bi-plus-circle me-2"></i>Add Immunization
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i>Save Changes
              </button>
            </div>
          </form>
        </div>

        <!-- Past Appointments -->
        <div class="tab-pane fade content-card" id="appointments" role="tabpanel" aria-labelledby="appointments-tab">
          <h4 class="mb-4 fw-bold text-primary">Past Appointments</h4>
          <form action="Process/process_edit_appointments.php" method="post" id="appointments-form">
            <div id="appointments-list">
              <?php if (empty($pastAppointments)): ?>
                <div class="appointment-item border rounded p-3 mb-3">
                  <input type="hidden" name="appointment_id[]" value="0">
                  <div class="form-group">
                    <label>Appointment Date/Time</label>
                    <input type="datetime-local" name="appointment_datetime[]" class="form-control" required>
                  </div>
                  <div class="form-group mt-3">
                    <label>Reason</label>
                    <input type="text" name="reason[]" class="form-control" required>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($pastAppointments as $appt): ?>
                  <div class="appointment-item border rounded p-3 mb-3">
                    <input type="hidden" name="appointment_id[]" value="<?= $appt['appointment_id'] ?>">
                    <div class="form-group">
                      <label>Appointment Date/Time</label>
                      <input type="datetime-local" name="appointment_datetime[]" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($appt['appointment_datetime'])) ?>" required>
                    </div>
                    <div class="form-group mt-3">
                      <label>Reason</label>
                      <input type="text" name="reason[]" class="form-control" value="<?= htmlspecialchars($appt['reason']) ?>" required>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-primary mb-3" id="add-appointment">+ Add Appointment</button><br>
            <button type="submit" class="btn btn-primary">Save Appointments</button>
          </form>
        </div>

        <!-- Lab Results -->
        <div class="tab-pane fade content-card" id="lab-results" role="tabpanel" aria-labelledby="lab-results-tab">
          <h4 class="mb-4 fw-bold text-primary">Lab Results</h4>
          <form action="Process/uploads_lab_result.php" method="post" enctype="multipart/form-data" class="mb-4" id="lab-result-upload-form">
            <div class="form-group mb-3">
              <label for="test_name" class="form-label">Test Name <span class="text-danger">*</span></label>
              <input type="text" name="test_name" id="test_name" class="form-control" required>
            </div>
            <div class="form-group mb-3">
              <label for="result_summary" class="form-label">Result Summary</label>
              <textarea name="result_summary" id="result_summary" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group mb-3">
              <label for="result_date" class="form-label">Result Date <span class="text-danger">*</span></label>
              <input type="date" name="result_date" id="result_date" class="form-control" required>
            </div>
            <div class="form-group mb-4">
              <label for="file_upload" class="form-label">Upload Report (JPG, PNG, PDF) <span class="text-danger">*</span></label>
              <input type="file" name="file_upload" id="file_upload" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>
            <button type="submit" class="btn btn-success">Upload Lab Result</button>
          </form>

          <?php if (empty($labResults)): ?>
            <p class="text-muted">No lab results found.</p>
          <?php else: ?>
            <?php foreach ($labResults as $lab): ?>
              <div class="lab-result-card d-flex justify-content-between align-items-center">
                <div>
                  <h5 class="mb-1"><?= htmlspecialchars($lab['test_name']) ?></h5>
                  <p class="mb-1"><?= nl2br(htmlspecialchars($lab['result_summary'])) ?></p>
                  <small class="text-muted">
                    Result Date: <?= date('F j, Y', strtotime($lab['result_date'])) ?> | Uploaded: <?= date('F j, Y, g:i A', strtotime($lab['uploaded_at'])) ?>
                  </small>
                </div>
                <div class="lab-result-actions d-flex align-items-center">
                  <?php if (!empty($lab['file_path'])): ?>
                    <a href="Process/download_lab_result.php?id=<?= (int)$lab['lab_result_id'] ?>" class="btn btn-outline-success btn-sm me-2" title="Download Report">
                      <i class="bi bi-download"></i> Download
                    </a>
                    <a href="<?= htmlspecialchars($lab['file_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm me-2" title="View Report">
                      <i class="bi bi-eye"></i> View
                    </a>
                  <?php endif; ?>

                  <!-- Remove/Delete form -->
                  <form action="Process/delete_lab_result.php" method="post" onsubmit="return confirm('Are you sure you want to delete this lab result?');" style="display:inline;">
                    <input type="hidden" name="lab_result_id" value="<?= (int)$lab['lab_result_id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove Report">
                      <i class="bi bi-trash"></i> Remove
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

          <?php endif; ?>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="viewLabModal" tabindex="-1" aria-labelledby="viewLabModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:90vw;">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="viewLabModalLabel">Lab Report Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body" style="height:80vh;">
                <iframe id="labReportFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
              </div>
            </div>
          </div>
        </div>

        <!-- Doctor's Notes -->
        <div class="tab-pane fade content-card" id="doctors-notes" role="tabpanel" aria-labelledby="doctors-notes-tab">
          <h4 class="mb-4 fw-bold text-primary">Doctor's Notes</h4>
          <?php if (empty($doctorNotes)): ?>
            <div class="text-center py-5">
              <i class="bi bi-journal-text text-muted" style="font-size: 3rem;"></i>
              <p class="text-muted mt-3">No doctor's notes found.</p>
            </div>
          <?php else: ?>
            <?php foreach ($doctorNotes as $note): ?>
              <div class="doctor-note">
                <p class="mb-3"><?= nl2br(htmlspecialchars($note['note_text'])) ?></p>
                <small class="text-muted d-flex align-items-center">
                  <i class="bi bi-person-circle me-2"></i>
                  Dr. <?= htmlspecialchars($note['doctor_last_name']) ?> 
                  <span class="mx-2">•</span>
                  <i class="bi bi-calendar3 me-2"></i>
                  <?= date('F j, Y', strtotime($note['created_at'])) ?>
                </small>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Lifestyle Recommendations -->
        <div class="tab-pane fade content-card" id="lifestyle" role="tabpanel" aria-labelledby="lifestyle-tab">
          <h4 class="mb-4 fw-bold text-primary">Lifestyle Recommendations</h4>
          <?php if (empty($lifestyleRecommendations)): ?>
            <div class="text-center py-5">
              <i class="bi bi-heart-pulse text-muted" style="font-size: 3rem;"></i>
              <p class="text-muted mt-3">No lifestyle recommendations found.</p>
            </div>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($lifestyleRecommendations as $rec): ?>
                <li class="list-group-item d-flex align-items-center">
                  <i class="bi bi-check-circle-fill text-success me-3"></i>
                  <?= htmlspecialchars($rec['recommendation']) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
   var viewLabModal = document.getElementById('viewLabModal');
  var labReportFrame = document.getElementById('labReportFrame');

  viewLabModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var fileUrl = button.getAttribute('data-file');
    labReportFrame.src = fileUrl;
  });

  viewLabModal.addEventListener('hidden.bs.modal', function () {
    labReportFrame.src = ''; // Clear iframe when modal closes to stop loading
  });
  // Add new Medical History entry
  document.getElementById('add-medical-history').addEventListener('click', function() {
    const container = document.getElementById('medical-history-list');
    const newItem = document.createElement('div');
    newItem.classList.add('medical-history-item', 'border', 'rounded', 'p-3', 'mb-3');
    newItem.innerHTML = `
      <input type="hidden" name="history_id[]" value="0">
      <div class="form-group">
        <label>Condition Name <span class="text-danger">*</span></label>
        <input type="text" name="condition_name[]" class="form-control" required>
      </div>
      <div class="form-group mt-3">
        <label>Diagnosis Date</label>
        <input type="date" name="diagnosis_date[]" class="form-control">
      </div>
      <div class="form-group mt-3">
        <label>Recovery Date</label>
        <input type="date" name="recovery_date[]" class="form-control">
      </div>
      <div class="text-end mt-3">
        <button type="button" class="btn btn-outline-danger btn-sm remove-history">
          <i class="bi bi-trash"></i> Remove
        </button>
      </div>`;
    container.appendChild(newItem);
  });

  // Remove Medical History entry
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-history') || e.target.closest('.remove-history')) {
      const button = e.target.classList.contains('remove-history') ? e.target : e.target.closest('.remove-history');
      const historyItem = button.closest('.medical-history-item');
      const historyId = historyItem.querySelector('input[name="history_id[]"]').value;

      if (confirm('Are you sure you want to remove this medical history entry?')) {
        if (historyId !== '0') {
          // If it's an existing entry, send delete request
          fetch('Process/delete_medical_history.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ history_id: historyId })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              historyItem.remove();
            } else {
              alert('Failed to delete medical history entry. Please try again.');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the entry. Please try again.');
          });
        } else {
          // If it's a new entry, just remove from DOM
          historyItem.remove();
        }
      }
    }
  });

  // Add new Immunization entry
  document.getElementById('add-immunization').addEventListener('click', function() {
    const container = document.getElementById('immunizations-list');
    const newItem = document.createElement('div');
    newItem.classList.add('record-item', 'immunization-item');
    newItem.innerHTML = `
      <input type="hidden" name="immunization_id[]" value="0">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label">Vaccine Name <span class="text-danger">*</span></label>
            <input type="text" name="vaccine_name[]" class="form-control" required>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label class="form-label">Date Administered</label>
            <input type="date" name="date_administered[]" class="form-control">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status[]" class="form-control">
              <option value="Completed">Completed</option>
              <option value="Pending">Pending</option>
              <option value="Scheduled">Scheduled</option>
              <option value="Not Required">Not Required</option>
            </select>
          </div>
        </div>
        <div class="col-md-2">
          <div class="actions">
            <button type="button" class="btn btn-outline-danger btn-sm remove-immunization" data-bs-toggle="tooltip" title="Remove">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
      </div>`;
    container.appendChild(newItem);
    
    // Initialize tooltip for the new button
    new bootstrap.Tooltip(newItem.querySelector('[data-bs-toggle="tooltip"]'));
  });

  // Remove Immunization entry
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-immunization') || e.target.closest('.remove-immunization')) {
      const button = e.target.classList.contains('remove-immunization') ? e.target : e.target.closest('.remove-immunization');
      const immunizationItem = button.closest('.immunization-item');
      const immunizationId = immunizationItem.querySelector('input[name="immunization_id[]"]').value;

      if (confirm('Are you sure you want to remove this immunization record?')) {
        if (immunizationId !== '0') {
          // If it's an existing entry, send delete request
          fetch('Process/delete_immunization.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ immunization_id: immunizationId })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              immunizationItem.remove();
            } else {
              alert('Failed to delete immunization record. Please try again.');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the record. Please try again.');
          });
        } else {
          // If it's a new entry, just remove from DOM
          immunizationItem.remove();
        }
      }
    }
  });

  // Add new Appointment entry
  document.getElementById('add-appointment').addEventListener('click', function() {
    const container = document.getElementById('appointments-list');
    const newItem = document.createElement('div');
    newItem.classList.add('appointment-item', 'border', 'rounded', 'p-3', 'mb-3');
    newItem.innerHTML = `
      <input type="hidden" name="appointment_id[]" value="0">
      <div class="form-group">
        <label>Appointment Date/Time</label>
        <input type="datetime-local" name="appointment_datetime[]" class="form-control">
      </div>
      <div class="form-group mt-3">
        <label>Reason</label>
        <input type="text" name="reason[]" class="form-control">
      </div>`;
    container.appendChild(newItem);
  });

  function filterRecords(input, containerId) {
    const searchText = input.value.toLowerCase();
    const container = document.getElementById(containerId);
    const items = container.getElementsByClassName('record-item');

    Array.from(items).forEach(item => {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(searchText) ? '' : 'none';
    });
  }

  function sortRecords(select, containerId) {
    const container = document.getElementById(containerId);
    const items = Array.from(container.getElementsByClassName('record-item'));
    const value = select.value;

    items.sort((a, b) => {
      if (value.includes('date')) {
        const dateA = new Date(a.querySelector('input[type="date"]').value);
        const dateB = new Date(b.querySelector('input[type="date"]').value);
        return value === 'date-desc' ? dateB - dateA : dateA - dateB;
      } else if (value === 'status') {
        const statusA = a.querySelector('select[name="status[]"]').value;
        const statusB = b.querySelector('select[name="status[]"]').value;
        return statusA.localeCompare(statusB);
      } else {
        const nameA = a.querySelector('input[type="text"]').value.toLowerCase();
        const nameB = b.querySelector('input[type="text"]').value.toLowerCase();
        return value === 'name-desc' ? nameB.localeCompare(nameA) : nameA.localeCompare(nameB);
      }
    });

    items.forEach(item => container.appendChild(item));
  }

  function printRecords() {
    const activeTab = document.querySelector('.tab-pane.active');
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
      <html>
        <head>
          <title>Print Records</title>
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
          <style>
            body { padding: 2rem; }
            .print-header { text-align: center; margin-bottom: 2rem; }
            .print-content { margin-top: 2rem; }
          </style>
        </head>
        <body>
          <div class="print-header">
            <h2>${activeTab.querySelector('h4').textContent}</h2>
            <p>Printed on ${new Date().toLocaleDateString()}</p>
          </div>
          <div class="print-content">
            ${activeTab.innerHTML}
          </div>
        </body>
      </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
  }

  // Show loading overlay during form submissions
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      document.querySelector('.loading-overlay').style.display = 'flex';
    });
  });

  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
</script>

<?php include 'footer.php'; ?>
