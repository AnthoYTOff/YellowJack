<?php
/**
 * Script de réparation finale du système de connexion
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Réparation Finale du Système de Connexion</h1>";
echo "<p>Date: " . date('Y-m-d H:i:s') . "</p>";

$success_count = 0;
$total_tests = 0;

// 1. Vérifier et réparer la base de données
echo "<h2>1. Vérification Base de Données</h2>";
$total_tests++;
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "<p style='color: green;'>✓ Connexion DB réussie</p>";
    $success_count++;
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur DB: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit(1);
}

// 2. Vérifier et réparer l'utilisateur admin
echo "<h2>2. Vérification Utilisateur Admin</h2>";
$total_tests++;
try {
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, status FROM users WHERE username = 'admin' OR email = 'admin@yellowjack.com'");
    $stmt->execute();
    $admin_user = $stmt->fetch();
    
    if (!$admin_user) {
        echo "<p style='color: orange;'>⚠ Utilisateur admin non trouvé, création...</p>";
        
        // Créer l'utilisateur admin
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $insert_stmt->execute([
            'admin',
            'admin@yellowjack.com',
            $password_hash,
            'Admin',
            'System',
            'Patron',
            'active'
        ]);
        
        if ($result) {
            echo "<p style='color: green;'>✓ Utilisateur admin créé avec succès</p>";
            $success_count++;
        } else {
            echo "<p style='color: red;'>✗ Échec de la création de l'utilisateur admin</p>";
        }
    } else {
        echo "<p style='color: blue;'>Utilisateur admin trouvé:</p>";
        echo "<ul>";
        echo "<li>ID: " . $admin_user['id'] . "</li>";
        echo "<li>Username: " . htmlspecialchars($admin_user['username']) . "</li>";
        echo "<li>Email: " . htmlspecialchars($admin_user['email']) . "</li>";
        echo "<li>Status: " . htmlspecialchars($admin_user['status']) . "</li>";
        echo "</ul>";
        
        // Vérifier le mot de passe
        $password_valid = password_verify('admin123', $admin_user['password_hash']);
        
        if (!$password_valid) {
            echo "<p style='color: orange;'>⚠ Mot de passe incorrect, mise à jour...</p>";
            
            $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            
            if ($update_stmt->execute([$new_hash, $admin_user['id']])) {
                echo "<p style='color: green;'>✓ Mot de passe mis à jour</p>";
                $success_count++;
            } else {
                echo "<p style='color: red;'>✗ Échec de la mise à jour du mot de passe</p>";
            }
        } else {
            echo "<p style='color: green;'>✓ Mot de passe valide</p>";
            $success_count++;
        }
        
        // Vérifier le statut
        if ($admin_user['status'] !== 'active') {
            echo "<p style='color: orange;'>⚠ Statut inactif, activation...</p>";
            
            $activate_stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            if ($activate_stmt->execute([$admin_user['id']])) {
                echo "<p style='color: green;'>✓ Utilisateur activé</p>";
            } else {
                echo "<p style='color: red;'>✗ Échec de l'activation</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur lors de la vérification de l'utilisateur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Test d'authentification
echo "<h2>3. Test d'Authentification</h2>";
$total_tests++;
try {
    // Nettoyer la session
    session_unset();
    
    $auth = getAuth();
    $login_result = $auth->login('admin@yellowjack.com', 'admin123');
    
    if ($login_result) {
        echo "<p style='color: green;'>✓ Test d'authentification réussi</p>";
        echo "<ul>";
        echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
        echo "<li>Username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
        echo "<li>Email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
        echo "<li>Role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
        echo "</ul>";
        $success_count++;
        
        // Nettoyer la session
        $auth->logout();
    } else {
        echo "<p style='color: red;'>✗ Test d'authentification échoué</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur lors du test d'authentification: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. Vérifier les fichiers critiques
echo "<h2>4. Vérification des Fichiers</h2>";
$files_to_check = [
    'config/database.php' => 'Configuration base de données',
    'includes/auth.php' => 'Système d\'authentification',
    'panel/login.php' => 'Page de connexion',
    'panel/dashboard.php' => 'Tableau de bord'
];

foreach ($files_to_check as $file => $description) {
    $total_tests++;
    $full_path = __DIR__ . '/' . $file;
    
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✓ {$description} ({$file})</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>✗ {$description} manquant ({$file})</p>";
    }
}

// 5. Test de session
echo "<h2>5. Test de Session</h2>";
$total_tests++;
try {
    // Générer un token CSRF
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    echo "<p style='color: green;'>✓ Session fonctionnelle</p>";
    echo "<ul>";
    echo "<li>Session ID: " . session_id() . "</li>";
    echo "<li>CSRF Token: " . substr($_SESSION['csrf_token'], 0, 16) . "...</li>";
    echo "</ul>";
    $success_count++;
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur de session: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Résumé final
echo "<hr>";
echo "<h2>Résumé Final</h2>";
echo "<p><strong>Tests réussis: {$success_count}/{$total_tests}</strong></p>";

if ($success_count === $total_tests) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='margin: 0 0 10px 0;'>🎉 SYSTÈME DE CONNEXION RÉPARÉ AVEC SUCCÈS !</h3>";
    echo "<p><strong>Vous pouvez maintenant vous connecter avec :</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email :</strong> admin@yellowjack.com</li>";
    echo "<li><strong>Nom d'utilisateur :</strong> admin</li>";
    echo "<li><strong>Mot de passe :</strong> admin123</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p style='text-align: center; margin: 30px 0;'>";
    echo "<a href='panel/login.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold;'>🚀 ALLER À LA PAGE DE CONNEXION</a>";
    echo "</p>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='margin: 0 0 10px 0;'>⚠️ PROBLÈMES DÉTECTÉS</h3>";
    echo "<p>Certains tests ont échoué. Veuillez vérifier les erreurs ci-dessus et contacter le support technique si nécessaire.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Actions Supplémentaires</h3>";
echo "<p>";
echo "<a href='test_web_login.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; margin-right: 10px;'>Test Simulation Web</a>";
echo "<a href='debug/debug_detailed.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Débogage Détaillé</a>";
echo "</p>";
?>