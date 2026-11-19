<?php
session_start();
include 'header.php';
include '../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo '<div class="container py-5"><div class="alert alert-danger text-center">Please log in as a patient to view this page.</div></div>';
    include 'footer.php';
    exit;
}

$patient_id = $_SESSION['user_id'];

// Fetch patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo '<div class="container py-5"><div class="alert alert-danger text-center">No patient record found for this account.</div></div>';
    include 'footer.php';
    exit;
}
$patient = $result->fetch_assoc();
$patient_id = $patient['patient_id'];

// Fetch doctors
$doctorsSql = "SELECT d.doctor_id, u.user_id, u.first_name, u.last_name, d.specialty, d.clinic_address, u.email 
               FROM doctors d 
               JOIN users u ON d.user_id = u.user_id 
               ORDER BY u.last_name ASC";
$doctorsResult = $conn->query($doctorsSql);
$doctors = [];
if ($doctorsResult) {
    while ($row = $doctorsResult->fetch_assoc()) {
        $doctors[] = $row;
    }
}

// Fetch pending appointments
$pendingSql = "SELECT a.appointment_id, a.appointment_datetime, a.status, a.reason,
               u.first_name, u.last_name
               FROM appointments a
               JOIN doctors d ON a.doctor_id = d.doctor_id
               JOIN users u ON d.user_id = u.user_id
               WHERE a.patient_id = ?
               AND a.status = 'pending'
               AND a.appointment_datetime >= NOW()
               ORDER BY a.appointment_datetime ASC";
$pendingStmt = $conn->prepare($pendingSql);
$pendingStmt->bind_param("i", $patient_id);
if (!$pendingStmt->execute()) {
    error_log('Pending appointments query failed: ' . $pendingStmt->error);
    echo '<div class="alert alert-danger">Error fetching pending appointments.</div>';
}
$pendingResult = $pendingStmt->get_result();
$pendingAppointments = $pendingResult->fetch_all(MYSQLI_ASSOC);

// Fetch confirmed appointments
$upcomingSql = "SELECT a.appointment_id, a.appointment_datetime, a.status, a.reason,
                u.first_name, u.last_name
               FROM appointments a
               JOIN doctors d ON a.doctor_id = d.doctor_id
               JOIN users u ON d.user_id = u.user_id
               WHERE a.patient_id = ?
                 AND a.status = 'confirmed'
                 AND a.appointment_datetime >= NOW()
               ORDER BY a.appointment_datetime ASC";
