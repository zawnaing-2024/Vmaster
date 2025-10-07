<?php
// Quick admin password reset tool
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Reset admin password
$newPassword = 'admin123';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    // Check if admin exists
    $stmt = $conn->query("SELECT * FROM admins WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Update existing admin
        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
        $stmt->execute([$hashedPassword]);
        echo "✅ Admin password updated!<br>";
    } else {
        // Create new admin
        $stmt = $conn->prepare("INSERT INTO admins (username, password, full_name, email) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, 'Administrator', 'admin@vmaster.local']);
        echo "✅ Admin user created!<br>";
    }
    
    echo "<br>Login credentials:<br>";
    echo "Username: <strong>admin</strong><br>";
    echo "Password: <strong>admin123</strong><br>";
    echo "<br><a href='login.php'>Go to Login Page</a>";
    
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

