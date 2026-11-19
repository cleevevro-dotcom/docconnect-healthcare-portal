<?php
session_start();
include '../../../database/DBconnect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function logError($message) {
    error_log("Lab Result Download Error: " . $message);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    logError("Unauthorized access attempt - User not logged in or not a patient");
    http_response_code(403);
    die('Unauthorized access.');
}

$user_id = $_SESSION['user_id'];
logError("Attempting download for user_id: " . $user_id);

// Get patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
if (!$stmt) {
    logError("Database prepare failed: " . $conn->error);
    die('Database error occurred.');
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$stmt->close();

if (!$patient) {
    logError("No patient record found for user_id: " . $user_id);
    http_response_code(404);
    die('Patient record not found.');
}

$patient_id = $patient['patient_id'];
logError("Found patient_id: " . $patient_id);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    logError("Invalid lab_result_id in request");
    http_response_code(400);
    die('Invalid request.');
}

$lab_result_id = (int)$_GET['id'];
logError("Processing lab_result_id: " . $lab_result_id);

// Fetch file info and verify ownership
$stmt = $conn->prepare("SELECT file_path, file_type FROM lab_results WHERE lab_result_id = ? AND patient_id = ?");
if (!$stmt) {
    logError("Database prepare failed for lab result query: " . $conn->error);
    die('Database error occurred.');
}

$stmt->bind_param("ii", $lab_result_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$lab_result = $result->fetch_assoc();
$stmt->close();

if (!$lab_result) {
    logError("No lab result found for lab_result_id: " . $lab_result_id . " and patient_id: " . $patient_id);
    http_response_code(404);
    die('File not found or access denied.');
}

logError("Found lab result with file_path: " . $lab_result['file_path']);

$file_full_path = $_SERVER['DOCUMENT_ROOT'] . $lab_result['file_path'];
logError("Full file path: " . $file_full_path);

if (!file_exists($file_full_path)) {
    logError("File does not exist at path: " . $file_full_path);
    http_response_code(404);
    die('File not found on server.');
}

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Send headers and output file
header('Content-Description: File Transfer');
header('Content-Type: ' . $lab_result['file_type']);
header('Content-Disposition: attachment; filename="' . basename($file_full_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_full_path));

logError("Attempting to send file: " . basename($file_full_path));
readfile($file_full_path);
exit;
