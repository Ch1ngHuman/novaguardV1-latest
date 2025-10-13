<?php
require_once '../connect.php';

/**
 * Archive violation data for a specific school year
 * This function should be called when transitioning to a new school year
 */
function archiveSchoolYearData($conn, $school_year_id) {
    $success_messages = [];
    $error_messages = [];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get school year info
        $year_query = "SELECT school_year FROM school_year WHERE id = ?";
        $year_stmt = $conn->prepare($year_query);
        $year_stmt->bind_param('i', $school_year_id);
        $year_stmt->execute();
        $year_info = $year_stmt->get_result()->fetch_assoc();
        
        if (!$year_info) {
            throw new Exception("School year not found");
        }
        
        $school_year_text = $year_info['school_year'];
        
        // Archive JHS violations
        $jhs_archive = "INSERT INTO violation_history_archive
                       (student_id, student_name, student_type, student_level, violation_type,
                        violation_description, date_recorded, recorded_by, school_year_id, school_year, sanction_hours)
                       SELECT
                           v.student_id,
                           CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.lastName),
                           'jhs',
                           s.year_lvl,
                           v.violation_type,
                           v.description,
                           v.date_recorded,
                           v.recorded_by,
                           v.school_year_id,
                           ?,
                           COALESCE(san.sanction_hours, '0')
                       FROM hs_violations v
                       JOIN jhs_students s ON v.student_id = s.id
                       LEFT JOIN student_sanctions san ON v.student_id = san.student_id
                           AND san.student_type = 'jhs' AND san.school_year_id = v.school_year_id
                       WHERE v.school_year_id = ?";
        
        $stmt = $conn->prepare($jhs_archive);
        $stmt->bind_param('si', $school_year_text, $school_year_id);
        $stmt->execute();
        $jhs_archived = $stmt->affected_rows;
        $success_messages[] = "Archived $jhs_archived JHS violations";
        
        // Archive SHS violations
        $shs_archive = "INSERT INTO violation_history_archive 
                       (student_id, student_name, student_type, student_level, student_course_strand, violation_type, 
                        violation_description, date_recorded, recorded_by, school_year_id, school_year, sanction_hours)
                       SELECT 
                           v.student_id,
                           CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.lastName),
                           'shs',
                           s.year_lvl,
                           s.strand,
                           v.violation_type,
                           v.description,
                           v.date_recorded,
                           v.recorded_by,
                           v.school_year_id,
                           ?,
                           COALESCE(san.sanction_hours, '0')
                       FROM shs_violations v
                       JOIN shs_students s ON v.student_id = s.id
                       LEFT JOIN student_sanctions san ON v.student_id = san.student_id 
                           AND san.student_type = 'shs' AND san.school_year_id = v.school_year_id
                       WHERE v.school_year_id = ?";
        
        $stmt = $conn->prepare($shs_archive);
        $stmt->bind_param('si', $school_year_text, $school_year_id);
        $stmt->execute();
        $shs_archived = $stmt->affected_rows;
        $success_messages[] = "Archived $shs_archived SHS violations";
        
        // Archive College violations
        $college_archive = "INSERT INTO violation_history_archive 
                           (student_id, student_name, student_type, student_level, student_course_strand, violation_type, 
                            violation_description, date_recorded, recorded_by, school_year_id, school_year, sanction_hours)
                           SELECT 
                               v.student_id,
                               CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.LastName),
                               'college',
                               s.year_lvl,
                               s.course,
                               v.violation_type,
                               v.description,
                               v.date_recorded,
                               v.recorded_by,
                               v.school_year_id,
                               ?,
                               COALESCE(san.sanction_hours, '0')
                           FROM college_violations v
                           JOIN college_students s ON v.student_id = s.id
                           LEFT JOIN student_sanctions san ON v.student_id = san.student_id 
                               AND san.student_type = 'college' AND san.school_year_id = v.school_year_id
                           WHERE v.school_year_id = ?";
        
        $stmt = $conn->prepare($college_archive);
        $stmt->bind_param('si', $school_year_text, $school_year_id);
        $stmt->execute();
        $college_archived = $stmt->affected_rows;
        $success_messages[] = "Archived $college_archived College violations";
        
        // Archive College CRIM violations
        $college_crim_archive = "INSERT INTO violation_history_archive 
                                (student_id, student_name, student_type, student_level, student_course_strand, violation_type, 
                                 violation_description, date_recorded, recorded_by, school_year_id, school_year, sanction_hours)
                                SELECT 
                                    v.student_id,
                                    CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.LastName),
                                    'college',
                                    s.year_lvl,
                                    s.course,
                                    v.violation_type,
                                    v.description,
                                    v.date_recorded,
                                    v.recorded_by,
                                    v.school_year_id,
                                    ?,
                                    COALESCE(san.sanction_hours, '0')
                                FROM college_crim_violations v
                                JOIN college_students s ON v.student_id = s.id
                                LEFT JOIN student_sanctions san ON v.student_id = san.student_id 
                                    AND san.student_type = 'college' AND san.school_year_id = v.school_year_id
                                WHERE v.school_year_id = ?";
        
        $stmt = $conn->prepare($college_crim_archive);
        $stmt->bind_param('si', $school_year_text, $school_year_id);
        $stmt->execute();
        $college_crim_archived = $stmt->affected_rows;
        $success_messages[] = "Archived $college_crim_archived College CRIM violations";
        
        // Archive student summaries for JHS (omit last_violation_date column — removed from schema)
        $jhs_summary = "INSERT INTO student_summary_archive 
                       (student_id, student_name, student_type, student_level, school_year_id, 
                        school_year, total_violations, total_sanction_hours, first_violation_date)
                       SELECT 
                           s.id,
                           CONCAT(s.firstName, ' ', COALESCE(s.middleName, ''), ' ', s.lastName),
                           'jhs',
                           s.year_lvl,
                           ?,
                           ?,
                           COUNT(v.id),
                           COALESCE(san.sanction_hours, '0'),
                           MIN(v.date_recorded)
                       FROM jhs_students s
                       LEFT JOIN hs_violations v ON s.id = v.student_id AND v.school_year_id = ?
                       LEFT JOIN student_sanctions san ON s.id = san.student_id AND san.student_type = 'jhs' AND san.school_year_id = ?
                       WHERE v.id IS NOT NULL
                       GROUP BY s.id
                       ON DUPLICATE KEY UPDATE
                           total_violations = VALUES(total_violations),
                           total_sanction_hours = VALUES(total_sanction_hours),
                           first_violation_date = VALUES(first_violation_date)";
        
        $stmt = $conn->prepare($jhs_summary);
        $stmt->bind_param('isii', $school_year_id, $school_year_text, $school_year_id, $school_year_id);
        $stmt->execute();
        $jhs_summary_count = $stmt->affected_rows;
        $success_messages[] = "Archived $jhs_summary_count JHS student summaries";
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'messages' => $success_messages,
            'total_archived' => $jhs_archived + $shs_archived + $college_archived + $college_crim_archived
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Check if a school year has already been archived
 */
