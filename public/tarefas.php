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

// ═══════════════════════════════════════════════════════════════
// DICAS ROTATIVAS
// ═══════════════════════════════════════════════════════════════
$dicas = [
    [
        'icone' => '💡',
        'titulo' => 'Filtros rápidos',
        'texto' => 'Use os <strong>filtros</strong> acima para encontrar tarefas rapidamente por status ou tipo.'
    ],
    [
        'icone' => '🔄',
        'titulo' => 'Tarefas recorrentes',
        'texto' => 'Tarefas <strong>recorrentes</strong> se renovam automaticamente todo mês após o prazo.'
    ],
    [
        'icone' => '✅',
        'titulo' => 'Marcar como concluída',
        'texto' => 'Clique no <strong>círculo</strong> ao lado da tarefa para marcá-la como concluída.'
    ],
    [
        'icone' => '📝',
        'titulo' => 'Observações',
        'texto' => 'Use o botão de <strong>editar</strong> para adicionar observações importantes às tarefas.'
    ],
    [
        'icone' => '📅',
        'titulo' => 'Atalhos de data',
        'texto' => 'Use os botões <strong>Hoje</strong>, <strong>Amanhã</strong> ou <strong>+7 dias</strong> para definir prazos rapidamente.'
    ],
    [
        'icone' => '🗂️',
        'titulo' => 'Histórico',
        'texto' => 'Tarefas arquivadas vão para o <strong>Histórico</strong>, onde podem ser restauradas ou excluídas.'
    ],
    [
        'icone' => '↩️',
        'titulo' => 'Desfazer exclusão',
        'texto' => 'Arquivou sem querer? Clique em <strong>Desfazer</strong> no aviso que aparece para recuperar a tarefa.'
    ],
    [
        'icone' => '🔍',
        'titulo' => 'Busca inteligente',
        'texto' => 'Digite qualquer parte do <strong>título</strong> da tarefa na busca para encontrá-la rapidamente.'
    ],
    [
        'icone' => '👥',
        'titulo' => 'Atribuir tarefas',
        'texto' => 'Supervisores podem <strong>atribuir tarefas</strong> para seus funcionários no formulário de criação.'
    ],
    [
        'icone' => '📊',
        'titulo' => 'Ordenação',
        'texto' => 'Use a <strong>ordenação</strong> para ver primeiro as tarefas mais urgentes ou mais recentes.'
    ]
];

// Seleciona uma dica aleatória
$dicaAtual = $dicas[array_rand($dicas)];

// ═══════════════════════════════════════════════════════════════
// FILTROS E BUSCA
// ═══════════════════════════════════════════════════════════════
$filtro_busca = trim($_GET['busca'] ?? '');
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_tipo = $_GET['tipo'] ?? 'todos';
$filtro_usuario = $_GET['usuario'] ?? 'todos';
$ordenar_por = $_GET['ordenar'] ?? 'vencimento_asc';

