<?php
require_once '../../../database/DBconnect.php'; // database connection
$doctorId = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = $_GET['date'] ?? '';

if (!$doctorId || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing doctor_id or date']);
    exit;
}

// Get all appointments for the selected doctor and date
$sql = "SELECT TIME(appointment_datetime) as time FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_datetime) = ? AND status IN ('pending', 'approved')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $doctorId, $date);
$stmt->execute();
$result = $stmt->get_result();

$bookedTimes = [];
while ($row = $result->fetch_assoc()) {
    $bookedTimes[] = $row['time'];
}

// Generate time slots (e.g., 09:00 to 17:00, every 30 mins)
$start = strtotime('09:00');
$end = strtotime('17:00');
$availableSlots = [];

for ($t = $start; $t <= $end; $t += 30 * 60) {
    $time = date('H:i:s', $t);
    if (!in_array($time, $bookedTimes)) {
        $availableSlots[] = $time;
    }
}

echo json_encode($availableSlots);
