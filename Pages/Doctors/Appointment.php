<?php
session_start();
include 'header.php';
include '../../database/DBconnect.php';

// Session validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo '<div class="container py-5">';
    echo '<div class="alert alert-danger text-center">Please log in as a doctor to view this page.</div>';
    echo '</div>';
    include 'footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo '<div class="container py-5">';
    echo '<div class="alert alert-danger text-center">Doctor profile not found.</div>';
    echo '</div>';
    include 'footer.php';
    exit;
}
$row = $result->fetch_assoc();
$doctor_id = $row['doctor_id'];

// Fetch weekly schedules
$sql = "SELECT day_of_week, start_time, end_time, is_off FROM doctor_schedule WHERE doctor_id = ? AND schedule_date IS NULL ORDER BY day_of_week";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$res = $stmt->get_result();

$weekly_hours = [];
while ($h = $res->fetch_assoc()) {
    $weekly_hours[$h['day_of_week']] = $h;
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$stmt->close();
$conn->close();
?>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
<!-- Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
<!-- FontAwesome -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<style>
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(180deg, #eff6ff, #dbeafe);
    color: #1e3a8a;
  }
  .fc {
    font-size: 0.85rem;
    max-width: 90%;
    margin: 0 auto;
  }
  .fc .fc-button {
    background-color: #2563eb;
    border-radius: 0.375rem;
    padding: 0.3rem 0.6rem;
    font-size: 0.8rem;
    transition: background-color 0.2s;
  }
  .fc .fc-button:hover {
    background-color: #1e40af;
  }
  .fc .fc-event {
    background-color:rgb(231, 236, 255);
    border-radius: 0.25rem;
    padding: 0.2rem;
    cursor: pointer;
    transition: transform 0.2s;
    font-size: 0.75rem;
    color:rgb(32, 33, 48);
  }
  .fc .fc-event:hover {
    transform: scale(1.05);
  }
  #pendingList {
    max-height: 300px;
    overflow-y: auto;
  }
  #pendingList::-webkit-scrollbar {
    width: 4px;
  }
  #pendingList::-webkit-scrollbar-thumb {
    background: #2563eb;
    border-radius: 2px;
  }
  .fade-in {
    animation: fadeIn 0.5s ease;
  }
  .slide-in {
    animation: slideIn 0.4s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
  }
  #pendingAppointmentsCard {
    position: sticky;
    top: 2rem;
  }
</style>

