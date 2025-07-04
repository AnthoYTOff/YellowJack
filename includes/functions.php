<?php
/**
 * Fonctions utilitaires pour le système YellowJack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtient le vendredi de la semaine pour une date donnée
 */
function getFridayOfWeek($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('w', $timestamp); // 0 = dimanche, 5 = vendredi
    
    if ($dayOfWeek == 5) {
        // C'est déjà vendredi
        return date('Y-m-d', $timestamp);
    } elseif ($dayOfWeek == 6) {
        // Samedi - prendre le vendredi précédent
        return date('Y-m-d', strtotime('-1 day', $timestamp));
    } elseif ($dayOfWeek == 0) {
        // Dimanche - prendre le vendredi précédent
        return date('Y-m-d', strtotime('-2 days', $timestamp));
    } else {
        // Lundi à jeudi - prendre le vendredi précédent
        $daysToSubtract = ($dayOfWeek + 2) % 7;
        return date('Y-m-d', strtotime("-{$daysToSubtract} days", $timestamp));
    }
}

/**
 * Obtient le vendredi suivant (fin de semaine)
 */
function getFridayAfterFriday($friday) {
    return date('Y-m-d', strtotime('+7 days', strtotime($friday)));
}

/**
 * Obtient la semaine active (non finalisée) ou crée une nouvelle semaine si nécessaire
 * Retourne un tableau avec week_start, week_end, et id
 */
function getActiveWeek() {
    try {
        $db = getDB();
        
        // Chercher une semaine non finalisée
        $stmt = $db->prepare("
            SELECT id, week_start, week_end 
            FROM weekly_taxes 
            WHERE is_finalized = FALSE 
            ORDER BY week_start DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $activeWeek = $stmt->fetch();
        
        if ($activeWeek) {
            return $activeWeek;
        }
        
        // Aucune semaine active trouvée, créer une nouvelle semaine
        $today = date('Y-m-d');
        $week_start = getFridayOfWeek($today);
        $week_end = getFridayAfterFriday($week_start);
        
        // Vérifier si cette semaine existe déjà
        $check_stmt = $db->prepare("SELECT id, week_start, week_end FROM weekly_taxes WHERE week_start = ?");
        $check_stmt->execute([$week_start]);
        $existingWeek = $check_stmt->fetch();
        
        if ($existingWeek) {
            // La semaine existe, la marquer comme active (non finalisée)
            $update_stmt = $db->prepare("UPDATE weekly_taxes SET is_finalized = FALSE WHERE week_start = ?");
            $update_stmt->execute([$week_start]);
            return $existingWeek;
        }
        
        // Créer une nouvelle semaine
        $create_stmt = $db->prepare("
            INSERT INTO weekly_taxes 
            (week_start, week_end, total_revenue, tax_amount, effective_tax_rate, tax_breakdown, is_finalized) 
            VALUES (?, ?, 0, 0, 0, '[]', FALSE)
        ");
        $create_stmt->execute([$week_start, $week_end]);
        
        return [
            'id' => $db->lastInsertId(),
            'week_start' => $week_start,
            'week_end' => $week_end
        ];
        
    } catch (Exception $e) {
        // En cas d'erreur, retourner la semaine courante basée sur la date
        $today = date('Y-m-d');
        $week_start = getFridayOfWeek($today);
        $week_end = getFridayAfterFriday($week_start);
        
        return [
            'id' => null,
            'week_start' => $week_start,
            'week_end' => $week_end
        ];
    }
}

/**
 * Vérifie si une date est dans la période de la semaine active
 */
function isDateInActiveWeek($date) {
    $activeWeek = getActiveWeek();
    $checkDate = date('Y-m-d', strtotime($date));
    
    return $checkDate >= $activeWeek['week_start'] && $checkDate <= $activeWeek['week_end'];
}
?>