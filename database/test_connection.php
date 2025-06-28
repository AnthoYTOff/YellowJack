<?php
/**
 * Script de test de connexion à la base de données
 */

require_once __DIR__ . '/../config/database.php';

echo "<h2>Test de connexion à la base de données</h2>";
echo "<p>Configuration actuelle :</p>";
echo "<ul>";
echo "<li>Host: " . DB_HOST . "</li>";
echo "<li>Port: " . DB_PORT . "</li>";
echo "<li>Database: " . DB_NAME . "</li>";
echo "<li>User: " . DB_USER . "</li>";
echo "<li>Debug: " . (APP_DEBUG ? 'Activé' : 'Désactivé') . "</li>";
echo "</ul>";

try {
    echo "<p>Tentative de connexion...</p>";
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "<p style='color: green;'><strong>✓ Connexion réussie !</strong></p>";
    
    // Test d'une requête simple
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    
    echo "<p>Nombre d'utilisateurs dans la base : " . $result['count'] . "</p>";
    
    // Test de la table users
    $stmt = $pdo->query("SELECT id, username, email, role FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    
    echo "<h3>Utilisateurs dans la base :</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Erreur de connexion PDO :</strong></p>";
    echo "<p>Code d'erreur : " . $e->getCode() . "</p>";
    echo "<p>Message : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Fichier : " . $e->getFile() . " (ligne " . $e->getLine() . ")</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Erreur générale :</strong></p>";
    echo "<p>Message : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Fichier : " . $e->getFile() . " (ligne " . $e->getLine() . ")</p>";
}

echo "<hr>";
echo "<p><a href='../panel/login.php'>Retour à la page de connexion</a></p>";
?>