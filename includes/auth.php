<?php
// auth.php - Mighty School For Valours Authentication System

// Include config if not already included
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================
// STUDENT AUTHENTICATION FUNCTIONS
// =============================================

/**
 * Check if student is logged in
 */
function isStudentLoggedIn() {
    return isset($_SESSION['student_id']) && isset($_SESSION['admission_number']);
}

/**
 * Student login
 */
function studentLogin($pdo, $admissionNo, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE admission_number = ?");
        $stmt->execute([$admissionNo]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Check hashed password
            if (password_verify($password, $student['password'])) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['admission_number'] = $student['admission_number'];
                $_SESSION['full_name'] = $student['full_name'];
                $_SESSION['class'] = $student['class'];
                $_SESSION['login_time'] = time();
                
                // Log activity
                logActivity($pdo, $student['id'], "Student logged in", "student");
                return true;
            }
            
            // TEMPORARY: For initial setup - check plain text password
            if ($student['password'] === $password) {
                // Hash the password for future use
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $student['id']]);
                
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['admission_number'] = $student['admission_number'];
                $_SESSION['full_name'] = $student['full_name'];
                $_SESSION['class'] = $student['class'];
                $_SESSION['login_time'] = time();
                
                logActivity($pdo, $student['id'], "Student logged in (password upgraded)", "student");
                return true;
            }
            
            // Check if password is the admission number (common default)
            if ($admissionNo === $password) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['admission_number'] = $student['admission_number'];
                $_SESSION['full_name'] = $student['full_name'];
                $_SESSION['class'] = $student['class'];
                $_SESSION['login_time'] = time();
                
                logActivity($pdo, $student['id'], "Student logged in with admission number as password", "student");
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Student login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Require student login
 */
function requireStudentLogin() {
    if (!isStudentLoggedIn()) {
        $_SESSION['flash_message'] = "Please login to access this page";
        $_SESSION['flash_type'] = "warning";
        header("Location: /cbt_system/student/login.php");
        exit();
    }
}

// =============================================
// ADMIN AUTHENTICATION FUNCTIONS
// =============================================

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

/**
 * Admin login
 */
function adminLogin($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            // Check if password is already hashed
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = time();
                
                // Log activity
                logActivity($pdo, $admin['id'], "Admin logged in", "admin");
                return true;
            }
            
            // TEMPORARY: For initial setup - check plain text password
            if ($admin['password'] === $password) {
                // Hash the password for future use
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $admin['id']]);
                
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = time();
                
                logActivity($pdo, $admin['id'], "Admin logged in (password upgraded)", "admin");
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Require admin login
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        $_SESSION['flash_message'] = "Please login as administrator to access this page";
        $_SESSION['flash_type'] = "warning";
        header("Location: /cbt_system/admin/login.php");
        exit();
    }
}

// =============================================
// STAFF AUTHENTICATION FUNCTIONS
// =============================================

/**
 * Check if staff is logged in
 */
function isStaffLoggedIn() {
    return isset($_SESSION['staff_id']) && isset($_SESSION['staff_role']);
}

/**
 * Staff login
 */
// Update the staffLogin function in auth.php with better debugging:
function staffLogin($pdo, $staff_id, $password) {
    try {
        error_log("Staff login attempt: " . $staff_id);
        
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ? AND is_active = TRUE");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            error_log("Staff found: " . $staff['full_name']);
            error_log("Stored password hash: " . $staff['password']);
            error_log("Input password: " . $password);
            
            // Check hashed password
            if (password_verify($password, $staff['password'])) {
                error_log("Password verified successfully (hashed)");
                return setupStaffSession($pdo, $staff);
            }
            
            // Check plain text password (for initial setup)
            if ($staff['password'] === $password) {
                error_log("Password matched (plain text) - upgrading to hash");
                // Upgrade to hashed password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE staff SET password = ? WHERE staff_id = ?");
                $updateStmt->execute([$hashedPassword, $staff_id]);
                
                return setupStaffSession($pdo, $staff);
            }
            
            // Check if password is the staff ID (common default)
            if ($staff_id === $password) {
                error_log("Password is staff ID - upgrading to hash");
                // Upgrade to hashed password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE staff SET password = ? WHERE staff_id = ?");
                $updateStmt->execute([$hashedPassword, $staff_id]);
                
                return setupStaffSession($pdo, $staff);
            }
            
            error_log("All password checks failed");
            return false;
        }
        
        error_log("Staff not found or inactive: " . $staff_id);
        return false;
        
    } catch (Exception $e) {
        error_log("Staff login error: " . $e->getMessage());
        return false;
    }
}

