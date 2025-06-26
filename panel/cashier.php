<?php
/**
 * Caisse enregistreuse - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePermission('cashier');

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

$message = '';
$error = '';

// Traitement de la vente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'process_sale') {
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $items = $_POST['items'] ?? [];
            
            if (empty($items)) {
                $error = 'Aucun produit sélectionné.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Calculer le total et vérifier le stock
                    $total_amount = 0;
                    $sale_items = [];
                    
                    foreach ($items as $item) {
                        $product_id = intval($item['product_id']);
                        $quantity = intval($item['quantity']);
                        
                        if ($quantity <= 0) continue;
                        
                        // Récupérer les informations du produit
                        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch();
                        
                        if (!$product) {
                            throw new Exception("Produit introuvable: ID $product_id");
                        }
                        
                        if ($product['stock_quantity'] < $quantity) {
                            throw new Exception("Stock insuffisant pour {$product['name']} (disponible: {$product['stock_quantity']}, demandé: $quantity)");
                        }
                        
                        $item_total = $product['sale_price'] * $quantity;
                        $total_amount += $item_total;
                        
                        $sale_items[] = [
                            'product' => $product,
                            'quantity' => $quantity,
                            'unit_price' => $product['sale_price'],
                            'total_price' => $item_total
                        ];
                    }
                    
                    if (empty($sale_items)) {
                        throw new Exception('Aucun produit valide dans la commande.');
                    }
                    
                    // Récupérer les informations du client
                    $customer = null;
                    if ($customer_id > 0) {
                        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
                        $stmt->execute([$customer_id]);
                        $customer = $stmt->fetch();
                    }
                    
                    // Appliquer la réduction client fidèle
                    $discount_amount = 0;
                    if ($customer && $customer['is_loyal']) {
                        $discount_amount = $total_amount * ($customer['discount_percentage'] / 100);
                    }
                    
                    $final_amount = $total_amount - $discount_amount;
                    $commission = $final_amount * (COMMISSION_RATE / 100);
                    
                    // Créer la vente
                    $stmt = $db->prepare("
                        INSERT INTO sales (user_id, customer_id, total_amount, discount_amount, final_amount, commission, sale_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user['id'],
                        $customer_id ?: null,
                        $total_amount,
                        $discount_amount,
                        $final_amount,
                        $commission,
                        getCurrentDateTime()
                    ]);
                    
                    $sale_id = $db->lastInsertId();
                    
                    // Ajouter les détails de la vente et mettre à jour le stock
                    foreach ($sale_items as $item) {
                        // Ajouter le détail de vente
                        $stmt = $db->prepare("
                            INSERT INTO sale_details (sale_id, product_id, quantity, unit_price, total_price) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $sale_id,
                            $item['product']['id'],
                            $item['quantity'],
                            $item['unit_price'],
                            $item['total_price']
                        ]);
                        
                        // Mettre à jour le stock
                        $stmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                        $stmt->execute([$item['quantity'], $item['product']['id']]);
                    }
                    
                    $db->commit();
                    
                    // Envoyer le ticket Discord (si webhook configuré)
                    sendDiscordTicket($sale_id, $sale_items, $customer, $total_amount, $discount_amount, $final_amount, $commission, $user);
                    
                    $message = "Vente enregistrée avec succès ! Ticket #$sale_id - Total: {$final_amount}$ - Commission: {$commission}$";
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Récupérer les produits disponibles
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN product_categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 AND p.stock_quantity > 0
    ORDER BY c.name, p.name
");
$stmt->execute();
$products = $stmt->fetchAll();

// Grouper les produits par catégorie
$products_by_category = [];
foreach ($products as $product) {
    $category = $product['category_name'] ?: 'Sans catégorie';
    $products_by_category[$category][] = $product;
}

// Récupérer les clients
$stmt = $db->prepare("SELECT * FROM customers ORDER BY name");
$stmt->execute();
$customers = $stmt->fetchAll();

// Fonction pour envoyer le ticket Discord
function sendDiscordTicket($sale_id, $items, $customer, $total, $discount, $final, $commission, $user) {
    if (!DISCORD_WEBHOOK_URL) return;
    
    $customer_name = $customer ? $customer['name'] : 'Client anonyme';
    $discount_text = $discount > 0 ? "\nRéduction: -{$discount}$" : '';
    
    $items_text = "";
    foreach ($items as $item) {
        $items_text .= "• {$item['product']['name']} x{$item['quantity']} = {$item['total_price']}$\n";
    }
    
    $embed = [
        'title' => "🧾 Ticket de Caisse #$sale_id",
        'description' => "Nouvelle vente au Yellowjack",
        'color' => 0xD4AF37,
        'fields' => [
            ['name' => '👤 Client', 'value' => $customer_name, 'inline' => true],
            ['name' => '🧑‍💼 Vendeur', 'value' => $user['first_name'] . ' ' . $user['last_name'], 'inline' => true],
            ['name' => '📅 Date', 'value' => formatDateTime(getCurrentDateTime()), 'inline' => true],
            ['name' => '🛒 Produits', 'value' => $items_text, 'inline' => false],
            ['name' => '💰 Montant', 'value' => "Sous-total: {$total}${discount_text}\n**Total: {$final}$**", 'inline' => true],
            ['name' => '💵 Commission', 'value' => "{$commission}$", 'inline' => true]
        ],
        'footer' => ['text' => 'Le Yellowjack - Système de caisse'],
        'timestamp' => date('c')
    ];
    
    $payload = ['embeds' => [$embed]];
    
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

$page_title = 'Caisse Enregistreuse';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Le Yellowjack</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/panel.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cash-register me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="sales_history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-history me-1"></i>
                                Historique
                            </a>
                            <a href="customers.php" class="btn btn-outline-info">
                                <i class="fas fa-users me-1"></i>
                                Clients
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="saleForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                    <input type="hidden" name="action" value="process_sale">
                    
                    <div class="row">
                        <!-- Sélection des produits -->
                        <div class="col-lg-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        Sélection des produits
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($products_by_category)): ?>
                                        <div class="row">
                                            <?php foreach ($products_by_category as $category => $category_products): ?>
                                                <div class="col-12 mb-4">
                                                    <h6 class="text-muted mb-3">
                                                        <i class="fas fa-tag me-2"></i>
                                                        <?php echo htmlspecialchars($category); ?>
                                                    </h6>
                                                    <div class="row">
                                                        <?php foreach ($category_products as $product): ?>
                                                            <div class="col-md-6 col-lg-4 mb-3">
                                                                <div class="card product-card h-100" data-product-id="<?php echo $product['id']; ?>">
                                                                    <div class="card-body">
                                                                        <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                                        <p class="card-text">
                                                                            <span class="text-success fw-bold"><?php echo number_format($product['sale_price'], 2); ?>$</span>
                                                                            <br>
                                                                            <small class="text-muted">
                                                                                Stock: <?php echo $product['stock_quantity']; ?>
                                                                                <br>
                                                                                Marge: <?php echo number_format($product['sale_price'] - $product['purchase_price'], 2); ?>$
                                                                            </small>
                                                                        </p>
                                                                        <div class="d-flex align-items-center">
                                                                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="decreaseQuantity(<?php echo $product['id']; ?>)">
                                                                                <i class="fas fa-minus"></i>
                                                                            </button>
                                                                            <input type="number" class="form-control form-control-sm text-center" 
                                                                                   id="qty_<?php echo $product['id']; ?>" 
                                                                                   name="items[<?php echo $product['id']; ?>][quantity]" 
                                                                                   value="0" min="0" max="<?php echo $product['stock_quantity']; ?>" 
                                                                                   style="width: 60px;" 
                                                                                   onchange="updateCart()">
                                                                            <input type="hidden" name="items[<?php echo $product['id']; ?>][product_id]" value="<?php echo $product['id']; ?>">
                                                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="increaseQuantity(<?php echo $product['id']; ?>, <?php echo $product['stock_quantity']; ?>)">
                                                                                <i class="fas fa-plus"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">Aucun produit disponible</h5>
                                            <p class="text-muted">Aucun produit en stock pour le moment.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Panier et finalisation -->
                        <div class="col-lg-4">
                            <!-- Sélection du client -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        Client
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <select class="form-select" name="customer_id" id="customer_id" onchange="updateCart()">
                                        <option value="0">Client anonyme</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>" 
                                                    data-loyal="<?php echo $customer['is_loyal'] ? 1 : 0; ?>" 
                                                    data-discount="<?php echo $customer['discount_percentage']; ?>">
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                                <?php if ($customer['is_loyal']): ?>
                                                    <span class="badge bg-warning">Fidèle -<?php echo $customer['discount_percentage']; ?>%</span>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Panier -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shopping-basket me-2"></i>
                                        Panier
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="cart-items">
                                        <p class="text-muted text-center">Panier vide</p>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div id="cart-summary">
                                        <div class="d-flex justify-content-between">
                                            <span>Sous-total:</span>
                                            <span id="subtotal">0.00$</span>
                                        </div>
                                        <div class="d-flex justify-content-between" id="discount-row" style="display: none;">
                                            <span>Réduction:</span>
                                            <span id="discount" class="text-success">-0.00$</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-bold border-top pt-2">
                                            <span>Total:</span>
                                            <span id="total">0.00$</span>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted">
                                            <span>Commission (<?php echo COMMISSION_RATE; ?>%):</span>
                                            <span id="commission">0.00$</span>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success w-100 mt-3" id="processBtn" disabled>
                                        <i class="fas fa-credit-card me-2"></i>
                                        Encaisser
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearCart()">
                                        <i class="fas fa-trash me-2"></i>
                                        Vider le panier
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Données des produits pour JavaScript
        const products = <?php echo json_encode($products); ?>;
        const productsById = {};
        products.forEach(product => {
            productsById[product.id] = product;
        });
        
        function increaseQuantity(productId, maxStock) {
            const input = document.getElementById('qty_' + productId);
            const currentValue = parseInt(input.value) || 0;
            if (currentValue < maxStock) {
                input.value = currentValue + 1;
                updateCart();
            }
        }
        
        function decreaseQuantity(productId) {
            const input = document.getElementById('qty_' + productId);
            const currentValue = parseInt(input.value) || 0;
            if (currentValue > 0) {
                input.value = currentValue - 1;
                updateCart();
            }
        }
        
        function updateCart() {
            const cartItems = document.getElementById('cart-items');
            const customerSelect = document.getElementById('customer_id');
            const selectedCustomer = customerSelect.options[customerSelect.selectedIndex];
            
            let cartHTML = '';
            let subtotal = 0;
            let hasItems = false;
            
            // Parcourir tous les produits
            products.forEach(product => {
                const qtyInput = document.getElementById('qty_' + product.id);
                const quantity = parseInt(qtyInput.value) || 0;
                
                if (quantity > 0) {
                    hasItems = true;
                    const itemTotal = quantity * parseFloat(product.sale_price);
                    subtotal += itemTotal;
                    
                    cartHTML += `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <small class="fw-bold">${product.name}</small><br>
                                <small class="text-muted">${quantity} x ${parseFloat(product.sale_price).toFixed(2)}$</small>
                            </div>
                            <span class="fw-bold">${itemTotal.toFixed(2)}$</span>
                        </div>
                    `;
                }
            });
            
            if (!hasItems) {
                cartHTML = '<p class="text-muted text-center">Panier vide</p>';
            }
            
            cartItems.innerHTML = cartHTML;
            
            // Calculer la réduction
            let discount = 0;
            if (selectedCustomer.dataset.loyal === '1' && subtotal > 0) {
                discount = subtotal * (parseFloat(selectedCustomer.dataset.discount) / 100);
            }
            
            const total = subtotal - discount;
            const commission = total * (<?php echo COMMISSION_RATE; ?> / 100);
            
            // Mettre à jour l'affichage
            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + '$';
            document.getElementById('total').textContent = total.toFixed(2) + '$';
            document.getElementById('commission').textContent = commission.toFixed(2) + '$';
            
            const discountRow = document.getElementById('discount-row');
            if (discount > 0) {
                document.getElementById('discount').textContent = '-' + discount.toFixed(2) + '$';
                discountRow.style.display = 'flex';
            } else {
                discountRow.style.display = 'none';
            }
            
            // Activer/désactiver le bouton
            document.getElementById('processBtn').disabled = !hasItems;
        }
        
        function clearCart() {
            products.forEach(product => {
                document.getElementById('qty_' + product.id).value = 0;
            });
            updateCart();
        }
        
        // Initialiser le panier
        updateCart();
    </script>
</body>
</html>