<?php

$host="localhost";
$user="root";
$password="";
$database="users_db";
$conn=new mysqli($host, $user, $password, $database);

if($conn->connect_error){
    die("Connection Failed: ".$conn->connect_error);
}

// Function to get current semester ID from active school year
function getCurrentSemester($conn) {
    $query = "SELECT s.id FROM school_year sy 
              JOIN semesters s ON sy.semester = s.semester_number 
              WHERE sy.is_current = TRUE LIMIT 1";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['id'];
    }
    
    // Fallback: if no current school year or semesters table doesn't exist, 
    // try to get semester ID 1 (assuming it exists)
    $fallback_query = "SELECT id FROM semesters WHERE semester_number = 1 LIMIT 1";
    $fallback_result = $conn->query($fallback_query);
    if ($fallback_result && $fallback_row = $fallback_result->fetch_assoc()) {
        return (int)$fallback_row['id'];
    }
    
    return 1; // Default fallback
}

// Function to get current semester name from active school year
function getCurrentSemesterName($conn) {
    $query = "SELECT s.semester_name FROM school_year sy 
              JOIN semesters s ON sy.semester = s.semester_number 
              WHERE sy.is_current = TRUE LIMIT 1";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['semester_name'];
    }
    
    // Fallback: if no current school year or semesters table doesn't exist, 
    // try to get semester name for semester 1
    $fallback_query = "SELECT semester_name FROM semesters WHERE semester_number = 1 LIMIT 1";
    $fallback_result = $conn->query($fallback_query);
    if ($fallback_result && $fallback_row = $fallback_result->fetch_assoc()) {
        return $fallback_row['semester_name'];
    }
    
    return 'First Semester'; // Default fallback
}

return $conn;

?>
