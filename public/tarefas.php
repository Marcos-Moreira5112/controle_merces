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
            WHEN status != 'concluida' THEN 'concluida'
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
    <title>Controle de Tarefas | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

</head>
<body>

    <!-- Header -->
    <header>
        <h1>Controle de Tarefas</h1>
        <p>Óticas Mercês</p>
    </header>

    <!-- Conteúdo principal -->
<main>
    <div class="layout">

        <!-- Coluna esquerda -->
        <section class="nova-tarefa">
            <h2>Nova tarefa</h2>

            <form method="POST">
                <div>
                    <label for="titulo">Título</label><br>
                    <input type="text" id="titulo" name="titulo" required>
                </div>

                <br>

                <div>
                    <label for="prazo">Prazo</label><br>
                    <input type="date" id="prazo" name="prazo" required>
                </div>

                <br>

                <button type="submit">Adicionar tarefa</button>
            </form>
        </section>

        <!-- Coluna direita -->
        <section class="lista-tarefas">
            <h2>Minhas tarefas</h2>

            <?php if (count($tarefas) === 0): ?>
                <p>Nenhuma tarefa cadastrada.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($tarefas as $tarefa): ?>
                        <li class="<?= $tarefa['status'] === 'concluida' ? 'concluida' : '' ?>">
                            <h3><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                            <p>Prazo: <?= $tarefa['prazo'] ?></p>
                            <p>Status: <?= $tarefa['status'] ?></p>

                            <div>
                                <a href="?acao=toggle&id=<?= $tarefa['id'] ?>" class="acao-toggle">
                                    <?= $tarefa['status'] === 'pendente' ? 'Concluir' : 'Reabrir' ?>
                                </a>
                                |
                                <a href="?acao=delete&id=<?= $tarefa['id'] ?>" class="acao-delete">
                                    Excluir
                                </a>
                            </div>
                        </li>
                        <hr>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>
</main>

    <script src="assets/js/main.js"></script>

</body>
</html>