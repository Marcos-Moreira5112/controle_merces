<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

// Se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = $_POST['titulo'];
    $prazo  = $_POST['prazo'];

    $sqlInsert = "INSERT INTO tarefas (titulo, prazo, status)
                  VALUES (:titulo, :prazo, 'pendente')";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->bindParam(':titulo', $titulo);
    $stmtInsert->bindParam(':prazo', $prazo);

    $stmtInsert->execute();

    // Evita duplicar ao atualizar a página
    header('Location: tarefas.php');
    exit;
}

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

<h2>Nova tarefa</h2>

<form method="POST" action="">
    <label>
        Título:<br>
        <input type="text" name="titulo" required>
    </label>
    <br><br>

    <label>
        Prazo:<br>
        <input type="date" name="prazo" required>
    </label>
    <br><br>

    <button type="submit">Adicionar tarefa</button>
</form>

<hr>

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