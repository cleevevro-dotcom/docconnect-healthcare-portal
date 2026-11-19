<?php
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die('Unauthorized access.');
}

$user_id = $_SESSION['user_id'];

// Get patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$stmt->close();

if (!$patient) {
    die('Patient record not found.');
}

$patient_id = $patient['patient_id'];

if (
    !isset($_POST['appointment_id']) || !is_array($_POST['appointment_id']) ||
    !isset($_POST['appointment_datetime']) || !is_array($_POST['appointment_datetime']) ||
    !isset($_POST['reason']) || !is_array($_POST['reason'])
) {
    die('Invalid form submission.');
}

$appointment_ids = $_POST['appointment_id'];
$appointment_datetimes = $_POST['appointment_datetime'];
$reasons = $_POST['reason'];

if (
    count($appointment_ids) !== count($appointment_datetimes) ||
    count($appointment_ids) !== count($reasons)
) {
    die('Mismatched form data.');
}

for ($i = 0; $i < count($appointment_ids); $i++) {
    $appointment_id = intval($appointment_ids[$i]);
    $appointment_datetime = trim($appointment_datetimes[$i]);
    $reason = trim($reasons[$i]);

    // Validate required fields
    if ($appointment_datetime === '' || $reason === '') {
        continue; // skip incomplete entries
    }

    if ($appointment_id > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE appointments SET appointment_datetime = ?, reason = ? WHERE appointment_id = ? AND patient_id = ?");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("ssii", $appointment_datetime, $reason, $appointment_id, $patient_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, appointment_datetime, reason) VALUES (?, ?, ?)");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("iss", $patient_id, $appointment_datetime, $reason);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ../Records.php?success=appointments_saved');
exit;
