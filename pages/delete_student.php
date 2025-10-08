<?php
session_start();
require_once '../connect.php';
require_once '../utils/notifications.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !isset($input['student_id']) || !isset($input['department'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$student_id = (int)$input['student_id'];
$department = $input['department'];

// Validate department
$valid_departments = ['JHS', 'SHS', 'College'];
if (!in_array($department, $valid_departments)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid department']);
    exit();
}

try {
    // Get current school year
    $school_year_query = "SELECT id FROM school_year WHERE is_current = 1 LIMIT 1";
    $school_year_result = $conn->query($school_year_query);
    $current_school_year_id = 1; // Default fallback
    
    if ($school_year_result && $school_year_result->num_rows > 0) {
        $school_year_row = $school_year_result->fetch_assoc();
        $current_school_year_id = $school_year_row['id'];
    }

    // Check if student has active sanctions
    $student_type = strtolower($department === 'College' ? 'college' : ($department === 'SHS' ? 'shs' : 'jhs'));
    
    $sanction_check_query = "SELECT id FROM student_sanctions 
                            WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
    $sanction_stmt = $conn->prepare($sanction_check_query);
    $sanction_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);
    $sanction_stmt->execute();
    $sanction_result = $sanction_stmt->get_result();
    
    if ($sanction_result->num_rows > 0) {
        // Student has active sanctions, cannot delete
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete student with active sanctions',
            'has_sanctions' => true
        ]);
        $sanction_stmt->close();
        exit();
    }
    $sanction_stmt->close();

    // Determine the correct table and delete the student
    $table_name = '';
    switch ($department) {
        case 'JHS':
            $table_name = 'jhs_students';
            break;
        case 'SHS':
            $table_name = 'shs_students';
            break;
        case 'College':
            $table_name = 'college_students';
            break;
    }

    // Fetch student name for logging before deletion
    $full_name = '';
    if ($table_name !== '') {
        $name_q = "SELECT firstName, middleName, lastName FROM $table_name WHERE id = ? LIMIT 1";
        $name_stmt = $conn->prepare($name_q);
        $name_stmt->bind_param('i', $student_id);
        if ($name_stmt->execute()) {
            $res = $name_stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $parts = array_filter([$row['firstName'] ?? '', $row['middleName'] ?? '', $row['lastName'] ?? '']);
                $full_name = trim(implode(' ', $parts));
            }
        }
        $name_stmt->close();
    }

    // Delete the student
    $delete_query = "DELETE FROM $table_name WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $student_id);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            // Log notification after successful deletion
            $dept_label = $department;
            $name_for_msg = $full_name !== '' ? $full_name : (string)$student_id;
            $name_for_msg_html = '<strong>' . htmlspecialchars($name_for_msg, ENT_QUOTES, 'UTF-8') . '</strong>';
            $message = $dept_label . ' student ' . $name_for_msg_html . ' has been removed.';
            logNotification($conn, 'student_deleted', $message);
            echo json_encode([
                'success' => true, 
                'message' => 'Student deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Student not found'
            ]);
        }
    } else {
        throw new Exception('Failed to delete student');
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
