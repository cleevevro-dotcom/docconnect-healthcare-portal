<?php
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode([]);
    exit;
}
$row = $result->fetch_assoc();
$doctor_id = $row['doctor_id'];

// Fetch specific date schedules (where schedule_date is NOT NULL)
$sql = "SELECT id, schedule_date, start_time, end_time FROM doctor_schedule WHERE doctor_id = ? AND schedule_date IS NOT NULL ORDER BY schedule_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$res = $stmt->get_result();

$schedules = [];
while ($row = $res->fetch_assoc()) {
    $schedules[] = $row;
}

header('Content-Type: application/json');
echo json_encode($schedules);