// Buscar lista de usuários para atribuição de tarefas (só admin e supervisor veem)
$usuariosDisponiveis = [];
if ($cargoUsuario === 'administrador' || $cargoUsuario === 'supervisor') {
    $sqlUsuarios = "
        SELECT id, nome, cargo 
        FROM usuarios 
        WHERE id != :usuario_id
    ";
    
    if ($cargoUsuario === 'supervisor') {
        $sqlUsuarios .= " AND supervisor_id = :usuario_id";
    }
    
    $sqlUsuarios .= " ORDER BY nome ASC";
    
    $stmtUsuarios = $pdo->prepare($sqlUsuarios);
    $stmtUsuarios->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtUsuarios->execute();
    $usuariosDisponiveis = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar lista de usuários para FILTRO (diferente da atribuição)
$usuariosFiltro = [];
if ($cargoUsuario === 'administrador') {
    // Admin vê todos os usuários
    $sqlFiltroUsuarios = "
        SELECT id, nome, cargo 
        FROM usuarios 
        ORDER BY cargo, nome ASC
    ";
    $stmtFiltroUsuarios = $pdo->prepare($sqlFiltroUsuarios);
    $stmtFiltroUsuarios->execute();
    $usuariosFiltro = $stmtFiltroUsuarios->fetchAll(PDO::FETCH_ASSOC);
} elseif ($cargoUsuario === 'supervisor') {
    // Supervisor vê ele mesmo + seus funcionários
    $sqlFiltroUsuarios = "
        SELECT id, nome, cargo 
        FROM usuarios 
        WHERE id = :usuario_id OR supervisor_id = :usuario_id2
        ORDER BY cargo DESC, nome ASC
    ";
    $stmtFiltroUsuarios = $pdo->prepare($sqlFiltroUsuarios);
    $stmtFiltroUsuarios->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtFiltroUsuarios->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    $stmtFiltroUsuarios->execute();
    $usuariosFiltro = $stmtFiltroUsuarios->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $prazo  = $_POST['prazo'];
    $tipo = $_POST['tipo'] ?? 'normal';
    $atribuida_para = isset($_POST['atribuida_para']) && $_POST['atribuida_para'] !== '' ? (int)$_POST['atribuida_para'] : null;

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

    // Verificar se o usuário tem permissão para alterar esta tarefa
    $sqlCheck = "SELECT usuario_id, atribuida_para FROM tarefas WHERE id = :id";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    $stmtCheck->execute();
    $tarefaCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $podeAlterar = false;
    if ($cargoUsuario === 'administrador') {
        $podeAlterar = true;
    } elseif ($tarefaCheck['usuario_id'] == $usuario_id || $tarefaCheck['atribuida_para'] == $usuario_id) {
        $podeAlterar = true;
    }

    if ($podeAlterar) {
        $sqlUpdate = "
            UPDATE tarefas
            SET status = CASE 
                WHEN status != 'concluida' THEN 'concluida'
                ELSE 'pendente'
            END
            WHERE id = :id
        ";

        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
        $stmtUpdate->execute();

        $_SESSION['mensagem'] = 'Status da tarefa atualizado!';
        $_SESSION['tipo_mensagem'] = 'sucesso';
    }

    // Preservar filtros ao redirecionar
    $params = http_build_query([
        'busca' => $filtro_busca,
        'status' => $filtro_status,
        'tipo' => $filtro_tipo,
        'usuario' => $filtro_usuario,
        'ordenar' => $ordenar_por
    ]);
    header('Location: tarefas.php?' . $params);
    exit;
}

$hoje = new DateTime('today');

// Lógica de tarefas fixas
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

    if ($prazo >= $hoje) {
        continue;
    }

    $proximoPrazo = clone $prazo;
    $proximoPrazo->modify('+1 month');

    $mes = (int) $proximoPrazo->format('n');
    $ano = (int) $proximoPrazo->format('Y');

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

    $origemId = $tarefa['tarefa_origem_id'] ?? $tarefa['id'];

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

// ═══════════════════════════════════════════════════════════════
// MONTAR QUERY COM FILTROS
// ═══════════════════════════════════════════════════════════════
$params = [];
$where_extra = "";

// Filtro de busca por título
if ($filtro_busca !== '') {
    $where_extra .= " AND t.titulo LIKE :busca";
    $params[':busca'] = '%' . $filtro_busca . '%';
}

// Filtro por tipo
if ($filtro_tipo === 'normal') {
    $where_extra .= " AND t.tipo = 'normal'";
} elseif ($filtro_tipo === 'fixa') {
    $where_extra .= " AND t.tipo = 'fixa'";
}

// Filtro por status (será aplicado depois no PHP para "atrasada")
if ($filtro_status === 'pendente') {
    $where_extra .= " AND t.status = 'pendente'";
} elseif ($filtro_status === 'concluida') {
    $where_extra .= " AND t.status = 'concluida'";
}

// Filtro por usuário específico
if ($filtro_usuario !== 'todos' && is_numeric($filtro_usuario)) {
    $where_extra .= " AND (t.usuario_id = :filtro_usuario_id OR t.atribuida_para = :filtro_usuario_id2)";
    $params[':filtro_usuario_id'] = (int)$filtro_usuario;
    $params[':filtro_usuario_id2'] = (int)$filtro_usuario;
}

// Definir ordenação SQL
$ordem_sql = "t.prazo ASC"; // padrão
switch ($ordenar_por) {
    case 'vencimento_asc':
        $ordem_sql = "t.prazo ASC";
        break;
    case 'vencimento_desc':
        $ordem_sql = "t.prazo DESC";
        break;
    case 'criacao_asc':
        $ordem_sql = "t.id ASC";
        break;
    case 'criacao_desc':
        $ordem_sql = "t.id DESC";
        break;
}

// Montar query baseada no cargo do usuário
if ($cargoUsuario === 'administrador') {
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes, 
               t.usuario_id, t.atribuida_para,
               u.nome as criador_nome,
               ua.nome as atribuido_nome
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
        WHERE t.arquivada = 0 {$where_extra}
        ORDER BY {$ordem_sql}
    ";
    $stmt = $pdo->prepare($sql);
    
} elseif ($cargoUsuario === 'supervisor') {
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
               OR t.atribuida_para = :usuario_id2
               OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = :usuario_id3)
               OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = :usuario_id4))
          {$where_extra}
        ORDER BY {$ordem_sql}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id3', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id4', $usuario_id, PDO::PARAM_INT);
    
} else {
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
               t.usuario_id, t.atribuida_para,
               u.nome as criador_nome,
               ua.nome as atribuido_nome
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
        WHERE t.arquivada = 0
          AND (t.usuario_id = :usuario_id OR t.atribuida_para = :usuario_id2)
          {$where_extra}
        ORDER BY {$ordem_sql}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
}

