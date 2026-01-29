<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações do usuário logado
$sqlUsuario = "SELECT nome, cargo FROM usuarios WHERE id = ?";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->execute([$usuario_id]);
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

$nome_usuario = $usuario['nome'];
$cargo = $usuario['cargo'];

$dataHoje = date('Y-m-d');

// Construir queries baseadas no cargo
if ($cargo === 'administrador') {
    $sqlTotal = "SELECT COUNT(*) as total FROM tarefas WHERE arquivada = 0";
    $sqlPendentes = "SELECT COUNT(*) as total FROM tarefas WHERE arquivada = 0 AND status = 'pendente'";
    $sqlConcluidas = "SELECT COUNT(*) as total FROM tarefas WHERE arquivada = 0 AND status = 'concluida'";
    $sqlAtrasadas = "SELECT COUNT(*) as total FROM tarefas WHERE arquivada = 0 AND status = 'pendente' AND prazo < ?";
    $sqlUrgentes = "SELECT id, titulo, prazo, status,
                        CASE 
                            WHEN prazo < ? THEN 'atrasada'
                            WHEN prazo = ? THEN 'hoje'
                            ELSE 'proxima'
                        END as urgencia
                    FROM tarefas 
                    WHERE arquivada = 0 AND status = 'pendente' AND prazo <= ?
                    ORDER BY prazo ASC LIMIT 5";
    
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute();
    
    $stmtPendentes = $pdo->prepare($sqlPendentes);
    $stmtPendentes->execute();
    
    $stmtConcluidas = $pdo->prepare($sqlConcluidas);
    $stmtConcluidas->execute();
    
    $stmtAtrasadas = $pdo->prepare($sqlAtrasadas);
    $stmtAtrasadas->execute([$dataHoje]);
    
    $dataLimite = date('Y-m-d', strtotime('+7 days'));
    $stmtUrgentes = $pdo->prepare($sqlUrgentes);
    $stmtUrgentes->execute([$dataHoje, $dataHoje, $dataLimite]);

} elseif ($cargo === 'supervisor') {
    $sqlTotal = "SELECT COUNT(*) as total FROM tarefas t
                 WHERE t.arquivada = 0 
                   AND (t.usuario_id = ? OR t.atribuida_para = ? 
                        OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = ?)
                        OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = ?))";
    
    $sqlPendentes = "SELECT COUNT(*) as total FROM tarefas t
                     WHERE t.arquivada = 0 AND t.status = 'pendente'
                       AND (t.usuario_id = ? OR t.atribuida_para = ?
                            OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = ?)
                            OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = ?))";
    
    $sqlConcluidas = "SELECT COUNT(*) as total FROM tarefas t
                      WHERE t.arquivada = 0 AND t.status = 'concluida'
                        AND (t.usuario_id = ? OR t.atribuida_para = ?
                             OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = ?)
                             OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = ?))";
    
    $sqlAtrasadas = "SELECT COUNT(*) as total FROM tarefas t
                     WHERE t.arquivada = 0 AND t.status = 'pendente' AND t.prazo < ?
                       AND (t.usuario_id = ? OR t.atribuida_para = ?
                            OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = ?)
                            OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = ?))";
    
    $sqlUrgentes = "SELECT t.id, t.titulo, t.prazo, t.status,
                        CASE 
                            WHEN t.prazo < ? THEN 'atrasada'
                            WHEN t.prazo = ? THEN 'hoje'
                            ELSE 'proxima'
                        END as urgencia
                    FROM tarefas t
                    WHERE t.arquivada = 0 AND t.status = 'pendente' AND t.prazo <= ?
                      AND (t.usuario_id = ? OR t.atribuida_para = ?
                           OR t.usuario_id IN (SELECT id FROM usuarios WHERE supervisor_id = ?)
                           OR t.atribuida_para IN (SELECT id FROM usuarios WHERE supervisor_id = ?))
                    ORDER BY t.prazo ASC LIMIT 5";
    
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id]);
    
    $stmtPendentes = $pdo->prepare($sqlPendentes);
    $stmtPendentes->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id]);
    
    $stmtConcluidas = $pdo->prepare($sqlConcluidas);
    $stmtConcluidas->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id]);
    
    $stmtAtrasadas = $pdo->prepare($sqlAtrasadas);
    $stmtAtrasadas->execute([$dataHoje, $usuario_id, $usuario_id, $usuario_id, $usuario_id]);
    
    $dataLimite = date('Y-m-d', strtotime('+7 days'));
    $stmtUrgentes = $pdo->prepare($sqlUrgentes);
    $stmtUrgentes->execute([$dataHoje, $dataHoje, $dataLimite, $usuario_id, $usuario_id, $usuario_id, $usuario_id]);

} else {
    $sqlTotal = "SELECT COUNT(*) as total FROM tarefas 
                 WHERE arquivada = 0 AND (usuario_id = ? OR atribuida_para = ?)";
    
    $sqlPendentes = "SELECT COUNT(*) as total FROM tarefas 
                     WHERE arquivada = 0 AND status = 'pendente' AND (usuario_id = ? OR atribuida_para = ?)";
    
    $sqlConcluidas = "SELECT COUNT(*) as total FROM tarefas 
                      WHERE arquivada = 0 AND status = 'concluida' AND (usuario_id = ? OR atribuida_para = ?)";
    
    $sqlAtrasadas = "SELECT COUNT(*) as total FROM tarefas 
                     WHERE arquivada = 0 AND status = 'pendente' AND prazo < ? AND (usuario_id = ? OR atribuida_para = ?)";
    
    $sqlUrgentes = "SELECT id, titulo, prazo, status,
                        CASE 
                            WHEN prazo < ? THEN 'atrasada'
                            WHEN prazo = ? THEN 'hoje'
                            ELSE 'proxima'
                        END as urgencia
                    FROM tarefas 
                    WHERE arquivada = 0 AND status = 'pendente' AND prazo <= ? AND (usuario_id = ? OR atribuida_para = ?)
                    ORDER BY prazo ASC LIMIT 5";
    
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute([$usuario_id, $usuario_id]);
    
    $stmtPendentes = $pdo->prepare($sqlPendentes);
    $stmtPendentes->execute([$usuario_id, $usuario_id]);
    
    $stmtConcluidas = $pdo->prepare($sqlConcluidas);
    $stmtConcluidas->execute([$usuario_id, $usuario_id]);
    
    $stmtAtrasadas = $pdo->prepare($sqlAtrasadas);
    $stmtAtrasadas->execute([$dataHoje, $usuario_id, $usuario_id]);
    
    $dataLimite = date('Y-m-d', strtotime('+7 days'));
    $stmtUrgentes = $pdo->prepare($sqlUrgentes);
    $stmtUrgentes->execute([$dataHoje, $dataHoje, $dataLimite, $usuario_id, $usuario_id]);
}