<div class="container mx-auto py-8 px-8 max-w-7xl">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Calendar -->
    <div class="order-2 lg:order-1 lg:col-span-2">
      <div class="bg-white rounded-lg shadow-md p-4 fade-in">
        <h2 class="text-base font-semibold mb-3 flex items-center text-blue-800 bg-gradient-to-r from-blue-100 to-blue-200 px-3 py-2 rounded">
          <i class="fas fa-calendar-alt mr-2"></i> Weekly Schedule
        </h2>
        <div id="calendar" class="bg-gray-50 rounded-md p-2"></div>
      </div>
    </div>

    <!-- Pending Appointments -->
    <div class="order-1 lg:order-2">
      <div id="pendingAppointmentsCard" class="bg-white rounded-lg shadow-md p-4 fade-in">
        <h2 class="text-base font-semibold mb-3 flex items-center text-yellow-800 bg-gradient-to-r from-yellow-100 to-yellow-200 px-3 py-2 rounded">
          <i class="fas fa-hourglass-half mr-2"></i> Pending Appointments
        </h2>
        <div id="pendingList" class="space-y-2">
          <ul class="list-group">
            <li class="text-center text-gray-500 py-3 text-sm">
              <div class="animate-spin rounded-full h-4 w-4 border-t-2 border-yellow-600 mx-auto"></div>
              <div class="mt-2">Loading pending appointments...</div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <!-- Weekly Opening Hours -->
    <div>
      <div class="bg-white rounded-lg shadow-md p-4 fade-in">
        <h2 class="text-base font-semibold mb-3 flex items-center text-teal-800 bg-gradient-to-r from-teal-100 to-teal-200 px-3 py-2 rounded">
          <i class="fas fa-clock mr-2"></i> Weekly Opening Hours
        </h2>
        <form id="hoursForm" method="post" class="space-y-3">
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="bg-blue-50">
                  <th class="p-1.5 text-left font-medium">Day</th>
                  <th class="p-1.5 text-left font-medium">Start</th>
                  <th class="p-1.5 text-left font-medium">End</th>
                  <th class="p-1.5 text-center font-medium">Off</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($days as $index => $day):
                  $open = isset($weekly_hours[$index]) ? $weekly_hours[$index]['start_time'] : '';
                  $close = isset($weekly_hours[$index]) ? $weekly_hours[$index]['end_time'] : '';
                  $off = isset($weekly_hours[$index]) ? (bool)$weekly_hours[$index]['is_off'] : false;
                ?>
                <tr class="hover:bg-blue-50 slide-in">
                  <td class="p-1.5 font-medium text-gray-700"><?php echo htmlspecialchars($day); ?></td>
                  <td class="p-1.5">
                    <input type="time" name="start_<?php echo $index; ?>" class="w-full border-gray-300 rounded text-xs focus:ring-blue-400 focus:border-blue-400" value="<?php echo htmlspecialchars($open); ?>" <?php echo $off ? 'disabled' : ''; ?>>
                  </td>
                  <td class="p-1.5">
                    <input type="time" name="end_<?php echo $index; ?>" class="w-full border-gray-300 rounded text-xs focus:ring-blue-400 focus:border-blue-400" value="<?php echo htmlspecialchars($close); ?>" <?php echo $off ? 'disabled' : ''; ?>>
                  </td>
                  <td class="p-1.5 text-center">
                    <input type="checkbox" name="off_<?php echo $index; ?>" class="form-check-input off-checkbox h-3.5 w-3.5 text-blue-600" data-index="<?php echo $index; ?>" <?php echo $off ? 'checked' : ''; ?>>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="submit" class="w-full bg-blue-600 text-white py-1.5 rounded text-sm hover:bg-blue-700 transition">Save Weekly Hours</button>
        </form>
        <div id="hoursMessage" class="mt-2"></div>
      </div>
    </div>

    <!-- Specific Date Schedules -->
    <div>
      <div class="bg-white rounded-lg shadow-md p-4 fade-in">
        <h2 class="text-base font-semibold mb-3 flex items-center text-gray-800 bg-gradient-to-r from-gray-100 to-gray-200 px-3 py-2 rounded">
          <i class="fas fa-calendar-plus mr-2"></i> Specific Date Schedules
        </h2>
        <div class="overflow-x-auto">
          <table class="w-full text-xs" id="scheduleTable">
            <thead>
              <tr class="bg-blue-50">
                <th class="p-1.5 text-left font-medium">Date</th>
                <th class="p-1.5 text-left font-medium">Start</th>
                <th class="p-1.5 text-left font-medium">End</th>
                <th class="p-1.5 text-center font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="4" class="text-center text-gray-500 py-2 text-xs">Loading schedules...</td></tr>
            </tbody>
          </table>
        </div>
        <button class="w-full bg-blue-600 text-white py-1.5 rounded text-sm hover:bg-blue-700 transition mt-3" id="addScheduleBtn">
          <i class="fas fa-plus mr-2"></i>Add New Schedule
        </button>
        <form id="scheduleForm" class="mt-3 hidden">
          <input type="hidden" name="schedule_id" id="schedule_id" value="">
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label for="schedule_date" class="block text-xs font-medium text-gray-700">Date</label>
              <input type="date" class="w-full border-gray-300 rounded text-xs focus:ring-blue-400 focus:border-blue-400" id="schedule_date" name="schedule_date" required>
            </div>
            <div>
              <label for="start_time" class="block text-xs font-medium text-gray-700">Start</label>
              <input type="time" class="w-full border-gray-300 rounded text-xs focus:ring-blue-400 focus:border-blue-400" id="start_time" name="start_time" required>
            </div>
            <div>
              <label for="end_time" class="block text-xs font-medium text-gray-700">End</label>
              <input type="time" class="w-full border-gray-300 rounded text-xs focus:ring-blue-400 focus:border-blue-400" id="end_time" name="end_time" required>
            </div>
            <div class="flex space-x-2">
              <button type="submit" class="flex-1 bg-green-600 text-white py-1 rounded text-xs hover:bg-green-700 transition"><i class="fas fa-check"></i></button>
              <button type="button" class="flex-1 bg-gray-600 text-white py-1 rounded text-xs hover:bg-gray-700 transition" id="cancelScheduleBtn"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="h-10"></div>
</div>

