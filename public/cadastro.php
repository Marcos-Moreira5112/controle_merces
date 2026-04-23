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
$sucesso = '';
$totalUsuarios = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$cargoInicial = $totalUsuarios === 0 ? 'administrador' : 'funcionario';
$tituloOnboarding = $cargoInicial === 'administrador' ? 'Crie a primeira conta do sistema' : 'Criar nova conta';
$subtituloOnboarding = $cargoInicial === 'administrador'
    ? 'A primeira conta criada já entra com acesso de administrador.'
    : 'Novas contas públicas entram como funcionário e podem ser ajustadas depois por um administrador.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
        $erro = 'Preencha todos os campos para criar a conta.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'A confirmação da senha não confere.';
    } else {
        $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmtEmail->execute([$email]);
        $emailExistente = $stmtEmail->fetch(PDO::FETCH_ASSOC);

        if ($emailExistente) {
            $erro = 'Esse e-mail já está cadastrado.';
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare(
                "INSERT INTO usuarios (nome, email, senha, cargo, supervisor_id, ativo)
                 VALUES (?, ?, ?, ?, NULL, 1)"
            );
            $stmtInsert->execute([$nome, $email, $senhaHash, $cargoInicial]);

            $_SESSION['usuario_id'] = (int) $pdo->lastInsertId();
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Conta | Óticas Mercês</title>
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
                        <span>Centralize tarefas, prazos e prioridades</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <span>Organize usuários e responsabilidades</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"/>
                                <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"/>
                            </svg>
                        </div>
                        <span>Comece com uma conta e personalize depois</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-form-container">
            <div class="login-header">
                <h2><?= htmlspecialchars($tituloOnboarding) ?></h2>
                <p><?= htmlspecialchars($subtituloOnboarding) ?></p>
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

            <?php if ($sucesso): ?>
                <div class="login-success"><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-field">
                    <label for="nome">Nome</label>
                    <div class="input-wrapper">
                        <input type="text" id="nome" name="nome" placeholder="Seu nome" required autocomplete="name">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>

                <div class="form-field">
                    <label for="email">E-mail</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="seu@email.com" required autocomplete="email">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                </div>

                <div class="form-field">
                    <label for="senha">Senha</label>
                    <div class="input-wrapper">
                        <input type="password" id="senha" name="senha" placeholder="••••••••" required minlength="6" autocomplete="new-password">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                </div>

                <div class="form-field">
                    <label for="confirmar_senha">Confirmar senha</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="••••••••" required minlength="6" autocomplete="new-password">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9Z"/>
                        </svg>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Criar conta
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <div class="auth-switch">
                <p>Já tem conta?</p>
                <a href="login.php">Entrar agora</a>
            </div>
        </div>
    </div>

</body>
</html>