$upcomingStmt = $conn->prepare($upcomingSql);
$upcomingStmt->bind_param("i", $patient_id);
if (!$upcomingStmt->execute()) {
    error_log('Upcoming appointments query failed: ' . $upcomingStmt->error);
    echo '<div class="alert alert-danger">Error fetching upcoming appointments.</div>';
}
$upcomingResult = $upcomingStmt->get_result();
$upcomingAppointments = $upcomingResult->fetch_all(MYSQLI_ASSOC);
?>
<style>
  body {
    background: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

  /* Enhanced Card Styling */
  .card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
  }

  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  }

  .card-header {
    border-radius: 20px 20px 0 0;
    font-weight: 700;
    letter-spacing: 0.5px;
    font-size: 1.1rem;
    padding: 1.25rem;
    border: none;
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    color: white;
  }

  /* Enhanced Search Input */
  .search-input-wrapper {
    position: relative;
    max-width: 500px;
    margin: 0 auto 2rem;
  }

  .search-input {
    width: 100%;
    padding: 1rem 1.5rem 1rem 3.5rem;
    border-radius: 15px;
    border: 2px solid #e2e8f0;
    font-size: 1.1rem;
    outline: none;
    transition: all 0.3s ease;
    background: #f8fafc;
  }

  .search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    background: #ffffff;
  }

  .search-icon {
    position: absolute;
    left: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.2rem;
    transition: color 0.3s ease;
  }

  /* Enhanced Doctor Cards */
  .doctor-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 1.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
  }

  .doctor-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(59, 130, 246, 0.12);
    border-color: #3b82f6;
  }

  .doctor-name {
    font-weight: 800;
    font-size: 1.5rem;
    color: #1e40af;
    margin-bottom: 0.5rem;
  }

  .doctor-specialty {
    font-style: italic;
    color: #64748b;
    margin-bottom: 1rem;
    font-size: 1.1rem;
  }

  .doctor-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #475569;
    font-size: 1rem;
    margin-bottom: 0.75rem;
  }

  .doctor-info i {
    color: #3b82f6;
    font-size: 1.2rem;
  }

  /* Enhanced Buttons */
  .btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .btn-outline-primary {
    border: 2px solid #3b82f6;
    color: #3b82f6;
  }

  .btn-outline-primary:hover {
    background: #3b82f6;
    color: white;
    transform: translateY(-2px);
  }

  .btn-outline-success {
    border: 2px solid #10b981;
    color: #10b981;
  }

  .btn-outline-success:hover {
    background: #10b981;
    color: white;
    transform: translateY(-2px);
  }

  .btn-outline-secondary {
    border: 2px solid #64748b;
    color: #64748b;
  }

  .btn-outline-secondary:hover {
    background: #64748b;
    color: white;
    transform: translateY(-2px);
  }

  /* Enhanced Appointment Cards */
  .appointment-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 1.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
  }

  .appointment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
  }

  .appointment-date {
    font-weight: 800;
    font-size: 1.25rem;
    color: #1e40af;
    margin-bottom: 1rem;
  }

  .appointment-detail {
    font-size: 1.1rem;
    color: #475569;
    margin-bottom: 0.75rem;
  }

  .appointment-detail strong {
    color: #1e293b;
    font-weight: 600;
  }

  .badge {
    padding: 0.75rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
  }

  /* Loading States */
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

  /* Enhanced Modals */
  .modal-content {
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
  }

  .modal-header {
    border-radius: 20px 20px 0 0;
    padding: 1.5rem;
  }

  .modal-body {
    padding: 2rem;
  }

  .modal-footer {
    border-radius: 0 0 20px 20px;
    padding: 1.5rem;
  }

  /* Responsive Improvements */
  @media (max-width: 991px) {
    .container {
      padding: 1rem;
    }

    .page-title {
      font-size: 2rem;
    }

    .doctor-card {
      padding: 1.25rem;
    }

    .doctor-actions {
      flex-wrap: wrap;
      gap: 0.75rem;
    }

    .btn {
      width: 100%;
      justify-content: center;
    }
  }
</style>

