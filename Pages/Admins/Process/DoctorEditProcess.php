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
$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$specialty = trim($_POST['specialty'] ?? '');
$years_experience = isset($_POST['years_experience']) ? (int)$_POST['years_experience'] : 0;
$clinic_address = trim($_POST['clinic_address'] ?? '');

// Validate required fields
if (empty($doctor_id) || empty($user_id) || empty($first_name) || empty($last_name) || empty($email) || empty($gender)) {
    header('Location: ../ManagePeople.php?error=invalid_input');
    exit;
}

// Validate years of experience if provided
if ($years_experience < 0) {
    header('Location: ../ManagePeople.php?error=invalid_input');
    exit;
}

// Check if email is unique (excluding current user)
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
if (!$stmt) {
    error_log("DoctorEditProcess: Email check prepare failed: " . $conn->error);
    header('Location: ../ManagePeople.php?error=database_error&error_message=' . urlencode($conn->error));
    exit;
}
$stmt->bind_param("si", $email, $user_id);
$stmt->execute();
if ($stmt->fetch()) {
    $stmt->close();
    header('Location: ../ManagePeople.php?error=email_exists');
    exit;
}
$stmt->close();

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
    // Update users table
    $current_time = date('Y-m-d H:i:s');
    $sql_user = "UPDATE users SET first_name = ?, last_name = ?, email = ?, gender = ?, updated_at = ?";
    $params = [$first_name, $last_name, $email, $gender, $current_time];
    $types = "sssss";
    
    // Update password only if provided
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql_user .= ", password_hash = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }
    
    $sql_user .= " WHERE user_id = ?";
    $params[] = $user_id;
    $types .= "i";
    
    $stmt_user = $conn->prepare($sql_user);
    if (!$stmt_user) {
        throw new Exception("Users update prepare failed: " . $conn->error);
    }
    $stmt_user->bind_param($types, ...$params);
    if (!$stmt_user->execute()) {
        throw new Exception("Users update execute failed: " . $stmt_user->error);
    }
    $stmt_user->close();

    // Update doctors table
    $sql_doctor = "UPDATE doctors SET specialty = ?, years_experience = ?, clinic_address = ?";
    $doctor_params = [$specialty, $years_experience, $clinic_address];
    $doctor_types = "sis";
    
    if (!empty($profile_picture)) {
        $sql_doctor .= ", profile_picture = ?";
        $doctor_params[] = $profile_picture;
        $doctor_types .= "s";
    }
    
    $sql_doctor .= " WHERE doctor_id = ?";
    $doctor_params[] = $doctor_id;
    $doctor_types .= "i";
    
    $stmt_doctor = $conn->prepare($sql_doctor);
    if (!$stmt_doctor) {
        throw new Exception("Doctors update prepare failed: " . $conn->error);
    }
    $stmt_doctor->bind_param($doctor_types, ...$doctor_params);
    if (!$stmt_doctor->execute()) {
        throw new Exception("Doctors update execute failed: " . $stmt_doctor->error);
    }
    $stmt_doctor->close();

    // Commit transaction
    $conn->commit();
    header('Location: ../ManagePeople.php?edit_success=1');
    exit;
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("DoctorEditProcess: " . $e->getMessage());
    header('Location: ../ManagePeople.php?error=database_error&error_message=' . urlencode($e->getMessage()));
    exit;
}
?>