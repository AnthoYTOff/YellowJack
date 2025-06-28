<?php
/**
 * Test final de la connexion après corrections
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html><html><head><title>Test Final Login</title></head><body>";
echo "<h1>🎯 Test Final de Connexion</h1>";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Nettoyer complètement la session
session_unset();
session_destroy();
session_start();

echo "<h2>1. Préparation</h2>";
echo "<p style='color: green;'>✅ Session nettoyée et redémarrée</p>";

// Vérifier et corriger l'utilisateur admin
echo "<h2>2. Vérification Utilisateur Admin</h2>";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Vérifier l'utilisateur admin
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, status FROM users WHERE username = 'admin' OR email = 'admin@yellowjack.com'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✅ Utilisateur admin trouvé</p>";
        
        // Vérifier le mot de passe
        $password_ok = password_verify('admin123', $admin['password_hash']);
        
        if (!$password_ok) {
            echo "<p style='color: orange;'>🔧 Correction du mot de passe...</p>";
            $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE id = ?");
            $update_stmt->execute([$new_hash, $admin['id']]);
            echo "<p style='color: green;'>✅ Mot de passe corrigé</p>";
        } else {
            echo "<p style='color: green;'>✅ Mot de passe valide</p>";
        }
        
        // S'assurer que le statut est actif
        if ($admin['status'] !== 'active') {
            $activate_stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $activate_stmt->execute([$admin['id']]);
            echo "<p style='color: green;'>✅ Utilisateur activé</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Utilisateur admin non trouvé</p>";
        exit(1);
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit(1);
}

// Test de connexion direct
echo "<h2>3. Test de Connexion Direct</h2>";
try {
    $auth = getAuth();
    
    // Test avec email
    $result1 = $auth->login('admin@yellowjack.com', 'admin123');
    
    if ($result1) {
        echo "<p style='color: green;'>✅ Connexion avec email réussie</p>";
        echo "<ul>";
        echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
        echo "<li>username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
        echo "<li>email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
        echo "<li>role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
        echo "</ul>";
        
        // Déconnexion pour test suivant
        $auth->logout();
        
        // Test avec username
        $result2 = $auth->login('admin', 'admin123');
        
        if ($result2) {
            echo "<p style='color: green;'>✅ Connexion avec username réussie</p>";
            $auth->logout();
        } else {
            echo "<p style='color: orange;'>⚠ Connexion avec username échouée</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Connexion avec email échouée</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur lors du test: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test de simulation POST (comme dans login.php)
echo "<h2>4. Test Simulation POST (Exact comme login.php)</h2>";

// Réinitialiser la session
session_unset();

// Initialiser les variables de session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// Générer token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simuler POST
$_POST['username'] = 'admin@yellowjack.com';
$_POST['password'] = 'admin123';
$_POST['csrf_token'] = $_SESSION['csrf_token'];

echo "<p><strong>Simulation POST:</strong></p>";
echo "<ul>";
echo "<li>username: '" . htmlspecialchars($_POST['username']) . "'</li>";
echo "<li>password: '" . htmlspecialchars($_POST['password']) . "'</li>";
echo "<li>csrf_token: " . substr($_POST['csrf_token'], 0, 16) . "...</li>";
echo "</ul>";

// Vérifications exactes comme dans login.php
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo "<p style='color: red;'>❌ Token CSRF invalide</p>";
} elseif ($_SESSION['login_attempts'] >= 5) {
    echo "<p style='color: red;'>❌ Trop de tentatives</p>";
} else {
    echo "<p style='color: green;'>✅ Vérifications préliminaires OK</p>";
    
    // Nettoyer exactement comme dans login.php (version corrigée)
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Supprimer les caractères invisibles
    $username = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $username);
    $password = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $password);
    
    echo "<p><strong>Données nettoyées:</strong></p>";
    echo "<ul>";
    echo "<li>username: '" . htmlspecialchars($username) . "' (" . strlen($username) . " chars)</li>";
    echo "<li>password: '" . htmlspecialchars($password) . "' (" . strlen($password) . " chars)</li>";
    echo "</ul>";
    
    if (empty($username) || empty($password)) {
        echo "<p style='color: red;'>❌ Champs vides après nettoyage</p>";
    } else {
        echo "<p style='color: green;'>✅ Champs valides</p>";
        
        try {
            $auth = getAuth();
            
            if ($auth->login($username, $password)) {
                echo "<p style='color: green; font-size: 20px; font-weight: bold;'>🎉 CONNEXION RÉUSSIE !</p>";
                
                // Réinitialiser les tentatives
                $_SESSION['login_attempts'] = 0;
                
                echo "<p><strong>Session créée:</strong></p>";
                echo "<ul>";
                echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
                echo "<li>username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
                echo "<li>email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
                echo "<li>role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
                echo "</ul>";
                
                // Test de redirection
                $redirect_url = $_GET['redirect'] ?? 'dashboard.php';
                if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+\.php$/', $redirect_url)) {
                    $redirect_url = 'dashboard.php';
                }
                
                echo "<p><strong>Redirection vers:</strong> {$redirect_url}</p>";
                
            } else {
                echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ CONNEXION ÉCHOUÉE</p>";
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

echo "<hr>";
echo "<h2>🎯 Résultat Final</h2>";

if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3 style='margin: 0 0 15px 0;'>🎉 SYSTÈME DE CONNEXION FONCTIONNEL !</h3>";
    echo "<p><strong>La connexion fonctionne parfaitement.</strong></p>";
    echo "<p><strong>Utilisateur connecté:</strong> " . ($_SESSION['username'] ?? 'Inconnu') . " (" . ($_SESSION['email'] ?? 'Inconnu') . ")</p>";
    echo "<p><strong>Rôle:</strong> " . ($_SESSION['role'] ?? 'Inconnu') . "</p>";
    echo "</div>";
    
    echo "<p style='text-align: center; margin: 30px 0;'>";
    echo "<a href='panel/login.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; margin-right: 15px;'>🔐 Page de Connexion</a>";
    echo "<a href='panel/dashboard.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold;'>🚀 Dashboard</a>";
    echo "</p>";
    
    echo "<h3>📋 Identifiants de Connexion</h3>";
    echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px;'>";
    echo "<ul style='margin: 0;'>";
    echo "<li><strong>Email:</strong> admin@yellowjack.com</li>";
    echo "<li><strong>Username:</strong> admin</li>";
    echo "<li><strong>Mot de passe:</strong> admin123</li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3 style='margin: 0 0 15px 0;'>⚠️ PROBLÈME PERSISTANT</h3>";
    echo "<p>La connexion n'a pas fonctionné. Veuillez vérifier les logs ci-dessus pour identifier le problème.</p>";
    echo "</div>";
}

echo "</body></html>";
?>