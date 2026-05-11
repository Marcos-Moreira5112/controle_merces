<?php
session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';
require_once __DIR__ . '/includes/auth_helpers.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario = buscarUsuarioAutenticado($pdo, (int) $_SESSION['usuario_id']);
$tarefaId = (int) ($_POST['tarefa_id'] ?? 0);
$observacoes = trim($_POST['observacoes'] ?? '');

if (!$usuario || $tarefaId <= 0) {
    header('Location: tarefas.php');
    exit;
}

$stmtTarefa = $pdo->prepare("
    SELECT id, usuario_id, atribuida_para, titular_id
    FROM tarefas
    WHERE id = :id
    LIMIT 1
");
$stmtTarefa->execute([':id' => $tarefaId]);
$tarefa = $stmtTarefa->fetch(PDO::FETCH_ASSOC);

if ($tarefa && usuarioPodeAcessarTarefa($pdo, $usuario, $tarefa)) {
    $stmt = $pdo->prepare("
        UPDATE tarefas
        SET observacoes = :observacoes
        WHERE id = :id
    ");
    $stmt->execute([
        ':observacoes' => $observacoes,
        ':id' => $tarefaId,
    ]);

    $_SESSION['mensagem'] = 'Observações salvas com sucesso!';
    $_SESSION['tipo_mensagem'] = 'sucesso';
}

header('Location: tarefas.php');
exit;