// Buscar resultados
$total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
$pendentes = $stmtPendentes->fetch(PDO::FETCH_ASSOC)['total'];
$concluidas = $stmtConcluidas->fetch(PDO::FETCH_ASSOC)['total'];
$atrasadas = $stmtAtrasadas->fetch(PDO::FETCH_ASSOC)['total'];
$tarefasUrgentes = $stmtUrgentes->fetchAll(PDO::FETCH_ASSOC);

// Calcular percentual de conclusão
$percentualConclusao = $total > 0 ? round(($concluidas / $total) * 100) : 0;

// Função para formatar data
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Função para calcular dias
function calcularDias($prazo) {
    $hoje = new DateTime();
    $dataPrazo = new DateTime($prazo);
    $diferenca = $hoje->diff($dataPrazo);
    $dias = (int)$diferenca->format('%r%a');
    
    if ($dias < 0) {
        return abs($dias) . " dia(s) de atraso";
    } elseif ($dias == 0) {
        return "Vence hoje";
    } else {
        return "em " . $dias . " dia(s)";
    }
}

// Data formatada em português
$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$dataFormatada = $diasSemana[date('w')] . ', ' . date('d') . ' de ' . $meses[date('n')] . ' de ' . date('Y');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
    <header>
        <div class="header-content">
            <div>
                <h1>Dashboard</h1>
                <p>Óticas Mercês • <?= htmlspecialchars($nome_usuario) ?>
                    <span class="badge-cargo <?= $cargo ?>">
                        <?= ucfirst($cargo) ?>
                    </span>
                </p>
            </div>
            <div class="header-actions">
                <a href="tarefas.php" class="btn-gerenciar">📝 Tarefas</a>
                <?php if ($cargo === 'administrador'): ?>
                    <a href="gerenciar_usuarios.php" class="btn-gerenciar">👥 Usuários</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>
    </header>

    <main class="dashboard-container">

        <!-- Boas-vindas com data -->
        <section class="welcome-hero">
            <div class="welcome-text">
                <span class="welcome-date"><?= $dataFormatada ?></span>
                <h1>Olá, <?= htmlspecialchars(explode(' ', $nome_usuario)[0]) ?>!</h1>
                <p>Veja o resumo das suas atividades</p>
            </div>
            <div class="welcome-illustration">
                <div class="quick-stat">
                    <span class="quick-number"><?= $pendentes ?></span>
                    <span class="quick-label">tarefas aguardando</span>
                </div>
            </div>
        </section>

        <!-- Grid principal -->
        <div class="dashboard-main-grid">
            
            <!-- Coluna esquerda: Métricas + Progresso -->
            <div class="dashboard-left">
                
                <!-- Métricas em grid 2x2 -->
                <div class="stats-grid">
                    <div class="stat-card stat-total">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $total ?></span>
                            <span class="stat-label">Total</span>
                        </div>
                    </div>

                    <div class="stat-card stat-pending">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $pendentes ?></span>
                            <span class="stat-label">Pendentes</span>
                        </div>
                    </div>

                    <div class="stat-card stat-overdue">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $atrasadas ?></span>
                            <span class="stat-label">Atrasadas</span>
                        </div>
                    </div>

                    <div class="stat-card stat-done">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $concluidas ?></span>
                            <span class="stat-label">Concluídas</span>
                        </div>
                    </div>
                </div>

                <!-- Progresso circular -->
                <div class="progress-card">
                    <div class="progress-circle-container">
                        <div class="progress-circle" style="--progress: <?= $percentualConclusao ?>">
                            <div class="progress-circle-inner">
                                <span class="progress-percent"><?= $percentualConclusao ?>%</span>
                                <span class="progress-text">concluído</span>
                            </div>
                        </div>
                    </div>
                    <div class="progress-legend">
                        <h3>Progresso Geral</h3>
                        <div class="legend-items">
                            <div class="legend-item">
                                <span class="legend-dot done"></span>
                                <span>Concluídas: <?= $concluidas ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot pending"></span>
                                <span>Pendentes: <?= $pendentes ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot overdue"></span>
                                <span>Atrasadas: <?= $atrasadas ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Coluna direita: Urgentes + Atalhos -->
            <div class="dashboard-right">
                
                <!-- Tarefas urgentes -->
                <div class="urgent-card">
                    <div class="card-header">
                        <h2>
                            <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Próximas Tarefas
                        </h2>
                        <a href="tarefas.php" class="see-all">Ver todas →</a>
                    </div>
                    
                    <?php if (count($tarefasUrgentes) > 0): ?>
                        <div class="urgent-timeline">
                            <?php foreach ($tarefasUrgentes as $index => $tarefa): ?>
                                <div class="timeline-item <?= $tarefa['urgencia'] ?>">
                                    <div class="timeline-marker">
                                        <?php if ($tarefa['urgencia'] === 'atrasada'): ?>
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                        <?php elseif ($tarefa['urgencia'] === 'hoje'): ?>
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="8"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <h4><?= htmlspecialchars($tarefa['titulo']) ?></h4>
                                        <div class="timeline-meta">
                                            <span class="timeline-date"><?= formatarData($tarefa['prazo']) ?></span>
                                            <span class="timeline-status <?= $tarefa['urgencia'] ?>"><?= calcularDias($tarefa['prazo']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-urgent">
                            <div class="empty-icon">🎉</div>
                            <p>Nenhuma tarefa urgente!</p>
                            <span>Você está em dia com tudo.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Atalhos rápidos -->
                <div class="shortcuts-card">
                    <h2>Acesso Rápido</h2>
                    <div class="shortcuts-list">
                        <a href="tarefas.php" class="shortcut-item">
                            <div class="shortcut-icon blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </div>
                            <div class="shortcut-text">
                                <span class="shortcut-title">Tarefas</span>
                                <span class="shortcut-desc">Gerenciar atividades</span>
                            </div>
                            <svg class="shortcut-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                        
                        <a href="historico.php" class="shortcut-item">
                            <div class="shortcut-icon purple">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div class="shortcut-text">
                                <span class="shortcut-title">Histórico</span>
                                <span class="shortcut-desc">Tarefas arquivadas</span>
                            </div>
                            <svg class="shortcut-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                        
                        <?php if ($cargo === 'administrador'): ?>
                        <a href="gerenciar_usuarios.php" class="shortcut-item">
                            <div class="shortcut-icon green">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                                </svg>
                            </div>
                            <div class="shortcut-text">
                                <span class="shortcut-title">Usuários</span>
                                <span class="shortcut-desc">Gerenciar equipe</span>
                            </div>
                            <svg class="shortcut-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                        <?php endif; ?>
                        
                        <a href="perfil.php" class="shortcut-item">
                            <div class="shortcut-icon orange">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="shortcut-text">
                                <span class="shortcut-title">Meu Perfil</span>
                                <span class="shortcut-desc">Configurações pessoais</span>
                            </div>
                            <svg class="shortcut-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </main>
</body>
</html>