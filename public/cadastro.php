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
$valores = [
    'nome' => '',
    'equipe_nome' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $equipeNome = trim($_POST['equipe_nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    $valores = [
        'nome' => $nome,
        'equipe_nome' => $equipeNome,
        'email' => $email,
    ];

    if ($nome === '' || $equipeNome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
        $erro = 'Preencha todos os campos para criar sua conta.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas não conferem.';
    } else {
        try {
            $stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
            $stmtExiste->execute([':email' => $email]);

            if ((int) $stmtExiste->fetchColumn() > 0) {
                $erro = 'Já existe uma conta usando este e-mail.';
            } else {
                $stmtCriar = $pdo->prepare(
                    "INSERT INTO usuarios (nome, equipe_nome, email, senha, cargo, supervisor_id, titular_id, ativo)
                     VALUES (:nome, :equipe_nome, :email, :senha, 'administrador', NULL, NULL, 1)"
                );
                $stmtCriar->execute([
                    ':nome' => $nome,
                    ':equipe_nome' => $equipeNome,
                    ':email' => $email,
                    ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                ]);

                $novoUsuarioId = (int) $pdo->lastInsertId();
                $stmtTitular = $pdo->prepare("UPDATE usuarios SET titular_id = :id WHERE id = :id");
                $stmtTitular->execute([':id' => $novoUsuarioId]);

                $_SESSION['mensagem_login'] = 'Conta criada com sucesso. Entre para acessar sua equipe.';
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            $erro = 'Não foi possível criar a conta agora. Confira os dados e tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar conta | TaskBlue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page signup-page">
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
                        <span>Crie a conta titular da sua equipe</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <span>Depois convide outros membros para essa equipe</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-form-container">
            <div class="login-header">
                <h2>Criar conta titular</h2>
                <p>Informe os dados iniciais para acessar o TaskBlue.</p>
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
                    <label for="nome">Nome do titular</label>
                    <div class="input-wrapper no-icon">
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" placeholder="Seu nome" required autocomplete="name">
                    </div>
                </div>

                <div class="form-field">
                    <label for="equipe_nome">Nome da equipe</label>
                    <div class="input-wrapper no-icon">
                        <input type="text" id="equipe_nome" name="equipe_nome" value="<?= htmlspecialchars($valores['equipe_nome']) ?>" placeholder="Ex: Grupo de estudos, Projeto X..." required>
                    </div>
                </div>

                <div class="form-field">
                    <label for="email">E-mail</label>
                    <div class="input-wrapper no-icon">
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($valores['email']) ?>" placeholder="seu@email.com" required autocomplete="email">
                    </div>
                </div>

                <div class="form-field">
                    <label for="senha">Senha</label>
                    <div class="input-wrapper no-icon">
                        <input type="password" id="senha" name="senha" placeholder="Mínimo de 6 caracteres" required autocomplete="new-password">
                    </div>
                </div>

                <div class="form-field">
                    <label for="confirmar_senha">Confirmar senha</label>
                    <div class="input-wrapper no-icon">
                        <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Digite a senha novamente" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Criar conta
                </button>
            </form>

            <div class="auth-switch">
                <p>Já tem uma conta?</p>
                <a href="login.php">Entrar</a>
            </div>
        </div>
    </div>
</body>
</html>
