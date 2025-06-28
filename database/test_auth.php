<?php
/**
 * Script de test d'authentification
 */

require_once __DIR__ . '/../includes/auth.php';

echo "<h2>Test d'authentification</h2>";

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test avec les identifiants par défaut
$test_username = 'admin@yellowjack.com';
$test_password = 'admin123';

echo "<p>Test avec les identifiants par défaut :</p>";
echo "<ul>";
echo "<li>Username: " . htmlspecialchars($test_username) . "</li>";
echo "<li>Password: " . htmlspecialchars($test_password) . "</li>";
echo "</ul>";

try {
    $auth = getAuth();
    
    echo "<p>Tentative de connexion...</p>";
    
    $result = $auth->login($test_username, $test_password);
    
    if ($result) {
        echo "<p style='color: green;'><strong>✓ Authentification réussie !</strong></p>";
        
        // Afficher les informations de session
        echo "<h3>Informations de session :</h3>";
        echo "<ul>";
        echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
        echo "<li>Username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
        echo "<li>Email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
        echo "<li>Role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
        echo "</ul>";
        
        // Déconnexion pour nettoyer
        $auth->logout();
        echo "<p>Déconnexion effectuée.</p>";
        
    } else {
        echo "<p style='color: red;'><strong>✗ Échec de l'authentification</strong></p>";
        echo "<p>Vérification des utilisateurs dans la base...</p>";
        
        // Vérifier les utilisateurs existants
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$test_username, $test_username]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<p style='color: orange;'>Utilisateur trouvé dans la base :</p>";
                echo "<ul>";
                echo "<li>ID: " . $user['id'] . "</li>";
                echo "<li>Username: " . htmlspecialchars($user['username']) . "</li>";
                echo "<li>Email: " . htmlspecialchars($user['email']) . "</li>";
                echo "<li>Role: " . htmlspecialchars($user['role']) . "</li>";
                echo "</ul>";
                
                // Vérifier le hash du mot de passe
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$test_username, $test_username]);
                $hash_result = $stmt->fetch();
                
                if ($hash_result) {
                    $hash_check = password_verify($test_password, $hash_result['password_hash']);
                    echo "<p>Vérification du mot de passe : " . ($hash_check ? '<span style="color: green;">✓ Correct</span>' : '<span style="color: red;">✗ Incorrect</span>') . "</p>";
                    
                    if (!$hash_check) {
                        echo "<p style='color: blue;'>Tentative de création d'un nouveau hash...</p>";
                        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
                        echo "<p>Nouveau hash généré : " . substr($new_hash, 0, 50) . "...</p>";
                        
                        // Mettre à jour le mot de passe
                        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ? OR email = ?");
                        $update_result = $update_stmt->execute([$new_hash, $test_username, $test_username]);
                        
                        if ($update_result) {
                            echo "<p style='color: green;'>✓ Mot de passe mis à jour avec succès</p>";
                        } else {
                            echo "<p style='color: red;'>✗ Échec de la mise à jour du mot de passe</p>";
                        }
                    }
                }
            } else {
                echo "<p style='color: red;'>Aucun utilisateur trouvé avec ces identifiants.</p>";
                
                // Lister tous les utilisateurs
                $stmt = $pdo->query("SELECT id, username, email, role FROM users");
                $all_users = $stmt->fetchAll();
                
                echo "<h4>Tous les utilisateurs dans la base :</h4>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
                foreach ($all_users as $user) {
                    echo "<tr>";
                    echo "<td>" . $user['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erreur lors de la vérification : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Erreur de base de données :</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Erreur générale :</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='../panel/login.php'>Retour à la page de connexion</a></p>";
?>