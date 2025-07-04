<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Test de gestion des semaines</h2>";

try {
    // Test de connexion à la base de données
    $db = getDB();
    echo "<p style='color: green;'>✓ Connexion à la base de données réussie</p>";
    
    // Test des fonctions de période
    $currentPeriod = getCurrentWeekPeriod();
    echo "<p><strong>Période actuelle:</strong> du " . date('d/m/Y', strtotime($currentPeriod['week_start'])) . " au " . date('d/m/Y', strtotime($currentPeriod['week_end'])) . "</p>";
    
    $nextPeriod = getNextWeekPeriod();
    echo "<p><strong>Prochaine période:</strong> du " . date('d/m/Y', strtotime($nextPeriod['week_start'])) . " au " . date('d/m/Y', strtotime($nextPeriod['week_end'])) . "</p>";
    
    // Test de la semaine active
    $activeWeek = getActiveWeek();
    if ($activeWeek) {
        echo "<p style='color: green;'>✓ Semaine active trouvée:</p>";
        echo "<ul>";
        echo "<li>ID: " . $activeWeek['id'] . "</li>";
        echo "<li>Début: " . date('d/m/Y', strtotime($activeWeek['week_start'])) . "</li>";
        echo "<li>Fin: " . date('d/m/Y', strtotime($activeWeek['week_end'])) . "</li>";
        echo "<li>Finalisée: " . ($activeWeek['is_finalized'] ? 'Oui' : 'Non') . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ Aucune semaine active trouvée</p>";
    }
    
    // Afficher toutes les semaines
    $stmt = $db->query("SELECT * FROM weekly_taxes ORDER BY week_start DESC");
    $weeks = $stmt->fetchAll();
    
    echo "<h3>Toutes les semaines:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Début</th><th>Fin</th><th>Finalisée</th></tr>";
    foreach ($weeks as $week) {
        echo "<tr>";
        echo "<td>" . $week['id'] . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($week['week_start'])) . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($week['week_end'])) . "</td>";
        echo "<td>" . ($week['is_finalized'] ? 'Oui' : 'Non') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erreur: " . $e->getMessage() . "</p>";
}
?>