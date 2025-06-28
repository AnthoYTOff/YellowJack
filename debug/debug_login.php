<?php
/**
 * Script de débogage pour analyser les données de connexion
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Débogage des données de connexion</h2>";

// Afficher les données POST
echo "<h3>Données POST reçues :</h3>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

// Afficher les données de session
echo "<h3>Données de session :</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Afficher les en-têtes HTTP
echo "<h3>En-têtes HTTP :</h3>";
echo "<pre>";
print_r(getallheaders());
echo "</pre>";

// Afficher les variables serveur pertinentes
echo "<h3>Variables serveur :</h3>";
echo "<ul>";
echo "<li>REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'Non défini') . "</li>";
echo "<li>CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'Non défini') . "</li>";
echo "<li>CONTENT_LENGTH: " . ($_SERVER['CONTENT_LENGTH'] ?? 'Non défini') . "</li>";
echo "<li>HTTP_USER_AGENT: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non défini') . "</li>";
echo "<li>REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'Non défini') . "</li>";
echo "</ul>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Analyse des données de connexion :</h3>";
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<p><strong>Username reçu :</strong></p>";
    echo "<ul>";
    echo "<li>Valeur brute: '" . htmlspecialchars($username) . "'</li>";
    echo "<li>Longueur: " . strlen($username) . " caractères</li>";
    echo "<li>Encodage: " . mb_detect_encoding($username) . "</li>";
    echo "<li>Après trim(): '" . htmlspecialchars(trim($username)) . "'</li>";
    echo "<li>Caractères invisibles: " . (preg_match('/[\x00-\x1F\x7F]/', $username) ? 'Détectés' : 'Aucun') . "</li>";
    echo "</ul>";
    
    echo "<p><strong>Password reçu :</strong></p>";
    echo "<ul>";
    echo "<li>Longueur: " . strlen($password) . " caractères</li>";
    echo "<li>Encodage: " . mb_detect_encoding($password) . "</li>";
    echo "<li>Après trim(): Longueur " . strlen(trim($password)) . " caractères</li>";
    echo "<li>Caractères invisibles: " . (preg_match('/[\x00-\x1F\x7F]/', $password) ? 'Détectés' : 'Aucun') . "</li>";
    echo "</ul>";
    
    // Test de connexion avec les données reçues
    echo "<h3>Test de connexion :</h3>";
    
    try {
        $auth = getAuth();
        
        echo "<p>Test 1 - Données brutes :</p>";
        $result1 = $auth->login($username, $password);
        echo "<p>Résultat: " . ($result1 ? '<span style="color: green;">✓ Succès</span>' : '<span style="color: red;">✗ Échec</span>') . "</p>";
        
        if (!$result1) {
            echo "<p>Test 2 - Données nettoyées (trim) :</p>";
            $result2 = $auth->login(trim($username), trim($password));
            echo "<p>Résultat: " . ($result2 ? '<span style="color: green;">✓ Succès</span>' : '<span style="color: red;">✗ Échec</span>') . "</p>";
        }
        
        if (!$result1 && !$result2) {
            echo "<p>Test 3 - Vérification en base de données :</p>";
            
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?");
            $stmt->execute([trim($username), trim($username)]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<p style='color: orange;'>Utilisateur trouvé :</p>";
                echo "<ul>";
                echo "<li>ID: " . $user['id'] . "</li>";
                echo "<li>Username: '" . htmlspecialchars($user['username']) . "'</li>";
                echo "<li>Email: '" . htmlspecialchars($user['email']) . "'</li>";
                echo "</ul>";
                
                $password_check = password_verify(trim($password), $user['password_hash']);
                echo "<p>Vérification mot de passe: " . ($password_check ? '<span style="color: green;">✓ Correct</span>' : '<span style="color: red;">✗ Incorrect</span>') . "</p>";
                
                if (!$password_check) {
                    // Test avec différents mots de passe courants
                    $test_passwords = ['admin123', 'admin', 'password', 'yellowjack'];
                    echo "<p>Test avec mots de passe courants :</p>";
                    echo "<ul>";
                    foreach ($test_passwords as $test_pwd) {
                        $test_result = password_verify($test_pwd, $user['password_hash']);
                        echo "<li>'" . $test_pwd . "': " . ($test_result ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>') . "</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "<p style='color: red;'>Aucun utilisateur trouvé avec l'identifiant: '" . htmlspecialchars(trim($username)) . "'</p>";
                
                // Lister les utilisateurs existants
                $stmt = $pdo->query("SELECT username, email FROM users WHERE status = 'active'");
                $users = $stmt->fetchAll();
                
                echo "<p>Utilisateurs actifs dans la base :</p>";
                echo "<ul>";
                foreach ($users as $u) {
                    echo "<li>Username: '" . htmlspecialchars($u['username']) . "' | Email: '" . htmlspecialchars($u['email']) . "'</li>";
                }
                echo "</ul>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<hr>";
echo "<h3>Formulaire de test :</h3>";
echo "<form method='POST' action=''>";
echo "<p>Username: <input type='text' name='username' value='admin@yellowjack.com'></p>";
echo "<p>Password: <input type='password' name='password' value='admin123'></p>";
echo "<p><input type='submit' value='Tester la connexion'></p>";
echo "</form>";

echo "<p><a href='../panel/login.php'>Retour à la page de connexion</a></p>";
?>