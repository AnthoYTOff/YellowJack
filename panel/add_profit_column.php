<?php
/**
 * Script pour ajouter la colonne total_profit à la table weekly_performance
 * À exécuter une seule fois pour mettre à jour la structure de la base de données
 */

require_once '../config/database.php';

$db = getDB();

try {
    // Vérifier si la colonne existe déjà
    $stmt = $db->prepare("SHOW COLUMNS FROM weekly_performance LIKE 'total_profit'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Ajouter la colonne total_profit après total_revenue
        $sql = "ALTER TABLE weekly_performance ADD COLUMN total_profit DECIMAL(10,2) DEFAULT 0.00 AFTER total_revenue";
        $db->exec($sql);
        echo "Colonne total_profit ajoutée avec succès à la table weekly_performance.\n";
        
        // Mettre à jour les enregistrements existants avec une valeur par défaut
        $update_sql = "UPDATE weekly_performance SET total_profit = 0.00 WHERE total_profit IS NULL";
        $db->exec($update_sql);
        echo "Valeurs par défaut mises à jour pour les enregistrements existants.\n";
    } else {
        echo "La colonne total_profit existe déjà dans la table weekly_performance.\n";
    }
    
} catch (Exception $e) {
    echo "Erreur lors de l'ajout de la colonne : " . $e->getMessage() . "\n";
}

echo "Script terminé.\n";
?>