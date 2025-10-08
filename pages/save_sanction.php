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
$required_fields = ['student_id', 'student_type', 'sanction_hours'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$student_id = (int)$input['student_id'];
$student_type = $input['student_type'];
$sanction_hours = $input['sanction_hours'];

// Validate student_type
$valid_types = ['jhs', 'shs', 'college'];
if (!in_array($student_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student type']);
    exit();
}

// Validate sanction_hours
$valid_hours = ['3 hours', '6 hours', '8 hours'];
if (!in_array($sanction_hours, $valid_hours)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid sanction hours']);
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

// Get current semester ID (for SHS and College only)
$current_semester_id = getCurrentSemester($conn);

try {
    // Extract numeric value from sanction_hours (e.g., "3 hours" -> 3)
    $new_hours_numeric = (int)filter_var($sanction_hours, FILTER_SANITIZE_NUMBER_INT);

    // Check if student already has an active sanction for this school year
    $check_query = "SELECT id, sanction_hours FROM student_sanctions
                    WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Student has existing active sanction - add to it
        $existing_sanction = $check_result->fetch_assoc();
        $existing_hours_numeric = (int)filter_var($existing_sanction['sanction_hours'], FILTER_SANITIZE_NUMBER_INT);

        // Calculate total hours (store as integer)
        $total_hours = $existing_hours_numeric + $new_hours_numeric;

        // Update existing sanction with total hours (numeric storage)
        // JHS doesn't use semester_id
        if ($student_type === 'jhs') {
            $update_query = "UPDATE student_sanctions
                            SET sanction_hours = ?, date_assigned = NOW(), status = 'active'
                            WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('iisi', $total_hours, $student_id, $student_type, $current_school_year_id);
        } else {
            // SHS and College use semester_id
            $update_query = "UPDATE student_sanctions
                            SET sanction_hours = ?, date_assigned = NOW(), status = 'active', semester_id = ?
                            WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('iiisi', $total_hours, $current_semester_id, $student_id, $student_type, $current_school_year_id);
        }

        if ($update_stmt->execute()) {
            $display_total = $total_hours . ' hours';
            $display_previous = $existing_hours_numeric . ' hours';
            echo json_encode([
                'success' => true,
                'message' => "Sanction added successfully. Total: {$display_total} (was {$display_previous}, added {$sanction_hours})",
                'total_hours' => $display_total
            ]);
        } else {
            throw new Exception('Failed to update sanction');
        }
        $update_stmt->close();
    } else {
        // Insert new sanction (numeric storage)
        // JHS doesn't use semester_id
        if ($student_type === 'jhs') {
            $insert_query = "INSERT INTO student_sanctions (student_id, student_type, sanction_hours, date_assigned, school_year_id, status)
                            VALUES (?, ?, ?, NOW(), ?, 'active')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('isii', $student_id, $student_type, $new_hours_numeric, $current_school_year_id);
        } else {
            // SHS and College use semester_id
            $insert_query = "INSERT INTO student_sanctions (student_id, student_type, sanction_hours, date_assigned, school_year_id, semester_id, status)
                            VALUES (?, ?, ?, NOW(), ?, ?, 'active')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('isiii', $student_id, $student_type, $new_hours_numeric, $current_school_year_id, $current_semester_id);
        }

        if ($insert_stmt->execute()) {
            $display_total = $new_hours_numeric . ' hours';
            echo json_encode([
                'success' => true,
                'message' => 'Sanction assigned successfully',
                'total_hours' => $display_total
            ]);
        } else {
            throw new Exception('Failed to assign sanction');
        }
        $insert_stmt->close();
    }

    $check_stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