<div class="container">
  <h2 class="page-title">My Appointments</h2>
  
  <div class="row g-4">
    <!-- Left Column: Doctor Search -->
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header">
          <i class="bi bi-search me-2"></i>Find a Doctor
        </div>
        <div class="card-body p-4">
          <div class="search-input-wrapper">
            <input 
              type="text" 
              id="doctorSearch" 
              class="search-input" 
              placeholder="Search doctors by name or specialty..." 
              autocomplete="off"
              aria-label="Search doctors">
            <i class="bi bi-search search-icon"></i>
          </div>
          
          <div id="doctorsList" style="max-height: 600px; overflow-y: auto; padding-right: 0.5rem;">
            <?php if (empty($doctors)): ?>
              <div class="text-center py-5">
                <i class="bi bi-person-x text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No doctors available at the moment.</p>
              </div>
            <?php else: ?>
              <?php foreach ($doctors as $doc): ?>
                <div class="doctor-card" 
                    data-name="<?= strtolower(htmlspecialchars($doc['last_name'])) ?>" 
                    data-specialty="<?= strtolower(htmlspecialchars($doc['specialty'])) ?>">
                  <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div>
                      <div class="doctor-name">Dr. <?= htmlspecialchars($doc['last_name']) ?></div>
                      <div class="doctor-specialty"><?= htmlspecialchars($doc['specialty']) ?></div>
                      <div class="doctor-info">
                        <i class="bi bi-geo-alt-fill"></i>
                        <?= htmlspecialchars($doc['clinic_address']) ?>
                      </div>
                      <div class="doctor-info">
                        <i class="bi bi-envelope-fill"></i>
                        <?= htmlspecialchars($doc['email']) ?>
                      </div>
                    </div>
                    <div class="doctor-actions d-flex gap-2 mt-3 mt-md-0">
                      <button 
                        class="btn btn-outline-primary view-profile-btn" 
                        data-doctor-id="<?= htmlspecialchars($doc['doctor_id']) ?>" 
                        title="View Profile">
                        <i class="bi bi-person-lines-fill"></i> View
                      </button>
                      <button
                        class="btn btn-outline-success request-appointment-btn"
                        data-doctor-id="<?= htmlspecialchars($doc['doctor_id']) ?>"
                        title="Request Appointment">
                        <i class="bi bi-calendar-plus-fill"></i> Request
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Appointments -->
    <div class="col-lg-5">
      <!-- Pending Requests -->
      <div class="card mb-4">
        <div class="card-header bg-warning text-white">
          <i class="bi bi-clock-history me-2"></i>Pending Appointment Requests
        </div>
        <div class="card-body p-4" style="max-height: 300px; overflow-y: auto;">
          <?php if (empty($pendingAppointments)): ?>
            <div class="text-center py-4">
              <i class="bi bi-calendar-check text-muted" style="font-size: 2rem;"></i>
              <p class="text-muted mt-3">You have no pending appointment requests.</p>
            </div>
          <?php else: ?>
            <?php foreach ($pendingAppointments as $appt): ?>
              <div class="appointment-card">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="appointment-date">
                      <?= date("F j, Y h:i A", strtotime($appt['appointment_datetime'])) ?>
                    </div>
                    <div class="appointment-detail">
                      <strong>Doctor:</strong> Dr. <?= htmlspecialchars($appt['last_name']) ?>
                    </div>
                    <div class="appointment-detail">
                      <strong>Reason:</strong> <?= htmlspecialchars($appt['reason']) ?>
                    </div>
                    <div class="appointment-status mt-3">
                      <span class="badge bg-warning text-dark">
                        <?= htmlspecialchars($appt['status']) ?>
                      </span>
                    </div>
                  </div>
                  <button class="btn btn-outline-danger cancel-pending-btn" data-appointment-id="<?= $appt['appointment_id'] ?>" title="Cancel Request">
                    <i class="bi bi-x-circle"></i> Cancel
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Upcoming Appointments -->
      <div class="card">
        <div class="card-header bg-success text-white">
          <i class="bi bi-calendar-check me-2"></i>Upcoming Appointments
        </div>
        <div class="card-body p-4" style="max-height: 300px; overflow-y: auto;">
          <?php if (empty($upcomingAppointments)): ?>
            <div class="text-center py-4">
              <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
              <p class="text-muted mt-3">You have no upcoming appointments.</p>
            </div>
          <?php else: ?>
            <?php foreach ($upcomingAppointments as $appt): ?>
              <div class="appointment-card">
                <div class="appointment-date">
                  <?= date("F j, Y h:i A", strtotime($appt['appointment_datetime'])) ?>
                </div>
                <div class="appointment-detail">
                  <strong>Doctor:</strong> Dr. <?= htmlspecialchars($appt['last_name']) ?>
                </div>
                <div class="appointment-detail">
                  <strong>Reason:</strong> <?= htmlspecialchars($appt['reason']) ?>
                </div>
                <div class="appointment-status mt-3">
                  <span class="badge bg-success">
                    <?= htmlspecialchars($appt['status']) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Enhanced Modals -->
