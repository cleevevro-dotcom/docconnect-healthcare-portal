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

// Get doctor's user_id
$stmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stmt->bind_result($doctor_user_id);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit;
}
$stmt->close();

// Delete messages between patient and doctor
$stmt = $conn->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
$stmt->bind_param("iiii", $_SESSION['user_id'], $doctor_user_id, $doctor_user_id, $_SESSION['user_id']);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear chat']);
}
?> 