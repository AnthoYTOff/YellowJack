<?php
/**
 * Script pour vérifier les données du tableau de bord
 * 
 * Ce script vérifie s'il y a des données dans les tables pour expliquer
 * pourquoi le tableau de bord apparaît vide
 * 
 * @author Assistant IA
 * @version 1.0
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

try {
    $db = getDB();
    
    echo "<h2>🔍 Vérification des Données du Tableau de Bord</h2>";
    
    // Vérifier les utilisateurs
    echo "<h3>👥 Utilisateurs</h3>";
    $stmt = $db->query("SELECT id, username, email, first_name, last_name, role, status FROM users");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color: red;'>❌ Aucun utilisateur trouvé</p>";
    } else {
        echo "<p style='color: green;'>✅ " . count($users) . " utilisateur(s) trouvé(s)</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Nom</th><th>Rôle</th><th>Statut</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Vérifier les services de ménage
    echo "<h3>🧹 Services de Ménage</h3>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM cleaning_services");
    $cleaning_count = $stmt->fetch()['count'];
    
    if ($cleaning_count == 0) {
        echo "<p style='color: orange;'>⚠️ Aucun service de ménage enregistré</p>";
        echo "<p><em>C'est normal pour un nouveau système. Les données apparaîtront quand les employés commenceront à enregistrer leurs services.</em></p>";
    } else {
        echo "<p style='color: green;'>✅ " . $cleaning_count . " service(s) de ménage trouvé(s)</p>";
        
        // Afficher quelques exemples
        $stmt = $db->query("SELECT cs.*, u.username FROM cleaning_services cs LEFT JOIN users u ON cs.user_id = u.id ORDER BY cs.created_at DESC LIMIT 5");
        $services = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Utilisateur</th><th>Début</th><th>Fin</th><th>Statut</th><th>Salaire</th></tr>";
        foreach ($services as $service) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($service['id']) . "</td>";
            echo "<td>" . htmlspecialchars($service['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($service['start_time'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($service['end_time'] ?? 'En cours') . "</td>";
            echo "<td>" . htmlspecialchars($service['status']) . "</td>";
            echo "<td>" . htmlspecialchars($service['total_salary'] ?? '0') . "$</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Vérifier les ventes
    echo "<h3>💰 Ventes</h3>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM sales");
    $sales_count = $stmt->fetch()['count'];
    
    if ($sales_count == 0) {
        echo "<p style='color: orange;'>⚠️ Aucune vente enregistrée</p>";
        echo "<p><em>C'est normal pour un nouveau système. Les données apparaîtront quand les employés commenceront à utiliser la caisse.</em></p>";
    } else {
        echo "<p style='color: green;'>✅ " . $sales_count . " vente(s) trouvée(s)</p>";
        
        // Afficher quelques exemples
        $stmt = $db->query("SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 5");
        $sales = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Utilisateur</th><th>Client</th><th>Montant</th><th>Commission</th><th>Date</th></tr>";
        foreach ($sales as $sale) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sale['id']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['username'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($sale['customer_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($sale['final_amount']) . "$</td>";
            echo "<td>" . htmlspecialchars($sale['employee_commission'] ?? '0') . "$</td>";
            echo "<td>" . htmlspecialchars($sale['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Vérifier les produits
    echo "<h3>📦 Produits</h3>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $products_count = $stmt->fetch()['count'];
    
    if ($products_count == 0) {
        echo "<p style='color: red;'>❌ Aucun produit actif trouvé</p>";
    } else {
        echo "<p style='color: green;'>✅ " . $products_count . " produit(s) actif(s) trouvé(s)</p>";
        
        // Vérifier les stocks
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_alert AND is_active = 1");
        $low_stock = $stmt->fetch()['count'];
        
        if ($low_stock > 0) {
            echo "<p style='color: orange;'>⚠️ " . $low_stock . " produit(s) en rupture de stock</p>";
        } else {
            echo "<p style='color: green;'>✅ Tous les produits ont un stock suffisant</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>💡 Conclusion</h3>";
    
    if ($cleaning_count == 0 && $sales_count == 0) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
        echo "<h4>📋 Tableau de bord vide - C'est normal !</h4>";
        echo "<p>Le tableau de bord apparaît vide car aucune activité n'a encore été enregistrée :</p>";
        echo "<ul>";
        echo "<li><strong>Aucun service de ménage</strong> - Les employés doivent commencer à enregistrer leurs services via 'Gestion Ménages'</li>";
        echo "<li><strong>Aucune vente</strong> - Les employés doivent commencer à utiliser la 'Caisse Enregistreuse'</li>";
        echo "</ul>";
        echo "<p><strong>Pour tester le système :</strong></p>";
        echo "<ol>";
        echo "<li>Allez dans 'Gestion Ménages' et démarrez un service</li>";
        echo "<li>Allez dans 'Nouvelle Vente' et enregistrez une vente</li>";
        echo "<li>Retournez au tableau de bord pour voir les statistiques</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<p style='color: green;'>✅ Des données existent. Si le tableau de bord est toujours vide, il pourrait y avoir un problème de permissions ou de requêtes.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT :</strong> Supprimez ce fichier après utilisation pour des raisons de sécurité.</p>";
echo "<p>🕒 Vérification effectuée le " . date('Y-m-d H:i:s') . "</p>";
?>