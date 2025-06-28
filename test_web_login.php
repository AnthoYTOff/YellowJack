<?php
/**
 * Test de simulation de connexion web
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Test de Simulation Connexion Web</h1>";
echo "<p>Date: " . date('Y-m-d H:i:s') . "</p>";

// Nettoyer la session
session_unset();

// Générer un token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo "<h2>1. État Initial</h2>";
echo "<ul>";
echo "<li>Session ID: " . session_id() . "</li>";
echo "<li>CSRF Token: " . substr($_SESSION['csrf_token'], 0, 16) . "...</li>";
echo "<li>Login Attempts: " . ($_SESSION['login_attempts'] ?? 0) . "</li>";
echo "</ul>";

// Simuler une soumission de formulaire
if (isset($_GET['test'])) {
    echo "<h2>2. Test de Connexion</h2>";
    
    // Simuler les données POST
    $_POST['username'] = $_GET['username'] ?? 'admin@yellowjack.com';
    $_POST['password'] = $_GET['password'] ?? 'admin123';
    $_POST['csrf_token'] = $_SESSION['csrf_token'];
    
    echo "<p><strong>Données reçues :</strong></p>";
    echo "<ul>";
    echo "<li>Username: '" . htmlspecialchars($_POST['username']) . "'</li>";
    echo "<li>Password: '" . htmlspecialchars($_POST['password']) . "'</li>";
    echo "<li>CSRF Token: " . substr($_POST['csrf_token'], 0, 16) . "...</li>";
    echo "</ul>";
    
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<p style='color: red;'>✗ Erreur CSRF Token</p>";
    } else {
        echo "<p style='color: green;'>✓ CSRF Token valide</p>";
        
        // Gestion des tentatives de connexion
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt'] = 0;
        }
        
        $login_attempts = $_SESSION['login_attempts'];
        $time_since_last_attempt = time() - $_SESSION['last_attempt'];
        
        echo "<p>Tentatives de connexion: {$login_attempts}</p>";
        echo "<p>Temps depuis dernière tentative: {$time_since_last_attempt}s</p>";
        
        // Vérifier si bloqué
        if ($login_attempts >= 5 && $time_since_last_attempt < 300) {
            $remaining_time = 300 - $time_since_last_attempt;
            echo "<p style='color: red;'>✗ Compte temporairement bloqué. Réessayez dans {$remaining_time} secondes.</p>";
        } else {
            // Réinitialiser les tentatives si le délai est écoulé
            if ($time_since_last_attempt >= 300) {
                $_SESSION['login_attempts'] = 0;
            }
            
            // Nettoyer et normaliser les données
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            
            // Supprimer les caractères invisibles
            $username = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $username);
            $password = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $password);
            
            echo "<p><strong>Données nettoyées :</strong></p>";
            echo "<ul>";
            echo "<li>Username: '" . htmlspecialchars($username) . "' (" . strlen($username) . " chars)</li>";
            echo "<li>Password: '" . htmlspecialchars($password) . "' (" . strlen($password) . " chars)</li>";
            echo "</ul>";
            
            // Vérifier que les champs ne sont pas vides
            if (empty($username) || empty($password)) {
                echo "<p style='color: red;'>✗ Veuillez remplir tous les champs.</p>";
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
            } else {
                echo "<p style='color: green;'>✓ Champs valides</p>";
                
                // Tentative de connexion
                try {
                    $auth = getAuth();
                    
                    echo "<p><strong>Tentative de connexion...</strong></p>";
                    
                    if ($auth->login($username, $password)) {
                        echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ CONNEXION RÉUSSIE !</p>";
                        
                        // Réinitialiser les tentatives
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['last_attempt'] = 0;
                        
                        echo "<p><strong>Session créée :</strong></p>";
                        echo "<ul>";
                        echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'Non défini') . "</li>";
                        echo "<li>username: " . ($_SESSION['username'] ?? 'Non défini') . "</li>";
                        echo "<li>email: " . ($_SESSION['email'] ?? 'Non défini') . "</li>";
                        echo "<li>role: " . ($_SESSION['role'] ?? 'Non défini') . "</li>";
                        echo "<li>login_time: " . ($_SESSION['login_time'] ?? 'Non défini') . "</li>";
                        echo "</ul>";
                        
                        // Test de redirection
                        $redirect_url = 'dashboard.php';
                        if (isset($_SESSION['redirect_after_login'])) {
                            $redirect_url = $_SESSION['redirect_after_login'];
                            unset($_SESSION['redirect_after_login']);
                        }
                        
                        echo "<p><strong>Redirection vers :</strong> {$redirect_url}</p>";
                        echo "<p><a href='{$redirect_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Aller au Dashboard</a></p>";
                        
                    } else {
                        echo "<p style='color: red; font-size: 18px; font-weight: bold;'>✗ CONNEXION ÉCHOUÉE</p>";
                        echo "<p>Nom d'utilisateur ou mot de passe incorrect.</p>";
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt'] = time();
                        
                        echo "<p>Nouvelles tentatives: " . $_SESSION['login_attempts'] . "</p>";
                    }
                } catch (PDOException $e) {
                    echo "<p style='color: red;'>✗ Erreur de base de données: " . htmlspecialchars($e->getMessage()) . "</p>";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                } catch (Exception $e) {
                    echo "<p style='color: red;'>✗ Erreur système: " . htmlspecialchars($e->getMessage()) . "</p>";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                }
            }
        }
    }
} else {
    echo "<h2>2. Formulaire de Test</h2>";
    echo "<form method='GET' action=''>";
    echo "<input type='hidden' name='test' value='1'>";
    echo "<p>";
    echo "<label>Username:</label><br>";
    echo "<input type='text' name='username' value='admin@yellowjack.com' style='width: 300px; padding: 5px;'>";
    echo "</p>";
    echo "<p>";
    echo "<label>Password:</label><br>";
    echo "<input type='password' name='password' value='admin123' style='width: 300px; padding: 5px;'>";
    echo "</p>";
    echo "<p>";
    echo "<input type='submit' value='Tester la Connexion' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>";
    echo "</p>";
    echo "</form>";
    
    echo "<h3>Tests Rapides</h3>";
    echo "<p>";
    echo "<a href='?test=1&username=admin@yellowjack.com&password=admin123' style='background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;'>Test avec Email</a>";
    echo "<a href='?test=1&username=admin&password=admin123' style='background: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px;'>Test avec Username</a>";
    echo "</p>";
}

echo "<hr>";
echo "<p><a href='panel/login.php'>← Retour à la page de connexion</a></p>";
?>