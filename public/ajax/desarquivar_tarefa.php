<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . '/config/conexao.php';

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Pegar dados do POST
$input = json_decode(file_get_contents('php://input'), true);
$tarefa_id = isset($input['id']) ? (int)$input['id'] : 0;

if ($tarefa_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar cargo do usuário
$sqlUsuario = "SELECT cargo FROM usuarios WHERE id = :usuario_id";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmtUsuario->execute();
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
$cargoUsuario = $usuario['cargo'];

// Verificar permissão
$sqlCheck = "SELECT usuario_id, atribuida_para FROM tarefas WHERE id = :id";
$stmtCheck = $pdo->prepare($sqlCheck);
$stmtCheck->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
$stmtCheck->execute();
$tarefa = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$tarefa) {
    echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada']);
    exit;
}

$podeDesarquivar = false;
if ($cargoUsuario === 'administrador') {
    $podeDesarquivar = true;
} elseif ($tarefa['usuario_id'] == $usuario_id || $tarefa['atribuida_para'] == $usuario_id) {
    $podeDesarquivar = true;
} elseif ($cargoUsuario === 'supervisor') {
    $sqlSub = "SELECT id FROM usuarios WHERE supervisor_id = :supervisor_id AND (id = :criador OR id = :atribuido)";
    $stmtSub = $pdo->prepare($sqlSub);
    $stmtSub->bindParam(':supervisor_id', $usuario_id, PDO::PARAM_INT);
    $stmtSub->bindParam(':criador', $tarefa['usuario_id'], PDO::PARAM_INT);
    $stmtSub->bindParam(':atribuido', $tarefa['atribuida_para'], PDO::PARAM_INT);
    $stmtSub->execute();
    if ($stmtSub->fetch()) {
        $podeDesarquivar = true;
    }
}

if (!$podeDesarquivar) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Desarquivar tarefa
try {
    $sqlDesarquivar = "
        UPDATE tarefas
        SET arquivada = 0, data_arquivamento = NULL
        WHERE id = :id
    ";
    $stmtDesarquivar = $pdo->prepare($sqlDesarquivar);
    $stmtDesarquivar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtDesarquivar->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
}