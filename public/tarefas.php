<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Sistema de mensagens flash
$mensagem = $_SESSION['mensagem'] ?? null;
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações do usuário logado (cargo)
$sqlUsuario = "SELECT cargo, nome FROM usuarios WHERE id = :usuario_id";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmtUsuario->execute();
$usuarioLogado = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
$cargoUsuario = $usuarioLogado['cargo'];
$nomeUsuario = $usuarioLogado['nome'];

// Buscar lista de usuários para atribuição de tarefas (só admin e supervisor veem)
$usuariosDisponiveis = [];
if ($cargoUsuario === 'administrador' || $cargoUsuario === 'supervisor') {
    $sqlUsuarios = "
        SELECT id, nome, cargo 
        FROM usuarios 
        WHERE id != :usuario_id
    ";
    
    // Supervisor só vê seus subordinados
    if ($cargoUsuario === 'supervisor') {
        $sqlUsuarios .= " AND supervisor_id = :usuario_id";
    }
    
    $sqlUsuarios .= " ORDER BY nome ASC";
    
    $stmtUsuarios = $pdo->prepare($sqlUsuarios);
    $stmtUsuarios->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtUsuarios->execute();
    $usuariosDisponiveis = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = trim($_POST['titulo']);
    $prazo  = $_POST['prazo'];
    $tipo = $_POST['tipo'] ?? 'normal';
    $atribuida_para = isset($_POST['atribuida_para']) && $_POST['atribuida_para'] !== '' ? (int)$_POST['atribuida_para'] : null;

    // Validar se a data não é anterior a hoje
    $hoje = date('Y-m-d');
    
    if ($titulo === '' || $prazo === '') {
        $_SESSION['mensagem'] = 'Preencha todos os campos obrigatórios!';
        $_SESSION['tipo_mensagem'] = 'erro';
    } elseif ($prazo < $hoje) {
        $_SESSION['mensagem'] = 'A data não pode ser anterior a hoje!';
        $_SESSION['tipo_mensagem'] = 'erro';
    } else {
        $sqlInsert = "
            INSERT INTO tarefas (titulo, prazo, status, tipo, usuario_id, atribuida_para)
            VALUES (:titulo, :prazo, 'pendente', :tipo, :usuario_id, :atribuida_para)
        ";

        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindParam(':titulo', $titulo);
        $stmtInsert->bindParam(':prazo', $prazo);
        $stmtInsert->bindParam(':usuario_id', $usuario_id);
        $stmtInsert->bindParam(':tipo', $tipo);
        $stmtInsert->bindParam(':atribuida_para', $atribuida_para, PDO::PARAM_INT);
        $stmtInsert->execute();

        $_SESSION['mensagem'] = 'Tarefa adicionada com sucesso!';
        $_SESSION['tipo_mensagem'] = 'sucesso';
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

    $_SESSION['mensagem'] = 'Status da tarefa atualizado!';
    $_SESSION['tipo_mensagem'] = 'sucesso';

    header('Location: tarefas.php');
    exit;
}

// ARQUIVAR - marcar tarefa como arquivada (vai para histórico)
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'delete') {

    $tarefa_id = (int) $_GET['id'];

    $sqlArquivar = "
        UPDATE tarefas
        SET arquivada = 1, data_arquivamento = NOW()
        WHERE id = :id AND usuario_id = :usuario_id
    ";

    $stmtArquivar = $pdo->prepare($sqlArquivar);
    $stmtArquivar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtArquivar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtArquivar->execute();

    $_SESSION['mensagem'] = 'Tarefa movida para o histórico!';
    $_SESSION['tipo_mensagem'] = 'sucesso';

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

$tarefasFixasPendentes = $stmtFixas->fetchAll(PDO::FETCH_ASSOC);

foreach ($tarefasFixasPendentes as $tarefa) {

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

// Montar query baseada no cargo do usuário
if ($cargoUsuario === 'administrador') {
    // Admin vê todas as tarefas
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes, 
               t.usuario_id, t.atribuida_para,
               u.nome as criador_nome,
               ua.nome as atribuido_nome
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
        WHERE t.arquivada = 0
        ORDER BY t.prazo ASC
    ";
    $stmt = $pdo->prepare($sql);
    
} elseif ($cargoUsuario === 'supervisor') {
    // Supervisor vê suas tarefas + tarefas dos subordinados
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
               t.usuario_id, t.atribuida_para,
               u.nome as criador_nome,
               ua.nome as atribuido_nome
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
        WHERE t.arquivada = 0
          AND (t.usuario_id = :usuario_id 
               OR t.atribuida_para = :usuario_id
               OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = :usuario_id2)
               OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = :usuario_id3))
        ORDER BY t.prazo ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id3', $usuario_id, PDO::PARAM_INT);
    
} else {
    // Funcionário vê apenas suas tarefas
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
               t.usuario_id, t.atribuida_para,
               u.nome as criador_nome,
               ua.nome as atribuido_nome
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
        WHERE t.arquivada = 0
          AND (t.usuario_id = :usuario_id OR t.atribuida_para = :usuario_id)
        ORDER BY t.prazo ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
}

$stmt->execute();

$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// NOVO: Classificar tarefas e adicionar informações visuais
$tarefasNormais = [];
$tarefasFixas   = [];

$contadorPendentes = 0;
$contadorConcluidas = 0;