<div class="modal fade" id="doctorProfileModal" tabindex="-1" aria-labelledby="doctorProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="doctorProfileModalLabel">
          <i class="bi bi-person-badge me-2"></i>Doctor Profile
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="doctorProfileContent" class="text-center py-5">
          <div class="loading-spinner mx-auto"></div>
          <div class="mt-3">Loading profile...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="appointmentRequestModal" tabindex="-1" aria-labelledby="appointmentRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" id="appointmentRequestModalContent">
      <!-- Content loaded dynamically -->
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Doctor search filter
  const searchInput = document.getElementById('doctorSearch');
  const doctorsList = document.getElementById('doctorsList');
  if (searchInput && doctorsList) {
    searchInput.addEventListener('input', () => {
      const filter = searchInput.value.toLowerCase();
      doctorsList.querySelectorAll('.doctor-card').forEach(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        const specialty = card.getAttribute('data-specialty').toLowerCase();
        card.style.display = (name.includes(filter) || specialty.includes(filter)) ? '' : 'none';
      });
    });
  }

  // Initialize Bootstrap modals
  const appointmentRequestModalEl = document.getElementById('appointmentRequestModal');
  const appointmentRequestModalContent = document.getElementById('appointmentRequestModalContent');
  const appointmentRequestModal = appointmentRequestModalEl ? new bootstrap.Modal(appointmentRequestModalEl) : null;

  const doctorProfileModalEl = document.getElementById('doctorProfileModal');
  const doctorProfileContent = document.getElementById('doctorProfileContent');
  const doctorProfileModal = doctorProfileModalEl ? new bootstrap.Modal(doctorProfileModalEl) : null;

  // Load Request Appointment modal content dynamically
  document.querySelectorAll('.request-appointment-btn').forEach(button => {
    button.addEventListener('click', () => {
      const doctorId = button.getAttribute('data-doctor-id');
      if (!appointmentRequestModal || !appointmentRequestModalContent) return;

      appointmentRequestModalContent.innerHTML = `
        <div class="text-center p-5">
          <div class="spinner-border text-primary" role="status"></div>
          <div>Loading...</div>
        </div>`;
      appointmentRequestModal.show();

      fetch(`/Healthcare_portal/Pages/Patients/Process/request_appointment_model.php?doctor_id=${encodeURIComponent(doctorId)}`, {
        credentials: 'include'
      })
      .then(res => res.text())
      .then(html => {
        appointmentRequestModalContent.innerHTML = html;
        attachAppointmentFormHandlers(doctorId);
      })
      .catch(() => {
        appointmentRequestModalContent.innerHTML = '<div class="alert alert-danger p-3">Failed to load appointment form.</div>';
      });
    });
  });

  // Load doctor profile modal content dynamically
  document.querySelectorAll('.view-profile-btn').forEach(button => {
    button.addEventListener('click', () => {
      const doctorId = button.getAttribute('data-doctor-id');
      if (!doctorProfileModal || !doctorProfileContent) return;

      doctorProfileContent.innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
          <div>Loading profile...</div>
        </div>`;
      doctorProfileModal.show();

      fetch(`Process/doctor_view_ajax.php?doctor_id=${encodeURIComponent(doctorId)}`, {
        credentials: 'include'
      })
      .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
      })
      .then(html => {
        doctorProfileContent.innerHTML = html;
      })
      .catch(() => {
        doctorProfileContent.innerHTML = '<div class="alert alert-danger">Failed to load profile. Please try again.</div>';
      });
    });
  });

  // Cancel Pending Appointment
  document.querySelectorAll('.cancel-pending-btn').forEach(button => {
    button.addEventListener('click', () => {
      const apptId = button.getAttribute('data-appointment-id');
      if (!apptId) return;

      if (!confirm('Are you sure you want to cancel this pending appointment?')) return;

      button.disabled = true;
      button.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Cancelling...';

      fetch('/Healthcare_portal/Pages/Patients/Process/cancel_appointment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ appointment_id: apptId })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove the appointment card from UI
          const appointmentCard = button.closest('.appointment-card');
          appointmentCard.style.transition = 'all 0.3s ease';
          appointmentCard.style.opacity = '0';
          appointmentCard.style.transform = 'translateX(-20px)';
          
          setTimeout(() => {
            appointmentCard.remove();
            
            // Check if there are no more pending appointments
            const pendingContainer = document.querySelector('.card-body');
            const remainingAppointments = pendingContainer.querySelectorAll('.appointment-card');
            
            if (remainingAppointments.length === 0) {
              pendingContainer.innerHTML = `
                <div class="text-center py-4">
                  <i class="bi bi-calendar-check text-muted" style="font-size: 2rem;"></i>
                  <p class="text-muted mt-3">You have no pending appointment requests.</p>
                </div>
              `;
            }
          }, 300);
        } else {
          alert(data.message || 'Failed to cancel appointment.');
          button.disabled = false;
          button.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error cancelling appointment. Please try again.');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
      });
    });
  });

  // Attach handlers to the dynamically loaded appointment form
  window.attachAppointmentFormHandlers = function(doctorId) {
    const appointmentDateInput = document.getElementById('appointment_date');
    const slotsContainer = document.getElementById('slotsContainer');
    const submitBtn = document.getElementById('submitBtn');
    const appointmentTimeInput = document.getElementById('appointment_time');
    const requestAppointmentForm = document.getElementById('requestAppointmentForm');
    const requestAppointmentMsg = document.getElementById('requestAppointmentMsg');

    if (!appointmentDateInput || !slotsContainer || !submitBtn || !appointmentTimeInput || !requestAppointmentForm) {
      return;
    }

    submitBtn.disabled = true;
    appointmentTimeInput.value = '';
    slotsContainer.innerHTML = '<p class="text-muted">Please select a date to see available time slots.</p>';
    requestAppointmentMsg.innerHTML = '';

    appointmentDateInput.addEventListener('change', () => {
      const date = appointmentDateInput.value;
      submitBtn.disabled = true;
      appointmentTimeInput.value = '';

      if (!date) {
        slotsContainer.innerHTML = '<p class="text-muted">Please select a date to see available time slots.</p>';
        return;
      }

      slotsContainer.innerHTML = '<p>Loading available slots...</p>';

      fetch(`/Healthcare_portal/Pages/Patients/Process/fetch_available_slots.php?doctor_id=${doctorId}&date=${date}`, {
        credentials: 'include'
      })
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          slotsContainer.innerHTML = `<p class="text-danger">${data.error}</p>`;
          return;
        }
        if (!data.slots || data.slots.length === 0) {
          slotsContainer.innerHTML = '<p class="text-muted">No available slots on this date.</p>';
          return;
        }

        // Create table for slots
        let html = `
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Time Slot</th>
                <th>Status</th>
                <th>Select</th>
              </tr>
            </thead>
            <tbody>
        `;

        data.slots.forEach(slot => {
          const disabled = slot.booked ? 'disabled' : '';
          const status = slot.booked ? 'Booked' : 'Available';
          const statusClass = slot.booked ? 'text-danger' : 'text-success';

          html += `
            <tr>
              <td>${slot.time}</td>
              <td class="${statusClass}">${status}</td>
              <td>
                <input 
                  type="radio" 
                  name="time_slot" 
                  value="${slot.time}" 
                  ${disabled}
                  onchange="document.getElementById('appointment_time').value = this.value; document.getElementById('submitBtn').disabled = false;"
                >
              </td>
            </tr>
          `;
        });

        html += '</tbody></table>';
        slotsContainer.innerHTML = html;
      })
      .catch(() => {
        slotsContainer.innerHTML = '<p class="text-danger">Failed to load slots. Please try again.</p>';
      });
    });

    requestAppointmentForm.addEventListener('submit', e => {
      e.preventDefault();
      requestAppointmentMsg.innerHTML = '';

      const date = appointmentDateInput.value;
      const time = appointmentTimeInput.value;
      const reason = requestAppointmentForm.reason.value.trim();

      if (!date || !time) {
        alert('Please select a date and time slot.');
        return;
      }
      if (!reason) {
        alert('Please enter a reason for the appointment.');
        return;
      }

      // Convert 12-hour time to 24-hour format
      function to24Hour(time12h) {
        const [time, modifier] = time12h.split(' ');
        let [hours, minutes] = time.split(':');
        if (hours === '12') hours = '00';
        if (modifier === 'PM') hours = String(parseInt(hours, 10) + 12);
        return `${hours.padStart(2, '0')}:${minutes}`;
      }
      const time24 = to24Hour(time);
      const appointment_datetime = `${date} ${time24}:00`;

      // Log the data being sent
      console.log('Sending appointment data:', {
        doctor_id: doctorId,
        appointment_datetime,
        reason
      });

      const formData = new FormData();
      formData.append('doctor_id', doctorId);
      formData.append('appointment_datetime', appointment_datetime);
      formData.append('reason', reason);

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

      // Log the exact URL and data being sent
      console.log('Sending request to:', '/Healthcare_portal/Pages/Patients/Process/request_appointment_submit.php');
      console.log('Form data:', {
        doctor_id: doctorId,
        appointment_datetime: appointment_datetime,
        reason: reason
      });

      fetch('/Healthcare_portal/Pages/Patients/Process/request_appointment_submit.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
      })
      .then(async res => {
        // Log the response status and headers
        console.log('Response status:', res.status);
        console.log('Response headers:', Object.fromEntries(res.headers.entries()));
        
        // Get the response text first
        const text = await res.text();
        console.log('Raw response:', text);
        
        // Try to parse as JSON
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error('Failed to parse response as JSON:', e);
          console.error('Raw response that failed to parse:', text);
          throw new Error('Invalid server response');
        }
      })
      .then(data => {
        console.log('Server response:', data);
        if (data.success) {
          requestAppointmentMsg.innerHTML = '<div class="alert alert-success">Appointment requested! It will appear in your upcoming appointments.</div>';
          setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('appointmentRequestModal'));
            if (modal) {
              modal.hide();
            }
            location.reload();
          }, 1300);
        } else {
          requestAppointmentMsg.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to request appointment.'}</div>`;
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Request';
        }
      })
      .catch(error => {
        console.error('Error details:', error);
        console.error('Error stack:', error.stack);
        requestAppointmentMsg.innerHTML = '<div class="alert alert-danger">Network error. Please try again. Check console for details.</div>';
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Request';
      });
    });
  }
});
</script>

<?php include 'footer.php'; ?>
