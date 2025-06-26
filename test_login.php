<?php
/**
 * Test de connexion avec l'utilisateur admin existant
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

echo "<h2>Test de connexion</h2>";

$auth = getAuth();
$db = getDB();

// Vérifier l'utilisateur admin
echo "<h3>Informations de l'utilisateur admin :</h3>";
$stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
$stmt->execute();
$admin_user = $stmt->fetch();

if ($admin_user) {
    echo "<p>✓ Utilisateur trouvé :</p>";
    echo "<p>- ID: {$admin_user['id']}</p>";
    echo "<p>- Username: {$admin_user['username']}</p>";
    echo "<p>- Role: {$admin_user['role']}</p>";
    echo "<p>- Status: {$admin_user['status']}</p>";
    echo "<p>- Password hash: " . substr($admin_user['password_hash'], 0, 20) . "...</p>";
    
    // Test de vérification du mot de passe
    echo "<h3>Test de vérification du mot de passe :</h3>";
    
    // Essayer plusieurs mots de passe possibles
    $passwords_to_test = ['admin', 'admin123', 'password', '123456', 'yellowjack'];
    
    foreach ($passwords_to_test as $password) {
        if (password_verify($password, $admin_user['password_hash'])) {
            echo "<p style='color: green;'>✓ Mot de passe trouvé : <strong>$password</strong></p>";
            break;
        } else {
            echo "<p style='color: red;'>❌ Mot de passe '$password' incorrect</p>";
        }
    }
    
    // Mettre à jour le mot de passe avec 'admin123'
    echo "<h3>Mise à jour du mot de passe :</h3>";
    $new_password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    
    if ($stmt->execute([$new_password_hash])) {
        echo "<p style='color: green;'>✓ Mot de passe mis à jour avec succès</p>";
        echo "<p><strong>Nouveau mot de passe :</strong> admin123</p>";
    } else {
        echo "<p style='color: red;'>❌ Erreur lors de la mise à jour du mot de passe</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Utilisateur admin non trouvé</p>";
}

echo "<hr>";
echo "<p><strong>Informations de connexion :</strong></p>";
echo "<p>Nom d'utilisateur : <strong>admin</strong></p>";
echo "<p>Mot de passe : <strong>admin123</strong></p>";
echo "<p><a href='panel/login.php'>Se connecter maintenant</a></p>";
?>