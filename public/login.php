<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';
$mensagemSucesso = $_SESSION['mensagem_login'] ?? '';
unset($_SESSION['mensagem_login']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    $stmt = $pdo->prepare("SELECT id, senha, ativo FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && (int) ($usuario['ativo'] ?? 1) === 1 && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        header('Location: dashboard.php');
        exit;
    }

    $erro = 'E-mail ou senha incorretos. Tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login | TaskBlue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-wrapper auth-wrapper">
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
                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <path d="M9 14l2 2 4-4"/>
                            </svg>
                        </div>
                        <span>Gerencie tarefas da sua equipe</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <span>Membros entram com login próprio dentro da equipe</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-form-container">
            <div class="login-header">
                <h2>Bem-vindo de volta!</h2>
                <p>Entre com suas credenciais para acessar sua equipe.</p>
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

            <?php if ($mensagemSucesso): ?>
                <div class="login-success">
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-field">
                    <label for="email">E-mail</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="seu@email.com" required autocomplete="email">
                    </div>
                </div>

                <div class="form-field">
                    <label for="senha">Senha</label>
                    <div class="input-wrapper">
                        <input type="password" id="senha" name="senha" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Entrar no sistema
                </button>
            </form>

            <div class="auth-switch">
                <p>Vai criar a conta titular?</p>
                <a href="cadastro.php">Criar conta</a>
            </div>
        </div>
    </div>
</body>
</html>