// Helper function to set up staff session
function setupStaffSession($pdo, $staff) {
    $_SESSION['staff_id'] = $staff['staff_id'];
    $_SESSION['staff_name'] = $staff['full_name'];
    $_SESSION['staff_role'] = $staff['role'];
    
    // Get assigned classes
    $stmt = $pdo->prepare("SELECT class FROM staff_classes WHERE staff_id = ?");
    $stmt->execute([$staff['staff_id']]);
    $_SESSION['assigned_classes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get assigned subjects
    $stmt = $pdo->prepare("SELECT subject_id FROM staff_subjects WHERE staff_id = ?");
    $stmt->execute([$staff['staff_id']]);
    $_SESSION['assigned_subject_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $_SESSION['login_time'] = time();
    
    // Log activity
    logActivity($pdo, $staff['id'], "Staff logged in", "staff");
    
    error_log("Staff session setup complete for: " . $staff['staff_id']);
    return true;
}
/**
 * Require staff login
 */
function requireStaffLogin() {
    if (!isStaffLoggedIn()) {
        $_SESSION['flash_message'] = "Please login as staff to access this page";
        $_SESSION['flash_type'] = "warning";
        header("Location: /cbt_system/staff/login.php");
        exit();
    }
}

/**
 * Get staff access information
 */
function getStaffAccess($pdo) {
    if (!isStaffLoggedIn()) return null;
    
    return [
        'classes' => $_SESSION['assigned_classes'] ?? [],
        'subject_ids' => $_SESSION['assigned_subject_ids'] ?? []
    ];
}

/**
 * Check if staff has access to a specific class
 */
function staffHasClassAccess($class) {
    return in_array($class, $_SESSION['assigned_classes'] ?? []);
}

/**
 * Check if staff has access to a specific subject
 */
function staffHasSubjectAccess($subject_id) {
    return in_array($subject_id, $_SESSION['assigned_subject_ids'] ?? []);
}

// =============================================
// GENERAL AUTHENTICATION FUNCTIONS
// =============================================

/**
 * Logout function
 */
function logout() {
    // Store flash message before destroying session
    $flash_message = $_SESSION['flash_message'] ?? "You have been successfully logged out";
    $flash_type = $_SESSION['flash_type'] ?? "success";
    
    // Destroy all session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    // Start new session for flash message
    session_start();
    $_SESSION['flash_message'] = $flash_message;
    $_SESSION['flash_type'] = $flash_type;
    
    // Redirect to appropriate login page based on referrer
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referrer, '/staff/') !== false) {
        header("Location: /cbt_system/staff/login.php");
    } else if (strpos($referrer, '/admin/') !== false) {
        header("Location: /cbt_system/admin/login.php");
    } else {
        header("Location: /cbt_system/student/login.php");
    }
    exit();
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (!isset($_SESSION['login_time'])) {
        return;
    }
    
    $loginTime = $_SESSION['login_time'];
    $currentTime = time();
    
    // Set timeout periods (in seconds)
    if (isAdminLoggedIn()) {
        $timeout = 4 * 60 * 60; // 4 hours for admin
    } else if (isStaffLoggedIn()) {
        $timeout = 4 * 60 * 60; // 4 hours for staff
    } else {
        $timeout = 2 * 60 * 60; // 2 hours for students
    }
    
    if ($currentTime - $loginTime > $timeout) {
        $_SESSION['flash_message'] = "Your session has expired due to inactivity";
        $_SESSION['flash_type'] = "warning";
        logout();
    }
    
    // Update login time on activity
    $_SESSION['login_time'] = $currentTime;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (isStudentLoggedIn()) {
        return [
            'id' => $_SESSION['student_id'],
            'name' => $_SESSION['full_name'],
            'admission_no' => $_SESSION['admission_number'],
            'class' => $_SESSION['class'],
            'type' => 'student'
        ];
    } elseif (isAdminLoggedIn()) {
        return [
            'id' => $_SESSION['admin_id'],
            'name' => $_SESSION['admin_name'],
            'username' => $_SESSION['admin_username'],
            'role' => $_SESSION['admin_role'],
            'type' => 'admin'
        ];
    } elseif (isStaffLoggedIn()) {
        return [
            'id' => $_SESSION['staff_id'],
            'name' => $_SESSION['staff_name'],
            'role' => $_SESSION['staff_role'],
            'classes' => $_SESSION['assigned_classes'] ?? [],
            'subject_ids' => $_SESSION['assigned_subject_ids'] ?? [],
            'type' => 'staff'
        ];
    }
    
    return null;
}

