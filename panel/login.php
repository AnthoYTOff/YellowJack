<?php
/**
 * Page de connexion - Panel Employé Le Yellowjack
 * 
 * @author Développeur Web Professionnel
 * @version 1.0
 */

require_once '../includes/auth.php';

$auth = getAuth();
$error_message = '';
$success_message = '';
$login_attempts = 0;

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rediriger si déjà connecté
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Gestion des tentatives de connexion (protection contre le brute force)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// Réinitialiser les tentatives après 15 minutes
if (time() - $_SESSION['last_attempt'] > 900) {
    $_SESSION['login_attempts'] = 0;
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        // Vérifier le token CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            $error_message = 'Token de sécurité invalide. Veuillez réessayer.';
        }
        // Vérifier le nombre de tentatives
        elseif ($_SESSION['login_attempts'] >= 5) {
            $error_message = 'Trop de tentatives de connexion. Veuillez attendre 15 minutes.';
        }
        else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error_message = 'Veuillez remplir tous les champs.';
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
            } else {
                // Tentative de connexion avec gestion d'erreur améliorée
                try {
                    if ($auth->login($username, $password)) {
                        // Réinitialiser les tentatives en cas de succès
                        $_SESSION['login_attempts'] = 0;
                        
                        // Redirection sécurisée
                        $redirect_url = $_GET['redirect'] ?? 'dashboard.php';
                        // Valider l'URL de redirection pour éviter les attaques
                        if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+\.php$/', $redirect_url)) {
                            $redirect_url = 'dashboard.php';
                        }
                        
                        header('Location: ' . $redirect_url);
                        exit;
                    } else {
                        $error_message = 'Nom d\'utilisateur ou mot de passe incorrect.';
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt'] = time();
                        
                        // Log des tentatives de connexion échouées
                        error_log("Tentative de connexion échouée pour: " . $username . " depuis " . $_SERVER['REMOTE_ADDR']);
                    }
                } catch (Exception $e) {
                    $error_message = 'Erreur de connexion au système. Veuillez réessayer.';
                    error_log("Erreur de connexion: " . $e->getMessage());
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                }
            }
        }
    }
}

// Générer un token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$login_attempts = $_SESSION['login_attempts'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Panel Employé | Le Yellowjack</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rye&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #8B4513;
            --secondary-color: #DAA520;
            --accent-color: #CD853F;
            --dark-color: #2F1B14;
            --light-color: #F5DEB3;
            --text-dark: #1a1a1a;
            --text-light: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, 
                rgba(47, 27, 20, 0.9), 
                rgba(139, 69, 19, 0.7)
            ),
            url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><pattern id="wood" patternUnits="userSpaceOnUse" width="100" height="100"><rect width="100" height="100" fill="%23654321"/><path d="M0,50 Q25,40 50,50 T100,50" stroke="%23543311" stroke-width="2" fill="none"/></pattern></defs><rect width="1000" height="1000" fill="url(%23wood)"/></svg>');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .western-font {
            font-family: 'Rye', cursive;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            margin: 2rem;
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .brand-header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .brand-header p {
            color: var(--accent-color);
            font-weight: 500;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(218, 165, 32, 0.25);
        }
        
        .input-group {
            margin-bottom: 1.5rem;
        }
        
        .input-group-text {
            background: var(--secondary-color);
            border: 2px solid var(--secondary-color);
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .btn-login {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.3);
            color: white;
        }
        
        .btn-back {
            background: transparent;
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
            padding: 0.5rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
            margin-bottom: 2rem;
        }
        
        .btn-back:hover {
            background: var(--accent-color);
            color: white;
            text-decoration: none;
        }
        
        .alert {
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: rgba(25, 135, 84, 0.1);
            color: #0f5132;
            border-left: 4px solid #198754;
        }
        
        .footer-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .footer-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .footer-link a:hover {
            color: var(--secondary-color);
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .brand-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="../index.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Retour au site
        </a>
        
        <div class="brand-header">
            <h1 class="western-font">
                <i class="fas fa-glass-whiskey me-2"></i>
                Le Yellowjack
            </h1>
            <p>Panel Employé</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <?php if ($login_attempts > 0): ?>
                    <br><small><i class="fas fa-shield-alt me-1"></i>Tentatives: <?php echo $login_attempts; ?>/5</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($login_attempts >= 3 && $login_attempts < 5): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                Attention: <?php echo (5 - $login_attempts); ?> tentative(s) restante(s) avant blocage temporaire.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <!-- Token CSRF pour la sécurité -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-user"></i>
                </span>
                <input type="text" 
                       class="form-control" 
                       name="username" 
                       placeholder="Nom d'utilisateur ou email"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       maxlength="50"
                       autocomplete="username"
                       <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>
                       required>
            </div>
            
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-lock"></i>
                </span>
                <input type="password" 
                       class="form-control" 
                       name="password" 
                       placeholder="Mot de passe"
                       maxlength="255"
                       autocomplete="current-password"
                       <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>
                       required>
            </div>
            
            <button type="submit" 
                    name="login" 
                    class="btn btn-login"
                    <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt me-2"></i>
                <?php echo ($login_attempts >= 5) ? 'Compte temporairement bloqué' : 'Se Connecter'; ?>
            </button>
            
            <?php if ($login_attempts >= 5): ?>
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        Réessayez dans 15 minutes
                    </small>
                </div>
            <?php endif; ?>
        </form>
        
        <div class="footer-link">
            <p class="mb-2">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Accès réservé aux employés du Yellowjack
                </small>
            </p>
            <a href="../index.php">
                <i class="fas fa-home me-1"></i>
                Retour au site vitrine
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const usernameInput = document.querySelector('input[name="username"]');
            const passwordInput = document.querySelector('input[name="password"]');
            const submitButton = document.querySelector('button[name="login"]');
            
            // Auto-focus sur le premier champ si pas désactivé
            if (usernameInput && !usernameInput.disabled) {
                usernameInput.focus();
            }
            
            // Validation en temps réel
            function validateForm() {
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                
                const isValid = username.length >= 3 && password.length >= 1;
                
                if (submitButton && !submitButton.disabled) {
                    submitButton.disabled = !isValid;
                    submitButton.style.opacity = isValid ? '1' : '0.6';
                }
                
                return isValid;
            }
            
            // Validation lors de la saisie
            if (usernameInput) {
                usernameInput.addEventListener('input', validateForm);
                usernameInput.addEventListener('blur', function() {
                    this.value = this.value.trim();
                });
            }
            
            if (passwordInput) {
                passwordInput.addEventListener('input', validateForm);
            }
            
            // Validation avant soumission
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        alert('Veuillez remplir correctement tous les champs.');
                        return false;
                    }
                    
                    // Désactiver le bouton pour éviter les doubles soumissions
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connexion...';
                    }
                });
            }
            
            // Gestion de l'entrée pour soumettre le formulaire
            document.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && validateForm()) {
                    if (form && !submitButton.disabled) {
                        form.submit();
                    }
                }
            });
            
            // Validation initiale
            validateForm();
        });
    </script>
</body>
</html>