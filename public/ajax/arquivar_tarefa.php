<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/config/conexao.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tarefaId = isset($input['id']) ? (int) $input['id'] : 0;

if ($tarefaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$usuario = buscarUsuarioAutenticado($pdo, (int) $_SESSION['usuario_id']);
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Usuário inválido']);
    exit;
}

$stmtTarefa = $pdo->prepare("SELECT usuario_id, atribuida_para, titular_id FROM tarefas WHERE id = :id LIMIT 1");
$stmtTarefa->execute([':id' => $tarefaId]);
$tarefa = $stmtTarefa->fetch(PDO::FETCH_ASSOC);

if (!$tarefa) {
    echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada']);
    exit;
}

if (!usuarioPodeAcessarTarefa($pdo, $usuario, $tarefa)) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $stmtArquivar = $pdo->prepare("
        UPDATE tarefas
        SET arquivada = 1, data_arquivamento = NOW()
        WHERE id = :id
    ");
    $stmtArquivar->execute([':id' => $tarefaId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
}
