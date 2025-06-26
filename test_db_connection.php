<?php
/**
 * Test de connexion à la base de données et création d'un utilisateur de test
 */

require_once 'config/database.php';

echo "<h2>Test de connexion à la base de données</h2>";

try {
    $db = getDB();
    echo "<p style='color: green;'>✓ Connexion à la base de données réussie</p>";
    
    // Vérifier les tables existantes
    echo "<h3>Tables existantes :</h3>";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "<p>- $table</p>";
    }
    
    // Vérifier s'il y a des utilisateurs
    echo "<h3>Utilisateurs existants :</h3>";
    $stmt = $db->query("SELECT id, username, role, status FROM users");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: orange;'>⚠️ Aucun utilisateur trouvé</p>";
        
        // Créer un utilisateur de test
        echo "<h3>Création d'un utilisateur de test :</h3>";
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, role, status, first_name, last_name) 
            VALUES (?, ?, 'patron', 'active', 'Admin', 'Test')
        ");
        
        if ($stmt->execute(['admin', $password_hash])) {
            echo "<p style='color: green;'>✓ Utilisateur de test créé :</p>";
            echo "<p><strong>Nom d'utilisateur :</strong> admin</p>";
            echo "<p><strong>Mot de passe :</strong> admin123</p>";
            echo "<p><strong>Rôle :</strong> patron</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de la création de l'utilisateur</p>";
        }
    } else {
        echo "<p style='color: green;'>✓ Utilisateurs trouvés :</p>";
        foreach ($users as $user) {
            echo "<p>- ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}, Status: {$user['status']}</p>";
        }
    }
    
    // Vérifier la structure de la table cleaning_services
    echo "<h3>Structure de la table cleaning_services :</h3>";
    $stmt = $db->query("DESCRIBE cleaning_services");
    $columns = $stmt->fetchAll();
    foreach ($columns as $column) {
        echo "<p>- {$column['Field']} ({$column['Type']})</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='panel/login.php'>Aller à la page de connexion</a></p>";
echo "<p><a href='panel/dashboard.php'>Aller au dashboard</a></p>";
?>