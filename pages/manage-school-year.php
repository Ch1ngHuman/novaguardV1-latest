<?php
require_once '../connect.php';
session_start();

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' && isset($_POST['new_school_year'])) {
            // Validate school year format (YYYY-YYYY)
            $new_year = $_POST['new_school_year'];
            if (preg_match('/^\d{4}-\d{4}$/', $new_year)) {
                // Update current school year to not current
                $update_query = "UPDATE school_year SET is_current = FALSE WHERE is_current = TRUE";
                $conn->query($update_query);
                
                // Insert new school year
                $stmt = $conn->prepare("INSERT INTO school_year (school_year, is_current) VALUES (?, TRUE)");
                $stmt->bind_param("s", $new_year);
                if ($stmt->execute()) {
                    $message = "New school year {$new_year} created successfully.";
                    $message_type = "success";
                } else {
                    $message = "Error creating new school year.";
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Invalid school year format. Please use YYYY-YYYY format.";
                $message_type = "error";
            }
        } elseif ($_POST['action'] === 'set_current' && isset($_POST['school_year_id'])) {
            // Set selected school year as current
            $id = (int)$_POST['school_year_id'];
            $semester = isset($_POST['semester']) ? (int)$_POST['semester'] : 1;

            // Check if this is already the current school year
            $current_check = "SELECT is_current FROM school_year WHERE id = ?";
            $check_stmt = $conn->prepare($current_check);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $is_current = $check_stmt->get_result()->fetch_assoc()['is_current'];
            $check_stmt->close();

            if ($is_current) {
                // Just update the semester for current school year
                $stmt = $conn->prepare("UPDATE school_year SET semester = ? WHERE id = ?");
                $stmt->bind_param("ii", $semester, $id);
                if ($stmt->execute()) {
                    $message = "Semester updated successfully to Semester " . $semester . ".";
                    $message_type = "success";
                } else {
                    $message = "Error updating semester.";
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                // Update all to not current
                $conn->query("UPDATE school_year SET is_current = FALSE");

                // Set selected one as current and update semester
                $stmt = $conn->prepare("UPDATE school_year SET is_current = TRUE, semester = ? WHERE id = ?");
                $stmt->bind_param("ii", $semester, $id);
                if ($stmt->execute()) {
                    $message = "Current school year updated successfully for Semester " . $semester . ".";
                    $message_type = "success";
                } else {
                    $message = "Error updating current school year.";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch all school years
$query = "SELECT id, school_year, is_current, semester FROM school_year ORDER BY school_year DESC";
$result = $conn->query($query);
$school_years = $result->fetch_all(MYSQLI_ASSOC);

// Fetch current school year
$query = "SELECT school_year FROM school_year WHERE is_current = TRUE LIMIT 1";
$result = $conn->query($query);
$current_year = $result->fetch_assoc()['school_year'] ?? '2025-2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage School Year</title>
    <link rel="stylesheet" href="../styles/style.css">
    <style>
        .school-year-list {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .school-year-list th, .school-year-list td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .school-year-list th {
            background-color: #667eea;
            color: white;
        }
        .current {
            background-color: #e8f5e9;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        /* Confirmation Modal Styles */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .confirmation-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .confirmation-message {
            font-size: 1.2em;
            margin-bottom: 25px;
            color: #333;
        }

        .confirmation-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm-yes {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-confirm-yes:hover {
            background: #45a049;
        }

        .btn-confirm-no {
            background: #757575;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-confirm-no:hover {
            background: #616161;
        }
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-message" id="confirmationMessage">
                Are you sure you want to create this new school year?
            </div>
            <div class="confirmation-buttons">
                <button class="btn-confirm-yes" id="confirmYes">Yes</button>
                <button class="btn-confirm-no" id="confirmNo">No</button>
            </div>
        </div>
    </div>

    <div class="index-page">
        <div class="icon-back-wrapper top20">
            <a href="settings.php" class="icon-back-btn purple" aria-label="Back to Dashboard">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
        
        <h1>Manage School Year</h1>

        <div style="max-width: 800px; margin: 50px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <?php if (isset($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>



            <!-- Create New School Year -->
            <form method="POST" id="createForm" style="margin-top: 20px;">
                <input type="hidden" name="action" value="create">
                <div style="margin-bottom: 15px;">
                    <label for="new_school_year">Create New School Year:</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="new_school_year" name="new_school_year" 
                               pattern="\d{4}-\d{4}" 
                               placeholder="YYYY-YYYY"
                               required
                               style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <button type="button" id="createBtn" class="btn">Create New Year</button>
                    </div>
                    <small style="color: #666;">Format: YYYY-YYYY (e.g., 2025-2026)</small>
                </div>
            </form>

            <!-- School Years List -->
            <h3 style="margin-top: 30px;">School Years List</h3>
            <table class="school-year-list">
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($school_years as $year): ?>
                        <tr class="<?php echo $year['is_current'] ? 'current' : ''; ?>">
                            <td><?php echo htmlspecialchars($year['school_year']); ?></td>
                            <td><?php echo $year['is_current'] ? 'Current' : 'Inactive'; ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="set_current">
                                    <input type="hidden" name="school_year_id" value="<?php echo $year['id']; ?>">
                                    <select name="semester" style="padding: 4px; border-radius: 3px; border: 1px solid #ccc; margin-right: 8px;">
                                        <option value="1" <?php echo ($year['semester'] == 1) ? 'selected' : ''; ?>>Semester 1</option>
                                        <option value="2" <?php echo ($year['semester'] == 2) ? 'selected' : ''; ?>>Semester 2</option>
                                    </select>
                                    <?php if (!$year['is_current']): ?>
                                        <button type="submit" class="btn">Set as Current</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn" style="background: #28a745;">Update Semester</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            // Confirmation modal functionality
            document.addEventListener('DOMContentLoaded', function() {
                const createBtn = document.getElementById('createBtn');
                const createForm = document.getElementById('createForm');
                const newSchoolYearInput = document.getElementById('new_school_year');
                const modal = document.getElementById('confirmationModal');
                const confirmYes = document.getElementById('confirmYes');
                const confirmNo = document.getElementById('confirmNo');
                const confirmationMessage = document.getElementById('confirmationMessage');

                // Handle create button click
                createBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const schoolYear = newSchoolYearInput.value.trim();
                    
                    // Validate input
                    if (!schoolYear) {
                        alert('Please enter a school year.');
                        return;
                    }
                    
                    if (!/^\d{4}-\d{4}$/.test(schoolYear)) {
                        alert('Please enter a valid school year format (YYYY-YYYY).');
                        return;
                    }
                    
                    // Update confirmation message
                    confirmationMessage.innerHTML = `Are you sure you want to create the school year <strong>"${schoolYear}"</strong>?`;
                    
                    // Show modal
                    modal.style.display = 'flex';
                });

                // Handle Yes button click
                confirmYes.addEventListener('click', function() {
                    modal.style.display = 'none';
                    createForm.submit();
                });

                // Handle No button click
                confirmNo.addEventListener('click', function() {
                    modal.style.display = 'none';
                });

                // Close modal when clicking outside
                modal.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });
        </script>
