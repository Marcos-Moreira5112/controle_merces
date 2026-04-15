<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Não autenticado.'
    ]);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/config/conexao.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$tarefaId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($tarefaId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID da tarefa inválido.'
    ]);
    exit;
}

// Buscar cargo do usuário logado
$stmtUsuario = $pdo->prepare("SELECT cargo FROM usuarios WHERE id = :id");
$stmtUsuario->execute([':id' => $usuarioId]);
$usuarioLogado = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

if (!$usuarioLogado) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário inválido.'
    ]);
    exit;
}

$cargoUsuario = $usuarioLogado['cargo'];

// Buscar tarefa
$stmtTarefa = $pdo->prepare("
    SELECT id, usuario_id, atribuida_para, status, prazo
    FROM tarefas
    WHERE id = :id AND arquivada = 0
    LIMIT 1
");
$stmtTarefa->execute([':id' => $tarefaId]);
$tarefa = $stmtTarefa->fetch(PDO::FETCH_ASSOC);

if (!$tarefa) {
    echo json_encode([
        'success' => false,
        'message' => 'Tarefa não encontrada.'
    ]);
    exit;
}

// Permissão
$podeAlterar = false;

if ($cargoUsuario === 'administrador') {
    $podeAlterar = true;
} elseif (
    (int) $tarefa['usuario_id'] === $usuarioId ||
    (!empty($tarefa['atribuida_para']) && (int) $tarefa['atribuida_para'] === $usuarioId)
) {
    $podeAlterar = true;
}

if (!$podeAlterar) {
    echo json_encode([
        'success' => false,
        'message' => 'Sem permissão para alterar esta tarefa.'
    ]);
    exit;
}

// Toggle
$novoStatus = ($tarefa['status'] === 'concluida') ? 'pendente' : 'concluida';

$stmtUpdate = $pdo->prepare("
    UPDATE tarefas
    SET status = :status
    WHERE id = :id
");
$stmtUpdate->execute([
    ':status' => $novoStatus,
    ':id' => $tarefaId
]);

// status visual para a interface
$hoje = new DateTime('today');
$prazo = new DateTime($tarefa['prazo']);

if ($novoStatus === 'concluida') {
    $statusVisual = 'concluida';
    $diasAtraso = 0;
} elseif ($prazo < $hoje) {
    $statusVisual = 'atrasada';
    $diasAtraso = $hoje->diff($prazo)->days;
} elseif ($prazo == $hoje) {
    $statusVisual = 'hoje';
    $diasAtraso = 0;
} else {
    $statusVisual = 'futura';
    $diasAtraso = 0;
}

echo json_encode([
    'success' => true,
    'tarefa_id' => $tarefaId,
    'novo_status' => $novoStatus,
    'status_visual' => $statusVisual,
    'dias_atraso' => $diasAtraso
]);