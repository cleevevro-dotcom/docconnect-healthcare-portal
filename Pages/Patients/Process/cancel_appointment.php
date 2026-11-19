<?php
session_start();
include '../../../database/DBconnect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Patient record not found']);
    exit;
}
$patient = $result->fetch_assoc();
$patient_id = $patient['patient_id'];

// Get JSON data from POST body
$data = json_decode(file_get_contents('php://input'), true);
$appointment_id = $data['appointment_id'] ?? null;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

// Verify appointment belongs to patient and is pending
$stmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ? AND patient_id = ?");
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();

if ($appointment['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Only pending appointments can be cancelled']);
    exit;
}

// Update status to 'cancelled'
$update = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE appointment_id = ?");
$update->bind_param("i", $appointment_id);

if ($update->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
}
