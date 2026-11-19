<?php
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

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

$schedule_id = $_POST['schedule_id'] ?? null;
if (!$schedule_id) {
    echo json_encode(['success' => false, 'message' => 'Missing schedule ID']);
    exit;
}

// Delete schedule if owned by doctor
$sql = "DELETE FROM doctor_schedule WHERE id = ? AND doctor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $schedule_id, $doctor_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
}
