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

// Validate required fields
if (!isset($_POST['test_name']) || !isset($_POST['result_date']) || !isset($_FILES['file_upload'])) {
    die('Missing required fields.');
}

$test_name = trim($_POST['test_name']);
$result_summary = trim($_POST['result_summary'] ?? '');
$result_date = trim($_POST['result_date']);

// Validate file upload
$file = $_FILES['file_upload'];
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    die('File upload failed.');
}

if (!in_array($file['type'], $allowed_types)) {
    die('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
}

if ($file['size'] > $max_size) {
    die('File size exceeds the 5MB limit.');
}

// Create upload directory if it doesn't exist
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Patients/uploads/lab_results/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die('Failed to create upload directory.');
    }
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'lab_result_' . $patient_id . '_' . uniqid() . '.' . $file_ext;
$file_path = $upload_dir . $new_filename;
$web_path = '/Healthcare_portal/Pages/Patients/uploads/lab_results/' . $new_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    die('Failed to save uploaded file.');
}

// Insert record into database
$stmt = $conn->prepare("INSERT INTO lab_results (patient_id, test_name, result_summary, result_date, file_path, file_type) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    // Clean up uploaded file if database insert fails
    unlink($file_path);
    die('Database prepare failed: ' . $conn->error);
}

$file_type = $file['type'];
$stmt->bind_param("isssss", $patient_id, $test_name, $result_summary, $result_date, $web_path, $file_type);

if (!$stmt->execute()) {
    // Clean up uploaded file if database insert fails
    unlink($file_path);
    die('Failed to save lab result record.');
}

$stmt->close();
$conn->close();

header('Location: ../Records.php?success=lab_result_uploaded');
exit;
