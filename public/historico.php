<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';
require_once __DIR__ . '/includes/auth_helpers.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$usuario = buscarUsuarioAutenticado($pdo, $usuarioId);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$nomeUsuario = $usuario['nome'];
$cargoUsuario = $usuario['cargo'];
$titularId = obterTitularIdUsuario($usuario);
$nomeEquipe = obterNomeEquipe($usuario);
$idsVisiveis = buscarIdsUsuariosVisiveis($pdo, $usuario, true);

$mensagem = $_SESSION['mensagem'] ?? null;
$tipoMensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

if (usuarioEhTitular($usuario)) {
    $stmtLimpar = $pdo->prepare("
        DELETE FROM tarefas
        WHERE arquivada = 1
          AND titular_id = :titular_id
          AND data_arquivamento IS NOT NULL
          AND data_arquivamento < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmtLimpar->execute([':titular_id' => $titularId]);
} else {
    $placeholdersLimpeza = [];
    $paramsLimpeza = [':titular_id' => $titularId];

    foreach ($idsVisiveis as $index => $idVisivel) {
        $param = ':visivel_' . $index;
        $placeholdersLimpeza[] = $param;
        $paramsLimpeza[$param] = $idVisivel;
    }

    $listaLimpeza = implode(', ', $placeholdersLimpeza ?: ['0']);
    $stmtLimpar = $pdo->prepare("
        DELETE FROM tarefas
        WHERE arquivada = 1
          AND titular_id = :titular_id
          AND usuario_id IN ($listaLimpeza)
          AND data_arquivamento IS NOT NULL
          AND data_arquivamento < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmtLimpar->execute($paramsLimpeza);
}

if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'restaurar') {
    $tarefaId = (int) $_GET['id'];
    $stmtTarefa = $pdo->prepare("
        SELECT id, usuario_id, atribuida_para, titular_id
        FROM tarefas
        WHERE id = :id AND arquivada = 1
        LIMIT 1
    ");
    $stmtTarefa->execute([':id' => $tarefaId]);
    $tarefa = $stmtTarefa->fetch(PDO::FETCH_ASSOC);

    if ($tarefa && usuarioPodeAcessarTarefa($pdo, $usuario, $tarefa)) {
        $stmtRestaurar = $pdo->prepare("
            UPDATE tarefas
            SET arquivada = 0, data_arquivamento = NULL
            WHERE id = :id
        ");
        $stmtRestaurar->execute([':id' => $tarefaId]);

        $_SESSION['mensagem'] = 'Tarefa restaurada com sucesso!';
        $_SESSION['tipo_mensagem'] = 'sucesso';
    }

    header('Location: historico.php');
    exit;
}

if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'deletar') {
    $tarefaId = (int) $_GET['id'];
    $stmtTarefa = $pdo->prepare("
        SELECT id, usuario_id, atribuida_para, titular_id
        FROM tarefas
        WHERE id = :id AND arquivada = 1
        LIMIT 1
    ");
    $stmtTarefa->execute([':id' => $tarefaId]);
    $tarefa = $stmtTarefa->fetch(PDO::FETCH_ASSOC);

    if ($tarefa && usuarioPodeAcessarTarefa($pdo, $usuario, $tarefa)) {
        $stmtDeletar = $pdo->prepare("DELETE FROM tarefas WHERE id = :id AND arquivada = 1");
        $stmtDeletar->execute([':id' => $tarefaId]);

        $_SESSION['mensagem'] = 'Tarefa excluída permanentemente!';
        $_SESSION['tipo_mensagem'] = 'sucesso';
    }

    header('Location: historico.php');
    exit;
}

$sql = "
    SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
           t.created_at, t.data_arquivamento, t.usuario_id, t.atribuida_para, t.titular_id,
           u.nome AS nome_usuario
    FROM tarefas t
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.arquivada = 1
      AND t.titular_id = :titular_id
";
$params = [':titular_id' => $titularId];

if (!usuarioEhTitular($usuario)) {
    $placeholdersCriador = [];
    $placeholdersAtribuido = [];

    foreach ($idsVisiveis as $index => $idVisivel) {
        $paramCriador = ':visivel_criador_' . $index;
        $paramAtribuido = ':visivel_atribuido_' . $index;
        $placeholdersCriador[] = $paramCriador;
        $placeholdersAtribuido[] = $paramAtribuido;
        $params[$paramCriador] = $idVisivel;
        $params[$paramAtribuido] = $idVisivel;
    }

    $sql .= " AND (t.usuario_id IN (" . implode(', ', $placeholdersCriador ?: ['0']) . ")
              OR t.atribuida_para IN (" . implode(', ', $placeholdersAtribuido ?: ['0']) . "))";
}

$sql .= " ORDER BY t.data_arquivamento DESC";

$stmt = $pdo->prepare($sql);
foreach ($params as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->execute();
$tarefasArquivadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tarefasAgrupadas = [];
foreach ($tarefasArquivadas as $tarefa) {
    $dataArq = $tarefa['data_arquivamento']
        ? date('Y-m-d', strtotime($tarefa['data_arquivamento']))
        : 'sem-data';
    $tarefasAgrupadas[$dataArq][] = $tarefa;
}

function formatarDataGrupo(string $data): string
{
    if ($data === 'sem-data') {
        return 'Data não registrada';
    }

    $hoje = new DateTime();
    $dataObj = new DateTime($data);
    $diff = $hoje->diff($dataObj)->days;

    if ($dataObj->format('Y-m-d') === $hoje->format('Y-m-d')) {
        return 'Arquivadas hoje';
    }
    if ($diff === 1) {
        return 'Arquivadas ontem';
    }
    if ($diff < 7) {
        return 'Arquivadas há ' . $diff . ' dias';
    }

    return 'Arquivadas em ' . $dataObj->format('d/m/Y');
}

$pageTitle = 'Histórico';
$activePage = 'historico';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico | TaskBlue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/historico.css">
</head>
<body>
    <?php include __DIR__ . '/includes/partials/header.php'; ?>

    <?php if ($mensagem): ?>
        <div class="mensagem-toast <?= htmlspecialchars($tipoMensagem ?? 'sucesso') ?>">
            <span><?= htmlspecialchars($mensagem) ?></span>
        </div>
    <?php endif; ?>

    <main>
        <div class="historico-container">
            <div class="historico-header">
                <div class="historico-titulo">
                    <h2>Tarefas Arquivadas</h2>
                    <span class="contador"><?= count($tarefasArquivadas) ?> <?= count($tarefasArquivadas) === 1 ? 'tarefa' : 'tarefas' ?></span>
                </div>

                <div class="aviso-expiracao">
                    <span>Tarefas são excluídas automaticamente após <strong>7 dias</strong></span>
                </div>
            </div>

            <?php if (count($tarefasArquivadas) === 0): ?>
                <div class="empty-state">
                    <h3>Nenhuma tarefa arquivada</h3>
                    <p>Quando você arquivar tarefas, elas aparecerão aqui por 7 dias antes de serem excluídas permanentemente.</p>
                    <a href="tarefas.php" class="btn-voltar-tarefas">Voltar para Tarefas</a>
                </div>
            <?php else: ?>
                <div class="historico-lista">
                    <?php foreach ($tarefasAgrupadas as $dataGrupo => $tarefas): ?>
                        <div class="grupo-data">
                            <div class="grupo-header">
                                <span class="grupo-titulo"><?= formatarDataGrupo($dataGrupo) ?></span>
                                <span class="grupo-linha"></span>
                            </div>

                            <div class="grupo-tarefas">
                                <?php foreach ($tarefas as $tarefa): ?>
                                    <?php
                                    $diasRestantes = 7;
                                    $urgencia = '';
                                    if ($tarefa['data_arquivamento']) {
                                        $dataArquivamento = new DateTime($tarefa['data_arquivamento']);
                                        $hoje = new DateTime();
                                        $diasArquivada = $hoje->diff($dataArquivamento)->days;
                                        $diasRestantes = max(0, 7 - $diasArquivada);

                                        if ($diasRestantes <= 1) {
                                            $urgencia = 'critico';
                                        } elseif ($diasRestantes <= 3) {
                                            $urgencia = 'alerta';
                                        }
                                    }
                                    $porcentagemRestante = ($diasRestantes / 7) * 100;
                                    ?>
                                    <div class="tarefa-arquivada <?= htmlspecialchars($urgencia) ?>">
                                        <div class="tarefa-main">
                                            <div class="tarefa-info">
                                                <h3 class="tarefa-titulo"><?= htmlspecialchars($tarefa['titulo']) ?></h3>

                                                <div class="tarefa-meta">
                                                    <span class="meta-item">Prazo: <?= date('d/m/Y', strtotime($tarefa['prazo'])) ?></span>
                                                    <span class="badge-status <?= htmlspecialchars($tarefa['status']) ?>">
                                                        <?= $tarefa['status'] === 'concluida' ? 'Concluída' : 'Pendente' ?>
                                                    </span>
                                                    <?php if ($tarefa['tipo'] === 'fixa'): ?>
                                                        <span class="badge-tipo">Recorrente</span>
                                                    <?php endif; ?>
                                                    <?php if (count($idsVisiveis) > 1 && (int) $tarefa['usuario_id'] !== $usuarioId): ?>
                                                        <span class="meta-item usuario"><?= htmlspecialchars($tarefa['nome_usuario']) ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($tarefa['observacoes'])): ?>
                                                    <div class="tarefa-observacoes">
                                                        <p><?= nl2br(htmlspecialchars(mb_strimwidth($tarefa['observacoes'], 0, 150, '...'))) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="tarefa-expiracao">
                                                <div class="expiracao-info <?= htmlspecialchars($urgencia) ?>">
                                                    <?php if ($diasRestantes === 0): ?>
                                                        <span class="expiracao-texto">Expira hoje</span>
                                                    <?php elseif ($diasRestantes === 1): ?>
                                                        <span class="expiracao-texto">Expira amanhã</span>
                                                    <?php else: ?>
                                                        <span class="expiracao-texto"><?= $diasRestantes ?> dias restantes</span>
                                                    <?php endif; ?>
                                                    <div class="expiracao-barra">
                                                        <div class="expiracao-progresso" style="width: <?= $porcentagemRestante ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tarefa-acoes">
                                            <a href="?acao=restaurar&id=<?= $tarefa['id'] ?>" class="btn-restaurar">Restaurar</a>
                                            <button type="button" class="btn-excluir" onclick="confirmarExclusao(<?= $tarefa['id'] ?>, '<?= htmlspecialchars(addslashes($tarefa['titulo'])) ?>')">Excluir</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="modal-confirmacao" class="modal-overlay">
        <div class="modal-confirmacao">
            <h3>Excluir permanentemente?</h3>
            <p>A tarefa "<span id="modal-tarefa-nome"></span>" será excluída permanentemente. Esta ação não pode ser desfeita.</p>
            <div class="modal-acoes">
                <button type="button" class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
                <a id="link-confirmar-exclusao" href="#" class="btn-confirmar-excluir">Excluir</a>
            </div>
        </div>
    </div>

    <script>
        function confirmarExclusao(id, titulo) {
            const modal = document.getElementById('modal-confirmacao');
            document.getElementById('modal-tarefa-nome').textContent = titulo;
            document.getElementById('link-confirmar-exclusao').href = '?acao=deletar&id=' + id;
            modal.classList.add('ativo');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal() {
            document.getElementById('modal-confirmacao').classList.remove('ativo');
            document.body.style.overflow = '';
        }

        document.getElementById('modal-confirmacao').addEventListener('click', function (e) {
            if (e.target === this) {
                fecharModal();
            }
        });
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
