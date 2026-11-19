<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$doctor_id = null;

// Get doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Doctor profile not found']);
    exit;
}
$row = $result->fetch_assoc();
$doctor_id = $row['doctor_id'];

// Validate inputs
$schedule_id = $_POST['schedule_id'] ?? null;
$schedule_date = $_POST['schedule_date'] ?? null;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;

if (!$schedule_date || !$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($start_time >= $end_time) {
    echo json_encode(['success' => false, 'message' => 'Start time must be before end time']);
    exit;
}

if ($schedule_id) {
    // Update existing
    $sql = "UPDATE doctor_schedule SET schedule_date = ?, start_time = ?, end_time = ?, is_off = 0, updated_at = NOW() WHERE id = ? AND doctor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $schedule_date, $start_time, $end_time, $schedule_id, $doctor_id);
    $success = $stmt->execute();
} else {
    // Insert new
    $sql = "INSERT INTO doctor_schedule (doctor_id, schedule_date, start_time, end_time, is_off, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $doctor_id, $schedule_date, $start_time, $end_time);
    $success = $stmt->execute();
}

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
