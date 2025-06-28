<?php
/**
 * Diagnostic final pour identifier le problème de connexion
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html>";
echo "<html><head><title>Diagnostic Final</title></head><body>";
echo "<h1>🔍 Diagnostic Final du Problème de Connexion</h1>";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Base de données
echo "<h2>1. Test Base de Données</h2>";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "<p style='color: green;'>✅ Connexion DB OK</p>";
    
    // Vérifier l'utilisateur admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' OR email = 'admin@yellowjack.com'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✅ Utilisateur admin trouvé</p>";
        echo "<ul>";
        echo "<li>ID: " . $admin['id'] . "</li>";
        echo "<li>Username: " . htmlspecialchars($admin['username']) . "</li>";
        echo "<li>Email: " . htmlspecialchars($admin['email']) . "</li>";
        echo "<li>Status: " . htmlspecialchars($admin['status']) . "</li>";
        echo "</ul>";
        
        // Test du mot de passe
        $password_ok = password_verify('admin123', $admin['password_hash']);
        echo "<p>Mot de passe 'admin123': " . ($password_ok ? "<span style='color: green;'>✅ Valide</span>" : "<span style='color: red;'>❌ Invalide</span>") . "</p>";
        
        if (!$password_ok) {
            // Corriger le mot de passe
            $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($update_stmt->execute([$new_hash, $admin['id']])) {
                echo "<p style='color: orange;'>🔧 Mot de passe corrigé automatiquement</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Utilisateur admin non trouvé</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur DB: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Authentification directe
echo "<h2>2. Test Authentification Directe</h2>";
try {
    // Nettoyer la session
    session_unset();
    
    $auth = getAuth();
    $result = $auth->login('admin@yellowjack.com', 'admin123');
    
    if ($result) {
        echo "<p style='color: green;'>✅ Authentification réussie</p>";
        echo "<p>Session créée:</p>";
        echo "<ul>";
        echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
        echo "<li>username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
        echo "<li>email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
        echo "<li>role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
        echo "</ul>";
        
        // Test isLoggedIn
        $logged_in = $auth->isLoggedIn();
        echo "<p>isLoggedIn(): " . ($logged_in ? "<span style='color: green;'>✅ Oui</span>" : "<span style='color: red;'>❌ Non</span>") . "</p>";
        
        // Nettoyer pour les tests suivants
        $auth->logout();
    } else {
        echo "<p style='color: red;'>❌ Authentification échouée</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur auth: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: Simulation POST comme dans login.php
echo "<h2>3. Test Simulation POST (comme login.php)</h2>";

// Nettoyer la session
session_unset();

// Générer token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simuler POST
$_POST['username'] = 'admin@yellowjack.com';
$_POST['password'] = 'admin123';
$_POST['csrf_token'] = $_SESSION['csrf_token'];

echo "<p><strong>Simulation des données POST:</strong></p>";
echo "<ul>";
echo "<li>username: '" . htmlspecialchars($_POST['username']) . "'</li>";
echo "<li>password: '" . htmlspecialchars($_POST['password']) . "'</li>";
echo "<li>csrf_token: " . substr($_POST['csrf_token'], 0, 16) . "...</li>";
echo "</ul>";

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "<p style='color: red;'>❌ Erreur CSRF</p>";
} else {
    echo "<p style='color: green;'>✅ CSRF OK</p>";
    
    // Initialiser les tentatives
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = 0;
    }
    
    $login_attempts = $_SESSION['login_attempts'];
    $time_since_last = time() - $_SESSION['last_attempt'];
    
    echo "<p>Tentatives: {$login_attempts}, Temps écoulé: {$time_since_last}s</p>";
    
    // Vérifier le blocage
    if ($login_attempts >= 5 && $time_since_last < 300) {
        echo "<p style='color: red;'>❌ Compte bloqué</p>";
    } else {
        // Réinitialiser si nécessaire
        if ($time_since_last >= 300) {
            $_SESSION['login_attempts'] = 0;
        }
        
        // Nettoyer les données
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        $username = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $username);
        $password = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $password);
        
        echo "<p>Données nettoyées: username='" . htmlspecialchars($username) . "', password='" . htmlspecialchars($password) . "'</p>";
        
        if (empty($username) || empty($password)) {
            echo "<p style='color: red;'>❌ Champs vides après nettoyage</p>";
        } else {
            echo "<p style='color: green;'>✅ Champs valides</p>";
            
            // Tentative de connexion
            try {
                $auth = getAuth();
                
                if ($auth->login($username, $password)) {
                    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 CONNEXION RÉUSSIE !</p>";
                    
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['last_attempt'] = 0;
                    
                    echo "<p><strong>Session après connexion:</strong></p>";
                    echo "<ul>";
                    echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
                    echo "<li>username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
                    echo "<li>email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
                    echo "<li>role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
                    echo "</ul>";
                    
                    // Test de redirection
                    $redirect_url = 'dashboard.php';
                    if (isset($_SESSION['redirect_after_login'])) {
                        $redirect_url = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                    }
                    
                    echo "<p><strong>Redirection vers:</strong> {$redirect_url}</p>";
                    echo "<p><a href='panel/{$redirect_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Aller au Dashboard</a></p>";
                    
                } else {
                    echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ CONNEXION ÉCHOUÉE</p>";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                    echo "<p>Nouvelles tentatives: " . $_SESSION['login_attempts'] . "</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de la connexion: " . htmlspecialchars($e->getMessage()) . "</p>";
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
            }
        }
    }
}

// Test 4: Vérifier les fichiers
echo "<h2>4. Vérification des Fichiers</h2>";
$files = [
    'config/database.php' => 'Configuration DB',
    'includes/auth.php' => 'Authentification',
    'panel/login.php' => 'Page de connexion',
    'panel/dashboard.php' => 'Dashboard'
];

foreach ($files as $file => $desc) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p style='color: green;'>✅ {$desc} ({$file})</p>";
    } else {
        echo "<p style='color: red;'>❌ {$desc} manquant ({$file})</p>";
    }
}

echo "<hr>";
echo "<h2>🎯 Actions Recommandées</h2>";
echo "<p><a href='panel/login.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; margin-right: 10px;'>🔐 Tester la Page de Connexion</a></p>";
echo "<p style='margin-top: 20px;'><strong>Identifiants de test:</strong></p>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@yellowjack.com</li>";
echo "<li><strong>Username:</strong> admin</li>";
echo "<li><strong>Mot de passe:</strong> admin123</li>";
echo "</ul>";

echo "</body></html>";
?>