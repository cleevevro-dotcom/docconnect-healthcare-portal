<?php
include 'header.php';
include '../../database/DBconnect.php';

// Pagination settings
$limit = 10; // appointments per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search/filter (optional)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Prepare search SQL snippet
$searchSql = '';
$searchParam = '';
if ($search !== '') {
    $searchSql = " AND (CONCAT(u_p.first_name, ' ', u_p.last_name) LIKE ? OR CONCAT(u_d.first_name, ' ', u_d.last_name) LIKE ?)";
    $searchParam = '%' . $search . '%';
}

// Count total appointments for pagination
$countSql = "SELECT COUNT(*) FROM appointments a
             JOIN patients p ON a.patient_id = p.patient_id
             JOIN users u_p ON p.user_id = u_p.user_id
             JOIN doctors d ON a.doctor_id = d.doctor_id
             JOIN users u_d ON d.user_id = u_d.user_id
             WHERE 1=1 $searchSql";

$stmtCount = $conn->prepare($countSql);
if ($search !== '') {
    $stmtCount->bind_param("ss", $searchParam, $searchParam);
}
$stmtCount->execute();
$stmtCount->bind_result($totalAppointments);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = ceil($totalAppointments / $limit);

// Fetch appointments with joins and search filter
$sql = "SELECT a.appointment_id, a.appointment_datetime, a.status, a.reason,
               u_p.first_name AS patient_first, u_p.last_name AS patient_last,
               u_d.first_name AS doctor_first, u_d.last_name AS doctor_last
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u_p ON p.user_id = u_p.user_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        JOIN users u_d ON d.user_id = u_d.user_id
        WHERE 1=1 $searchSql
        ORDER BY a.appointment_datetime DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<style>
:root {
  --primary: #2563eb;
  --primary-dark: #1d4ed8;
  --success: #22c55e;
  --warning: #fbbf24;
  --danger: #ef4444;
  --info: #0ea5e9;
  --light: #f8fafc;
  --dark: #1e293b;
  --bg-gradient: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
  --card-radius: 1.25rem;
  --card-shadow: 0 4px 24px rgba(37,99,235,0.08);
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
}
.card {
  border-radius: var(--card-radius) !important;
  box-shadow: var(--card-shadow) !important;
  border: none !important;
  transition: var(--transition);
}
.card:hover {
  transform: translateY(-2px) scale(1.01);
  box-shadow: 0 8px 32px rgba(37,99,235,0.13);
}
.table {
  border-radius: 1rem;
  overflow: hidden;
  background: #fff;
}
.table thead {
  background: var(--primary);
  color: #fff;
  font-weight: 600;
  letter-spacing: 0.01em;
}
.table-striped > tbody > tr:nth-of-type(odd) {
  background-color: #f3f6fd;
}
.table-hover tbody tr:hover {
  background: #e0e7ff;
  transition: var(--transition);
}
.table td, .table th {
  vertical-align: middle;
  padding: 1rem 0.75rem;
}
.badge {
  border-radius: 1rem;
  font-size: 0.95rem;
  padding: 0.5em 1.1em;
  font-weight: 600;
  letter-spacing: 0.01em;
  box-shadow: 0 2px 8px rgba(37,99,235,0.06);
}
.bg-success { background: var(--success) !important; color: #fff !important; }
.bg-warning { background: var(--warning) !important; color: #222e3a !important; }
.bg-danger { background: var(--danger) !important; color: #fff !important; }
.bg-primary { background: var(--primary) !important; color: #fff !important; }
.bg-secondary { background: var(--info) !important; color: #fff !important; }

.btn-outline-primary {
  border-radius: 2rem;
  font-weight: 500;
  transition: var(--transition);
}
.btn-outline-primary:hover {
  background: var(--primary);
  color: #fff;
}
.btn-primary {
  border-radius: 2rem;
  font-weight: 500;
  box-shadow: 0 2px 8px rgba(37,99,235,0.08);
  transition: var(--transition);
}
.btn-primary:hover {
  filter: brightness(1.08);
  transform: translateY(-2px) scale(1.03);
}
.btn-info, .btn-warning, .btn-danger {
  border-radius: 2rem;
  font-weight: 500;
  transition: var(--transition);
}
.btn-info:hover, .btn-warning:hover, .btn-danger:hover {
  filter: brightness(1.08);
  transform: translateY(-2px) scale(1.03);
}
.form-control {
  border-radius: 2rem;
  font-size: 1rem;
  padding: 0.6rem 1.2rem;
  border: 1px solid #d1d5db;
  transition: var(--transition);
}
.form-control:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 2px #c7d2fe;
}
.pagination .page-item .page-link {
  border-radius: 1.5rem !important;
  margin: 0 0.2rem;
  color: var(--primary);
  font-weight: 500;
  transition: var(--transition);
}
.pagination .page-item.active .page-link {
  background: var(--primary);
  color: #fff;
  border: none;
  box-shadow: 0 2px 8px rgba(37,99,235,0.08);
}
.pagination .page-link:hover {
  background: var(--primary-dark);
  color: #fff;
}
@media (max-width: 992px) {
  .container { padding: 1rem; }
  .table th, .table td { padding: 0.7rem 0.5rem; }
}
</style>

<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h2 class="mb-0">Manage Appointments</h2>

    <form class="d-flex" method="get" action="appointments.php" role="search" aria-label="Search appointments">
      <input type="search" name="search" class="form-control me-2" placeholder="Search by patient or doctor" value="<?= htmlspecialchars($search) ?>" aria-label="Search appointments">
      <button type="submit" class="btn btn-outline-primary">Search</button>
    </form>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-dark">
          <tr>
            <th class="ps-4">Patient Name</th>
            <th class="text-center">Doctor</th>
            <th class="text-center">Date & Time</th>
            <th class="text-center">Status</th>
            <th class="text-center pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($appointment = $result->fetch_assoc()): ?>
              <tr>
                <td class="ps-4 fw-semibold"><?= htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']) ?></td>
                <td class="text-center">Dr. <?= htmlspecialchars($appointment['doctor_first'] . ' ' . $appointment['doctor_last']) ?></td>
                <td class="text-center"><?= date('M d, Y - h:i A', strtotime($appointment['appointment_datetime'])) ?></td>
                <td class="text-center">
                  <?php
                    $status = strtolower($appointment['status']);
                    $badgeClass = match($status) {
                      'confirmed' => 'bg-success',
                      'pending' => 'bg-warning text-dark',
                      'cancelled' => 'bg-danger',
                      'completed' => 'bg-primary',
                      default => 'bg-secondary',
                    };
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                </td>
                <td class="text-center pe-4">
                  <a href="AppointmentView.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-sm btn-info" title="View" aria-label="View appointment">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="AppointmentEdit.php?id=<?= $appointment['appointment_id'] ?>" class="btn btn-sm btn-warning" title="Edit" aria-label="Edit appointment">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                  <button class="btn btn-sm btn-danger" title="Cancel" aria-label="Cancel appointment" onclick="confirmCancel(<?= $appointment['appointment_id'] ?>)">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center py-4">No appointments found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <nav aria-label="Appointments pagination" class="mt-4">
      <ul class="pagination justify-content-center flex-wrap gap-2">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<script>
  function confirmCancel(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
      window.location.href = 'AppointmentCancel.php?id=' + appointmentId;
    }
  }
</script>

<?php
$stmt->close();
include 'footer.php';
?>
