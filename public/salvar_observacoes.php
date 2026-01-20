<?php
session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$tarefa_id = (int) ($_POST['tarefa_id'] ?? 0);
$observacoes = trim($_POST['observacoes'] ?? '');

$sql = "
    UPDATE tarefas
    SET observacoes = :observacoes
    WHERE id = :id AND usuario_id = :usuario_id
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':observacoes', $observacoes);
$stmt->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();

header('Location: tarefas.php');
exit;
