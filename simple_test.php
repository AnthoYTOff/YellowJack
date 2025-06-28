<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "TEST SIMPLE\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "DB OK\n";
    
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute(['admin', 'admin']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "User found: " . $user['username'] . "\n";
        
        $password = 'admin123';
        $valid = password_verify($password, $user['password_hash']);
        echo "Password valid: " . ($valid ? 'YES' : 'NO') . "\n";
        
        if (!$valid) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update->execute([$new_hash, $user['id']]);
            echo "Password updated\n";
        }
        
        $auth = getAuth();
        session_unset();
        $result = $auth->login('admin', $password);
        echo "Auth result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
        
        if ($result) {
            echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NONE') . "\n";
        }
    } else {
        echo "No user found\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>