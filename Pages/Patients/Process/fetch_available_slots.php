<?php
session_start();
include '../../../database/DBconnect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$doctor_id = $_GET['doctor_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$doctor_id || !$date) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Enable error logging
error_log("Fetching slots for doctor_id: $doctor_id, date: $date");

// Validate date format
try {
    $appointment_date = new DateTime($date);
} catch (Exception $e) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Get doctor's schedule for the specific date
$stmt = $conn->prepare("
    SELECT start_time, end_time, is_off
    FROM doctor_schedule
    WHERE doctor_id = ?
    AND schedule_date = ?
");

if (!$stmt) {
    error_log("Failed to prepare schedule query: " . $conn->error);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("is", $doctor_id, $date);
if (!$stmt->execute()) {
    error_log("Failed to execute schedule query: " . $stmt->error);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$result = $stmt->get_result();
error_log("Schedule query result rows: " . $result->num_rows);

if ($result->num_rows === 0) {
    // If no specific schedule for this date, check weekly schedule
    $day_of_week = $appointment_date->format('w');
    error_log("No specific schedule found, checking weekly schedule for day: $day_of_week");
    
    $stmt = $conn->prepare("
        SELECT start_time, end_time, is_off
        FROM doctor_schedule
        WHERE doctor_id = ?
        AND day_of_week = ?
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare weekly schedule query: " . $conn->error);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("ii", $doctor_id, $day_of_week);
    if (!$stmt->execute()) {
        error_log("Failed to execute weekly schedule query: " . $stmt->error);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    
    $result = $stmt->get_result();
    error_log("Weekly schedule query result rows: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Doctor is not available on this date']);
        exit;
    }
}

$schedule = $result->fetch_assoc();
error_log("Found schedule: " . print_r($schedule, true));

if ($schedule['is_off']) {
    echo json_encode(['error' => 'Doctor is not available on this date']);
    exit;
}

// Generate time slots
$start_time = strtotime($schedule['start_time']);
$end_time = strtotime($schedule['end_time']);
$interval = 30 * 60; // 30 minutes in seconds

$slots = [];
$current_time = $start_time;

// First, get all booked appointments for this date
$booked_slots = [];
$booked_stmt = $conn->prepare("
    SELECT TIME_FORMAT(appointment_datetime, '%H:%i') as time_slot
    FROM appointments 
    WHERE doctor_id = ? 
    AND DATE(appointment_datetime) = ?
    AND status IN ('pending', 'confirmed')
");

if ($booked_stmt) {
    $booked_stmt->bind_param("is", $doctor_id, $date);
    if ($booked_stmt->execute()) {
        $booked_result = $booked_stmt->get_result();
        while ($row = $booked_result->fetch_assoc()) {
            $booked_slots[] = $row['time_slot'];
        }
    }
    $booked_stmt->close();
}

error_log("Booked slots: " . print_r($booked_slots, true));

while ($current_time < $end_time) {
    $time_slot = date('h:i A', $current_time);
    $time_24h = date('H:i', $current_time);
    
    // Check if this slot is booked
    $is_booked = in_array($time_24h, $booked_slots);
    
    error_log("Checking slot $time_24h - Booked: " . ($is_booked ? 'Yes' : 'No'));
    
    $slots[] = [
        'time' => $time_slot,
        'booked' => $is_booked
    ];
    
    $current_time += $interval;
}

echo json_encode(['slots' => $slots]);
