<?php
/**
 * Configuration de base de données locale pour Le Yellowjack
 * Utilise SQLite pour éviter les problèmes de connexion réseau
 */

// Configuration SQLite locale
define('DB_TYPE', 'sqlite');
define('DB_PATH', __DIR__ . '/../database/yellowjack.sqlite');

// Fuseau horaire
define('TIMEZONE', 'Europe/Paris');
date_default_timezone_set(TIMEZONE);

// Configuration de l'application
define('APP_NAME', 'Le Yellowjack');
define('APP_URL', 'http://localhost');
define('APP_DEBUG', true);

// Configuration des sessions
define('SESSION_LIFETIME', 3600);
define('SESSION_NAME', 'yellowjack_session');

/**
 * Obtenir une connexion à la base de données SQLite
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Créer le répertoire database s'il n'existe pas
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Créer les tables de base si elles n'existent pas
            createBasicTables($pdo);
            
        } catch (PDOException $e) {
            throw new Exception('Erreur de connexion à la base de données: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Créer les tables de base
 */
function createBasicTables($pdo) {
    // Table users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'CDD',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Table sales
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            customer_id INTEGER,
            total_amount DECIMAL(10,2) NOT NULL,
            employee_commission DECIMAL(10,2) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Table products
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            selling_price DECIMAL(10,2) NOT NULL,
            stock_quantity INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Table cleaning_services
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cleaning_services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            cleaning_count INTEGER DEFAULT 0,
            total_salary DECIMAL(10,2) DEFAULT 0,
            start_time DATETIME,
            end_time DATETIME
        )
    ");
    
    // Insérer un utilisateur de test s'il n'existe pas
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO users (username, password, first_name, last_name, role) 
            VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Test', 'Patron')
        ");
    }
}

/**
 * Valider le token CSRF
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Générer un token CSRF
 */
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>