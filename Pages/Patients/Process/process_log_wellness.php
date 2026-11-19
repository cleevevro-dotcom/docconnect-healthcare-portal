<?php
session_start();
require '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// Debug: Log user_id
error_log("User ID: $user_id");

// Check if patient record exists for this user
$checkStmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    // Determine if patient_id is auto-incremented
    $schemaStmt = $conn->query("SHOW COLUMNS FROM patients WHERE Field = 'patient_id'");
    $schema = $schemaStmt->fetch_assoc();
    $is_auto_increment = strpos($schema['Extra'], 'auto_increment') !== false;

    if ($is_auto_increment) {
        // Insert with auto-incremented patient_id
        $insertPatientStmt = $conn->prepare("
            INSERT INTO patients (user_id, date_of_birth, height_cm, weight_kg, blood_type, bmi, profile_picture)
            VALUES (?, NULL, NULL, NULL, NULL, NULL, NULL)
        ");
        $insertPatientStmt->bind_param("i", $user_id);
    } else {
        // Generate a new patient_id
        $idStmt = $conn->query("SELECT MAX(patient_id) as max_id FROM patients");
        $row = $idStmt->fetch_assoc();
        $new_patient_id = ($row['max_id'] ?? 0) + 1;

        $insertPatientStmt = $conn->prepare("
            INSERT INTO patients (patient_id, user_id, date_of_birth, height_cm, weight_kg, blood_type, bmi, profile_picture)
            VALUES (?, ?, NULL, NULL, NULL, NULL, NULL, NULL)
        ");
        $insertPatientStmt->bind_param("ii", $new_patient_id, $user_id);
    }

    if ($insertPatientStmt->execute()) {
        // Get the new patient_id
        $patient_id = $is_auto_increment ? $conn->insert_id : $new_patient_id;
        error_log("New patient record created with patient_id: $patient_id");
    } else {
        $_SESSION['error'] = "Error creating patient record: " . $insertPatientStmt->error;
        error_log("Patient insert error: " . $insertPatientStmt->error);
        $insertPatientStmt->close();
        $checkStmt->close();
        header("Location: ../Home.php");
        exit;
    }
    $insertPatientStmt->close();
} else {
    // Use existing patient_id
    $row = $result->fetch_assoc();
    $patient_id = $row['patient_id'];
    error_log("Existing patient_id: $patient_id");
}
$checkStmt->close();

// Retrieve and sanitize form data
$log_date = $_POST['log_date'] ?? date('Y-m-d');

// Validate date format and prevent future dates
$log_date_obj = DateTime::createFromFormat('Y-m-d', $log_date);
$today = new DateTime('today');

if (!$log_date_obj || $log_date_obj > $today) {
    // Invalid date or future date: reset to today
    $log_date = $today->format('Y-m-d');
}

// Sanitize and validate numeric inputs
$water_liters = isset($_POST['water_liters']) && is_numeric($_POST['water_liters']) ? max(0, floatval($_POST['water_liters'])) : 0.0;
$sleep_hours = isset($_POST['sleep_hours']) && is_numeric($_POST['sleep_hours']) ? max(0, min(24, floatval($_POST['sleep_hours']))) : 0.0;
$exercise_minutes = isset($_POST['exercise_minutes']) && is_numeric($_POST['exercise_minutes']) ? max(0, intval($_POST['exercise_minutes'])) : 0;

// rady: Validate mood and exercise_type against allowed values
$allowed_moods = ['Feeling Good', 'Feeling Bad', 'Pain', 'Other'];
$allowed_exercises = ['Gym', 'Walk', 'Other', 'N/A'];

$mood = $_POST['mood'] ?? 'Feeling Good';
if (!in_array($mood, $allowed_moods, true)) {
    $mood = 'Other';
}

$exercise_type = $_POST['exercise_type'] ?? 'N/A';
if (!in_array($exercise_type, $allowed_exercises, true)) {
    $exercise_type = 'N/A';
}

// Debug: Log POST data
error_log("POST data: " . print_r($_POST, true));

// Check if a record exists for this patient and date
$stmt = $conn->prepare("SELECT id FROM patient_daily_wellness WHERE patient_id = ? AND log_date = ?");
$stmt->bind_param("is", $patient_id, $log_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing record
    $updateStmt = $conn->prepare("
        UPDATE patient_daily_wellness 
        SET water_liters = ?, mood = ?, sleep_hours = ?, exercise_type = ?, exercise_minutes = ?
        WHERE patient_id = ? AND log_date = ?
    ");

    // Update statement binding corrected
    $updateStmt->bind_param(
        "dsdssis", 
        $water_liters,  // double
        $mood,          // string
        $sleep_hours,   // double
        $exercise_type, // string
        $exercise_minutes, // integer
        $patient_id,    // integer
        $log_date       // string
    );

    if ($updateStmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Wellness data updated successfully.'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Error updating wellness data: ' . $updateStmt->error
        ];
        error_log("Update error: " . $updateStmt->error);
    }

    $updateStmt->close();
} else {
    // Insert new record
    $insertStmt = $conn->prepare("
        INSERT INTO patient_daily_wellness (patient_id, log_date, water_liters, mood, sleep_hours, exercise_type, exercise_minutes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $insertStmt->bind_param(
        "isdsdsi", 
        $patient_id,    // integer
        $log_date,      // string
        $water_liters,  // double
        $mood,          // string
        $sleep_hours,   // double
        $exercise_type, // string
        $exercise_minutes // integer
    );

    if ($insertStmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Wellness data saved successfully.'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Error saving wellness data: ' . $insertStmt->error
        ];
        error_log("Insert error: " . $insertStmt->error);
    }

    $insertStmt->close();
}

$stmt->close();

// Set proper JSON response headers
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>