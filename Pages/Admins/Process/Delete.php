<?php
// delete.php
include '../../../database/DBconnect.php';  // Adjust the path to your DB connection file

// Set header to return JSON
header('Content-Type: application/json');

// Check if 'type' and 'id' parameters are set
if (isset($_GET['type'], $_GET['id'])) {
    $type = $_GET['type'];  // Expected values: 'doctor' or 'patient'
    $id = (int)$_GET['id'];

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    // Determine table and primary key column based on type
    if ($type === 'doctor') {
        $table = 'doctors';
        $idColumn = 'doctor_id';
    } elseif ($type === 'patient') {
        $table = 'patients';
        $idColumn = 'patient_id';
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type specified.']);
        exit;
    }

    // Begin transaction for safe deletion
    $conn->begin_transaction();

    try {
        // Retrieve user_id associated with the doctor or patient
        $stmt = $conn->prepare("SELECT user_id FROM $table WHERE $idColumn = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($user_id);
        if (!$stmt->fetch()) {
            $stmt->close();
            throw new Exception(ucfirst($type) . " not found.");
        }
        $stmt->close();

        if ($type === 'patient') {
            // Delete patient's doctor reviews
            $stmt = $conn->prepare("DELETE FROM doctor_reviews WHERE patient_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete patient's medical history
            $stmt = $conn->prepare("DELETE FROM medical_history WHERE patient_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete patient's lab results
            $stmt = $conn->prepare("DELETE FROM lab_results WHERE patient_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete patient's lifestyle recommendations
            $stmt = $conn->prepare("DELETE FROM lifestyle_recommendations WHERE patient_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete patient's immunization records
            $stmt = $conn->prepare("DELETE FROM immunizations WHERE patient_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete patient's appointments
            $stmt = $conn->prepare("DELETE FROM appointments WHERE patient_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete patient's messages
            $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $stmt->close();
        } else if ($type === 'doctor') {
            // Delete doctor's reviews
            $stmt = $conn->prepare("DELETE FROM doctor_reviews WHERE doctor_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete doctor's appointments
            $stmt = $conn->prepare("DELETE FROM appointments WHERE doctor_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete doctor's messages
            $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        // Delete from doctors or patients table
        $stmt = $conn->prepare("DELETE FROM $table WHERE $idColumn = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to delete " . $type . ".");
        }
        $stmt->close();

        // Delete corresponding user record
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to delete user record.");
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();

        // Return success response
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' deleted successfully.']);
        exit;

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => "Error deleting $type: " . $e->getMessage()]);
        exit;
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Required parameters not provided.']);
    exit;
}
?>
