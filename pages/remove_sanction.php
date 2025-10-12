<?php
session_start();
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if request is POST and has required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
$required_fields = ['student_id', 'student_type'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$student_id = (int)$input['student_id'];
$student_type = $input['student_type'];

// Validate student_type
$valid_types = ['jhs', 'shs', 'college'];
if (!in_array($student_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student type']);
    exit();
}

// Get current school year
$school_year_query = "SELECT id FROM school_year WHERE is_current = 1 LIMIT 1";
$school_year_result = $conn->query($school_year_query);
$current_school_year_id = 1; // Default fallback

if ($school_year_result && $school_year_result->num_rows > 0) {
    $school_year_row = $school_year_result->fetch_assoc();
    $current_school_year_id = $school_year_row['id'];
}

try {
    // Update sanction status to 'completed' instead of deleting
    $update_query = "UPDATE student_sanctions
                    SET status = 'completed', date_assigned = NOW()
                    WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);
    
    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            // Do not modify student_summary_archive here: archive should only be incremented when hours are added
            echo json_encode(['success' => true, 'message' => 'Sanction removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active sanction found to remove']);
        }
    } else {
        throw new Exception('Failed to remove sanction');
    }
    
    $update_stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
