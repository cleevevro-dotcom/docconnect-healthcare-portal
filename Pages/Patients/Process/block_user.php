<?php
session_start();
include '../../../database/DBconnect.php';

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$doctor_id = isset($data['doctor_id']) ? (int)$data['doctor_id'] : null;

if (!$doctor_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
    exit;
}

// Get patient_id
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($patient_id);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Patient profile not found']);
    exit;
}
$stmt->close();

// Check if block already exists
$stmt = $conn->prepare("SELECT block_id FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'User is already blocked']);
    exit;
}
$stmt->close();

// Add block
$stmt = $conn->prepare("INSERT INTO blocked_users (blocker_id, blocked_id, blocked_at) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $_SESSION['user_id'], $doctor_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to block user']);
}
?> 