foreach ($tarefas as $tarefa) {
    
    // Classificar status visual da tarefa
    $prazoTarefa = new DateTime($tarefa['prazo']);
    $statusVisual = 'futura'; // padrão
    
    if ($tarefa['status'] === 'concluida') {
        $statusVisual = 'concluida';
        $contadorConcluidas++;
    } else {
        $contadorPendentes++;
        
        if ($prazoTarefa < $hoje) {
            $statusVisual = 'atrasada';
            $diasAtraso = $hoje->diff($prazoTarefa)->days;
            $tarefa['dias_atraso'] = $diasAtraso;
        } elseif ($prazoTarefa == $hoje) {
            $statusVisual = 'hoje';
        }
    }
    
    $tarefa['status_visual'] = $statusVisual;
    
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
    <header>
        <div class="header-content">
            <div>
                <h1>Controle de Tarefas</h1>
                <p>Óticas Mercês • <?= htmlspecialchars($nomeUsuario) ?> 
                    <span class="badge-cargo <?= $cargoUsuario ?>">
                        <?= ucfirst($cargoUsuario) ?>
                    </span>
                </p>
            </div>
            <div class="header-actions">
                <?php if ($cargoUsuario === 'administrador'): ?>
                    <a href="gerenciar_usuarios.php" class="btn-gerenciar">👥 Usuários</a>
                <?php endif; ?>
                <a href="historico.php" class="btn-historico">📋 Histórico</a>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>
    </header>

    <!-- Mensagens de feedback -->
    <?php if ($mensagem): ?>
        <div class="mensagem <?= $tipo_mensagem ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

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
                    <input type="date" id="prazo" name="prazo" required min="<?= date('Y-m-d') ?>">
                </div>

                <br>

                <div>
                    <label for="tipo">Tipo da tarefa</label><br>
                    <select id="tipo" name="tipo">
                        <option value="normal">Tarefa</option>
                        <option value="fixa">Tarefa fixa</option>
                    </select>
                </div>

                <?php if (!empty($usuariosDisponiveis)): ?>
                    <br>
                    <div>
                        <label for="atribuida_para">Atribuir para (opcional)</label><br>
                        <select id="atribuida_para" name="atribuida_para">
                            <option value="">Eu mesmo</option>
                            <?php foreach ($usuariosDisponiveis as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>">
                                    <?= htmlspecialchars($usuario['nome']) ?> 
                                    (<?= $usuario['cargo'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit">Adicionar tarefa</button>
            </form>
        </section>

        <!-- Coluna direita -->
        <div class="coluna-tarefas">

            <!-- Contador de tarefas -->
            <div class="contador-tarefas">
                <span class="contador-pendentes"><?= $contadorPendentes ?> pendentes</span>
                <span class="separador">•</span>
                <span class="contador-concluidas"><?= $contadorConcluidas ?> concluídas</span>
            </div>

            <section class="lista-tarefas">
                <h2>Tarefas</h2>
                <?php if (count($tarefasNormais) === 0): ?>
                    <p>Nenhuma tarefa cadastrada.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($tarefasNormais as $tarefa): ?>
                            <li class="tarefa-card <?= $tarefa['status_visual'] ?>">
                                <?php if ($tarefa['status_visual'] === 'atrasada'): ?>
                                    <span class="badge-atraso"><?= $tarefa['dias_atraso'] ?>d</span>
                                <?php elseif ($tarefa['status_visual'] === 'hoje'): ?>
                                    <span class="badge-hoje">HOJE</span>
                                <?php endif; ?>
                                
                                <h3><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                
                                <p class="prazo-info">
                                    Prazo: <?= date('d/m/Y', strtotime($tarefa['prazo'])) ?>
                                </p>

                                <p>Status: <?= $tarefa['status'] ?></p>
                                
                                <?php if ($cargoUsuario !== 'funcionario'): ?>
                                    <div class="info-atribuicao">
                                        <p class="info-criador">
                                            👤 Criada por: <strong><?= htmlspecialchars($tarefa['criador_nome'] ?? 'Desconhecido') ?></strong>
                                        </p>
                                        <?php if ($tarefa['atribuida_para']): ?>
                                            <p class="info-atribuido">
                                                📌 Atribuída para: <strong><?= htmlspecialchars($tarefa['atribuido_nome']) ?></strong>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

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
                            <li class="tarefa-card <?= $tarefa['status_visual'] ?>">
                                <?php if ($tarefa['status_visual'] === 'atrasada'): ?>
                                    <span class="badge-atraso"><?= $tarefa['dias_atraso'] ?>d</span>
                                <?php elseif ($tarefa['status_visual'] === 'hoje'): ?>
                                    <span class="badge-hoje">HOJE</span>
                                <?php endif; ?>
                                
                                <h3><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                
                                <p class="prazo-info">
                                    Prazo: <?= date('d/m/Y', strtotime($tarefa['prazo'])) ?>
                                </p>

                                <p>Status: <?= $tarefa['status'] ?></p>
                                
                                <?php if ($cargoUsuario !== 'funcionario'): ?>
                                    <div class="info-atribuicao">
                                        <p class="info-criador">
                                            👤 Criada por: <strong><?= htmlspecialchars($tarefa['criador_nome'] ?? 'Desconhecido') ?></strong>
                                        </p>
                                        <?php if ($tarefa['atribuida_para']): ?>
                                            <p class="info-atribuido">
                                                📌 Atribuída para: <strong><?= htmlspecialchars($tarefa['atribuido_nome']) ?></strong>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

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