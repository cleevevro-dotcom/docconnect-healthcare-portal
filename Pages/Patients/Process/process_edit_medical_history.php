<?php
session_start();
include '../../../database/DBconnect.php';

// Check if user is logged in and is a patient
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

// Validate POST data
if (
    !isset($_POST['condition_name']) || !is_array($_POST['condition_name']) ||
    !isset($_POST['history_id']) || !is_array($_POST['history_id']) ||
    !isset($_POST['diagnosis_date']) || !is_array($_POST['diagnosis_date']) ||
    !isset($_POST['recovery_date']) || !is_array($_POST['recovery_date'])
) {
    die('Invalid form submission.');
}

$condition_names = $_POST['condition_name'];
$history_ids = $_POST['history_id'];
$diagnosis_dates = $_POST['diagnosis_date'];
$recovery_dates = $_POST['recovery_date'];

if (
    count($condition_names) !== count($history_ids) ||
    count($condition_names) !== count($diagnosis_dates) ||
    count($condition_names) !== count($recovery_dates)
) {
    die('Mismatched form data.');
}

for ($i = 0; $i < count($condition_names); $i++) {
    $history_id = intval($history_ids[$i]);
    $condition_name = trim($condition_names[$i]);
    $diagnosis_date = trim($diagnosis_dates[$i]);
    $recovery_date = trim($recovery_dates[$i]);

    // Skip empty condition names (no meaningful record)
    if ($condition_name === '') {
        continue;
    }

    // Normalize empty dates to NULL
    $diagnosis_date = $diagnosis_date === '' ? null : $diagnosis_date;
    $recovery_date = $recovery_date === '' ? null : $recovery_date;

    if ($history_id > 0) {
        // Update existing record, ensure patient owns it
        $stmt = $conn->prepare("UPDATE medical_history SET condition_name = ?, diagnosis_date = ?, recovery_date = ? WHERE history_id = ? AND patient_id = ?");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("sssii", $condition_name, $diagnosis_date, $recovery_date, $history_id, $patient_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, condition_name, diagnosis_date, recovery_date) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("isss", $patient_id, $condition_name, $diagnosis_date, $recovery_date);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect back to Records.php with success message
header('Location: ../Records.php?success=medical_history_saved');
exit;