// Bind dos parâmetros extras (busca, filtro_usuario, etc)
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Classificar tarefas
$tarefasNormais = [];
$tarefasFixas   = [];
$contadorPendentes = 0;
$contadorConcluidas = 0;
$contadorAtrasadas = 0;
$contadorHoje = 0;

foreach ($tarefas as $tarefa) {
    $prazoTarefa = new DateTime($tarefa['prazo']);
    $statusVisual = 'futura';
    
    if ($tarefa['status'] === 'concluida') {
        $statusVisual = 'concluida';
        $contadorConcluidas++;
    } else {
        $contadorPendentes++;
        
        if ($prazoTarefa < $hoje) {
            $statusVisual = 'atrasada';
            $diasAtraso = $hoje->diff($prazoTarefa)->days;
            $tarefa['dias_atraso'] = $diasAtraso;
            $contadorAtrasadas++;
        } elseif ($prazoTarefa == $hoje) {
            $statusVisual = 'hoje';
            $contadorHoje++;
        }
    }
    
    $tarefa['status_visual'] = $statusVisual;
    
    // Filtro de atrasadas (feito aqui pois depende de cálculo de data)
    if ($filtro_status === 'atrasada' && $statusVisual !== 'atrasada') {
        continue;
    }
    
    if ($tarefa['tipo'] === 'fixa') {
        $tarefasFixas[] = $tarefa;
    } else {
        $tarefasNormais[] = $tarefa;
    }
}

// Total filtrado
$totalFiltrado = count($tarefasNormais) + count($tarefasFixas);

// Verificar se há filtros ativos
$filtrosAtivos = ($filtro_busca !== '' || $filtro_status !== 'todos' || $filtro_tipo !== 'todos' || $filtro_usuario !== 'todos');

// Função para formatar prazo de forma amigável
function formatarPrazo($prazo, $statusVisual, $diasAtraso = 0) {
    if ($statusVisual === 'atrasada') {
        return $diasAtraso . ' dia' . ($diasAtraso > 1 ? 's' : '') . ' de atraso';
    } elseif ($statusVisual === 'hoje') {
        return 'Vence hoje';
    } else {
        $hoje = new DateTime();
        $dataPrazo = new DateTime($prazo);
        $diff = $hoje->diff($dataPrazo)->days;
        if ($diff <= 7) {
            return 'em ' . $diff . ' dia' . ($diff > 1 ? 's' : '');
        }
        return date('d/m/Y', strtotime($prazo));
    }
}

