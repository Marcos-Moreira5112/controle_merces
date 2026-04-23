<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$mensagem = '';
$tipoMensagem = '';

function buscarUsuarioConta(PDO $pdo, int $usuarioId): array|false
{
    $sql = "SELECT u.id, u.nome, u.email, u.cargo, u.supervisor_id, u.ativo, u.created_at, s.nome AS supervisor_nome
            FROM usuarios u
            LEFT JOIN usuarios s ON s.id = u.supervisor_id
            WHERE u.id = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuarioId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'atualizar_perfil') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nome === '' || $email === '') {
            $mensagem = 'Nome e e-mail são obrigatórios.';
            $tipoMensagem = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Informe um e-mail válido.';
            $tipoMensagem = 'erro';
        } else {
            $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1");
            $stmtEmail->execute([$email, $usuarioId]);
            $emailExistente = $stmtEmail->fetch(PDO::FETCH_ASSOC);

            if ($emailExistente) {
                $mensagem = 'Esse e-mail já está em uso por outra conta.';
                $tipoMensagem = 'erro';
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
                $stmtUpdate->execute([$nome, $email, $usuarioId]);
                $mensagem = 'Seus dados foram atualizados com sucesso.';
                $tipoMensagem = 'sucesso';
            }
        }
    }

    if ($acao === 'atualizar_senha') {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmacaoSenha = $_POST['confirmar_senha'] ?? '';

        $stmtSenha = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ? LIMIT 1");
        $stmtSenha->execute([$usuarioId]);
        $senhaBanco = $stmtSenha->fetchColumn();

        if ($senhaAtual === '' || $novaSenha === '' || $confirmacaoSenha === '') {
            $mensagem = 'Preencha todos os campos da senha.';
            $tipoMensagem = 'erro';
        } elseif (!$senhaBanco || !password_verify($senhaAtual, $senhaBanco)) {
            $mensagem = 'A senha atual não confere.';
            $tipoMensagem = 'erro';
        } elseif (strlen($novaSenha) < 6) {
            $mensagem = 'A nova senha deve ter pelo menos 6 caracteres.';
            $tipoMensagem = 'erro';
        } elseif ($novaSenha !== $confirmacaoSenha) {
            $mensagem = 'A confirmação da senha não confere.';
            $tipoMensagem = 'erro';
        } else {
            $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $stmtSenhaUpdate = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmtSenhaUpdate->execute([$novaSenhaHash, $usuarioId]);
            $mensagem = 'Sua senha foi alterada com sucesso.';
            $tipoMensagem = 'sucesso';
        }
    }
}

$usuario = buscarUsuarioConta($pdo, $usuarioId);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$nomeUsuario = $usuario['nome'];
$cargoUsuario = $usuario['cargo'];
$pageTitle = 'Minha Conta';
$activePage = 'conta';
$dataCriacao = !empty($usuario['created_at']) ? date('d/m/Y', strtotime($usuario['created_at'])) : 'Não informado';
$statusConta = (int) ($usuario['ativo'] ?? 1) === 1 ? 'Ativa' : 'Inativa';
$supervisorNome = $usuario['supervisor_nome'] ?: 'Não vinculado';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Minha Conta | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="account-page">
    <?php include __DIR__ . '/includes/partials/header.php'; ?>

    <main class="account-container">
        <section class="account-hero">
            <div>
                <span class="account-eyebrow">Conta</span>
                <h1>Gerencie seus dados</h1>
                <p>Atualize suas informações de acesso e acompanhe os detalhes da sua conta.</p>
            </div>
        </section>

        <?php if ($mensagem !== ''): ?>
            <div class="account-alert <?= $tipoMensagem === 'sucesso' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <div class="account-grid">
            <section class="account-panel account-summary">
                <h2>Resumo da conta</h2>

                <div class="account-summary-list">
                    <div class="account-summary-item">
                        <span>Nome</span>
                        <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
                    </div>
                    <div class="account-summary-item">
                        <span>E-mail</span>
                        <strong><?= htmlspecialchars($usuario['email']) ?></strong>
                    </div>
                    <div class="account-summary-item">
                        <span>Cargo</span>
                        <strong><?= ucfirst(htmlspecialchars($usuario['cargo'])) ?></strong>
                    </div>
                    <div class="account-summary-item">
                        <span>Status</span>
                        <strong><?= htmlspecialchars($statusConta) ?></strong>
                    </div>
                    <div class="account-summary-item">
                        <span>Supervisor</span>
                        <strong><?= htmlspecialchars($supervisorNome) ?></strong>
                    </div>
                    <div class="account-summary-item">
                        <span>Conta criada em</span>
                        <strong><?= htmlspecialchars($dataCriacao) ?></strong>
                    </div>
                </div>
            </section>

            <section class="account-panel">
                <h2>Dados pessoais</h2>

                <form method="POST" class="account-form">
                    <input type="hidden" name="acao" value="atualizar_perfil">

                    <label class="account-field">
                        <span>Nome</span>
                        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                    </label>

                    <label class="account-field">
                        <span>E-mail</span>
                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </label>

                    <button type="submit" class="account-btn">Salvar alterações</button>
                </form>
            </section>

            <section class="account-panel">
                <h2>Segurança</h2>

                <form method="POST" class="account-form">
                    <input type="hidden" name="acao" value="atualizar_senha">

                    <label class="account-field">
                        <span>Senha atual</span>
                        <input type="password" name="senha_atual" required>
                    </label>

                    <label class="account-field">
                        <span>Nova senha</span>
                        <input type="password" name="nova_senha" minlength="6" required>
                    </label>

                    <label class="account-field">
                        <span>Confirmar nova senha</span>
                        <input type="password" name="confirmar_senha" minlength="6" required>
                    </label>

                    <button type="submit" class="account-btn">Atualizar senha</button>
                </form>
            </section>
        </div>
    </main>
    <script src="assets/js/main.js"></script>
</body>
</html>
