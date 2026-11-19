<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();

try {
    session_start();
    
    // Test database connection first
    include '../../../database/DBconnect.php';
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Check if user is admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception("Unauthorized access");
    }

    // Log the start of the process
    error_log("Starting PatientAddProcess.php");

    // Log POST data
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));

    // Validate and sanitize input
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');
    $height_cm = !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : null;
    $weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;

    // Calculate BMI
    $bmi = null;
    if ($height_cm && $weight_kg && $height_cm > 0) {
        $height_m = $height_cm / 100;
        $bmi = round($weight_kg / ($height_m * $height_m), 2);
    }

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($gender) || empty($date_of_birth)) {
        throw new Exception("Required fields are missing");
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Email check prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt->close();
        throw new Exception("Email already exists");
    }
    $stmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Handle profile picture upload
    $profile_picture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(dirname(dirname(__FILE__))) . '/Patients/uploads/';
        
        // Log upload attempt
        error_log("Attempting to upload file to: " . $upload_dir);
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        // Check if directory is writable
        if (!is_writable($upload_dir)) {
            throw new Exception("Upload directory is not writable");
        }
        
        $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
        $file_path = $upload_dir . $file_name;
        
        // Log file details
        error_log("File details - Name: " . $file_name . ", Path: " . $file_path);
        
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
            throw new Exception("Failed to save uploaded file: " . error_get_last()['message']);
        }
        
        $profile_picture = 'Patients/uploads/' . $file_name;
        error_log("File uploaded successfully to: " . $profile_picture);
    }

    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into users table
        $current_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, gender, created_at, updated_at) VALUES (?, ?, ?, ?, 'patient', ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Users insert prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssss", $first_name, $last_name, $email, $hashed_password, $gender, $current_time, $current_time);
        
        if (!$stmt->execute()) {
            throw new Exception("Users insert execute failed: " . $stmt->error);
        }
        
        $user_id = $conn->insert_id;
        $stmt->close();

        // Insert into patients table
        $stmt = $conn->prepare("INSERT INTO patients (user_id, date_of_birth, height_cm, weight_kg, blood_type, bmi, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Patients insert prepare failed: " . $conn->error);
        }
        
        // Initialize variables to ensure they're not null
        $height_cm = $height_cm ?? 0;
        $weight_kg = $weight_kg ?? 0;
        $blood_type = $blood_type ?: '';
        $bmi = $bmi ?? 0;
        $profile_picture = $profile_picture ?: '';
        
        $stmt->bind_param("isddssd", $user_id, $date_of_birth, $height_cm, $weight_kg, $blood_type, $bmi, $profile_picture);
        
        if (!$stmt->execute()) {
            throw new Exception("Patients insert execute failed: " . $stmt->error);
        }
        
        $stmt->close();

        // Commit transaction
        $conn->commit();
        
        // Clear output buffer and redirect
        ob_end_clean();
        header('Location: ../ManagePeople.php?success=add_patient');
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in PatientAddProcess: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear output buffer and redirect with error
    ob_end_clean();
    header('Location: ../ManagePeople.php?error=database_error&error_message=' . urlencode($e->getMessage()));
    exit;
}
?>