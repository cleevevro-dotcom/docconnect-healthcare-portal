<?php include 'header.php';
include '../../database/DBconnect.php';

// Fetch total patients
$sql_patients = "SELECT COUNT(*) as total FROM patients";
$result_patients = $conn->query($sql_patients);
$total_patients = $result_patients->fetch_assoc()['total'];

// Fetch active doctors
$sql_doctors = "SELECT COUNT(*) as total FROM doctors";
$result_doctors = $conn->query($sql_doctors);
$total_doctors = $result_doctors->fetch_assoc()['total'];

// Fetch today's appointments
$today = date('Y-m-d');
$sql_appointments = "SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_datetime) = ?";
$stmt = $conn->prepare($sql_appointments);
$stmt->bind_param("s", $today);
$stmt->execute();
$result_appointments = $stmt->get_result();
$total_appointments = $result_appointments->fetch_assoc()['total'];

// Fetch total appointments (all time)
$sql_total_appointments = "SELECT COUNT(*) as total FROM appointments";
$result_total_appointments = $conn->query($sql_total_appointments);
$total_appointments_all = $result_total_appointments->fetch_assoc()['total'];

// Fetch monthly appointments for chart
$sql_monthly = "SELECT 
    DATE_FORMAT(appointment_datetime, '%Y-%m') as month,
    COUNT(*) as count
    FROM appointments 
    WHERE appointment_datetime >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(appointment_datetime, '%Y-%m')
    ORDER BY month ASC";
$result_monthly = $conn->query($sql_monthly);
$monthly_data = [];
$monthly_labels = [];
while($row = $result_monthly->fetch_assoc()) {
    $monthly_labels[] = date('M Y', strtotime($row['month']));
    $monthly_data[] = $row['count'];
}

// Fetch monthly patients for chart
$sql_patients_monthly = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users WHERE role = 'patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC";
$result_patients_monthly = $conn->query($sql_patients_monthly);
$monthly_patients = array_fill(0, count($monthly_labels), 0);
$patients_month_map = [];
while($row = $result_patients_monthly->fetch_assoc()) {
    $patients_month_map[$row['month']] = $row['count'];
}
foreach ($monthly_labels as $i => $label) {
    $month_key = date('Y-m', strtotime($label));
    if (isset($patients_month_map[$month_key])) {
        $monthly_patients[$i] = $patients_month_map[$month_key];
    }
}

// Fetch monthly doctors for chart
$sql_doctors_monthly = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users WHERE role = 'doctor' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC";
$result_doctors_monthly = $conn->query($sql_doctors_monthly);
$monthly_doctors = array_fill(0, count($monthly_labels), 0);
$doctors_month_map = [];
while($row = $result_doctors_monthly->fetch_assoc()) {
    $doctors_month_map[$row['month']] = $row['count'];
}
foreach ($monthly_labels as $i => $label) {
    $month_key = date('Y-m', strtotime($label));
    if (isset($doctors_month_map[$month_key])) {
        $monthly_doctors[$i] = $doctors_month_map[$month_key];
    }
}

// Fetch recent activities
$sql_activities = "SELECT 
    'appointment' as type,
    a.appointment_datetime as datetime,
    CONCAT(u_p.first_name, ' ', u_p.last_name) as patient_name,
    CONCAT(u_d.first_name, ' ', u_d.last_name) as doctor_name,
    a.status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u_p ON p.user_id = u_p.user_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users u_d ON d.user_id = u_d.user_id
    ORDER BY a.appointment_datetime DESC
    LIMIT 5";
$result_activities = $conn->query($sql_activities);
?>