// Função auxiliar para manter filtros nos links
function buildFilterUrl($params = []) {
    $current = [
        'busca' => $_GET['busca'] ?? '',
        'status' => $_GET['status'] ?? 'todos',
        'tipo' => $_GET['tipo'] ?? 'todos',
        'usuario' => $_GET['usuario'] ?? 'todos',
        'ordenar' => $_GET['ordenar'] ?? 'vencimento_asc'
    ];
    $merged = array_merge($current, $params);
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Tarefas | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="tarefas-page">
    <header>
        <div class="header-content">
            <div>
                <h1>Tarefas</h1>
                <p>Óticas Mercês • <?= htmlspecialchars($nomeUsuario) ?> 
                    <span class="badge-cargo <?= $cargoUsuario ?>">
                        <?= ucfirst($cargoUsuario) ?>
                    </span>
                </p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn-gerenciar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Dashboard
                </a>
                <?php if ($cargoUsuario === 'administrador'): ?>
                    <a href="gerenciar_usuarios.php" class="btn-gerenciar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                        Usuários
                    </a>
                <?php endif; ?>
                <a href="historico.php" class="btn-gerenciar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Histórico
                </a>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>
    </header>

    <!-- Toast de mensagem (flash) -->
    <?php if ($mensagem): ?>
        <div class="toast <?= $tipo_mensagem ?>">
            <div class="toast-icon">
                <?php if ($tipo_mensagem === 'sucesso'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                <?php endif; ?>
            </div>
            <span><?= htmlspecialchars($mensagem) ?></span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <!-- Toast de Desfazer (controlado via JS) -->
    <div id="toastDesfazer" class="toast-desfazer hidden">
        <div class="toast-desfazer-content">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
            </svg>
            <span>Tarefa movida para o histórico</span>
            <button type="button" id="btnDesfazer" class="btn-desfazer">Desfazer</button>
            <div class="toast-progress"></div>
        </div>
    </div>

    <main class="tarefas-container">
        
        <!-- Stats Cards -->
        <div class="tarefas-stats">
            <div class="mini-stat">
                <div class="mini-stat-icon total">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                    </svg>
                </div>
                <div class="mini-stat-info">
                    <span class="mini-stat-value"><?= count($tarefas) ?></span>
                    <span class="mini-stat-label">Total</span>
                </div>
            </div>
            
            <div class="mini-stat">
                <div class="mini-stat-icon pending">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="mini-stat-info">
                    <span class="mini-stat-value"><?= $contadorPendentes ?></span>
                    <span class="mini-stat-label">Pendentes</span>
                </div>
            </div>
            
            <?php if ($contadorAtrasadas > 0): ?>
            <div class="mini-stat alert">
                <div class="mini-stat-icon overdue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="mini-stat-info">
                    <span class="mini-stat-value"><?= $contadorAtrasadas ?></span>
                    <span class="mini-stat-label">Atrasadas</span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mini-stat">
                <div class="mini-stat-icon done">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="mini-stat-info">
                    <span class="mini-stat-value"><?= $contadorConcluidas ?></span>
                    <span class="mini-stat-label">Concluídas</span>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- BARRA DE FILTROS E BUSCA -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="filtros-bar">
            <!-- Busca -->
            <form method="GET" class="filtro-busca-form">
                <!-- Manter filtros atuais -->
                <input type="hidden" name="status" value="<?= htmlspecialchars($filtro_status) ?>">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
                <input type="hidden" name="usuario" value="<?= htmlspecialchars($filtro_usuario) ?>">
                <input type="hidden" name="ordenar" value="<?= htmlspecialchars($ordenar_por) ?>">
                
                <div class="busca-wrapper">
                    <svg class="busca-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input 
                        type="text" 
                        name="busca" 
                        placeholder="Buscar tarefas..." 
                        value="<?= htmlspecialchars($filtro_busca) ?>"
                        class="busca-input"
                    >
                    <?php if ($filtro_busca !== ''): ?>
                        <a href="<?= buildFilterUrl(['busca' => '']) ?>" class="busca-limpar" title="Limpar busca">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Filtros Rápidos de Status -->
            <div class="filtros-grupo">
                <span class="filtros-label">Status:</span>
                <div class="filtros-botoes">
                    <a href="<?= buildFilterUrl(['status' => 'todos']) ?>" 
                       class="filtro-btn <?= $filtro_status === 'todos' ? 'ativo' : '' ?>">
                        Todos
                    </a>
                    <a href="<?= buildFilterUrl(['status' => 'pendente']) ?>" 
                       class="filtro-btn <?= $filtro_status === 'pendente' ? 'ativo' : '' ?>">
                        <span class="filtro-dot pendente"></span>
                        Pendentes
                    </a>
                    <a href="<?= buildFilterUrl(['status' => 'atrasada']) ?>" 
                       class="filtro-btn <?= $filtro_status === 'atrasada' ? 'ativo' : '' ?>">
                        <span class="filtro-dot atrasada"></span>
                        Atrasadas
                    </a>
                    <a href="<?= buildFilterUrl(['status' => 'concluida']) ?>" 
                       class="filtro-btn <?= $filtro_status === 'concluida' ? 'ativo' : '' ?>">
                        <span class="filtro-dot concluida"></span>
                        Concluídas
                    </a>
                </div>
            </div>

            <!-- Filtro por Tipo -->
            <div class="filtros-grupo">
                <span class="filtros-label">Tipo:</span>
                <div class="filtros-botoes">
                    <a href="<?= buildFilterUrl(['tipo' => 'todos']) ?>" 
                       class="filtro-btn <?= $filtro_tipo === 'todos' ? 'ativo' : '' ?>">
                        Todos
                    </a>
                    <a href="<?= buildFilterUrl(['tipo' => 'normal']) ?>" 
                       class="filtro-btn <?= $filtro_tipo === 'normal' ? 'ativo' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                        Únicas
                    </a>
                    <a href="<?= buildFilterUrl(['tipo' => 'fixa']) ?>" 
                       class="filtro-btn <?= $filtro_tipo === 'fixa' ? 'ativo' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <polyline points="23 4 23 10 17 10"/>
                            <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                        </svg>
                        Recorrentes
                    </a>
                </div>
            </div>

            <!-- Filtro por Usuário (só para admin e supervisor) -->
            <?php if (!empty($usuariosFiltro)): ?>
            <div class="filtros-grupo">
                <span class="filtros-label">Usuário:</span>
                <select class="filtro-select" onchange="window.location.href=this.value">
                    <option value="<?= buildFilterUrl(['usuario' => 'todos']) ?>" <?= $filtro_usuario === 'todos' ? 'selected' : '' ?>>
                        Todos
                    </option>
                    <?php foreach ($usuariosFiltro as $u): ?>
                        <option value="<?= buildFilterUrl(['usuario' => $u['id']]) ?>" <?= $filtro_usuario == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nome']) ?> (<?= ucfirst($u['cargo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Ordenação -->
            <div class="filtros-grupo">
                <span class="filtros-label">Ordenar:</span>
                <select class="filtro-select" onchange="window.location.href=this.value">
                    <option value="<?= buildFilterUrl(['ordenar' => 'vencimento_asc']) ?>" <?= $ordenar_por === 'vencimento_asc' ? 'selected' : '' ?>>
                        Vencimento ↑
                    </option>
                    <option value="<?= buildFilterUrl(['ordenar' => 'vencimento_desc']) ?>" <?= $ordenar_por === 'vencimento_desc' ? 'selected' : '' ?>>
                        Vencimento ↓
                    </option>
                    <option value="<?= buildFilterUrl(['ordenar' => 'criacao_desc']) ?>" <?= $ordenar_por === 'criacao_desc' ? 'selected' : '' ?>>
                        Mais recentes
                    </option>
                    <option value="<?= buildFilterUrl(['ordenar' => 'criacao_asc']) ?>" <?= $ordenar_por === 'criacao_asc' ? 'selected' : '' ?>>
                        Mais antigas
                    </option>
                </select>
            </div>

            <!-- Limpar Filtros -->
            <?php if ($filtrosAtivos): ?>
                <a href="tarefas.php" class="btn-limpar-filtros">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18"/>
                        <path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                    </svg>
                    Limpar filtros
                </a>
            <?php endif; ?>
        </div>

        <!-- Indicador de Resultados -->
        <?php if ($filtrosAtivos): ?>
            <div class="filtros-resultado">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                </svg>
                <span>
                    Mostrando <strong><?= $totalFiltrado ?></strong> tarefa<?= $totalFiltrado !== 1 ? 's' : '' ?>
                    <?php if ($filtro_busca !== ''): ?>
                        para "<strong><?= htmlspecialchars($filtro_busca) ?></strong>"
                    <?php endif; ?>
                    <?php if ($filtro_usuario !== 'todos'): ?>
                        <?php 
                        $nomeUsuarioFiltro = '';
                        foreach ($usuariosFiltro as $u) {
                            if ($u['id'] == $filtro_usuario) {
                                $nomeUsuarioFiltro = $u['nome'];
                                break;
                            }
                        }
                        ?>
                        de <strong><?= htmlspecialchars($nomeUsuarioFiltro) ?></strong>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Main Grid -->
        <div class="tarefas-grid">
            
            <!-- Sidebar: Nova Tarefa -->
            <aside class="tarefas-sidebar">
                <div class="new-task-card">
                    <div class="new-task-header">
                        <div class="new-task-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </div>
                        <h2>Nova Tarefa</h2>
                    </div>

                    <form method="POST" class="new-task-form">
                        <div class="form-group">
                            <label for="titulo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="17" y1="10" x2="3" y2="10"/>
                                    <line x1="21" y1="6" x2="3" y2="6"/>
                                    <line x1="21" y1="14" x2="3" y2="14"/>
                                    <line x1="17" y1="18" x2="3" y2="18"/>
                                </svg>
                                Título
                            </label>
                            <input type="text" id="titulo" name="titulo" placeholder="O que precisa ser feito?" required>
                        </div>

                        <div class="form-group">
                            <label for="prazo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                Prazo
                            </label>
                            
                            <!-- Botões de Data Rápida -->
                            <div class="date-shortcuts">
                                <button type="button" class="date-shortcut-btn" data-days="0">Hoje</button>
                                <button type="button" class="date-shortcut-btn" data-days="1">Amanhã</button>
                                <button type="button" class="date-shortcut-btn" data-days="7">+7 dias</button>
                            </div>
                            
                            <input type="date" id="prazo" name="prazo" required min="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label for="tipo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                                    <polyline points="2 17 12 22 22 17"/>
                                    <polyline points="2 12 12 17 22 12"/>
                                </svg>
                                Tipo
                            </label>
                            <select id="tipo" name="tipo">
                                <option value="normal">Única</option>
                                <option value="fixa">Recorrente</option>
                            </select>
                        </div>

                        <?php if (!empty($usuariosDisponiveis)): ?>
                            <div class="form-group">
                                <label for="atribuida_para">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    Atribuir para
                                </label>
                                <select id="atribuida_para" name="atribuida_para">
                                    <option value="">Eu mesmo</option>
                                    <?php foreach ($usuariosDisponiveis as $usuario): ?>
                                        <option value="<?= $usuario['id'] ?>">
                                            <?= htmlspecialchars($usuario['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-create-task">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Criar Tarefa
                        </button>
                    </form>
                </div>

                <!-- Quick Tips (Dicas Rotativas) -->
                <div class="tips-card">
                    <h3><?= $dicaAtual['icone'] ?> <?= $dicaAtual['titulo'] ?></h3>
                    <p><?= $dicaAtual['texto'] ?></p>
                </div>
            </aside>

            <!-- Main Content: Lista de Tarefas -->
            <div class="tarefas-main">
                
                <?php if ($totalFiltrado === 0 && $filtrosAtivos): ?>
                    <!-- Nenhum resultado com filtros -->
                    <div class="empty-filtros">
                        <div class="empty-filtros-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="M21 21l-4.35-4.35"/>
                                <line x1="8" y1="11" x2="14" y2="11"/>
                            </svg>
                        </div>
                        <h3>Nenhuma tarefa encontrada</h3>
                        <p>Não encontramos tarefas com os filtros selecionados.</p>
                        <a href="tarefas.php" class="btn-voltar-lista">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="19" y1="12" x2="5" y2="12"/>
                                <polyline points="12 19 5 12 12 5"/>
                            </svg>
                            Ver todas as tarefas
                        </a>
                    </div>
                <?php else: ?>
                
                    <!-- Tarefas Normais (só mostra se não filtrou por tipo "fixa") -->
                    <?php if ($filtro_tipo !== 'fixa'): ?>
                    <section class="tasks-section">
                        <div class="section-header">
                            <h2>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                                    <line x1="9" y1="12" x2="15" y2="12"/>
                                    <line x1="9" y1="16" x2="15" y2="16"/>
                                </svg>
                                Tarefas
                            </h2>
                            <span class="section-count"><?= count($tarefasNormais) ?></span>
                        </div>
                        
                        <?php if (count($tarefasNormais) === 0): ?>
                            <div class="empty-tasks">
                                <div class="empty-illustration">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                                        <path d="M9 14l2 2 4-4"/>
                                    </svg>
                                </div>
                                <p>Nenhuma tarefa encontrada</p>
                                <span>Crie sua primeira tarefa ao lado!</span>
                            </div>
                        <?php else: ?>
                            <div class="tasks-list">
                                <?php foreach ($tarefasNormais as $tarefa): ?>
                                    <div class="task-item <?= $tarefa['status_visual'] ?>" data-task-id="<?= $tarefa['id'] ?>">
                                        <!-- Checkbox -->
                                        <a href="?acao=toggle&id=<?= $tarefa['id'] ?>&busca=<?= urlencode($filtro_busca) ?>&status=<?= $filtro_status ?>&tipo=<?= $filtro_tipo ?>&usuario=<?= $filtro_usuario ?>&ordenar=<?= $ordenar_por ?>" class="task-checkbox" title="<?= $tarefa['status'] === 'pendente' ? 'Marcar como concluída' : 'Reabrir tarefa' ?>">
                                            <?php if ($tarefa['status'] === 'concluida'): ?>
                                                <svg viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                </svg>
                                            <?php endif; ?>
                                        </a>
                                        
                                        <!-- Content -->
                                        <div class="task-content">
                                            <div class="task-header">
                                                <h3 class="task-title"><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                                <?php if ($tarefa['status_visual'] === 'atrasada'): ?>
                                                    <span class="task-badge overdue">
                                                        <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12">
                                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                                        </svg>
                                                        <?= $tarefa['dias_atraso'] ?>d
                                                    </span>
                                                <?php elseif ($tarefa['status_visual'] === 'hoje'): ?>
                                                    <span class="task-badge today">Hoje</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="task-meta">
                                                <span class="task-date <?= $tarefa['status_visual'] ?>">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                                    </svg>
                                                    <?= formatarPrazo($tarefa['prazo'], $tarefa['status_visual'], $tarefa['dias_atraso'] ?? 0) ?>
                                                </span>
                                                
                                                <?php if ($cargoUsuario !== 'funcionario'): ?>
                                                    <!-- Criador da tarefa -->
                                                    <span class="task-creator">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                                            <circle cx="12" cy="7" r="4"/>
                                                        </svg>
                                                        <?= htmlspecialchars($tarefa['criador_nome'] ?? 'Desconhecido') ?>
                                                    </span>
                                                    
                                                    <!-- Atribuído para (se diferente do criador) -->
                                                    <?php if ($tarefa['atribuida_para'] && $tarefa['atribuida_para'] != $tarefa['usuario_id']): ?>
                                                        <span class="task-assignee">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M22 2L11 13"/>
                                                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                                            </svg>
                                                            → <?= htmlspecialchars($tarefa['atribuido_nome']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($tarefa['observacoes'])): ?>
                                                <div class="task-notes">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                                        <polyline points="14 2 14 8 20 8"/>
                                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                                    </svg>
                                                    <span><?= htmlspecialchars(substr($tarefa['observacoes'], 0, 60)) ?><?= strlen($tarefa['observacoes']) > 60 ? '...' : '' ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="task-actions">
                                            <button 
                                                type="button"
                                                class="action-btn btn-observacoes"
                                                data-id="<?= $tarefa['id'] ?>"
                                                data-observacoes="<?= htmlspecialchars($tarefa['observacoes'] ?? '', ENT_QUOTES) ?>"
                                                title="Observações"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                            <button 
                                                type="button" 
                                                class="action-btn btn-delete btn-arquivar" 
                                                data-id="<?= $tarefa['id'] ?>"
                                                data-titulo="<?= htmlspecialchars($tarefa['titulo'], ENT_QUOTES) ?>"
                                                title="Arquivar"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <!-- Tarefas Fixas (só mostra se não filtrou por tipo "normal") -->
                    <?php if ($filtro_tipo !== 'normal'): ?>
                    <section class="tasks-section recurring">
                        <div class="section-header">
                            <h2>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 4 23 10 17 10"/>
                                    <polyline points="1 20 1 14 7 14"/>
                                    <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                                </svg>
                                Tarefas Recorrentes
                            </h2>
                            <span class="section-count"><?= count($tarefasFixas) ?></span>
                        </div>
                        
                        <?php if (count($tarefasFixas) === 0): ?>
                            <div class="empty-tasks compact">
                                <p>Nenhuma tarefa recorrente encontrada</p>
                            </div>
                        <?php else: ?>
                            <div class="tasks-list">
                                <?php foreach ($tarefasFixas as $tarefa): ?>
                                    <div class="task-item <?= $tarefa['status_visual'] ?>" data-task-id="<?= $tarefa['id'] ?>">
                                        <!-- Checkbox -->
                                        <a href="?acao=toggle&id=<?= $tarefa['id'] ?>&busca=<?= urlencode($filtro_busca) ?>&status=<?= $filtro_status ?>&tipo=<?= $filtro_tipo ?>&usuario=<?= $filtro_usuario ?>&ordenar=<?= $ordenar_por ?>" class="task-checkbox" title="<?= $tarefa['status'] === 'pendente' ? 'Marcar como concluída' : 'Reabrir tarefa' ?>">
                                            <?php if ($tarefa['status'] === 'concluida'): ?>
                                                <svg viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                </svg>
                                            <?php endif; ?>
                                        </a>
                                        
                                        <!-- Content -->
                                        <div class="task-content">
                                            <div class="task-header">
                                                <h3 class="task-title">
                                                    <?= htmlspecialchars($tarefa['titulo']) ?>
                                                    <span class="recurring-badge">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                            <polyline points="23 4 23 10 17 10"/>
                                                            <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                                                        </svg>
                                                    </span>
                                                </h3>
                                                <?php if ($tarefa['status_visual'] === 'atrasada'): ?>
                                                    <span class="task-badge overdue">
                                                        <?= $tarefa['dias_atraso'] ?>d
                                                    </span>
                                                <?php elseif ($tarefa['status_visual'] === 'hoje'): ?>
                                                    <span class="task-badge today">Hoje</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="task-meta">
                                                <span class="task-date <?= $tarefa['status_visual'] ?>">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                                    </svg>
                                                    <?= formatarPrazo($tarefa['prazo'], $tarefa['status_visual'], $tarefa['dias_atraso'] ?? 0) ?>
                                                </span>
                                                
                                                <?php if ($cargoUsuario !== 'funcionario'): ?>
                                                    <span class="task-creator">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                                            <circle cx="12" cy="7" r="4"/>
                                                        </svg>
                                                        <?= htmlspecialchars($tarefa['criador_nome'] ?? 'Desconhecido') ?>
                                                    </span>
                                                    
                                                    <?php if ($tarefa['atribuida_para'] && $tarefa['atribuida_para'] != $tarefa['usuario_id']): ?>
                                                        <span class="task-assignee">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M22 2L11 13"/>
                                                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                                            </svg>
                                                            → <?= htmlspecialchars($tarefa['atribuido_nome']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="task-actions">
                                            <button 
                                                type="button"
                                                class="action-btn btn-observacoes"
                                                data-id="<?= $tarefa['id'] ?>"
                                                data-observacoes="<?= htmlspecialchars($tarefa['observacoes'] ?? '', ENT_QUOTES) ?>"
                                                title="Observações"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                            <button 
                                                type="button" 
                                                class="action-btn btn-delete btn-arquivar" 
                                                data-id="<?= $tarefa['id'] ?>"
                                                data-titulo="<?= htmlspecialchars($tarefa['titulo'], ENT_QUOTES) ?>"
                                                title="Arquivar"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                    
                <?php endif; ?>

            </div>
        </div>

    </main>

    <!-- Modal de Observações -->
    <div id="modalObservacoes" class="modal hidden">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    Observações
                </h3>
                <button type="button" id="fecharModal" class="modal-close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="salvar_observacoes.php">
                <input type="hidden" name="tarefa_id" id="modalTarefaId">

                <textarea 
                    name="observacoes" 
                    id="modalObservacoesTexto"
                    rows="6"
                    placeholder="Adicione suas anotações aqui..."
                ></textarea>

                <div class="modal-actions">
                    <button type="button" id="cancelarModal" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-save">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>