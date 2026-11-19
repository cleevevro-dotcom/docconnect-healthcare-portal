<?php

session_start();
include '../../../database/DBconnect.php';

// Validate doctor_id parameter
$doctor_id = $_GET['doctor_id'] ?? '';
if (!$doctor_id) {
    echo '<div class="alert alert-danger">Invalid doctor selected.</div>';
    exit;
}

// Validate patient session
$patient_id = $_SESSION['user_id'] ?? null;
if (!$patient_id) {
    echo '<div class="alert alert-danger">You must be logged in to request an appointment.</div>';
    exit;
}
?>

<div class="modal-header bg-success text-white">
  <h5 class="modal-title">Request Appointment</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="requestAppointmentForm" novalidate>
  <div class="modal-body">
    <input type="hidden" name="doctor_id" value="<?= htmlspecialchars($doctor_id) ?>">
    
    <div class="mb-3">
      <label for="appointment_date" class="form-label">Select Date</label>
      <input type="date" class="form-control" name="appointment_date" id="appointment_date" required min="<?= date('Y-m-d') ?>">
      <div class="invalid-feedback">Please select a valid date.</div>
    </div>
    
    <div class="mb-3" id="slotsContainer">
      <p class="text-muted">Please select a date to see available time slots.</p>
    </div>
    
    <div class="mb-3">
      <label for="reason" class="form-label">Reason for Appointment</label>
      <textarea class="form-control" name="reason" id="reason" rows="3" required></textarea>
      <div class="invalid-feedback">Please provide a reason for your appointment.</div>
    </div>
    
    <input type="hidden" name="appointment_time" id="appointment_time" required>
    <div id="requestAppointmentMsg"></div>
  </div>
  
  <div class="modal-footer">
    <button type="submit" class="btn btn-success" disabled id="submitBtn">Request</button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
  </div>
</form>

<script>
(() => {
  const appointmentDateInput = document.getElementById('appointment_date');
  const slotsContainer = document.getElementById('slotsContainer');
  const submitBtn = document.getElementById('submitBtn');
  const appointmentTimeInput = document.getElementById('appointment_time');
  const form = document.getElementById('requestAppointmentForm');
  const msgDiv = document.getElementById('requestAppointmentMsg');

  // Reset form state
  function resetSlots() {
    slotsContainer.innerHTML = '<p class="text-muted">Please select a date to see available time slots.</p>';
    appointmentTimeInput.value = '';
    submitBtn.disabled = true;
  }

  // Load available slots when date changes
  appointmentDateInput.addEventListener('change', () => {
    const date = appointmentDateInput.value;
    const doctorId = <?= json_encode($doctor_id) ?>;

    submitBtn.disabled = true;
    appointmentTimeInput.value = '';
    msgDiv.innerHTML = '';

    if (!date) {
      resetSlots();
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

  // Form validation and submission
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    msgDiv.innerHTML = '';

    // Basic HTML5 validation
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    const date = appointmentDateInput.value;
    const time = appointmentTimeInput.value;
    const reason = form.reason.value.trim();

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
    const appointment_datetime = `${date}T${time24}:00`;

    // Log the data being sent
    console.log('Sending appointment data:', {
      doctor_id: <?= json_encode($doctor_id) ?>,
      appointment_datetime,
      reason
    });

    const formData = new FormData();
    formData.append('doctor_id', <?= json_encode($doctor_id) ?>);
    formData.append('appointment_datetime', appointment_datetime);
    formData.append('reason', reason);

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

    fetch('/Healthcare_portal/Pages/Patients/Process/request_appointment_submit.php', {
      method: 'POST',
      body: formData,
      credentials: 'include'
    })
    .then(res => {
      if (!res.ok) {
        throw new Error('Network response was not ok');
      }
      return res.json();
    })
    .then(data => {
      console.log('Server response:', data);
      if (data.success) {
        msgDiv.innerHTML = '<div class="alert alert-success">Appointment requested! It will appear in your upcoming appointments.</div>';
        setTimeout(() => {
          const modal = bootstrap.Modal.getInstance(document.getElementById('appointmentRequestModal'));
          if (modal) {
            modal.hide();
          }
          location.reload();
        }, 1300);
      } else {
        msgDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to request appointment.'}</div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Request';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      msgDiv.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Request';
    });
  });
})();
</script>