<style>
:root {
  --primary: #2563eb;
  --primary-dark: #1d4ed8;
  --secondary: #64748b;
  --accent: #3b82f6;
  --success: #22c55e;
  --info: #0ea5e9;
  --warning: #fbbf24;
  --danger: #ef4444;
  --light: #f8fafc;
  --dark: #1e293b;
  --bg-gradient: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
  --card-radius: 1.25rem;
  --card-shadow: 0 4px 24px rgba(37,99,235,0.08);
  --transition: all 0.2s cubic-bezier(.4,0,.2,1);
  --font-main: 'Poppins', 'Inter', Arial, sans-serif;
  --chart-gradient: linear-gradient(120deg, #e0e7ff 0%, #c7d2fe 100%);
}
body {
  font-family: var(--font-main);
  background: var(--bg-gradient);
  color: var(--dark);
}
.container {
  max-width: 1300px;
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
.card-title {
  font-weight: 600;
  letter-spacing: 0.01em;
}
.card-text {
  font-size: 2.2rem;
  font-weight: 700;
  margin-bottom: 0.2rem;
}
.border-left-primary {
  border-left: 6px solid var(--primary) !important;
}
.border-left-success {
  border-left: 6px solid var(--success) !important;
}
.border-left-info {
  border-left: 6px solid var(--info) !important;
}
.border-left-warning {
  border-left: 6px solid var(--warning) !important;
}
.card-header.bg-primary, .card-header.bg-secondary {
  border-radius: var(--card-radius) var(--card-radius) 0 0 !important;
  font-weight: 600;
  font-size: 1.1rem;
}
.btn {
  border-radius: 2rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  transition: var(--transition);
}
.btn-primary, .btn-info {
  box-shadow: 0 2px 8px rgba(37,99,235,0.08);
}
.btn-primary:hover, .btn-info:hover {
  filter: brightness(1.08);
  transform: translateY(-2px) scale(1.03);
}

/* --- Enhanced Chart Card --- */
.card.chart-card {
  background: var(--chart-gradient);
  box-shadow: 0 8px 32px rgba(37,99,235,0.13);
  border-radius: 2rem !important;
  padding: 2rem 2rem 1.5rem 2rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: hidden;
}
.card.chart-card .card-header {
  background: transparent !important;
  color: var(--primary-dark) !important;
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  border-bottom: none;
  padding-bottom: 0.5rem;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.card.chart-card .card-header i {
  font-size: 1.5rem;
  color: var(--primary);
}
.card.chart-card .card-body {
  padding: 0;
}
.card.chart-card .chart-legend {
  display: flex;
  gap: 1.5rem;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  font-size: 1rem;
  font-weight: 500;
  color: var(--dark);
}
.card.chart-card .chart-legend span {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.card.chart-card .legend-dot {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  display: inline-block;
}
.card.chart-card .legend-dot.appointments { background: #2563eb; }
.card.chart-card .legend-dot.patients { background: #22c55e; }
.card.chart-card .legend-dot.doctors { background: #fbbf24; }

@media (max-width: 992px) {
  .card-text { font-size: 1.5rem; }
  .container { padding: 1rem; }
  .card.chart-card { padding: 1rem; }
}
</style>

<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Admin Dashboard</h1>
    <div class="text-muted">Last updated: <?= date('M d, Y h:i A') ?></div>
  </div>

  <!-- Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card shadow-sm border-left-primary">
        <div class="card-body">
          <h5 class="card-title text-primary">
            <i class="bi bi-people-fill"></i>
            Total Patients
          </h5>
          <h2 class="card-text"><?= number_format($total_patients) ?></h2>
          <small class="text-muted">Active patients in the system</small>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card shadow-sm border-left-success">
        <div class="card-body">
          <h5 class="card-title text-success">
            <i class="bi bi-person-badge-fill"></i>
            Total Doctors
          </h5>
          <h2 class="card-text"><?= number_format($total_doctors) ?></h2>
          <small class="text-muted">Registered doctors</small>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card shadow-sm border-left-info">
        <div class="card-body">
          <h5 class="card-title text-info">
            <i class="bi bi-calendar-check-fill"></i>
            Today's Appointments
          </h5>
          <h2 class="card-text"><?= number_format($total_appointments) ?></h2>
          <small class="text-muted">Scheduled for today</small>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card shadow-sm border-left-warning">
        <div class="card-body">
          <h5 class="card-title text-warning">
            <i class="bi bi-clipboard-data"></i>
            Total Appointments
          </h5>
          <h2 class="card-text"><?= number_format($total_appointments_all) ?></h2>
          <small class="text-muted">All time</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts and Activities -->
  <div class="row">
    <!-- Monthly Trends Chart -->
    <div class="col-lg-8 mb-4">
      <div class="card chart-card">
        <div class="card-header">
          <i class="bi bi-graph-up"></i>
          Monthly Trends
        </div>
        <div class="card-body">
          <canvas id="dashboardTrendsChart" height="250"></canvas>
          <div class="chart-legend">
            <span><span class="legend-dot appointments"></span>Appointments</span>
            <span><span class="legend-dot patients"></span>New Patients</span>
            <span><span class="legend-dot doctors"></span>New Doctors</span>
          </div>
          <div class="text-muted small mt-2">Shows the number of new patients, new doctors, and appointments per month (last 12 months).</div>
        </div>
      </div>
    </div>

    <!-- Recent Activities -->
    <div class="col-lg-4 mb-4">
      <div class="card shadow-sm">
        <div class="card-header text-white">
          <h5 class="mb-0">
            <i class="bi bi-activity"></i>
            Recent Activities
          </h5>
        </div>
        <div class="card-body">
          <?php if ($result_activities->num_rows > 0): ?>
            <?php while($activity = $result_activities->fetch_assoc()): ?>
              <div class="activity-item">
                <div class="d-flex align-items-center gap-3">
                  <div class="activity-icon bg-primary">
                    <i class="bi bi-calendar-check"></i>
                  </div>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                      <strong><?= htmlspecialchars($activity['patient_name']) ?></strong>
                      <span class="status-badge bg-<?= $activity['status'] === 'confirmed' ? 'success' : 'warning' ?>">
                        <?= ucfirst($activity['status']) ?>
                      </span>
                    </div>
                    <small class="text-muted">
                      With Dr. <?= htmlspecialchars($activity['doctor_name']) ?>
                    </small>
                    <div class="text-muted small">
                      <?= date('M d, Y h:i A', strtotime($activity['datetime'])) ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-inbox fs-1"></i>
              <p class="mt-2">No recent activities</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header text-white">
          <h5 class="mb-0">
            <i class="bi bi-lightning-fill"></i>
            Quick Actions
          </h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <a href="ManagePeople.php" class="btn btn-primary w-100">
                <i class="bi bi-people-fill me-2"></i>
                Manage Users
              </a>
            </div>
            <div class="col-md-3">
              <a href="Appointments.php" class="btn btn-info w-100">
                <i class="bi bi-calendar-check me-2"></i>
                Appointments
              </a>
            </div>
            <div class="col-md-3">
              <a href="#" class="btn btn-success w-100">
                <i class="bi bi-file-earmark-text me-2"></i>
                Reports
              </a>
            </div>
            <div class="col-md-3">
              <a href="#" class="btn btn-warning w-100">
                <i class="bi bi-gear-fill me-2"></i>
                Settings
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('dashboardTrendsChart').getContext('2d');
const dashboardTrendsChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode($monthly_labels) ?>,
    datasets: [
      {
        label: 'Appointments',
        data: <?= json_encode($monthly_data) ?>,
        backgroundColor: 'rgba(37, 99, 235, 0.08)',
        borderColor: '#2563eb',
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#2563eb',
        pointBorderColor: '#fff',
        pointHoverRadius: 6,
      },
      {
        label: 'New Patients',
        data: <?= json_encode($monthly_patients) ?>,
        backgroundColor: 'rgba(34, 197, 94, 0.08)',
        borderColor: '#22c55e',
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#22c55e',
        pointBorderColor: '#fff',
        pointHoverRadius: 6,
      },
      {
        label: 'New Doctors',
        data: <?= json_encode($monthly_doctors) ?>,
        backgroundColor: 'rgba(234, 179, 8, 0.08)',
        borderColor: '#fbbf24',
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#fbbf24',
        pointBorderColor: '#fff',
        pointHoverRadius: 6,
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          font: { size: 14 }
        }
      },
      tooltip: {
        backgroundColor: 'rgba(37, 99, 235, 0.9)',
        padding: 12,
        titleFont: {
          size: 14,
          weight: 'bold'
        },
        bodyFont: {
          size: 13
        },
        cornerRadius: 8
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: 'rgba(0, 0, 0, 0.05)'
        },
        ticks: {
          stepSize: 1
        }
      },
      x: {
        grid: {
          display: false
        }
      }
    }
  }
});
</script>

<?php include 'footer.php'; ?>
