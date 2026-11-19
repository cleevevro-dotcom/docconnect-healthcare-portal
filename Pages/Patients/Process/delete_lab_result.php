<?php
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die('Unauthorized access.');
}

$patient_id = $_SESSION['user_id'];

if (!isset($_POST['lab_result_id']) || !is_numeric($_POST['lab_result_id'])) {
    die('Invalid request.');
}

$lab_result_id = (int)$_POST['lab_result_id'];

// Fetch the file path to delete the file from server
$stmt = $conn->prepare("SELECT file_path FROM lab_results WHERE lab_result_id = ? AND patient_id = ?");
$stmt->bind_param("ii", $lab_result_id, $patient_id);
$stmt->execute();
$stmt->bind_result($file_path);
if (!$stmt->fetch()) {
    $stmt->close();
    die('Lab result not found or access denied.');
}
$stmt->close();

// Delete the file from server
$file_full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path;
if (file_exists($file_full_path)) {
    unlink($file_full_path);
}

// Delete the record from database
$stmt = $conn->prepare("DELETE FROM lab_results WHERE lab_result_id = ? AND patient_id = ?");
$stmt->bind_param("ii", $lab_result_id, $patient_id);
$stmt->execute();
$stmt->close();

// Redirect back with success message
header('Location: ../Records.php?success=lab_result_deleted');
exit;
