<?php
/**
 * Page de gestion des impôts - Panel Patron Le Yellowjack
 * Calcul automatique des impôts hebdomadaires (vendredi à vendredi)
 * Période: du vendredi inclus au vendredi suivant inclus
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$user = $auth->getCurrentUser();
$db = getDB();

// Vérifier les permissions
if (!in_array($user['role'], ['Patron', 'Responsable'])) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Gestion des Impôts';

// Fonction pour calculer les impôts selon le système de paliers
function calculateTax($revenue, $db) {
    // Récupérer les paliers d'impôts
    $stmt = $db->query("SELECT * FROM tax_brackets ORDER BY min_revenue DESC");
    $brackets = $stmt->fetchAll();
    
    $totalTax = 0;
    $breakdown = [];
    $applicableTaxRate = 0;
    
    // Trouver le palier applicable (le plus élevé où le revenu dépasse le minimum)
    foreach ($brackets as $bracket) {
        $minRevenue = $bracket['min_revenue'];
        $maxRevenue = $bracket['max_revenue'];
        
        // Vérifier si le revenu entre dans ce palier
        if ($revenue >= $minRevenue && ($maxRevenue === null || $revenue <= $maxRevenue)) {
            $applicableTaxRate = $bracket['tax_rate'] / 100;
            $totalTax = $revenue * $applicableTaxRate;
            
            $breakdown[] = [
                'min_revenue' => $minRevenue,
                'max_revenue' => $bracket['max_revenue'],
                'taxable_amount' => $revenue,
                'tax_rate' => $bracket['tax_rate'],
                'tax_amount' => $totalTax
            ];
            break;
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
$activeWeek = getActiveWeek();
if (isset($_GET['week'])) {
    $selected_week = $_GET['week'];
    $stmt = $db->prepare("SELECT * FROM weekly_taxes WHERE week_start = ?");
    $stmt->execute([$selected_week]);
    $weekData = $stmt->fetch();
    if ($weekData) {
        $week_start = $weekData['week_start'];
        $week_end = $weekData['week_end'];
    } else {
        $week_start = $activeWeek['week_start'];
        $week_end = $activeWeek['week_end'];
    }
} else {
    $week_start = $activeWeek['week_start'];
    $week_end = $activeWeek['week_end'];
    $selected_week = $week_start;
}

// Messages
$success_message = '';
$error_message = '';

// Gestion du message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Impôts calculés avec succès pour la semaine du " . date('d/m/Y', strtotime($week_start)) . " au " . date('d/m/Y', strtotime($week_end)) . " (inclus)";
}

// Action de calcul/recalcul des impôts
if ($_POST && isset($_POST['calculate_taxes'])) {
    try {
        // Calculer le CA total de la semaine (vendredi à vendredi inclus)
        // CA = Ventes + Salaire ménage
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(s.final_amount), 0) as sales_revenue,
                COALESCE(SUM(cs.total_salary), 0) as cleaning_revenue
            FROM 
                (SELECT final_amount FROM sales WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?) s
            CROSS JOIN
                (SELECT total_salary FROM cleaning_services WHERE DATE(start_time) >= ? AND DATE(start_time) <= ? AND status = 'completed') cs
        ");
        $stmt->execute([$week_start, $week_end, $week_start, $week_end]);
        $result = $stmt->fetch();
        
        // Calculer séparément pour éviter les problèmes de jointure
        $stmt_sales = $db->prepare("SELECT COALESCE(SUM(final_amount), 0) as sales_revenue FROM sales WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $stmt_sales->execute([$week_start, $week_end]);
        $sales_result = $stmt_sales->fetch();
        
        $stmt_cleaning = $db->prepare("SELECT COALESCE(SUM(total_salary), 0) as cleaning_revenue FROM cleaning_services WHERE DATE(start_time) >= ? AND DATE(start_time) <= ? AND status = 'completed'");
        $stmt_cleaning->execute([$week_start, $week_end]);
        $cleaning_result = $stmt_cleaning->fetch();
        
        $total_revenue = $sales_result['sales_revenue'] + $cleaning_result['cleaning_revenue'];
        
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
        
        // Redirection pour actualiser les données
        header("Location: taxes.php?week=" . urlencode($week_start) . "&success=1");
        exit();
        
    } catch (Exception $e) {
        $error_message = "Erreur lors du calcul des impôts : " . $e->getMessage();
    }
}

// Action de finalisation
if ($_POST && isset($_POST['finalize_week'])) {
    try {
        // Finaliser la semaine actuelle
        $stmt = $db->prepare("
            UPDATE weekly_taxes 
            SET is_finalized = TRUE, finalized_at = CURRENT_TIMESTAMP 
            WHERE week_start = ?
        ");
        $stmt->execute([$week_start]);
        
        // Créer la nouvelle période
        $nextPeriod = getNextWeekPeriod();
        
        // Vérifier si la nouvelle période n'existe pas déjà
        $checkStmt = $db->prepare("SELECT * FROM weekly_taxes WHERE week_start = ?");
        $checkStmt->execute([$nextPeriod['week_start']]);
        $existingNext = $checkStmt->fetch();
        
        if (!$existingNext) {
            $createStmt = $db->prepare("
                INSERT INTO weekly_taxes (week_start, week_end, total_revenue, tax_amount, effective_tax_rate, tax_breakdown, is_finalized) 
                VALUES (?, ?, 0, 0, 0, '[]', FALSE)
            ");
            $createStmt->execute([$nextPeriod['week_start'], $nextPeriod['week_end']]);
        }
        
        $success_message = "Semaine du " . date('d/m/Y', strtotime($week_start)) . " finalisée avec succès. Nouvelle période créée du " . date('d/m/Y', strtotime($nextPeriod['week_start'])) . " au " . date('d/m/Y', strtotime($nextPeriod['week_end'])) . ".";
        
        // Rediriger vers la nouvelle semaine active
        header("Location: taxes.php?week=" . urlencode($nextPeriod['week_start']) . "&success=1");
        exit();
        
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

// Récupérer le CA de la semaine courante (vendredi à vendredi inclus)
// CA = Ventes + Salaire ménage
$current_revenue = 0;
try {
    // Calculer les ventes
    $stmt_sales = $db->prepare("SELECT COALESCE(SUM(final_amount), 0) as sales_revenue FROM sales WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
    $stmt_sales->execute([$week_start, $week_end]);
    $sales_result = $stmt_sales->fetch();
    
    // Calculer le salaire ménage
    $stmt_cleaning = $db->prepare("SELECT COALESCE(SUM(total_salary), 0) as cleaning_revenue FROM cleaning_services WHERE DATE(start_time) >= ? AND DATE(start_time) <= ? AND status = 'completed'");
    $stmt_cleaning->execute([$week_start, $week_end]);
    $cleaning_result = $stmt_cleaning->fetch();
    
    $current_revenue = $sales_result['sales_revenue'] + $cleaning_result['cleaning_revenue'];
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
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .bg-orange {
            background-color: #fd7e14 !important;
            color: white !important;
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
                            Semaine du <?php echo date('d/m/Y', strtotime($week_start)); ?> au <?php echo date('d/m/Y', strtotime($week_end)); ?> (inclus)
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
                                            Semaine du <?php echo date('d/m/Y', strtotime($week)); ?> au <?php echo date('d/m/Y', strtotime(getFridayAfterFriday($week))); ?> (inclus)
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
                
                <!-- Détails du calcul des impôts -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Détails du calcul des impôts
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary"><i class="fas fa-calendar-alt me-2"></i>Période de calcul</h6>
                                        <p class="mb-3">Les impôts sont calculés sur une base hebdomadaire du <strong>vendredi inclus au vendredi suivant inclus</strong>.</p>
                                        
                                        <h6 class="text-primary"><i class="fas fa-percentage me-2"></i>Barème progressif</h6>
                                         <ul class="list-unstyled">
                                             <li><span class="badge bg-success me-2">0%</span> De 0€ à 200 000€</li>
                                             <li><span class="badge bg-info me-2">6%</span> De 200 001€ à 400 000€</li>
                                             <li><span class="badge bg-warning me-2">10%</span> De 400 001€ à 600 000€</li>
                                             <li><span class="badge bg-orange me-2">15%</span> De 600 001€ à 800 000€</li>
                                             <li><span class="badge bg-danger me-2">20%</span> De 800 001€ à 1 000 000€</li>
                                             <li><span class="badge bg-dark me-2">25%</span> Au-delà de 1 000 000€</li>
                                         </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary"><i class="fas fa-users me-2"></i>Revenus inclus</h6>
                                        <p class="mb-3">Tous les revenus de l'établissement sont pris en compte :</p>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Ventes Patron</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Ventes Sous-Patron</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Ventes Employés</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Ventes Stagiaires</li>
                                        </ul>
                                        
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-lightbulb me-2"></i>
                                            <strong>Calcul automatique :</strong> Les impôts sont calculés automatiquement selon le barème progressif appliqué au chiffre d'affaires total de la semaine.
                                        </div>
                                    </div>
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