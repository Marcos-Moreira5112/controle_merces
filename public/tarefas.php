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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = trim($_POST['titulo']);
    $prazo  = $_POST['prazo'];
    $tipo = $_POST['tipo'] ?? 'normal';

    if ($titulo !== '' && $prazo !== '') {

        $sqlInsert = "
            INSERT INTO tarefas (titulo, prazo, status, tipo, usuario_id)
            VALUES (:titulo, :prazo, 'pendente', :tipo, :usuario_id)
        ";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindParam(':titulo', $titulo);
        $stmtInsert->bindParam(':prazo', $prazo);
        $stmtInsert->bindParam(':usuario_id', $usuario_id);
        $stmtInsert->bindParam(':tipo', $tipo);


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

$hoje = new DateTime('today');

// buscar tarefas fixas pendentes
$sqlFixas = "
    SELECT *
    FROM tarefas
    WHERE tipo = 'fixa'
      AND status = 'pendente'
      AND usuario_id = :usuario_id
";

$stmtFixas = $pdo->prepare($sqlFixas);
$stmtFixas->bindParam(':usuario_id', $usuario_id);
$stmtFixas->execute();

$tarefasFixas = $stmtFixas->fetchAll(PDO::FETCH_ASSOC);

foreach ($tarefasFixas as $tarefa) {

    $prazo = new DateTime($tarefa['prazo']);

    // só gera próxima se já venceu
    if ($prazo >= $hoje) {
        continue;
    }

    // calcula próximo mês
    $proximoPrazo = clone $prazo;
    $proximoPrazo->modify('+1 month');

    $mes = (int) $proximoPrazo->format('n');
    $ano = (int) $proximoPrazo->format('Y');

    // verificar se já existe tarefa para o próximo mês
    $sqlExiste = "
        SELECT COUNT(*) 
        FROM tarefas
        WHERE tipo = 'fixa'
          AND usuario_id = :usuario_id
          AND titulo = :titulo
          AND mes_referencia = :mes
          AND ano_referencia = :ano
    ";

    $stmtExiste = $pdo->prepare($sqlExiste);
    $stmtExiste->execute([
        ':usuario_id' => $usuario_id,
        ':titulo'     => $tarefa['titulo'],
        ':mes'        => $mes,
        ':ano'        => $ano
    ]);

    if ($stmtExiste->fetchColumn() > 0) {
        continue;
    }

    // definir tarefa origem
    $origemId = $tarefa['tarefa_origem_id'] ?? $tarefa['id'];

    // criar nova tarefa fixa
    $sqlInsert = "
        INSERT INTO tarefas (
            titulo, prazo, status, tipo, usuario_id,
            mes_referencia, ano_referencia, tarefa_origem_id
        ) VALUES (
            :titulo, :prazo, 'pendente', 'fixa', :usuario_id,
            :mes, :ano, :origem
        )
    ";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        ':titulo'     => $tarefa['titulo'],
        ':prazo'      => $proximoPrazo->format('Y-m-d'),
        ':usuario_id' => $usuario_id,
        ':mes'        => $mes,
        ':ano'        => $ano,
        ':origem'     => $origemId
    ]);
}

$sql = "
    SELECT id, titulo, prazo, status, tipo, observacoes
    FROM tarefas
    WHERE usuario_id = :usuario_id
    ORDER BY prazo ASC
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id);
$stmt->execute();

$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tarefasNormais = [];
$tarefasFixas   = [];

