<?php
session_start();
include '../../../database/DBconnect.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit;
}

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/logs/php_errors.log');

// Validate and sanitize input
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$specialty = trim($_POST['specialty'] ?? '');
$years_experience = isset($_POST['years_experience']) ? (int)$_POST['years_experience'] : 0;
$clinic_address = trim($_POST['clinic_address'] ?? '');

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($gender) || empty($specialty) || $years_experience < 0) {
    header('Location: ../ManagePeople.php?error=invalid_input');
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
if (!$stmt) {
    error_log("DoctorAddProcess: Email check prepare failed: " . $conn->error);
    header('Location: ../ManagePeople.php?error=database_error&error_message=' . urlencode($conn->error));
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->fetch()) {
    $stmt->close();
    header('Location: ../ManagePeople.php?error=email_exists');
    exit;
}
$stmt->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Handle profile picture upload
$profile_picture = '';
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Doctors/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $file_path = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
        $profile_picture = 'Doctors/uploads/' . $file_name;
    } else {
        header('Location: ../ManagePeople.php?error=file_upload_error');
        exit;
    }
}

// Start transaction
$conn->begin_transaction();
try {
    // Insert into users table
    $current_time = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, gender, created_at, updated_at) VALUES (?, ?, ?, ?, 'doctor', ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Users insert prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssssss", $first_name, $last_name, $email, $hashed_password, $gender, $current_time, $current_time);
    if (!$stmt->execute()) {
        throw new Exception("Users insert execute failed: " . $stmt->error);
    }
    $user_id = $conn->insert_id;
    $stmt->close();

    // Insert into doctors table
    $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialty, years_experience, clinic_address, profile_picture) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Doctors insert prepare failed: " . $conn->error);
    }
    $stmt->bind_param("isiss", $user_id, $specialty, $years_experience, $clinic_address, $profile_picture);
    if (!$stmt->execute()) {
        throw new Exception("Doctors insert execute failed: " . $stmt->error);
    }
    $stmt->close();

    // Commit transaction
    $conn->commit();
    header('Location: ../ManagePeople.php?success=add_doctor');
    exit;
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("DoctorAddProcess: " . $e->getMessage());
    header('Location: ../ManagePeople.php?error=database_error&error_message=' . urlencode($e->getMessage()));
    exit;
}
?>