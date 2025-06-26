<?php
/**
 * Fichier de test pour vérifier la connexion à la base de données
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔧 Test de Connexion - Le Yellowjack</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

try {
    echo "<h2>📋 Étape 1 : Chargement de la configuration</h2>";
    
    // Vérifier si le fichier de configuration existe
    if (!file_exists('config/database.php')) {
        throw new Exception("❌ Le fichier config/database.php n'existe pas !");
    }
    
    echo "<p class='success'>✅ Fichier de configuration trouvé</p>";
    
    // Charger la configuration
    require_once 'config/database.php';
    
    echo "<p class='success'>✅ Configuration chargée</p>";
    echo "<p class='info'>📊 Host: " . DB_HOST . "</p>";
    echo "<p class='info'>📊 Port: " . DB_PORT . "</p>";
    echo "<p class='info'>📊 Database: " . DB_NAME . "</p>";
    echo "<p class='info'>📊 User: " . DB_USER . "</p>";
    
    echo "<h2>🔌 Étape 2 : Test de connexion à la base de données</h2>";
    
    // Tester la connexion directe
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p class='success'>✅ Connexion PDO réussie !</p>";
    
    // Tester la fonction getDB()
    $db = getDB();
    echo "<p class='success'>✅ Fonction getDB() fonctionne !</p>";
    
    echo "<h2>📊 Étape 3 : Vérification des tables</h2>";
    
    // Lister les tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p class='error'>❌ Aucune table trouvée ! La base de données n'a pas été initialisée.</p>";
        echo "<p class='info'>💡 Vous devez importer le fichier database/schema.sql</p>";
    } else {
        echo "<p class='success'>✅ Tables trouvées (" . count($tables) . ") :</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }
    
    echo "<h2>👤 Étape 4 : Vérification de l'utilisateur admin</h2>";
    
    // Vérifier si la table users existe
    if (in_array('users', $tables)) {
        $stmt = $db->prepare("SELECT id, username, email, role, status FROM users WHERE email = ?");
        $stmt->execute(['admin@yellowjack.com']);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "<p class='success'>✅ Utilisateur admin trouvé :</p>";
            echo "<ul>";
            echo "<li>ID: " . htmlspecialchars($admin['id']) . "</li>";
            echo "<li>Username: " . htmlspecialchars($admin['username']) . "</li>";
            echo "<li>Email: " . htmlspecialchars($admin['email']) . "</li>";
            echo "<li>Rôle: " . htmlspecialchars($admin['role']) . "</li>";
            echo "<li>Statut: " . htmlspecialchars($admin['status']) . "</li>";
            echo "</ul>";
            
            if ($admin['status'] !== 'active') {
                echo "<p class='error'>⚠️ L'utilisateur admin n'est pas actif !</p>";
            }
        } else {
            echo "<p class='error'>❌ Utilisateur admin non trouvé !</p>";
            echo "<p class='info'>💡 L'utilisateur admin n'a pas été créé ou a été supprimé.</p>";
        }
    } else {
        echo "<p class='error'>❌ Table 'users' non trouvée !</p>";
    }
    
    echo "<h2>🔐 Étape 5 : Test d'authentification</h2>";
    
    if (file_exists('includes/auth.php')) {
        require_once 'includes/auth.php';
        echo "<p class='success'>✅ Fichier auth.php chargé</p>";
        
        // Tester la création de l'objet Auth
        $auth = new Auth();
        echo "<p class='success'>✅ Objet Auth créé</p>";
        
        // Tester la fonction getAuth()
        $authInstance = getAuth();
        echo "<p class='success'>✅ Fonction getAuth() fonctionne</p>";
        
    } else {
        echo "<p class='error'>❌ Fichier includes/auth.php non trouvé !</p>";
    }
    
    echo "<h2>🎉 Résumé du Diagnostic</h2>";
    
    if (!empty($tables) && in_array('users', $tables) && isset($admin) && $admin && $admin['status'] === 'active') {
        echo "<p class='success'>✅ <strong>Tout semble fonctionner correctement !</strong></p>";
        echo "<p class='info'>💡 Si vous ne pouvez toujours pas vous connecter, vérifiez :</p>";
        echo "<ul>";
        echo "<li>Que vous utilisez les bons identifiants : admin@yellowjack.com / admin123</li>";
        echo "<li>Que les sessions PHP fonctionnent sur votre serveur</li>";
        echo "<li>Que JavaScript est activé dans votre navigateur</li>";
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ <strong>Problèmes détectés !</strong></p>";
        echo "<p class='info'>💡 Actions recommandées :</p>";
        echo "<ul>";
        if (empty($tables)) {
            echo "<li>Importer le fichier database/schema.sql dans votre base de données</li>";
        }
        if (!isset($admin) || !$admin) {
            echo "<li>Créer l'utilisateur admin en important les données par défaut</li>";
        }
        if (isset($admin) && $admin['status'] !== 'active') {
            echo "<li>Activer l'utilisateur admin dans la base de données</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Erreur de Base de Données</h2>";
    echo "<p class='error'>Erreur PDO : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='info'>💡 Vérifiez :</p>";
    echo "<ul>";
    echo "<li>Que le serveur MySQL est accessible</li>";
    echo "<li>Que les identifiants de connexion sont corrects</li>";
    echo "<li>Que la base de données existe</li>";
    echo "<li>Que l'utilisateur a les bonnes permissions</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Erreur Générale</h2>";
    echo "<p class='error'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>🕒 Test effectué le " . date('Y-m-d H:i:s') . "</small></p>";
echo "<p><small>⚠️ Supprimez ce fichier après le diagnostic pour des raisons de sécurité.</small></p>";
?>