/**
 * Display user welcome message
 */
function displayUserWelcome() {
    $user = getCurrentUser();
    if (!$user) return '';
    
    $color_secondary = '#3498db';
    $color_primary = '#2c3e50';
    $color_success = '#27ae60';
    $color_warning = '#f39c12';
    
    // Set color based on user type
    if ($user['type'] === 'admin') {
        $typeColor = $color_secondary;
    } elseif ($user['type'] === 'staff') {
        $typeColor = $color_warning;
    } else {
        $typeColor = $color_success;
    }
    
    // Build user info text
    if ($user['type'] === 'admin') {
        $userInfo = "Administrator • " . htmlspecialchars($user['role']);
    } elseif ($user['type'] === 'staff') {
        $classCount = count($user['classes']);
        $subjectCount = count($user['subject_ids']);
        $userInfo = "Staff • {$classCount} classes • {$subjectCount} subjects";
    } else {
        $userInfo = "Student • " . htmlspecialchars($user['class']) . " • " . htmlspecialchars($user['admission_no']);
    }
    
    return "
    <div style='background: linear-gradient(135deg, {$typeColor}, {$color_primary}); 
                color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px;'>
        <div style='display: flex; justify-content: space-between; align-items: center;'>
            <div>
                <h3 style='margin: 0 0 5px 0;'>Welcome, " . htmlspecialchars($user['name']) . "!</h3>
                <p style='margin: 0; opacity: 0.9;'>{$userInfo}</p>
            </div>
            <div style='margin-left: auto;'>
                <a href='/cbt_system/logout.php' 
                   style='color: white; text-decoration: none; padding: 8px 15px; 
                          border: 1px solid white; border-radius: 5px; 
                          transition: all 0.3s ease;'>
                   Logout
                </a>
            </div>
        </div>
    </div>";
}

/**
 * Log activity function
 */
function logActivity($pdo, $user_id, $activity, $user_type = 'student') {
    try {
        // Check if activity_logs table exists
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                user_type ENUM('student', 'admin', 'staff') NOT NULL,
                activity TEXT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute();
        
        // Insert activity log
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_type, activity, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([$user_id, $user_type, $activity, $ip_address, $user_agent]);
        
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

/**
 * Create default admin user if not exists
 */
function createDefaultAdmin($pdo) {
    try {
        // Check if admin table exists
        $stmt = $pdo->query("SELECT 1 FROM admin_users LIMIT 1");
    } catch (Exception $e) {
        // Table doesn't exist, create it
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE,
            password VARCHAR(255),
            full_name VARCHAR(100),
            role ENUM('super_admin', 'admin', 'teacher') DEFAULT 'admin',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Check if default admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Create default admin
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, 'System Administrator', 'super_admin']);
        
        error_log("Default admin user created: admin / admin123");
    }
}

/**
 * Check if user has permission (for future role-based access control)
 */
function hasPermission($required_permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // For now, simple role-based check
    if ($user['type'] === 'admin' && $user['role'] === 'super_admin') {
        return true; // Super admin has all permissions
    }
    
    // Add more permission logic here as needed
    return false;
}
?>