<?php
session_start();
include '../../../database/DBconnect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Enable error logging
error_log("Starting appointment submission process");

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    error_log("Unauthorized access attempt - User not logged in or not a patient");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check database connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Log all received data
error_log("Session data: " . print_r($_SESSION, true));
error_log("POST data: " . print_r($_POST, true));
error_log("Raw input: " . file_get_contents('php://input'));

// Get patient_id from patients table
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
if (!$stmt) {
    error_log("Failed to prepare patient query: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    error_log("Failed to execute patient query: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("No patient record found for user_id: " . $_SESSION['user_id']);
    echo json_encode(['success' => false, 'message' => 'Patient record not found']);
    exit;
}
$patient = $result->fetch_assoc();
$patient_id = $patient['patient_id'];

// Validate required fields
$doctor_id = $_POST['doctor_id'] ?? null;
$appointment_datetime = $_POST['appointment_datetime'] ?? null;
$reason = $_POST['reason'] ?? null;

error_log("Validated fields:");
error_log("doctor_id: " . print_r($doctor_id, true));
error_log("appointment_datetime: " . print_r($appointment_datetime, true));
error_log("reason: " . print_r($reason, true));

if (!$doctor_id || !$appointment_datetime || !$reason) {
    error_log("Missing required fields");
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate and format appointment datetime
try {
    $datetime = new DateTime($appointment_datetime);
    $appointment_datetime = $datetime->format('Y-m-d H:i:s');
    $appointment_date = $datetime->format('Y-m-d');
    $appointment_time = $datetime->format('H:i:s');
    
    error_log("Date processing details:");
    error_log("Original datetime: " . $appointment_datetime);
    error_log("Formatted date: " . $appointment_date);
    error_log("Formatted time: " . $appointment_time);
    
} catch (Exception $e) {
    error_log("Invalid datetime format: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Invalid date or time format']);
    exit;
}

// Check if the appointment is in the future
$now = new DateTime();
if ($datetime <= $now) {
    error_log("Attempted to book appointment in the past");
    echo json_encode(['success' => false, 'message' => 'Appointment must be scheduled for a future date and time']);
    exit;
}

// Check doctor's schedule
$stmt = $conn->prepare("
    SELECT start_time, end_time, is_off
    FROM doctor_schedule
    WHERE doctor_id = ?
    AND schedule_date = ?
");

if (!$stmt) {
    error_log("Failed to prepare schedule query: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param("is", $doctor_id, $appointment_date);
if (!$stmt->execute()) {
    error_log("Failed to execute schedule query: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$result = $stmt->get_result();
error_log("Schedule query result rows: " . $result->num_rows);

if ($result->num_rows === 0) {
    // Check weekly schedule
    $day_of_week = $datetime->format('w');
    error_log("No specific schedule found, checking weekly schedule for day: $day_of_week");
    
    $stmt = $conn->prepare("
        SELECT start_time, end_time, is_off
        FROM doctor_schedule
        WHERE doctor_id = ?
        AND day_of_week = ?
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare weekly schedule query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("ii", $doctor_id, $day_of_week);
    if (!$stmt->execute()) {
        error_log("Failed to execute weekly schedule query: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $result = $stmt->get_result();
    error_log("Weekly schedule query result rows: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        error_log("No schedule found for doctor");
        echo json_encode(['success' => false, 'message' => 'Doctor is not available on this date']);
        exit;
    }
}

$schedule = $result->fetch_assoc();
error_log("Found schedule: " . print_r($schedule, true));

if ($schedule['is_off']) {
    error_log("Doctor is marked as off");
    echo json_encode(['success' => false, 'message' => 'Doctor is not available on this date']);
    exit;
}

// Check if time is within schedule
$schedule_start = strtotime($schedule['start_time']);
$schedule_end = strtotime($schedule['end_time']);
$appointment_time_stamp = strtotime($appointment_time);

error_log("Time check details:");
error_log("Schedule start: " . date('H:i:s', $schedule_start));
error_log("Schedule end: " . date('H:i:s', $schedule_end));
error_log("Appointment time: " . date('H:i:s', $appointment_time_stamp));

if ($appointment_time_stamp < $schedule_start || $appointment_time_stamp >= $schedule_end) {
    error_log("Appointment time outside schedule");
    echo json_encode(['success' => false, 'message' => 'Appointment time is outside doctor\'s schedule']);
    exit;
}

// Check for existing bookings
$stmt = $conn->prepare("
    SELECT appointment_id, appointment_datetime, status, patient_id
    FROM appointments 
    WHERE doctor_id = ? 
    AND DATE(appointment_datetime) = ?
    AND TIME_FORMAT(appointment_datetime, '%H:%i') = TIME_FORMAT(?, '%H:%i')
    AND status IN ('pending', 'confirmed', 'cancelled')
");

if (!$stmt) {
    error_log("Failed to prepare booking check query: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_datetime);
if (!$stmt->execute()) {
    error_log("Failed to execute booking check query: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$result = $stmt->get_result();
error_log("Booking check query result rows: " . $result->num_rows);

if ($result->num_rows > 0) {
    $existing = $result->fetch_assoc();
    error_log("Found existing booking - ID: " . $existing['appointment_id'] . 
              ", DateTime: " . $existing['appointment_datetime'] . 
              ", Status: " . $existing['status']);

    // If the appointment is cancelled, update it
    if ($existing['status'] === 'cancelled') {
        $update_stmt = $conn->prepare("
            UPDATE appointments 
            SET patient_id = ?,
                reason = ?,
                status = 'pending',
                updated_at = NOW()
            WHERE appointment_id = ?
        ");

        if (!$update_stmt) {
            error_log("Failed to prepare update query: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        $update_stmt->bind_param("isi", $patient_id, $reason, $existing['appointment_id']);
        
        if ($update_stmt->execute()) {
            error_log("Successfully updated cancelled appointment - ID: " . $existing['appointment_id']);
            echo json_encode(['success' => true, 'message' => 'Appointment requested successfully']);
        } else {
            error_log("Failed to update appointment: " . $update_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Failed to request appointment. Please try again.']);
        }
        exit;
    } else {
        // If the appointment is pending or confirmed, it's already booked
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
        exit;
    }
}

// If no existing appointment found, insert a new one
$stmt = $conn->prepare("
    INSERT INTO appointments (
        patient_id, 
        doctor_id, 
        appointment_datetime, 
        reason, 
        status, 
        created_at
    ) VALUES (?, ?, ?, ?, 'pending', NOW())
");

if (!$stmt) {
    error_log("Failed to prepare appointment insertion query: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

error_log("Attempting to insert new appointment with values:");
error_log("Patient ID: " . $patient_id);
error_log("Doctor ID: " . $doctor_id);
error_log("Appointment DateTime: " . $appointment_datetime);
error_log("Reason: " . $reason);

$stmt->bind_param("iiss", $patient_id, $doctor_id, $appointment_datetime, $reason);

try {
    if ($stmt->execute()) {
        $appointment_id = $conn->insert_id;
        error_log("New appointment successfully inserted with ID: " . $appointment_id);
        echo json_encode(['success' => true, 'message' => 'Appointment requested successfully']);
    } else {
        error_log("Appointment insertion failed: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to request appointment. Please try again.']);
    }
} catch (Exception $e) {
    error_log("Exception during appointment insertion: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to request appointment. Please try again.']);
}
?>