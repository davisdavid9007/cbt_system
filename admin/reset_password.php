<?php
require_once '../includes/config.php';

// This is a one-time script to reset admin password
$username = 'admin';
$plain_password = 'admin123';
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Update existing admin
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
        $stmt->execute([$hashed_password, $username]);
        echo "Admin password updated successfully!<br>";
    } else {
        // Create new admin
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, 'System Administrator', 'super_admin']);
        echo "Admin user created successfully!<br>";
    }
    
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "Login here: <a href='login.php'>Admin Login</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>