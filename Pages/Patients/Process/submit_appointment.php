<?php
require_once '../../../database/DBconnect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized: You must be logged in as a patient.']);
    exit;
}

$patientId = $_SESSION['user_id'];
$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!$doctorId || !$date || !$time || empty($reason)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields.']);
    exit;
}

// Validate date and time format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date or time format.']);
    exit;
}

$datetime = $date . ' ' . $time;

// Check if the slot is still available
$sqlCheck = "SELECT 1 FROM appointments WHERE doctor_id = ? AND appointment_datetime = ? AND status IN ('pending', 'approved')";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param('is', $doctorId, $datetime);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Selected time slot is no longer available. Please choose another time.']);
    exit;
}

// Insert new appointment request
$sqlInsert = "INSERT INTO appointments (doctor_id, patient_id, appointment_datetime, reason, status, created_at, updated_at)
              VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())";
$stmtInsert = $conn->prepare($sqlInsert);
$stmtInsert->bind_param('iiss', $doctorId, $patientId, $datetime, $reason);

if ($stmtInsert->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit appointment request. Please try again later.']);
}
