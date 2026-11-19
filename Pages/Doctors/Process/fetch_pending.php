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

// Fetch pending appointments
$sql = "SELECT a.appointment_id, u.first_name, u.last_name, a.appointment_datetime, a.reason 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u ON p.user_id = u.user_id
        WHERE a.doctor_id = ? AND a.status = 'pending'
        ORDER BY a.appointment_datetime ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$res = $stmt->get_result();

$pending = [];
while ($row = $res->fetch_assoc()) {
    $pending[] = [
        'appointment_id' => $row['appointment_id'],
        'patient_name' => $row['first_name'] . ' ' . $row['last_name'],
        'appointment_datetime' => $row['appointment_datetime'],
        'reason' => $row['reason']
    ];
}

header('Content-Type: application/json');
echo json_encode($pending);
