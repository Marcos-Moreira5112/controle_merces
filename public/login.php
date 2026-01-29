<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    $sql = "SELECT id, senha FROM usuarios WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $senha === $usuario['senha']) {
        $_SESSION['usuario_id'] = $usuario['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = 'E-mail ou senha incorretos. Tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">

    <div class="login-wrapper">
        
        <!-- Left Side: Branding -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <img src="assets/img/Logo TaskBlue.png" alt="TaskBlue">
                </div>
                <p class="brand-tagline">Sistema de Controle de Tarefas</p>

                <div class="brand-features">
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <path d="M9 14l2 2 4-4"/>
                            </svg>
                        </div>
                        <span>Gerencie tarefas da equipe</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                            </svg>
                        </div>
                        <span>Controle de usuários</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"/>
                                <polyline points="1 20 1 14 7 14"/>
                                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                            </svg>
                        </div>
                        <span>Tarefas recorrentes automáticas</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="login-form-container">
            <div class="login-header">
                <h2>Bem-vindo de volta!</h2>
                <p>Entre com suas credenciais para acessar</p>
            </div>

            <?php if ($erro): ?>
                <div class="login-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span><?= htmlspecialchars($erro) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-field">
                    <label for="email">
                        E-mail
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="seu@email.com"
                            required
                            autocomplete="email"
                        >
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                </div>

                <div class="form-field">
                    <label for="senha">
                        Senha
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="senha" 
                            name="senha" 
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Entrar no Sistema
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <div class="login-footer">
                <p>© <?= date('Y') ?> Óticas Mercês • Todos os direitos reservados</p>
            </div>
        </div>

    </div>

</body>
</html>