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
    // Extract numeric value from sanction_hours to reduce (e.g., "3 hours" -> 3)
    $hours_to_reduce = (int)filter_var($sanction_hours, FILTER_SANITIZE_NUMBER_INT);
    
    // Check if student has an active sanction for this school year
    $check_query = "SELECT id, sanction_hours FROM student_sanctions 
                    WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Student has existing active sanction - reduce from it
        $existing_sanction = $check_result->fetch_assoc();
        // sanction_hours is stored as integer; fallback to numeric parse if legacy
        $existing_hours_numeric = is_numeric($existing_sanction['sanction_hours'])
            ? (int)$existing_sanction['sanction_hours']
            : (int)filter_var($existing_sanction['sanction_hours'], FILTER_SANITIZE_NUMBER_INT);

        // Calculate remaining hours
        $remaining_hours = $existing_hours_numeric - $hours_to_reduce;
        
        if ($remaining_hours <= 0) {
            // Sanction completed - delete the sanction record and all violations
            $conn->begin_transaction();

            try {
                // Delete sanction record
                $delete_sanction_query = "DELETE FROM student_sanctions
                                        WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
                $delete_sanction_stmt = $conn->prepare($delete_sanction_query);
                $delete_sanction_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);

                if (!$delete_sanction_stmt->execute()) {
                    throw new Exception('Failed to delete completed sanction');
                }
                $delete_sanction_stmt->close();

                // Delete all violations for this student based on student type
                if ($student_type === 'shs') {
                    $delete_violations_query = "DELETE FROM shs_violations
                                              WHERE student_id = ? AND student_type = ? AND school_year_id = ?";
                    $delete_violations_stmt = $conn->prepare($delete_violations_query);
                    $delete_violations_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);

                    if (!$delete_violations_stmt->execute()) {
                        throw new Exception('Failed to delete SHS violations');
                    }
                    $delete_violations_stmt->close();
                } elseif ($student_type === 'jhs') {
                    $delete_violations_query = "DELETE FROM hs_violations
                                              WHERE student_id = ? AND student_type = ? AND school_year_id = ?";
                    $delete_violations_stmt = $conn->prepare($delete_violations_query);
                    $delete_violations_stmt->bind_param('isi', $student_id, $student_type, $current_school_year_id);

                    if (!$delete_violations_stmt->execute()) {
                        throw new Exception('Failed to delete JHS violations');
                    }
                    $delete_violations_stmt->close();
                } else { // college
                    // College tables do not have a student_type column; delete from both sources
                    $delete_college_query1 = "DELETE FROM college_violations
                                              WHERE student_id = ? AND school_year_id = ?";
                    $stmt1 = $conn->prepare($delete_college_query1);
                    $stmt1->bind_param('ii', $student_id, $current_school_year_id);
                    if (!$stmt1->execute()) {
                        throw new Exception('Failed to delete College violations');
                    }
                    $stmt1->close();

                    $delete_college_query2 = "DELETE FROM college_crim_violations
                                              WHERE student_id = ? AND school_year_id = ?";
                    $stmt2 = $conn->prepare($delete_college_query2);
                    $stmt2->bind_param('ii', $student_id, $current_school_year_id);
                    if (!$stmt2->execute()) {
                        throw new Exception('Failed to delete College CRIM violations');
                    }
                    $stmt2->close();
                }

                // Commit transaction
                $conn->commit();

                    // Do not modify student_summary_archive on sanction deletion here (archive should reflect only added hours)

                echo json_encode([
                    'success' => true,
                    'message' => "Sanction completed! All violations and sanctions have been cleared for this student.",
                    'remaining_hours' => '-'
                ]);

            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                throw $e;
            }
        } else {
            // Update with remaining hours (store integer; format for response)
            // JHS doesn't use semester_id
            if ($student_type === 'jhs') {
                $update_query = "UPDATE student_sanctions
                                SET sanction_hours = ?, date_assigned = NOW(), status = 'active'
                                WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param('iisi', $remaining_hours, $student_id, $student_type, $current_school_year_id);
            } else {
                // SHS and College use semester_id
                $update_query = "UPDATE student_sanctions
                                SET sanction_hours = ?, date_assigned = NOW(), status = 'active', semester_id = ?
                                WHERE student_id = ? AND student_type = ? AND school_year_id = ? AND status = 'active'";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param('iiisi', $remaining_hours, $current_semester_id, $student_id, $student_type, $current_school_year_id);
            }

            if ($update_stmt->execute()) {
                $remaining_display = $remaining_hours . ' hours';
                $previous_display = $existing_hours_numeric . ' hours';
                echo json_encode([
                    'success' => true,
                    'message' => "Sanction reduced successfully. Remaining: {$remaining_display} (was {$previous_display}, reduced {$sanction_hours})",
                    'remaining_hours' => $remaining_display
                ]);
            } else {
                throw new Exception('Failed to reduce sanction');
            }
            $update_stmt->close();
            // Do not modify student_summary_archive on sanction reduction (only increment on additions)
        }
    } else {
        // No active sanction found
        echo json_encode(['success' => false, 'message' => 'No active sanction found to reduce']);
    }
    
    $check_stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
