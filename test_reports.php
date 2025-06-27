<?php
/**
 * Test simple pour vérifier reports.php
 */

echo "<h1>Test de reports.php</h1>";

// Test 1: Vérifier les fichiers requis
echo "<h2>1. Vérification des fichiers</h2>";
$files = [
    'includes/auth_local.php',
    'config/database_local.php',
    'panel/reports.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file existe<br>";
    } else {
        echo "✗ $file manquant<br>";
    }
}

// Test 2: Tester la configuration locale
echo "<h2>2. Test de la base de données locale</h2>";
try {
    require_once 'config/database_local.php';
    $db = getDB();
    echo "✓ Connexion à la base de données SQLite réussie<br>";
    
    // Test d'une requête simple
    $stmt = $db->query('SELECT COUNT(*) as count FROM users');
    $result = $stmt->fetch();
    echo "✓ Nombre d'utilisateurs: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "<br>";
}

// Test 3: Tester l'authentification locale
echo "<h2>3. Test de l'authentification locale</h2>";
try {
    require_once 'includes/auth_local.php';
    requireLogin();
    $auth = getAuth();
    $user = $auth->getCurrentUser();
    echo "✓ Utilisateur connecté: " . $user['username'] . " (" . $user['role'] . ")<br>";
} catch (Exception $e) {
    echo "✗ Erreur d'authentification: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Lien vers reports.php</h2>";
echo '<a href="panel/reports.php" target="_blank">Tester reports.php</a>';
?>