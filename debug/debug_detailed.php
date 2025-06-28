<?php
/**
 * Script de débogage détaillé pour analyser le problème de connexion
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Débogage Détaillé - Problème de Connexion</h1>";
echo "<p>Date/Heure : " . date('Y-m-d H:i:s') . "</p>";

// 1. Vérifier la configuration
echo "<h2>1. Configuration</h2>";
echo "<ul>";
echo "<li>DB_HOST: " . DB_HOST . "</li>";
echo "<li>DB_NAME: " . DB_NAME . "</li>";
echo "<li>DB_USER: " . DB_USER . "</li>";
echo "<li>APP_DEBUG: " . (APP_DEBUG ? 'true' : 'false') . "</li>";
echo "</ul>";

// 2. Test de connexion à la base
echo "<h2>2. Test de Connexion Base de Données</h2>";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "<p style='color: green;'>✓ Connexion DB réussie</p>";
    
    // Vérifier la structure de la table users
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    echo "<h3>Structure de la table users :</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur DB: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Vérifier les utilisateurs
echo "<h2>3. Utilisateurs dans la Base</h2>";
try {
    $stmt = $pdo->query("SELECT id, username, email, role, status, created_at FROM users");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: red;'>✗ Aucun utilisateur trouvé dans la base !</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur lors de la récupération des utilisateurs: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. Test d'authentification détaillé
echo "<h2>4. Test d'Authentification Détaillé</h2>";

$test_credentials = [
    ['admin@yellowjack.com', 'admin123'],
    ['admin', 'admin123']
];

foreach ($test_credentials as $index => $creds) {
    $username = $creds[0];
    $password = $creds[1];
    
    echo "<h3>Test " . ($index + 1) . " : " . htmlspecialchars($username) . " / " . htmlspecialchars($password) . "</h3>";
    
    try {
        // Recherche manuelle de l'utilisateur
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, status FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "<p style='color: red;'>✗ Utilisateur non trouvé ou inactif</p>";
            continue;
        }
        
        echo "<p style='color: blue;'>Utilisateur trouvé :</p>";
        echo "<ul>";
        echo "<li>ID: " . $user['id'] . "</li>";
        echo "<li>Username: " . htmlspecialchars($user['username']) . "</li>";
        echo "<li>Email: " . htmlspecialchars($user['email']) . "</li>";
        echo "<li>Role: " . htmlspecialchars($user['role']) . "</li>";
        echo "<li>Status: " . htmlspecialchars($user['status']) . "</li>";
        echo "<li>Hash: " . substr($user['password_hash'], 0, 30) . "...</li>";
        echo "</ul>";
        
        // Test du mot de passe
        $password_valid = password_verify($password, $user['password_hash']);
        echo "<p>Vérification mot de passe: " . ($password_valid ? '<span style="color: green;">✓ Valide</span>' : '<span style="color: red;">✗ Invalide</span>') . "</p>";
        
        if (!$password_valid) {
            // Créer un nouveau hash et tester
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "<p>Nouveau hash généré: " . substr($new_hash, 0, 30) . "...</p>";
            
            // Mettre à jour le hash
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_result = $update_stmt->execute([$new_hash, $user['id']]);
            
            if ($update_result) {
                echo "<p style='color: green;'>✓ Hash mis à jour dans la base</p>";
                
                // Re-tester
                $retest = password_verify($password, $new_hash);
                echo "<p>Re-test avec nouveau hash: " . ($retest ? '<span style="color: green;">✓ Valide</span>' : '<span style="color: red;">✗ Invalide</span>') . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Échec de la mise à jour du hash</p>";
            }
        }
        
        // Test avec la classe Auth
        echo "<h4>Test avec la classe Auth :</h4>";
        $auth = getAuth();
        $auth_result = $auth->login($username, $password);
        
        if ($auth_result) {
            echo "<p style='color: green;'>✓ Connexion Auth réussie</p>";
            echo "<p>Session créée :</p>";
            echo "<ul>";
            echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
            echo "<li>username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
            echo "<li>email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
            echo "<li>role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
            echo "</ul>";
            
            // Nettoyer la session pour les tests suivants
            $auth->logout();
            echo "<p>Session nettoyée pour les tests suivants.</p>";
        } else {
            echo "<p style='color: red;'>✗ Échec de la connexion Auth</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors du test: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Stack trace: <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></p>";
    }
    
    echo "<hr>";
}

// 5. Vérifier les sessions
echo "<h2>5. État des Sessions</h2>";
echo "<ul>";
echo "<li>Session ID: " . session_id() . "</li>";
echo "<li>Session Name: " . session_name() . "</li>";
echo "<li>Session Status: " . session_status() . "</li>";
echo "<li>Login Attempts: " . ($_SESSION['login_attempts'] ?? 0) . "</li>";
echo "<li>Last Attempt: " . (isset($_SESSION['last_attempt']) ? date('Y-m-d H:i:s', $_SESSION['last_attempt']) : 'Aucune') . "</li>";
echo "</ul>";

// 6. Vérifier les fichiers
echo "<h2>6. Vérification des Fichiers</h2>";
$files_to_check = [
    '../config/database.php',
    '../includes/auth.php',
    '../panel/login.php'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✓ " . $file . " existe</p>";
    } else {
        echo "<p style='color: red;'>✗ " . $file . " manquant</p>";
    }
}

echo "<hr>";
echo "<h3>Formulaire de Test Direct</h3>";
echo "<form method='POST' action=''>";
echo "<p>Username: <input type='text' name='test_username' value='admin@yellowjack.com' style='width: 200px;'></p>";
echo "<p>Password: <input type='password' name='test_password' value='admin123' style='width: 200px;'></p>";
echo "<p><input type='submit' name='test_login' value='Tester la Connexion' style='padding: 10px;'></p>";
echo "</form>";

// Traitement du formulaire de test
if (isset($_POST['test_login'])) {
    echo "<h3>Résultat du Test Direct</h3>";
    $test_user = $_POST['test_username'] ?? '';
    $test_pass = $_POST['test_password'] ?? '';
    
    echo "<p>Données reçues :</p>";
    echo "<ul>";
    echo "<li>Username: '" . htmlspecialchars($test_user) . "' (" . strlen($test_user) . " chars)</li>";
    echo "<li>Password: '" . htmlspecialchars($test_pass) . "' (" . strlen($test_pass) . " chars)</li>";
    echo "</ul>";
    
    try {
        $auth = getAuth();
        $result = $auth->login($test_user, $test_pass);
        
        if ($result) {
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ CONNEXION RÉUSSIE !</p>";
            echo "<p>Redirection vers dashboard.php devrait avoir lieu...</p>";
        } else {
            echo "<p style='color: red; font-size: 18px; font-weight: bold;'>✗ CONNEXION ÉCHOUÉE</p>";
            echo "<p>Vérifiez les logs ci-dessus pour identifier le problème.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<p><a href='../panel/login.php'>← Retour à la page de connexion</a></p>";
?>