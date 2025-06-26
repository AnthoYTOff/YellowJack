<?php
/**
 * Script de test pour la page de ménage
 */

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Test de la page de ménage</h2>";

try {
    echo "<p>1. Test de l'inclusion des fichiers...</p>";
    
    // Tester l'inclusion des fichiers
    require_once 'config/database.php';
    echo "<p style='color: green;'>✓ config/database.php chargé</p>";
    
    require_once 'includes/auth.php';
    echo "<p style='color: green;'>✓ includes/auth.php chargé</p>";
    
    echo "<p>2. Test de la connexion à la base de données...</p>";
    $db = getDB();
    echo "<p style='color: green;'>✓ Connexion à la base de données réussie</p>";
    
    echo "<p>3. Test des fonctions utilitaires...</p>";
    $current_time = getCurrentDateTime();
    echo "<p style='color: green;'>✓ getCurrentDateTime(): {$current_time}</p>";
    
    $formatted_time = formatDateTime($current_time);
    echo "<p style='color: green;'>✓ formatDateTime(): {$formatted_time}</p>";
    
    echo "<p>4. Test de la table cleaning_services...</p>";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cleaning_services");
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    echo "<p style='color: green;'>✓ Table cleaning_services accessible, {$count} enregistrements</p>";
    
    echo "<p>5. Test des constantes...</p>";
    echo "<p style='color: green;'>✓ CLEANING_RATE: " . CLEANING_RATE . "$</p>";
    
    echo "<p style='color: green;'><strong>Tous les tests sont passés ! La page de ménage devrait fonctionner.</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><a href='panel/cleaning.php'>Tester la page de ménage</a></p>";
echo "<p><a href='panel/dashboard.php'>Retour au dashboard</a></p>";
?>