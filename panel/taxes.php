<?php
/**
 * Page de gestion des impôts - Panel Patron Le Yellowjack
 * Calcul automatique des impôts hebdomadaires (vendredi à vendredi)
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$auth = getAuth();
$user = $auth->getCurrentUser();
$db = getDB();

// Vérifier que l'utilisateur est Patron
if (!$auth->hasPermission('Patron')) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Gestion des Impôts';

// Fonction pour obtenir le vendredi de début de la semaine courante (vendredi à jeudi)
function getFridayOfWeek($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 = Lundi, 5 = Vendredi
    
    if ($dayOfWeek == 5) {
        // Si c'est vendredi, c'est le début de la semaine
        return date('Y-m-d', $timestamp);
    } elseif ($dayOfWeek == 6 || $dayOfWeek == 7) {
        // Si c'est samedi ou dimanche, prendre le vendredi précédent
        $daysToSubtract = $dayOfWeek - 5;
        return date('Y-m-d', strtotime("-$daysToSubtract days", $timestamp));
    } else {
        // Si c'est lundi à jeudi, prendre le vendredi précédent
        $daysToSubtract = $dayOfWeek + 2; // lundi=3, mardi=4, mercredi=5, jeudi=6
        return date('Y-m-d', strtotime("-$daysToSubtract days", $timestamp));
    }
}

// Fonction pour obtenir le jeudi suivant le vendredi
function getThursdayAfterFriday($friday) {
    return date('Y-m-d', strtotime('+6 days', strtotime($friday)));
}

// Fonction pour calculer les impôts selon le barème progressif
function calculateTax($revenue, $db) {
    // Récupérer les tranches d'impôts
    $stmt = $db->query("SELECT * FROM tax_brackets ORDER BY min_revenue ASC");
    $brackets = $stmt->fetchAll();
    
    $totalTax = 0;
    $breakdown = [];
    $remainingRevenue = $revenue;
    
    foreach ($brackets as $bracket) {
        if ($remainingRevenue <= 0) break;
        
        $minRevenue = $bracket['min_revenue'];
        $maxRevenue = $bracket['max_revenue'] ?? PHP_FLOAT_MAX;
        $taxRate = $bracket['tax_rate'] / 100;
        
        if ($revenue > $minRevenue) {
            $taxableAmount = min($remainingRevenue, $maxRevenue - $minRevenue + 1);
            if ($revenue <= $maxRevenue || $bracket['max_revenue'] === null) {
                $taxableAmount = min($taxableAmount, $revenue - $minRevenue);
            }
            
            if ($taxableAmount > 0) {
                $taxForBracket = $taxableAmount * $taxRate;
                $totalTax += $taxForBracket;
                
                $breakdown[] = [
                    'min_revenue' => $minRevenue,
                    'max_revenue' => $bracket['max_revenue'],
                    'taxable_amount' => $taxableAmount,
                    'tax_rate' => $bracket['tax_rate'],
                    'tax_amount' => $taxForBracket
                ];
                
                $remainingRevenue -= $taxableAmount;
            }
        }
    }
    
    $effectiveRate = $revenue > 0 ? ($totalTax / $revenue) * 100 : 0;
    
    return [
        'total_tax' => $totalTax,
        'effective_rate' => $effectiveRate,
        'breakdown' => $breakdown
    ];
}

// Semaine sélectionnée (par défaut la semaine courante)
$selected_week = $_GET['week'] ?? getFridayOfWeek(date('Y-m-d'));
$week_start = $selected_week; // Vendredi
$week_end = getThursdayAfterFriday($week_start); // Jeudi suivant

// Messages
$success_message = '';
$error_message = '';

// Action de calcul/recalcul des impôts
if ($_POST && isset($_POST['calculate_taxes'])) {
    try {
        // Calculer le CA total de la semaine
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(final_amount), 0) as total_revenue
            FROM sales 
            WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
        ");
        $stmt->execute([$week_start, $week_end]);
        $result = $stmt->fetch();
        $total_revenue = $result['total_revenue'];
        
        // Calculer les impôts
        $tax_calculation = calculateTax($total_revenue, $db);
        
        // Insérer ou mettre à jour dans weekly_taxes
        $stmt = $db->prepare("
            INSERT INTO weekly_taxes 
            (week_start, week_end, total_revenue, tax_amount, effective_tax_rate, tax_breakdown) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            total_revenue = VALUES(total_revenue),
            tax_amount = VALUES(tax_amount),
            effective_tax_rate = VALUES(effective_tax_rate),
            tax_breakdown = VALUES(tax_breakdown),
            calculated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $week_start,
            $week_end,
            $total_revenue,
            $tax_calculation['total_tax'],
            $tax_calculation['effective_rate'],
            json_encode($tax_calculation['breakdown'])
        ]);
        
        $success_message = "Impôts calculés avec succès pour la semaine du " . date('d/m/Y', strtotime($week_start)) . " au " . date('d/m/Y', strtotime($week_end));
        
    } catch (Exception $e) {
        $error_message = "Erreur lors du calcul des impôts : " . $e->getMessage();
    }
}

// Action de finalisation
if ($_POST && isset($_POST['finalize_week'])) {
    try {
        $stmt = $db->prepare("
            UPDATE weekly_taxes 
            SET is_finalized = TRUE, finalized_at = CURRENT_TIMESTAMP 
            WHERE week_start = ?
        ");
        $stmt->execute([$week_start]);
        
        $success_message = "Semaine finalisée avec succès.";
        
    } catch (Exception $e) {
        $error_message = "Erreur lors de la finalisation : " . $e->getMessage();
    }
}

// Récupérer les données de la semaine sélectionnée
$current_week_data = null;
try {
    $stmt = $db->prepare("SELECT * FROM weekly_taxes WHERE week_start = ?");
    $stmt->execute([$week_start]);
    $current_week_data = $stmt->fetch();
} catch (Exception $e) {
    $error_message = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Récupérer l'historique des semaines
$tax_history = [];
try {
    $stmt = $db->query("
        SELECT * FROM weekly_taxes 
        ORDER BY week_start DESC 
        LIMIT 20
    ");
    $tax_history = $stmt->fetchAll();
} catch (Exception $e) {
    // Historique vide en cas d'erreur
}

// Récupérer les semaines disponibles pour le sélecteur
$available_weeks = [];
try {
    $stmt = $db->query("
        SELECT DISTINCT week_start 
        FROM weekly_taxes 
        ORDER BY week_start DESC
    ");
    $available_weeks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Ajouter la semaine courante si elle n'existe pas
    $current_week = getFridayOfWeek(date('Y-m-d'));
    if (!in_array($current_week, $available_weeks)) {
        array_unshift($available_weeks, $current_week);
    }
} catch (Exception $e) {
    $available_weeks = [getFridayOfWeek(date('Y-m-d'))];
}

// Récupérer le CA de la semaine courante
$current_revenue = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(final_amount), 0) as total_revenue
        FROM sales 
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
    ");
    $stmt->execute([$week_start, $week_end]);
    $result = $stmt->fetch();
    $current_revenue = $result['total_revenue'];
} catch (Exception $e) {
    // Revenue à 0 en cas d'erreur
}

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rye&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/panel.css">
    
    <style>
        :root {
            --primary-color: #8B4513; /* Brun western */
            --secondary-color: #DAA520; /* Or/Jaune */
            --accent-color: #CD853F; /* Beige sable */
            --dark-color: #2F1B14; /* Brun foncé */
            --light-color: #F5DEB3; /* Beige clair */
            --text-dark: #1a1a1a;
            --text-light: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
            --sidebar-width: 250px;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--dark-color), var(--primary-color)) !important;
            box-shadow: var(--shadow);
            padding: 0.75rem 0;
            border-bottom: 3px solid var(--secondary-color);
        }
        
        .navbar-brand {
            font-family: 'Rye', cursive;
            font-size: 1.5rem;
            color: var(--secondary-color) !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            font-weight: 400;
        }
        
        .navbar-nav .nav-link {
            color: var(--light-color) !important;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--secondary-color) !important;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 80px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, 0.1);
            background: linear-gradient(180deg, var(--light-color), #ffffff);
            width: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: var(--text-dark);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 0.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
        }
        
        .sidebar .nav-link:hover {
            color: var(--secondary-color);
            background: rgba(218, 165, 32, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: var(--dark-color);
            background: linear-gradient(45deg, var(--secondary-color), #FFD700);
            font-weight: 600;
            box-shadow: var(--shadow);
        }
        
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .btn {
            border-radius: 8px;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .tax-breakdown {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .tax-bracket {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-left: 4px solid var(--secondary-color);
        }
        
        .revenue-display {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .tax-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 western-font">
                        <i class="fas fa-calculator me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-warning text-dark fs-6">
                            <i class="fas fa-calendar-week me-1"></i>
                            Semaine du <?php echo date('d/m/Y', strtotime($week_start)); ?> au <?php echo date('d/m/Y', strtotime($week_end)); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Sélecteur de semaine -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Sélection de la semaine
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="d-flex gap-2">
                                    <select name="week" class="form-select">
                                        <?php foreach ($available_weeks as $week): ?>
                                            <option value="<?php echo $week; ?>" <?php echo ($week == $selected_week) ? 'selected' : ''; ?>>
                                                Semaine du <?php echo date('d/m/Y', strtotime($week)); ?> au <?php echo date('d/m/Y', strtotime(getThursdayAfterFriday($week))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-euro-sign me-2"></i>
                                    Chiffre d'affaires de la semaine
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="revenue-display">
                                    <?php echo number_format($current_revenue, 2, ',', ' '); ?> €
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cogs me-2"></i>
                                    Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="calculate_taxes" class="btn btn-success me-2">
                                        <i class="fas fa-calculator me-2"></i>
                                        Calculer les impôts
                                    </button>
                                </form>
                                
                                <?php if ($current_week_data && !$current_week_data['is_finalized']): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="finalize_week" class="btn btn-warning" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir finaliser cette semaine ? Cette action est irréversible.')">
                                            <i class="fas fa-lock me-2"></i>
                                            Finaliser la semaine
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($current_week_data && $current_week_data['is_finalized']): ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check me-1"></i>
                                        Semaine finalisée
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Résultats du calcul -->
                <?php if ($current_week_data): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Résultats du calcul d'impôts
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center">
                                            <h6>Chiffre d'affaires</h6>
                                            <div class="revenue-display">
                                                <?php echo number_format($current_week_data['total_revenue'], 2, ',', ' '); ?> €
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h6>Impôts à payer</h6>
                                            <div class="tax-display">
                                                <?php echo number_format($current_week_data['tax_amount'], 2, ',', ' '); ?> €
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h6>Taux effectif</h6>
                                            <div class="tax-display">
                                                <?php echo number_format($current_week_data['effective_tax_rate'], 2, ',', ' '); ?> %
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($current_week_data['tax_breakdown']): ?>
                                        <div class="tax-breakdown">
                                            <h6><i class="fas fa-list me-2"></i>Détail du calcul par tranche</h6>
                                            <?php 
                                            $breakdown = json_decode($current_week_data['tax_breakdown'], true);
                                            foreach ($breakdown as $bracket): 
                                            ?>
                                                <div class="tax-bracket">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <strong>Tranche :</strong> 
                                                            <?php echo number_format($bracket['min_revenue'], 0, ',', ' '); ?> € - 
                                                            <?php echo $bracket['max_revenue'] ? number_format($bracket['max_revenue'], 0, ',', ' ') . ' €' : '∞'; ?>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <strong>Taux :</strong> <?php echo $bracket['tax_rate']; ?>%
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Montant imposable :</strong> <?php echo number_format($bracket['taxable_amount'], 2, ',', ' '); ?> €
                                                        </div>
                                                        <div class="col-md-3">
                                                            <strong>Impôt :</strong> <?php echo number_format($bracket['tax_amount'], 2, ',', ' '); ?> €
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Historique -->
                <?php if (!empty($tax_history)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-history me-2"></i>
                                        Historique des impôts
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Période</th>
                                                    <th>Chiffre d'affaires</th>
                                                    <th>Impôts</th>
                                                    <th>Taux effectif</th>
                                                    <th>Statut</th>
                                                    <th>Calculé le</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tax_history as $record): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="?week=<?php echo $record['week_start']; ?>" class="text-decoration-none">
                                                                <?php echo date('d/m/Y', strtotime($record['week_start'])); ?> - 
                                                                <?php echo date('d/m/Y', strtotime($record['week_end'])); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo number_format($record['total_revenue'], 2, ',', ' '); ?> €</td>
                                                        <td class="text-danger fw-bold"><?php echo number_format($record['tax_amount'], 2, ',', ' '); ?> €</td>
                                                        <td><?php echo number_format($record['effective_tax_rate'], 2, ',', ' '); ?> %</td>
                                                        <td>
                                                            <?php if ($record['is_finalized']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-check me-1"></i>Finalisé
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="fas fa-clock me-1"></i>En cours
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($record['calculated_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>