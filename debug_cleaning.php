<?php
/**
 * Script de débogage pour la page de ménage
 */

// Démarrer la session et activer l'affichage des erreurs
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Débogage de la page de ménage</h2>";

try {
    echo "<h3>1. Vérification des sessions</h3>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Données de session:</p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    
    echo "<h3>2. Test d'inclusion des fichiers</h3>";
    require_once 'config/database.php';
    echo "<p style='color: green;'>✓ config/database.php</p>";
    
    require_once 'includes/auth.php';
    echo "<p style='color: green;'>✓ includes/auth.php</p>";
    
    echo "<h3>3. Test d'authentification</h3>";
    $auth = getAuth();
    echo "<p>Auth object: " . (is_object($auth) ? 'OK' : 'ERREUR') . "</p>";
    
    if ($auth->isLoggedIn()) {
        echo "<p style='color: green;'>✓ Utilisateur connecté</p>";
        $user = $auth->getCurrentUser();
        echo "<p>Utilisateur: " . $user['first_name'] . " " . $user['last_name'] . " (ID: " . $user['id'] . ")</p>";
        
        echo "<h3>4. Test de la base de données</h3>";
        $db = getDB();
        
        // Test de la requête principale
        $stmt = $db->prepare("SELECT * FROM cleaning_services WHERE user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $current_session = $stmt->fetch();
        
        if ($current_session) {
            echo "<p style='color: blue;'>ℹ Service en cours trouvé:</p>";
            echo "<pre>" . print_r($current_session, true) . "</pre>";
        } else {
            echo "<p style='color: orange;'>ℹ Aucun service en cours</p>";
        }
        
        // Test des statistiques du jour
        $today = date('Y-m-d');
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as sessions_today,
                COALESCE(SUM(cleaning_count), 0) as total_cleaning_today,
                COALESCE(SUM(total_salary), 0) as total_salary_today,
                COALESCE(SUM(duration_minutes), 0) as total_duration_today
            FROM cleaning_services 
            WHERE user_id = ? AND DATE(start_time) = ?
        ");
        $stmt->execute([$user['id'], $today]);
        $today_stats = $stmt->fetch();
        
        echo "<p style='color: green;'>✓ Statistiques du jour:</p>";
        echo "<pre>" . print_r($today_stats, true) . "</pre>";
        
        echo "<p style='color: green;'><strong>Tous les tests sont passés !</strong></p>";
        echo "<p><a href='panel/cleaning.php' target='_blank'>Ouvrir la page de ménage</a></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Utilisateur non connecté</p>";
        echo "<p><a href='panel/login.php'>Se connecter</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
    echo "<p style='color: red;'>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='panel/dashboard.php'>Retour au dashboard</a></p>";
?>