<!-- FullCalendar and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('calendar');

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'timeGridWeek',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'timeGridWeek,timeGridDay,listWeek'
    },
    allDaySlot: false,
    slotMinTime: "07:00:00",
    slotMaxTime: "19:00:00",
    events: [],
    eventClick: function(info) {
      alert('Appointment: ' + info.event.title + '\nDate & Time: ' + info.event.start.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }));
    }
  });

  calendar.render();

  // Load confirmed appointments
  function loadAppointments() {
    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/fetch_appointments.php',
      method: 'GET',
      dataType: 'json',
      success: function(data) {
        calendar.removeAllEvents();
        if (!Array.isArray(data) || data.length === 0) {
          $('#calendar').append('<div class="text-center text-gray-500 py-2 text-xs">No confirmed appointments.</div>');
          return;
        }
        data.forEach(function(appt) {
          if (appt.patient_name && appt.reason && appt.appointment_datetime) {
            calendar.addEvent({
              id: appt.appointment_id,
              title: `${appt.patient_name} - ${appt.reason}`,
              start: appt.appointment_datetime,
              backgroundColor: '#34d399',
              borderColor: '#34d399',
              textColor: '#1f2937'
            });
          }
        });
      },
      error: function(jqXHR, textStatus, errorThrown) {
        console.error('Failed to load appointments:', textStatus, errorThrown);
        $('#calendar').append('<div class="text-center text-red-500 py-2 text-xs">Failed to load appointments.</div>');
      }
    });
  }

  // Load pending appointments
  function loadPending() {
    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/fetch_pending.php',
      method: 'GET',
      dataType: 'json',
      success: function(data) {
        var $list = $('#pendingList ul');
        $list.empty();

        if (!Array.isArray(data) || data.length === 0) {
          $list.append('<li class="text-center text-gray-500 py-2 text-xs">No pending appointments.</li>');
          return;
        }

        data.forEach(function(pending) {
          if (pending.patient_name && pending.appointment_datetime && pending.reason) {
            var li = $(`
              <li class="bg-blue-50 p-2 rounded flex justify-between items-center slide-in">
                <div>
                  <strong class="text-blue-700 text-xs">${pending.patient_name}</strong><br/>
                  <small class="text-gray-500 text-xs">${pending.appointment_datetime}</small><br/>
                  <em class="text-xs text-gray-600">Reason: ${pending.reason}</em>
                </div>
                <div class="flex space-x-1">
                  <button class="bg-green-600 text-white px-1.5 py-0.5 rounded btn-accept text-xs" data-id="${pending.appointment_id}" title="Accept Appointment">
                    <i class="fas fa-check"></i>
                  </button>
                  <button class="bg-red-600 text-white px-1.5 py-0.5 rounded btn-reject text-xs" data-id="${pending.appointment_id}" title="Reject Appointment">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </li>
            `);
            $list.append(li);
          }
        });
      },
      error: function(jqXHR, textStatus, errorThrown) {
        console.error('Failed to load pending appointments:', textStatus, errorThrown);
        $('#pendingList ul').html('<li class="text-red-600 text-center text-xs">Failed to load pending appointments.</li>');
      }
    });
  }

  // Accept appointment
  $('#pendingList').on('click', '.btn-accept', function() {
    var appointmentId = $(this).data('id');
    if (!appointmentId) {
      alert('Invalid appointment ID.');
      return;
    }
    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/update_appointment_status.php',
      method: 'POST',
      data: { appointment_id: appointmentId, status: 'confirmed' },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          loadPending();
          loadAppointments();
        } else {
          alert('Failed to accept appointment: ' + (res.message || 'Unknown error'));
        }
      },
      error: function() {
        alert('Error occurred while accepting appointment.');
      }
    });
  });

  // Reject appointment
  $('#pendingList').on('click', '.btn-reject', function() {
    var appointmentId = $(this).data('id');
    if (!appointmentId) {
      alert('Invalid appointment ID.');
      return;
    }
    if (!confirm('Are you sure you want to reject this appointment?')) return;

    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/update_appointment_status.php',
      method: 'POST',
      data: { appointment_id: appointmentId, status: 'rejected' },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          loadPending();
          loadAppointments();
        } else {
          alert('Failed to reject appointment: ' + (res.message || 'Unknown error'));
        }
      },
      error: function() {
        alert('Error occurred while rejecting appointment.');
      }
    });
  });

  // Enable/disable time inputs when off checkbox toggled
  $('.off-checkbox').each(function() {
    var index = $(this).data('index');
    var checked = $(this).is(':checked');
    var startInput = $(`input[name='start_${index}']`);
    var endInput = $(`input[name='end_${index}']`);
    startInput.prop('disabled', checked);
    endInput.prop('disabled', checked);
  });

  $('#hoursForm').on('change', '.off-checkbox', function() {
    var index = $(this).data('index');
    var checked = $(this).is(':checked');
    var startInput = $(`input[name='start_${index}']`);
    var endInput = $(`input[name='end_${index}']`);
    startInput.prop('disabled', checked).val(checked ? '' : startInput.val());
    endInput.prop('disabled', checked).val(checked ? '' : endInput.val());
  });

  // Handle form submission via AJAX
  $('#hoursForm').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/save_opening_hours.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(res) {
        $('#hoursMessage').html(`<div class="bg-${res.success ? 'green' : 'red'}-100 text-${res.success ? 'green' : 'red'}-800 p-1.5 rounded text-xs">${res.success ? res.message : 'Error: ' + res.message}</div>`);
      },
      error: function() {
        $('#hoursMessage').html('<div class="bg-red-100 text-red-800 p-1.5 rounded text-xs">Error saving opening hours.</div>');
      }
    });
  });

  // Specific Date Schedules Section
  const doctorId = <?php echo json_encode($doctor_id); ?>;

  function loadSchedules() {
    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/fetch_doctor_schedule.php',
      method: 'GET',
      data: { doctor_id: doctorId },
      dataType: 'json',
      success: function(data) {
        const tbody = $('#scheduleTable tbody');
        tbody.empty();
        if (!Array.isArray(data) || data.length === 0) {
          tbody.append('<tr><td colspan="4" class="text-center text-gray-500 text-xs">No schedules found.</td></tr>');
          return;
        }
        data.forEach(function(item) {
          if (item.id && item.schedule_date && item.start_time && item.end_time) {
            tbody.append(`
              <tr data-id="${item.id}" class="hover:bg-blue-50 slide-in">
                <td class="p-1.5 font-medium text-gray-700">${item.schedule_date}</td>
                <td class="p-1.5 text-gray-600">${item.start_time}</td>
                <td class="p-1.5 text-gray-600">${item.end_time}</td>
                <td class="p-1.5 text-center">
                  <button class="bg-blue-600 text-white px-1.5 py-0.5 rounded editScheduleBtn text-xs" title="Edit"><i class="fas fa-edit"></i></button>
                  <button class="bg-red-600 text-white px-1.5 py-0.5 rounded deleteScheduleBtn text-xs" title="Delete"><i class="fas fa-trash-alt"></i></button>
                </td>
              </tr>
            `);
          }
        });
      },
      error: function(jqXHR, textStatus, errorThrown) {
        console.error('Failed to load schedules:', textStatus, errorThrown);
        $('#scheduleTable tbody').html('<tr><td colspan="4" class="text-center text-red-500 text-xs">Failed to load schedules.</td></tr>');
      }
    });
  }

  // Edit button
  $('#scheduleTable').on('click', '.editScheduleBtn', function() {
    const row = $(this).closest('tr');
    const id = row.data('id');
    const date = row.find('td:eq(0)').text();
    const start = row.find('td:eq(1)').text();
    const end = row.find('td:eq(2)').text();

    if (!id || !date || !start || !end) {
      alert('Invalid schedule data.');
      return;
    }

    $('#schedule_id').val(id);
    $('#schedule_date').val(date);
    $('#start_time').val(start);
    $('#end_time').val(end);
    $('#scheduleForm').removeClass('hidden');
  });

  // Delete button
  $('#scheduleTable').on('click', '.deleteScheduleBtn', function() {
    const id = $(this).closest('tr').data('id');
    if (!id) {
      alert('Invalid schedule ID.');
      return;
    }
    if (!confirm('Are you sure you want to delete this schedule?')) return;

    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/delete_doctor_schedule.php',
      method: 'POST',
      data: { schedule_id: id },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          loadSchedules();
        } else {
          alert('Failed to delete schedule: ' + (res.message || 'Unknown error'));
        }
      },
      error: function() {
        alert('Error deleting schedule.');
      }
    });
  });

  // Submit add/edit form
  $('#scheduleForm').submit(function(e) {
    e.preventDefault();
    const formData = $(this).serialize() + '&doctor_id=' + encodeURIComponent(doctorId);
    $.ajax({
      url: '/Healthcare_portal/Pages/Doctors/Process/save_doctor_schedule.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          $('#scheduleForm').addClass('hidden');
          loadSchedules();
        } else {
          alert('Failed to save schedule: ' + (res.message || 'Unknown error'));
        }
      },
      error: function() {
        alert('Error saving schedule.');
      }
    });
  });

  // Initial load
  loadAppointments();
  loadPending();
  loadSchedules();
});
</script>

<?php include 'footer.php'; ?>