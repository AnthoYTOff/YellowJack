<?php
/**
 * Test de connexion simple
 */

try {
    require_once 'config/database.php';
    $db = getDB();
    echo "✅ Connexion à la base de données réussie !\n";
    
    // Tester une requête simple
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Test de requête réussi : " . $result['test'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>