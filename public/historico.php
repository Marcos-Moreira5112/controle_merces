<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Sistema de mensagens flash
$mensagem = $_SESSION['mensagem'] ?? null;
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// AUTO-EXCLUSÃO: Deletar tarefas arquivadas há mais de 7 dias
$sqlLimparHistorico = "
    DELETE FROM tarefas
    WHERE usuario_id = :usuario_id
      AND arquivada = 1
      AND data_arquivamento IS NOT NULL
      AND data_arquivamento < DATE_SUB(NOW(), INTERVAL 7 DAY)
";

$stmtLimpar = $pdo->prepare($sqlLimparHistorico);
$stmtLimpar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmtLimpar->execute();

// RESTAURAR - volta tarefa para ativa
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'restaurar') {

    $tarefa_id = (int) $_GET['id'];

    $sqlRestaurar = "
        UPDATE tarefas
        SET arquivada = 0, data_arquivamento = NULL
        WHERE id = :id AND usuario_id = :usuario_id
    ";

    $stmtRestaurar = $pdo->prepare($sqlRestaurar);
    $stmtRestaurar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtRestaurar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtRestaurar->execute();

    $_SESSION['mensagem'] = 'Tarefa restaurada com sucesso!';
    $_SESSION['tipo_mensagem'] = 'sucesso';

    header('Location: historico.php');
    exit;
}

// DELETAR PERMANENTEMENTE
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'deletar') {

    $tarefa_id = (int) $_GET['id'];

    $sqlDeletar = "
        DELETE FROM tarefas
        WHERE id = :id AND usuario_id = :usuario_id AND arquivada = 1
    ";

    $stmtDeletar = $pdo->prepare($sqlDeletar);
    $stmtDeletar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtDeletar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtDeletar->execute();

    $_SESSION['mensagem'] = 'Tarefa excluída permanentemente!';
    $_SESSION['tipo_mensagem'] = 'sucesso';

    header('Location: historico.php');
    exit;
}

// Buscar tarefas arquivadas
$sql = "
    SELECT id, titulo, prazo, status, tipo, observacoes, created_at, data_arquivamento
    FROM tarefas
    WHERE usuario_id = :usuario_id
      AND arquivada = 1
    ORDER BY data_arquivamento DESC
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id);
$stmt->execute();

$tarefasArquivadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="header-content">
            <div>
                <h1>Histórico de Tarefas</h1>
                <p>Óticas Mercês</p>
            </div>
            <div class="header-actions">
                <a href="tarefas.php" class="btn-voltar">← Voltar</a>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>
    </header>

    <!-- Mensagens de feedback -->
    <?php if ($mensagem): ?>
        <div class="mensagem <?= $tipo_mensagem ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <main>
        <div class="layout-historico">

            <section class="lista-tarefas">
                <h2>Tarefas Arquivadas (<?= count($tarefasArquivadas) ?>)</h2>
                
                <div class="aviso-historico">
                    <span class="icone-info">ℹ️</span>
                    Tarefas no histórico são excluídas automaticamente após 7 dias
                </div>
                
                <?php if (count($tarefasArquivadas) === 0): ?>
                    <div class="historico-vazio">
                        <p>📋 Nenhuma tarefa no histórico</p>
                        <p class="texto-secundario">Tarefas excluídas aparecerão aqui</p>
                    </div>
                <?php else: ?>
                    <ul>
                        <?php foreach ($tarefasArquivadas as $tarefa): ?>
                            <?php
                            // Calcular dias restantes até exclusão automática
                            if ($tarefa['data_arquivamento']) {
                                $dataArquivamento = new DateTime($tarefa['data_arquivamento']);
                                $hoje = new DateTime();
                                $diasArquivada = $hoje->diff($dataArquivamento)->days;
                                $diasRestantes = 7 - $diasArquivada;
                            } else {
                                $diasRestantes = null;
                            }
                            ?>
                            <li class="tarefa-card historico">
                                <h3><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                
                                <p class="prazo-info">
                                    Prazo: <?= date('d/m/Y', strtotime($tarefa['prazo'])) ?>
                                </p>

                                <p>Status: <?= $tarefa['status'] ?></p>
                                <p>Tipo: <?= $tarefa['tipo'] === 'fixa' ? 'Tarefa fixa' : 'Tarefa' ?></p>
                                
                                <?php if ($diasRestantes !== null): ?>
                                    <p class="dias-restantes">
                                        <?php if ($diasRestantes > 1): ?>
                                            ⏱️ Será excluída em <?= $diasRestantes ?> dias
                                        <?php elseif ($diasRestantes == 1): ?>
                                            ⚠️ Será excluída amanhã
                                        <?php else: ?>
                                            🔴 Será excluída em breve
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($tarefa['observacoes'])): ?>
                                    <div class="observacoes-preview">
                                        <strong>Observações:</strong>
                                        <p><?= nl2br(htmlspecialchars($tarefa['observacoes'])) ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="acoes-historico">
                                    <a href="?acao=restaurar&id=<?= $tarefa['id'] ?>" class="btn-restaurar">
                                        ↺ Restaurar
                                    </a>
                                    <a href="?acao=deletar&id=<?= $tarefa['id'] ?>" class="btn-deletar">
                                        🗑️ Excluir permanentemente
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>