<?php
session_start();
include '../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($doctor_id);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Doctor profile not found.");
}
$stmt->close();

$oneMonthAgo = (new DateTime())->modify('-1 month')->format('Y-m-d H:i:s');

$sql = "
    SELECT p.patient_id, u.first_name, u.last_name, u.email, u.gender, p.date_of_birth, p.profile_picture,
           a.status, a.appointment_datetime
    FROM patients p
    INNER JOIN users u ON p.user_id = u.user_id
    INNER JOIN appointments a ON p.patient_id = a.patient_id
    INNER JOIN (
        SELECT patient_id, MAX(appointment_datetime) AS latest_appointment
        FROM appointments
        WHERE doctor_id = ?
        GROUP BY patient_id
    ) latest ON a.patient_id = latest.patient_id AND a.appointment_datetime = latest.latest_appointment
    WHERE a.doctor_id = ?
    ORDER BY u.last_name, u.first_name
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("ii", $doctor_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$patientsByStatus = [
    'pending' => [],
    'confirmed' => [],
    'completed' => [],
    'history' => [],
];

while ($row = $result->fetch_assoc()) {
    $apptDate = new DateTime($row['appointment_datetime']);
    $status = strtolower($row['status']);

    if ($status === 'completed' && $apptDate < new DateTime($oneMonthAgo)) {
        $patientsByStatus['history'][] = $row;
    } else {
        $patientsByStatus[$status][] = $row;
    }
}

$stmt->close();
$conn->close();
?>

<?php include 'header.php'; ?>

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
  #patientSearch {
    transition: box-shadow 0.3s ease;
  }
  #patientSearch:focus {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
  }
  .patient-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .patient-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
  .status-section::-webkit-scrollbar {
    width: 4px;
  }
  .status-section::-webkit-scrollbar-thumb {
    background: #2563eb;
    border-radius: 2px;
  }
  #searchContainer {
    position: sticky;
    top: 1rem;
    z-index: 10;
  }
</style>

