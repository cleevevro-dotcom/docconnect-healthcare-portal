<?php
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = $_POST['appointment_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$appointment_id || !in_array($status, ['confirmed', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

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

// Verify appointment belongs to doctor and is pending
$sqlCheck = "SELECT appointment_id FROM appointments WHERE appointment_id = ? AND doctor_id = ? AND status = 'pending' LIMIT 1";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("ii", $appointment_id, $doctor_id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or already processed']);
    exit;
}

// Update status
$sqlUpdate = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ?";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("si", $status, $appointment_id);

if ($stmtUpdate->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
}