foreach ($tarefas as $tarefa) {
    if ($tarefa['tipo'] === 'fixa') {
        $tarefasFixas[] = $tarefa;
    } else {
        $tarefasNormais[] = $tarefa;
    }
}

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

                <div>
                    <label for="tipo">Tipo da tarefa</label><br>
                    <select id="tipo" name="tipo">
                        <option value="normal">Tarefa</option>
                        <option value="fixa">Tarefa fixa</option>
                    </select>
                </div>

                <button type="submit">Adicionar tarefa</button>
            </form>
        </section>

        <!-- Coluna direita -->
        <div class="coluna-tarefas">

            <section class="lista-tarefas">
                <h2>Tarefas</h2>
                <?php if (count($tarefasNormais) === 0): ?>
                    <p>Nenhuma tarefa cadastrada.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($tarefasNormais as $tarefa): ?>
                            <li class="<?= $tarefa['status'] === 'concluida' ? 'concluida' : '' ?>">
                                <h3><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                <p>Prazo: <?= $tarefa['prazo'] ?></p>
                                <p>Status: <?= $tarefa['status'] ?></p>

                                <div class="observacoes-preview">
                                    <strong>Observações:</strong>

                                    <?php if (!empty($tarefa['observacoes'])): ?>
                                        <p><?= nl2br(htmlspecialchars($tarefa['observacoes'])) ?></p>
                                    <?php else: ?>
                                        <p class="sem-observacoes">Nenhuma observação</p>
                                    <?php endif; ?>
                                </div>

                                <button 
                                    type="button"
                                    class="btn-observacoes"
                                    data-id="<?= $tarefa['id'] ?>"
                                    data-observacoes="<?= htmlspecialchars($tarefa['observacoes'] ?? '', ENT_QUOTES) ?>"
                                >
                                    Editar observações
                                </button>

                                <div>
                                    <a href="?acao=toggle&id=<?= $tarefa['id'] ?>">
                                        <?= $tarefa['status'] === 'pendente' ? 'Concluir' : 'Reabrir' ?>
                                    </a>
                                    |
                                    <a href="?acao=delete&id=<?= $tarefa['id'] ?>">Excluir</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="lista-tarefas">
                <h2>Tarefas fixas</h2>
                <?php if (count($tarefasFixas) === 0): ?>
                    <p>Nenhuma tarefa fixa cadastrada.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($tarefasFixas as $tarefa): ?>
                            <li class="<?= $tarefa['status'] === 'concluida' ? 'concluida' : '' ?>">
                                <h3><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                <p>Prazo: <?= $tarefa['prazo'] ?></p>
                                <p>Status: <?= $tarefa['status'] ?></p>

                                <div class="observacoes-preview">
                                    <strong>Observações:</strong>

                                    <?php if (!empty($tarefa['observacoes'])): ?>
                                        <p><?= nl2br(htmlspecialchars($tarefa['observacoes'])) ?></p>
                                    <?php else: ?>
                                        <p class="sem-observacoes">Nenhuma observação</p>
                                    <?php endif; ?>
                                </div>          
                                
                                <button 
                                    type="button"
                                    class="btn-observacoes"
                                    data-id="<?= $tarefa['id'] ?>"
                                    data-observacoes="<?= htmlspecialchars($tarefa['observacoes'] ?? '', ENT_QUOTES) ?>"
                                >
                                    Editar observações
                                </button>

                                <div>
                                    <a href="?acao=toggle&id=<?= $tarefa['id'] ?>">
                                        <?= $tarefa['status'] === 'pendente' ? 'Concluir' : 'Reabrir' ?>
                                    </a>
                                    |
                                    <a href="?acao=delete&id=<?= $tarefa['id'] ?>">Excluir</a>
                                </div>

                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            
        </div>

    </div>
</main>

     <div id="modalObservacoes" class="modal hidden">
            <div class="modal-content">
                <h3>Observações da tarefa</h3>

                <form method="POST" action="salvar_observacoes.php">
                    <input type="hidden" name="tarefa_id" id="modalTarefaId">

                    <textarea 
                        name="observacoes" 
                        id="modalObservacoesTexto"
                        rows="6"
                        placeholder="Anotações da tarefa..."
                    ></textarea>

                    <div class="modal-actions">
                        <button type="submit">Salvar</button>
                        <button type="button" id="fecharModal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
                                   

    <script src="assets/js/main.js"></script>

</body>
</html>