<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

/*
|--------------------------------------------------------------------------
| CREATE - Nova tarefa
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = trim($_POST['titulo']);
    $prazo  = $_POST['prazo'];

    if ($titulo !== '' && $prazo !== '') {

        $sqlInsert = "
            INSERT INTO tarefas (titulo, prazo, status, usuario_id)
            VALUES (:titulo, :prazo, 'pendente', :usuario_id)
        ";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindParam(':titulo', $titulo);
        $stmtInsert->bindParam(':prazo', $prazo);
        $stmtInsert->bindParam(':usuario_id', $usuario_id);

        $stmtInsert->execute();
    }

    header('Location: tarefas.php');
    exit;
}

// UPDATE - alterar status da tarefa
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'toggle') {

    $tarefa_id = (int) $_GET['id'];

    $sqlUpdate = "
        UPDATE tarefas
        SET status = CASE 
            WHEN status = 'pendente' THEN 'concluída'
            ELSE 'pendente'
        END
        WHERE id = :id AND usuario_id = :usuario_id
    ";

    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtUpdate->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtUpdate->execute();

    header('Location: tarefas.php');
    exit;
}

// DELETE - excluir tarefa
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'delete') {

    $tarefa_id = (int) $_GET['id'];

    $sqlDelete = "
        DELETE FROM tarefas
        WHERE id = :id AND usuario_id = :usuario_id
    ";

    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtDelete->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtDelete->execute();

    header('Location: tarefas.php');
    exit;
}


$sql = "
    SELECT id, titulo, prazo, status
    FROM tarefas
    WHERE usuario_id = :usuario_id
    ORDER BY prazo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id);
$stmt->execute();

$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Tarefas</title>
</head>
<body>

<h2>Nova tarefa</h2>

<form method="POST">
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

<h1>Minhas tarefas</h1>

<?php if (count($tarefas) === 0): ?>
    <p>Nenhuma tarefa cadastrada.</p>
<?php else: ?>
    <ul>
        <?php foreach ($tarefas as $tarefa): ?>
            <li>
                <strong><?= htmlspecialchars($tarefa['titulo']) ?></strong><br>
                Prazo: <?= $tarefa['prazo'] ?><br>
                Status: <?= $tarefa['status'] ?><br>
                <a href="?acao=toggle&id=<?= $tarefa['id'] ?>">
                  <?= $tarefa['status'] === 'pendente' ? 'Concluir' : 'Reabrir' ?>
                </a>
                <a href="?acao=delete&id=<?= $tarefa['id'] ?>" onclick="return confirm('Excluir esta tarefa?')">Excluir</a>
            </li>
            <hr>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

</body>
</html>