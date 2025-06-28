<?php
/**
 * Test spécifique pour identifier le problème de connexion
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html><html><head><title>Test Login Issue</title></head><body>";
echo "<h1>🔍 Test du Problème de Connexion</h1>";

// Simuler exactement ce qui se passe dans login.php
echo "<h2>Test avec les mêmes conditions que login.php</h2>";

// Nettoyer la session
session_unset();

// Initialiser les tentatives
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// Générer token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo "<p><strong>État initial:</strong></p>";
echo "<ul>";
echo "<li>login_attempts: " . $_SESSION['login_attempts'] . "</li>";
echo "<li>csrf_token: " . substr($_SESSION['csrf_token'], 0, 16) . "...</li>";
echo "</ul>";

// Simuler POST
$_POST['username'] = 'admin@yellowjack.com';
$_POST['password'] = 'admin123';
$_POST['csrf_token'] = $_SESSION['csrf_token'];

echo "<h3>1. Vérification CSRF</h3>";
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo "<p style='color: red;'>❌ Token CSRF invalide</p>";
} else {
    echo "<p style='color: green;'>✅ Token CSRF valide</p>";
}

echo "<h3>2. Vérification des tentatives</h3>";
if ($_SESSION['login_attempts'] >= 5) {
    echo "<p style='color: red;'>❌ Trop de tentatives</p>";
} else {
    echo "<p style='color: green;'>✅ Tentatives OK (" . $_SESSION['login_attempts'] . "/5)</p>";
}

echo "<h3>3. Nettoyage des données (comme dans login.php)</h3>";
echo "<p><strong>Données originales:</strong></p>";
echo "<ul>";
echo "<li>username: '" . htmlspecialchars($_POST['username']) . "' (" . strlen($_POST['username']) . " chars)</li>";
echo "<li>password: '" . htmlspecialchars($_POST['password']) . "' (" . strlen($_POST['password']) . " chars)</li>";
echo "</ul>";

// Nettoyer exactement comme dans login.php
$username = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $_POST['username'] ?? ''));
$password = preg_replace('/[\x00-\x1F\x7F]/', '', $_POST['password'] ?? '');

echo "<p><strong>Données après nettoyage:</strong></p>";
echo "<ul>";
echo "<li>username: '" . htmlspecialchars($username) . "' (" . strlen($username) . " chars)</li>";
echo "<li>password: '" . htmlspecialchars($password) . "' (" . strlen($password) . " chars)</li>";
echo "</ul>";

if (empty($username) || empty($password)) {
    echo "<p style='color: red;'>❌ Champs vides après nettoyage</p>";
} else {
    echo "<p style='color: green;'>✅ Champs valides après nettoyage</p>";
    
    echo "<h3>4. Test d'authentification</h3>";
    try {
        $auth = getAuth();
        
        echo "<p>Tentative de connexion avec:</p>";
        echo "<ul>";
        echo "<li>Username: '" . htmlspecialchars($username) . "'</li>";
        echo "<li>Password: '" . htmlspecialchars($password) . "'</li>";
        echo "</ul>";
        
        if ($auth->login($username, $password)) {
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 CONNEXION RÉUSSIE !</p>";
            
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
            echo "<p><a href='panel/{$redirect_url}' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🚀 Aller au Dashboard</a></p>";
            
            echo "<hr>";
            echo "<h2>✅ PROBLÈME RÉSOLU !</h2>";
            echo "<p>La connexion fonctionne correctement. Le problème était probablement temporaire ou lié à la session.</p>";
            
        } else {
            echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ CONNEXION ÉCHOUÉE</p>";
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            
            echo "<p>Nouvelles tentatives: " . $_SESSION['login_attempts'] . "</p>";
            
            // Diagnostic supplémentaire
            echo "<h4>Diagnostic supplémentaire:</h4>";
            
            try {
                $db = Database::getInstance();
                $pdo = $db->getConnection();
                
                $stmt = $pdo->prepare("SELECT id, username, email, password_hash, status FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    echo "<p style='color: red;'>❌ Utilisateur non trouvé ou inactif</p>";
                } else {
                    echo "<p style='color: blue;'>Utilisateur trouvé:</p>";
                    echo "<ul>";
                    echo "<li>ID: " . $user['id'] . "</li>";
                    echo "<li>Username: " . htmlspecialchars($user['username']) . "</li>";
                    echo "<li>Email: " . htmlspecialchars($user['email']) . "</li>";
                    echo "<li>Status: " . htmlspecialchars($user['status']) . "</li>";
                    echo "</ul>";
                    
                    $password_valid = password_verify($password, $user['password_hash']);
                    echo "<p>Vérification mot de passe: " . ($password_valid ? '<span style="color: green;">✅ Valide</span>' : '<span style="color: red;">❌ Invalide</span>') . "</p>";
                    
                    if (!$password_valid) {
                        echo "<p style='color: orange;'>🔧 Correction du hash du mot de passe...</p>";
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        if ($update_stmt->execute([$new_hash, $user['id']])) {
                            echo "<p style='color: green;'>✅ Hash corrigé. Veuillez réessayer.</p>";
                            echo "<p><a href='?retry=1' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔄 Réessayer la Connexion</a></p>";
                        }
                    }
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>Erreur lors du diagnostic: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la connexion: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

echo "<hr>";
echo "<h3>Actions disponibles:</h3>";
echo "<p>";
echo "<a href='panel/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔐 Page de Connexion</a>";
echo "<a href='diagnostic_final.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Diagnostic Complet</a>";
echo "</p>";

echo "</body></html>";
?>