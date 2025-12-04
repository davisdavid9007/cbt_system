<?php
// File: admin/manage_staff.php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle staff addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $staff_id = trim($_POST['staff_id']);
    $full_name = trim($_POST['full_name']);
    $password_input = trim($_POST['password']);
    
    // Use custom password if provided, otherwise default to staff_id
    $password = !empty($password_input) ? password_hash($password_input, PASSWORD_DEFAULT) : password_hash($staff_id, PASSWORD_DEFAULT);
    
    $assigned_classes = $_POST['assigned_classes'] ?? [];
    $assigned_subjects = $_POST['assigned_subjects'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Insert staff basic info
        $stmt = $pdo->prepare("INSERT INTO staff (staff_id, password, full_name) VALUES (?, ?, ?)");
        $stmt->execute([$staff_id, $password, $full_name]);
        
        // Insert assigned classes
        $stmt = $pdo->prepare("INSERT INTO staff_classes (staff_id, class) VALUES (?, ?)");
        foreach ($assigned_classes as $class) {
            $stmt->execute([$staff_id, $class]);
        }
        
        // Insert assigned subjects
        $stmt = $pdo->prepare("INSERT INTO staff_subjects (staff_id, subject_id) VALUES (?, ?)");
        foreach ($assigned_subjects as $subject_id) {
            $stmt->execute([$staff_id, $subject_id]);
        }
        
        $pdo->commit();
        $message = "Staff member added successfully!";
        $message_type = "success";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $message = "Staff ID already exists!";
            $message_type = "error";
        } else {
            $message = "Error adding staff: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle staff update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $staff_id = trim($_POST['staff_id']);
    $original_staff_id = trim($_POST['original_staff_id']);
    $full_name = trim($_POST['full_name']);
    $password_input = trim($_POST['password']);
    $assigned_classes = $_POST['assigned_classes'] ?? [];
    $assigned_subjects = $_POST['assigned_subjects'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Update staff basic info
        if (!empty($password_input)) {
            $stmt = $pdo->prepare("UPDATE staff SET staff_id = ?, full_name = ?, password = ? WHERE staff_id = ?");
            $stmt->execute([$staff_id, $full_name, password_hash($password_input, PASSWORD_DEFAULT), $original_staff_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE staff SET staff_id = ?, full_name = ? WHERE staff_id = ?");
            $stmt->execute([$staff_id, $full_name, $original_staff_id]);
        }
        
        // Update assigned classes
        $stmt = $pdo->prepare("DELETE FROM staff_classes WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        
        $stmt = $pdo->prepare("INSERT INTO staff_classes (staff_id, class) VALUES (?, ?)");
        foreach ($assigned_classes as $class) {
            $stmt->execute([$staff_id, $class]);
        }
        
        // Update assigned subjects
        $stmt = $pdo->prepare("DELETE FROM staff_subjects WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        
        $stmt = $pdo->prepare("INSERT INTO staff_subjects (staff_id, subject_id) VALUES (?, ?)");
        foreach ($assigned_subjects as $subject_id) {
            $stmt->execute([$staff_id, $subject_id]);
        }
        
        $pdo->commit();
        $message = "Staff member updated successfully!";
        $message_type = "success";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $message = "Staff ID already exists!";
            $message_type = "error";
        } else {
            $message = "Error updating staff: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle staff deletion
if (isset($_GET['delete'])) {
    $staff_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ? AND role = 'staff'");
        $stmt->execute([$staff_id]);
        $message = "Staff member deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting staff: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get staff details for editing
$edit_staff = null;
if (isset($_GET['edit'])) {
    $staff_id = $_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT s.*, 
               GROUP_CONCAT(DISTINCT sc.class) as classes,
               GROUP_CONCAT(DISTINCT ss.subject_id) as subject_ids
        FROM staff s 
        LEFT JOIN staff_classes sc ON s.staff_id = sc.staff_id
        LEFT JOIN staff_subjects ss ON s.staff_id = ss.staff_id
        WHERE s.staff_id = ? AND s.role = 'staff'
        GROUP BY s.staff_id
    ");
    $stmt->execute([$staff_id]);
    $edit_staff = $stmt->fetch();
}

// Get all staff members with their classes and subjects
$staff_members = $pdo->query("
    SELECT s.*, 
           GROUP_CONCAT(DISTINCT sc.class) as classes,
           GROUP_CONCAT(DISTINCT sub.subject_name) as subjects
    FROM staff s 
    LEFT JOIN staff_classes sc ON s.staff_id = sc.staff_id
    LEFT JOIN staff_subjects ss ON s.staff_id = ss.staff_id
    LEFT JOIN subjects sub ON ss.subject_id = sub.id
    WHERE s.role = 'staff'
    GROUP BY s.staff_id
    ORDER BY s.full_name
")->fetchAll();

// Get subjects and classes for dropdowns
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();
$classes = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Mighty School For Valours</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #9b59b6 0%, #3498db 100%);
            min-height: 100vh;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .management-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }
        .management-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-row input, .form-row select {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
        }
        .password-note {
            background: #e7f3ff;
            padding: 0.75rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #2c3e50;
            border-left: 4px solid #3498db;
        }
        .multi-select-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .multi-select-section h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
        .checkbox-item label {
            margin: 0;
            font-weight: normal;
        }
        .add-btn, .update-btn {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s ease;
        }
        .update-btn {
            background: linear-gradient(45deg, #27ae60, #2ecc71);
        }
        .add-btn:hover, .update-btn:hover {
            transform: translateY(-2px);
        }
        .staff-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .staff-table th,
        .staff-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .staff-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .edit-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .edit-btn:hover {
            background: #2980b9;
        }
        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .delete-btn:hover {
            background: #c0392b;
        }
        .cancel-edit {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .cancel-edit:hover {
            background: #7f8c8d;
        }
        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 600;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #9b59b6;
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border: 2px solid #9b59b6;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            background: #9b59b6;
            color: white;
        }
        .classes-list, .subjects-list {
            font-size: 0.9rem;
            color: #666;
        }
        .classes-list span, .subjects-list span {
            display: inline-block;
            background: #e7f3ff;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            margin: 0.1rem;
        }
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .school-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .school-logo img {
            max-width: 50px;
            height: auto;
        }
        .school-logo span {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-logo">       
            <img src="../assets/logo.png" alt="School Logo">
            <span>Admin Panel - Manage Staff</span>
        </div>
    </div>

    <div class="container">
        <div class="management-card">
            <h2><?php echo $edit_staff ? '✏️ Edit Staff Member' : '👨‍🏫 Add New Staff Member'; ?></h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($edit_staff): ?>
                <a href="manage_staff.php" class="cancel-edit">❌ Cancel Edit</a>
            <?php endif; ?>

            <form method="POST">
                <?php if ($edit_staff): ?>
                    <input type="hidden" name="original_staff_id" value="<?php echo htmlspecialchars($edit_staff['staff_id']); ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <input type="text" name="staff_id" placeholder="Staff ID" required 
                           value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['staff_id']) : ''; ?>">
                    <input type="text" name="full_name" placeholder="Full Name" required
                           value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['full_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <div class="form-row password-container">
                        <input type="password" name="password" placeholder="New Password (leave blank to keep current)" id="password-field">
                    </div>
                    <div class="password-note">
                        💡 <strong>Password Note:</strong> 
                        <?php if ($edit_staff): ?>
                            Leave blank to keep current password. If provided, will update the password.
                        <?php else: ?>
                            If left blank, the Staff ID will be used as the default password.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="multi-select-section">
                    <h3>Assign Classes</h3>
                    <div class="checkbox-grid">
                        <?php foreach ($classes as $class): 
                            $is_checked = $edit_staff ? in_array($class['class'], explode(',', $edit_staff['classes'] ?? '')) : false;
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="assigned_classes[]" value="<?php echo $class['class']; ?>" 
                                       id="class_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $class['class']); ?>"
                                       <?php echo $is_checked ? 'checked' : ''; ?>>
                                <label for="class_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $class['class']); ?>">
                                    <?php echo htmlspecialchars($class['class']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="multi-select-section">
                    <h3>Assign Subjects</h3>
                    <div class="checkbox-grid">
                        <?php foreach ($subjects as $subject): 
                            $is_checked = $edit_staff ? in_array($subject['id'], explode(',', $edit_staff['subject_ids'] ?? '')) : false;
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="assigned_subjects[]" value="<?php echo $subject['id']; ?>" 
                                       id="subject_<?php echo $subject['id']; ?>"
                                       <?php echo $is_checked ? 'checked' : ''; ?>>
                                <label for="subject_<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($edit_staff): ?>
                    <button type="submit" name="update_staff" class="update-btn">💾 Update Staff Member</button>
                <?php else: ?>
                    <button type="submit" name="add_staff" class="add-btn">➕ Add Staff Member</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="management-card">
            <h2>📋 Current Staff Members</h2>
            
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Full Name</th>
                        <th>Assigned Classes</th>
                        <th>Assigned Subjects</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff_members)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                                No staff members found. Add your first staff member above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staff_members as $staff): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                <td class="classes-list">
                                    <?php if ($staff['classes']): ?>
                                        <?php $class_list = explode(',', $staff['classes']); ?>
                                        <?php foreach ($class_list as $class): ?>
                                            <span><?php echo htmlspecialchars($class); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No classes assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="subjects-list">
                                    <?php if ($staff['subjects']): ?>
                                        <?php $subject_list = explode(',', $staff['subjects']); ?>
                                        <?php foreach ($subject_list as $subject): ?>
                                            <span><?php echo htmlspecialchars($subject); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No subjects assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($staff['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="edit-btn" onclick="editStaff('<?php echo $staff['staff_id']; ?>')">
                                            ✏️ Edit
                                        </button>
                                        <button class="delete-btn" onclick="deleteStaff('<?php echo $staff['staff_id']; ?>')">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="index.php" class="back-link">← Back to Dashboard</a>
    </div>

    <script>
        function editStaff(staffId) {
            window.location.href = 'manage_staff.php?edit=' + staffId;
        }

        function deleteStaff(staffId) {
            if (confirm('Are you sure you want to delete this staff member? This action cannot be undone.')) {
                window.location.href = 'manage_staff.php?delete=' + staffId;
            }
        }

        // Select all checkboxes for classes
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllClasses = document.createElement('button');
            selectAllClasses.type = 'button';
            selectAllClasses.textContent = 'Select All Classes';
            selectAllClasses.style.cssText = 'background: #4a90e2; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; margin-bottom: 0.5rem;';
            selectAllClasses.onclick = function() {
                document.querySelectorAll('input[name="assigned_classes[]"]').forEach(cb => cb.checked = true);
            };
            
            const classesSection = document.querySelector('.multi-select-section:first-child');
            classesSection.insertBefore(selectAllClasses, classesSection.querySelector('.checkbox-grid'));

            // Select all checkboxes for subjects
            const selectAllSubjects = document.createElement('button');
            selectAllSubjects.type = 'button';
            selectAllSubjects.textContent = 'Select All Subjects';
            selectAllSubjects.style.cssText = 'background: #4a90e2; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; margin-bottom: 0.5rem;';
            selectAllSubjects.onclick = function() {
                document.querySelectorAll('input[name="assigned_subjects[]"]').forEach(cb => cb.checked = true);
            };
            
            const subjectsSection = document.querySelector('.multi-select-section:last-child');
            subjectsSection.insertBefore(selectAllSubjects, subjectsSection.querySelector('.checkbox-grid'));

            // Toggle password visibility
            const passwordField = document.getElementById('password-field');
            const togglePassword = document.createElement('button');
            togglePassword.type = 'button';
            togglePassword.textContent = '👁️';
            togglePassword.className = 'password-toggle';
            
            const passwordContainer = passwordField.parentElement;
            passwordContainer.appendChild(togglePassword);
            
            togglePassword.onclick = function() {
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    togglePassword.textContent = '🔒';
                } else {
                    passwordField.type = 'password';
                    togglePassword.textContent = '👁️';
                }
            };

            // Deselect all buttons
            const deselectAllClasses = document.createElement('button');
            deselectAllClasses.type = 'button';
            deselectAllClasses.textContent = 'Deselect All Classes';
            deselectAllClasses.style.cssText = 'background: #e74c3c; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; margin-bottom: 0.5rem; margin-left: 0.5rem;';
            deselectAllClasses.onclick = function() {
                document.querySelectorAll('input[name="assigned_classes[]"]').forEach(cb => cb.checked = false);
            };
            classesSection.insertBefore(deselectAllClasses, classesSection.querySelector('.checkbox-grid'));

            const deselectAllSubjects = document.createElement('button');
            deselectAllSubjects.type = 'button';
            deselectAllSubjects.textContent = 'Deselect All Subjects';
            deselectAllSubjects.style.cssText = 'background: #e74c3c; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; margin-bottom: 0.5rem; margin-left: 0.5rem;';
            deselectAllSubjects.onclick = function() {
                document.querySelectorAll('input[name="assigned_subjects[]"]').forEach(cb => cb.checked = false);
            };
            subjectsSection.insertBefore(deselectAllSubjects, subjectsSection.querySelector('.checkbox-grid'));
        });
    </script>
</body>
</html>