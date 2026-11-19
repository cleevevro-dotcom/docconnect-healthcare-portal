<?php
session_start();
include '../../../database/DBconnect.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['immunization_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing immunization ID']);
    exit;
}

$immunization_id = (int)$data['immunization_id'];
$user_id = $_SESSION['user_id'];

// Get patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient record not found']);
    exit;
}

$patient_id = $patient['patient_id'];

// Verify that the immunization entry belongs to the patient
$stmt = $conn->prepare("SELECT patient_id FROM immunizations WHERE immunization_id = ?");
$stmt->bind_param("i", $immunization_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Immunization record not found']);
    exit;
}

$entry = $result->fetch_assoc();
if ($entry['patient_id'] != $patient_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Delete the immunization record
$stmt = $conn->prepare("DELETE FROM immunizations WHERE immunization_id = ? AND patient_id = ?");
$stmt->bind_param("ii", $immunization_id, $patient_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete immunization record']);
}

$stmt->close();
$conn->close();
?> 