function isSchoolYearArchived($conn, $school_year_id) {
    $check_query = "SELECT COUNT(*) as count FROM violation_history_archive WHERE school_year_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('i', $school_year_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

/**
 * Get archivable school years (not current and not already archived)
 */
function getArchivableSchoolYears($conn) {
    $query = "SELECT sy.id, sy.school_year 
              FROM school_year sy 
              WHERE sy.is_current = FALSE 
              AND sy.id NOT IN (
                  SELECT DISTINCT school_year_id 
                  FROM violation_history_archive
              )
              ORDER BY sy.school_year DESC";
    
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get statistics for archived data
 */
function getArchiveStatistics($conn) {
    $stats = [];
    
    // Total archived violations
    $total_query = "SELECT COUNT(*) as total FROM violation_history_archive";
    $result = $conn->query($total_query);
    $stats['total_violations'] = $result->fetch_assoc()['total'];
    
    // Archived school years
    $years_query = "SELECT COUNT(DISTINCT school_year_id) as years FROM violation_history_archive";
    $result = $conn->query($years_query);
    $stats['archived_years'] = $result->fetch_assoc()['years'];
    
    // By student type
    $type_query = "SELECT student_type, COUNT(*) as count 
                   FROM violation_history_archive 
                   GROUP BY student_type";
    $result = $conn->query($type_query);
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][$row['student_type']] = $row['count'];
    }
    
    return $stats;
}
?>
