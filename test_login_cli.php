<?php
/**
 * Test de connexion en ligne de commande
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== TEST DE CONNEXION CLI ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test de connexion DB
echo "1. Test connexion base de données...\n";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "✓ Connexion DB réussie\n";
} catch (Exception $e) {
    echo "✗ Erreur DB: " . $e->getMessage() . "\n";
    exit(1);
}

// Vérifier les utilisateurs
echo "\n2. Vérification des utilisateurs...\n";
try {
    $stmt = $pdo->query("SELECT id, username, email, role, status FROM users WHERE status = 'active'");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "✗ Aucun utilisateur actif trouvé!\n";
        exit(1);
    }
    
    echo "Utilisateurs actifs trouvés:\n";
    foreach ($users as $user) {
        echo "  - ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur lors de la récupération des utilisateurs: " . $e->getMessage() . "\n";
    exit(1);
}

// Test d'authentification
echo "\n3. Test d'authentification...\n";

$test_credentials = [
    ['admin@yellowjack.com', 'admin123'],
    ['admin', 'admin123']
];

foreach ($test_credentials as $index => $creds) {
    $username = $creds[0];
    $password = $creds[1];
    
    echo "\nTest " . ($index + 1) . ": {$username} / {$password}\n";
    
    try {
        // Test manuel du hash
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "✗ Utilisateur non trouvé\n";
            continue;
        }
        
        echo "  Utilisateur trouvé: {$user['username']} ({$user['email']})\n";
        
        // Vérifier le mot de passe
        $password_valid = password_verify($password, $user['password_hash']);
        echo "  Vérification mot de passe: " . ($password_valid ? '✓ Valide' : '✗ Invalide') . "\n";
        
        if (!$password_valid) {
            echo "  Hash actuel: " . substr($user['password_hash'], 0, 30) . "...\n";
            
            // Créer un nouveau hash
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "  Nouveau hash: " . substr($new_hash, 0, 30) . "...\n";
            
            // Mettre à jour
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($update_stmt->execute([$new_hash, $user['id']])) {
                echo "  ✓ Hash mis à jour\n";
                
                // Re-tester
                $retest = password_verify($password, $new_hash);
                echo "  Re-test: " . ($retest ? '✓ Valide' : '✗ Invalide') . "\n";
            } else {
                echo "  ✗ Échec mise à jour hash\n";
            }
        }
        
        // Test avec Auth class
        echo "  Test avec classe Auth...\n";
        $auth = getAuth();
        
        // Nettoyer la session avant le test
        session_unset();
        
        $auth_result = $auth->login($username, $password);
        
        if ($auth_result) {
            echo "  ✓ Connexion Auth réussie!\n";
            echo "  Session créée:\n";
            echo "    - user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "\n";
            echo "    - username: " . ($_SESSION['username'] ?? 'Non défini') . "\n";
            echo "    - email: " . ($_SESSION['email'] ?? 'Non défini') . "\n";
            echo "    - role: " . ($_SESSION['role'] ?? 'Non défini') . "\n";
            
            // Test de vérification de session
            if ($auth->isLoggedIn()) {
                echo "  ✓ Session valide confirmée\n";
            } else {
                echo "  ✗ Session invalide après connexion\n";
            }
            
            // Nettoyer pour le test suivant
            $auth->logout();
            echo "  Session nettoyée\n";
        } else {
            echo "  ✗ Échec connexion Auth\n";
        }
        
    } catch (Exception $e) {
        echo "  ✗ Erreur: " . $e->getMessage() . "\n";
        echo "  Stack trace: " . $e->getTraceAsString() . "\n";
    }
}

// Test final avec simulation POST
echo "\n4. Test simulation POST...\n";

// Simuler les données POST
$_POST['username'] = 'admin@yellowjack.com';
$_POST['password'] = 'admin123';
$_POST['csrf_token'] = 'test_token'; // Pour éviter l'erreur CSRF

// Simuler le token CSRF en session
$_SESSION['csrf_token'] = 'test_token';

echo "Données POST simulées:\n";
echo "  username: {$_POST['username']}\n";
echo "  password: {$_POST['password']}\n";

// Nettoyer les données comme dans login.php
$username = trim($_POST['username']);
$password = trim($_POST['password']);

// Supprimer les caractères invisibles
$username = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $username);
$password = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $password);

echo "Données nettoyées:\n";
echo "  username: '{$username}' (" . strlen($username) . " chars)\n";
echo "  password: '{$password}' (" . strlen($password) . " chars)\n";

if (empty($username) || empty($password)) {
    echo "✗ Champs vides après nettoyage\n";
} else {
    echo "✓ Champs valides après nettoyage\n";
    
    try {
        $auth = getAuth();
        session_unset(); // Nettoyer la session
        
        $result = $auth->login($username, $password);
        
        if ($result) {
            echo "✓ CONNEXION FINALE RÉUSSIE!\n";
            echo "Le problème semble résolu.\n";
        } else {
            echo "✗ CONNEXION FINALE ÉCHOUÉE\n";
            echo "Le problème persiste.\n";
        }
    } catch (Exception $e) {
        echo "✗ Erreur finale: " . $e->getMessage() . "\n";
    }
}

echo "\n=== FIN DU TEST ===\n";
?>