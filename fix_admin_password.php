<?php
/**
 * Script pour corriger le mot de passe administrateur
 * 
 * Ce script met à jour le hash du mot de passe admin pour qu'il corresponde à 'admin123'
 * 
 * @author Assistant IA
 * @version 1.0
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    // Générer le bon hash pour 'admin123'
    $correct_password = 'admin123';
    $correct_hash = password_hash($correct_password, PASSWORD_DEFAULT);
    
    echo "<h2>🔧 Correction du mot de passe administrateur</h2>";
    echo "<p>📊 Nouveau hash généré : " . htmlspecialchars($correct_hash) . "</p>";
    
    // Mettre à jour le mot de passe admin
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin' OR email = 'admin@yellowjack.com'");
    $result = $stmt->execute([$correct_hash]);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Mot de passe administrateur mis à jour avec succès !</p>";
        echo "<p><strong>Identifiants de connexion :</strong></p>";
        echo "<ul>";
        echo "<li>Email : admin@yellowjack.com</li>";
        echo "<li>Mot de passe : admin123</li>";
        echo "</ul>";
        
        // Vérifier que la mise à jour a fonctionné
        $stmt = $db->prepare("SELECT username, email, password_hash FROM users WHERE username = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user && password_verify($correct_password, $user['password_hash'])) {
            echo "<p style='color: green;'>✅ Vérification : Le mot de passe fonctionne correctement !</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur : La vérification a échoué</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Erreur lors de la mise à jour du mot de passe</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité.</p>";
echo "<p>🕒 Script exécuté le " . date('Y-m-d H:i:s') . "</p>";
?>