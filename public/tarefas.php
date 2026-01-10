<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conexao.php';

// Buscar tarefas do banco
$sql = "SELECT * FROM tarefas";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// Guardar os resultados
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista de Tarefas</title>
</head>
<body>

<h1>Tarefas cadastradas</h1>

<?php if (count($tarefas) === 0): ?>
    <p>Nenhuma tarefa cadastrada.</p>
<?php else: ?>
    <ul>
        <?php foreach ($tarefas as $tarefa): ?>
            <li>
                <strong><?= htmlspecialchars($tarefa['titulo']) ?></strong><br>
                Prazo: <?= $tarefa['prazo'] ?><br>
                Status: <?= $tarefa['status'] ?>
            </li>
            <hr>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

</body>
</html>