<div class="container mx-auto py-8 px-8 max-w-7xl">
  <h2 class="text-2xl font-semibold text-center text-blue-900 mb-6 fade-in">My Patients</h2>

  <!-- Search Bar -->
  <div id="searchContainer" class="mb-6 fade-in">
    <div class="max-w-md mx-auto">
      <div class="relative">
        <input type="text" id="patientSearch" class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-400 focus:border-blue-400 pl-10 pr-4 py-2" placeholder="Search by name or email...">
        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
      </div>
      <div id="noResults" class="hidden mt-2 text-center text-red-600 text-sm">No patients found.</div>
    </div>
  </div>

  <!-- Pending, Confirmed, Completed Sections -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php foreach (['pending', 'confirmed', 'completed'] as $status): ?>
      <div class="bg-white rounded-lg shadow-md p-4 fade-in">
        <h4 class="text-base font-semibold mb-3 flex items-center text-<?php echo $status === 'pending' ? 'yellow' : ($status === 'confirmed' ? 'green' : 'gray') ?>-800 bg-gradient-to-r from-<?php echo $status === 'pending' ? 'yellow' : ($status === 'confirmed' ? 'green' : 'gray') ?>-100 to-<?php echo $status === 'pending' ? 'yellow' : ($status === 'confirmed' ? 'green' : 'gray') ?>-200 px-3 py-2 rounded capitalize">
          <i class="fas fa-<?php echo $status === 'pending' ? 'hourglass-half' : ($status === 'confirmed' ? 'check-circle' : 'clipboard-check') ?> mr-2"></i> <?php echo htmlspecialchars($status) ?> Patients
        </h4>
        <?php if (empty($patientsByStatus[$status])): ?>
          <p class="text-center text-gray-500 text-sm">No <?php echo htmlspecialchars($status) ?> patients found.</p>
        <?php else: ?>
          <div class="status-section space-y-3" style="max-height: 60vh; overflow-y: auto;">
            <?php foreach ($patientsByStatus[$status] as $patient):
              $dob = new DateTime($patient['date_of_birth']);
              $age = $dob->diff(new DateTime())->y;
              $profilePicPath = '/Healthcare_portal/Pages/Patients' . $patient['profile_picture'];
              $defaultPicPath = '/Healthcare_portal/Pages/Patients/uploads/default_profile.png';
              $finalPicPath = (!empty($patient['profile_picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profilePicPath)) ? $profilePicPath : $defaultPicPath;
              $badgeClass = match($patient['status']) {
                'pending' => 'bg-yellow-400 text-gray-800',
                'confirmed' => 'bg-green-500 text-white',
                'completed' => 'bg-gray-500 text-white',
                default => 'bg-gray-500 text-white'
              };
            ?>
              <div class="patient-card bg-gray-50 rounded-md p-3 flex items-center slide-in">
                <img src="<?php echo htmlspecialchars($finalPicPath) ?>" alt="Profile Picture" class="rounded-circle w-12 h-12 object-cover mr-3">
                <div class="flex-grow">
                  <h6 class="text-sm font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h6>
                  <p class="text-xs text-gray-600 mb-1"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($patient['email']) ?></p>
                  <div class="flex gap-1">
                    <span class="badge bg-blue-400 text-white text-xs px-2 py-1 rounded"><?php echo $age ?> yrs</span>
                    <span class="badge bg-gray-400 text-white text-xs px-2 py-1 rounded"><?php echo ucfirst($patient['gender']) ?></span>
                    <span class="badge <?php echo $badgeClass ?> text-xs px-2 py-1 rounded"><?php echo ucfirst($patient['status']) ?></span>
                  </div>
                </div>
                <a href="/Healthcare_portal/Pages/Doctors/Process/PatientProfile.php?id=<?php echo (int)$patient['patient_id'] ?>" class="btn bg-blue-600 text-white text-xs px-3 py-1 rounded hover:bg-blue-700 transition">View</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Patient History Section -->
  <div class="bg-white rounded-lg shadow-md p-4 fade-in">
    <h4 class="text-base font-semibold mb-3 flex items-center text-gray-800 bg-gradient-to-r from-gray-100 to-gray-200 px-3 py-2 rounded">
      <i class="fas fa-history mr-2"></i> Patient History
    </h4>
    <?php if (empty($patientsByStatus['history'])): ?>
      <p class="text-center text-gray-500 text-sm">No historical patients found.</p>
    <?php else: ?>
      <div class="status-section space-y-3" style="max-height: 50vh; overflow-y: auto;">
        <?php foreach ($patientsByStatus['history'] as $patient):
          $dob = new DateTime($patient['date_of_birth']);
          $age = $dob->diff(new DateTime())->y;
          $profilePicPath = '/Healthcare_portal/Pages/Patients' . $patient['profile_picture'];
          $defaultPicPath = '/Healthcare_portal/Pages/Patients/uploads/default_profile.png';
          $finalPicPath = (!empty($patient['profile_picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profilePicPath)) ? $profilePicPath : $defaultPicPath;
        ?>
          <div class="patient-card bg-gray-50 rounded-md p-3 flex items-center slide-in">
            <img src="<?php echo htmlspecialchars($finalPicPath) ?>" alt="Profile Picture" class="rounded-circle w-12 h-12 object-cover mr-3">
            <div class="flex-grow">
              <h6 class="text-sm font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h6>
              <p class="text-xs text-gray-600 mb-1"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($patient['email']) ?></p>
              <div class="flex gap-1">
                <span class="badge bg-blue-400 text-white text-xs px-2 py-1 rounded"><?php echo $age ?> yrs</span>
                <span class="badge bg-gray-400 text-white text-xs px-2 py-1 rounded"><?php echo ucfirst($patient['gender']) ?></span>
                <span class="badge bg-gray-500 text-white text-xs px-2 py-1 rounded">Completed</span>
              </div>
            </div>
            <a href="/Healthcare_portal/Pages/Doctors/Process/PatientProfile.php?id=<?php echo (int)$patient['patient_id'] ?>" class="btn bg-blue-600 text-white text-xs px-3 py-1 rounded hover:bg-blue-700 transition">View</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="h-10"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('patientSearch');
  const noResultsDiv = document.getElementById('noResults');
  let debounceTimeout;

  function filterPatients() {
    const filter = searchInput.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.patient-card');
    let visibleCount = 0;

    cards.forEach(card => {
      const name = card.querySelector('h6').textContent.toLowerCase();
      const email = card.querySelector('p').textContent.toLowerCase();
      const isVisible = name.includes(filter) || email.includes(filter);
      card.style.display = isVisible ? '' : 'none';
      if (isVisible) visibleCount++;
    });

    noResultsDiv.classList.toggle('hidden', visibleCount > 0);
  }

  searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(filterPatients, 300);
  });

  // Initial filter to handle empty state
  filterPatients();
});
</script>

<?php include 'footer.php'; ?>