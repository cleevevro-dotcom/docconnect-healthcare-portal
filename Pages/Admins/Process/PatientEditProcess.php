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
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$blood_type = trim($_POST['blood_type'] ?? '');
$height_cm = !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : null;
$weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;

// Validate required fields
if (empty($patient_id) || empty($user_id) || empty($first_name) || empty($last_name) || empty($email) || empty($gender)) {
    header('Location: ../ManagePeople.php?error=invalid_input');
    exit;
}

// Validate date of birth if provided
if (!empty($date_of_birth) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
    header('Location: ../ManagePeople.php?error=invalid_input');
    exit;
}

// Check if email is unique (excluding current user)
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
if (!$stmt) {
    error_log("PatientEditProcess: Email check prepare failed: " . $conn->error);
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
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Patients/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $file_path = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
        $profile_picture = 'Patients/uploads/' . $file_name;
    } else {
        header('Location: ../ManagePeople.php?error=file_upload_error');
        exit;
    }
}

// Calculate BMI if height and weight are provided
$bmi = null;
if ($height_cm && $weight_kg && $height_cm > 0) {
    $height_m = $height_cm / 100;
    $bmi = round($weight_kg / ($height_m * $height_m), 2);
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

    // Update patients table
    $sql_patient = "UPDATE patients SET date_of_birth = ?, blood_type = ?, height_cm = ?, weight_kg = ?, bmi = ?";
    $patient_params = [$date_of_birth, $blood_type, $height_cm, $weight_kg, $bmi];
    $patient_types = "ssdds";
    
    if (!empty($profile_picture)) {
        $sql_patient .= ", profile_picture = ?";
        $patient_params[] = $profile_picture;
        $patient_types .= "s";
    }
    
    $sql_patient .= " WHERE patient_id = ?";
    $patient_params[] = $patient_id;
    $patient_types .= "i";
    
    $stmt_patient = $conn->prepare($sql_patient);
    if (!$stmt_patient) {
        throw new Exception("Patients update prepare failed: " . $conn->error);
    }
    $stmt_patient->bind_param($patient_types, ...$patient_params);
    if (!$stmt_patient->execute()) {
        throw new Exception("Patients update execute failed: " . $stmt_patient->error);
    }
    $stmt_patient->close();

    // Commit transaction
    $conn->commit();
    header('Location: ../ManagePeople.php?edit_success=1');
    exit;
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("PatientEditProcess: " . $e->getMessage());
    header('Location: ../ManagePeople.php?error=database_error&error_message=' . urlencode($e->getMessage()));
    exit;
}
?>