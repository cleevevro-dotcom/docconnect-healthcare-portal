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
    !isset($_POST['vaccine_name']) || !is_array($_POST['vaccine_name']) ||
    !isset($_POST['immunization_id']) || !is_array($_POST['immunization_id']) ||
    !isset($_POST['date_administered']) || !is_array($_POST['date_administered']) ||
    !isset($_POST['status']) || !is_array($_POST['status'])
) {
    die('Invalid form submission.');
}

$vaccine_names = $_POST['vaccine_name'];
$immunization_ids = $_POST['immunization_id'];
$date_administered = $_POST['date_administered'];
$statuses = $_POST['status'];

if (
    count($vaccine_names) !== count($immunization_ids) ||
    count($vaccine_names) !== count($date_administered) ||
    count($vaccine_names) !== count($statuses)
) {
    die('Mismatched form data.');
}

for ($i = 0; $i < count($vaccine_names); $i++) {
    $immunization_id = intval($immunization_ids[$i]);
    $vaccine_name = trim($vaccine_names[$i]);
    $date = trim($date_administered[$i]);
    $status = trim($statuses[$i]);

    // Skip empty vaccine names
    if ($vaccine_name === '') {
        continue;
    }

    // Normalize empty dates to NULL
    $date = $date === '' ? null : $date;

    if ($immunization_id > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE immunizations SET vaccine_name = ?, date_administered = ?, status = ? WHERE immunization_id = ? AND patient_id = ?");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("sssii", $vaccine_name, $date, $status, $immunization_id, $patient_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO immunizations (patient_id, vaccine_name, date_administered, status) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param("isss", $patient_id, $vaccine_name, $date, $status);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ../Records.php?success=immunizations_saved');
exit;
