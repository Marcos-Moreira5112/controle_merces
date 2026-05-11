<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';
require_once __DIR__ . '/includes/auth_helpers.php';

$usuarioId = (int) $_SESSION['usuario_id'];

function fetchAllAssoc(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchOneAssoc(PDO $pdo, string $sql, array $params = []): array|false
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buildTaskScope(string $alias, string $cargo, array $visibleUserIds, array $scopeUserIds, ?string $dataInicio, ?string $dataFim): array
{
    $clauses = ["{$alias}.arquivada = 0"];
    $params = [];

    if ($cargo !== 'administrador') {
        if (empty($visibleUserIds)) {
            $clauses[] = '1 = 0';
        } else {
            $placeholders = implode(', ', array_fill(0, count($visibleUserIds), '?'));
            $clauses[] = "({$alias}.usuario_id IN ($placeholders) OR {$alias}.atribuida_para IN ($placeholders))";
            $params = array_merge($params, $visibleUserIds, $visibleUserIds);
        }
    }

    if (!empty($scopeUserIds)) {
        $scopeUserIds = array_values(array_unique(array_map('intval', $scopeUserIds)));
        $placeholders = implode(', ', array_fill(0, count($scopeUserIds), '?'));
        $clauses[] = "({$alias}.usuario_id IN ($placeholders) OR {$alias}.atribuida_para IN ($placeholders))";
        $params = array_merge($params, $scopeUserIds, $scopeUserIds);
    }

    if ($dataInicio !== null) {
        $clauses[] = "{$alias}.prazo >= ?";
        $params[] = $dataInicio;
    }

    if ($dataFim !== null) {
        $clauses[] = "{$alias}.prazo <= ?";
        $params[] = $dataFim;
    }

    return [implode(' AND ', $clauses), $params];
}

function formatarData(string $data): string
{
    return date('d/m/Y', strtotime($data));
}

function calcularDias(string $prazo): string
{
    $hoje = new DateTime('today');
    $dataPrazo = new DateTime($prazo);
    $diferenca = $hoje->diff($dataPrazo);
    $dias = (int) $diferenca->format('%r%a');

    if ($dias < 0) {
        return abs($dias) . ' dia(s) de atraso';
    }

    if ($dias === 0) {
        return 'Vence hoje';
    }

    return 'em ' . $dias . ' dia(s)';
}

function montarQueryString(array $params): string
{
    return http_build_query(array_filter($params, static fn ($value) => $value !== null && $value !== ''));
}

$usuario = buscarUsuarioAutenticado($pdo, $usuarioId);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$nomeUsuario = $usuario['nome'];
$cargoUsuario = $usuario['cargo'];
$titularId = obterTitularIdUsuario($usuario);
$nomeEquipe = obterNomeEquipe($usuario);
$hoje = date('Y-m-d');

$periodosPermitidos = ['hoje', 'semana', 'mes', 'tudo', 'personalizado'];
$periodo = $_GET['periodo'] ?? 'semana';
$periodo = in_array($periodo, $periodosPermitidos, true) ? $periodo : 'semana';

$dataInicio = null;
$dataFim = null;

switch ($periodo) {
    case 'hoje':
        $dataInicio = $hoje;
        $dataFim = $hoje;
        break;
    case 'semana':
        $dataInicio = date('Y-m-d', strtotime('monday this week'));
        $dataFim = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $dataInicio = date('Y-m-01');
        $dataFim = date('Y-m-t');
        break;
    case 'personalizado':
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';
        $dataInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) ? $dataInicio : null;
        $dataFim = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim) ? $dataFim : null;

        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        break;
    case 'tudo':
    default:
        $dataInicio = null;
        $dataFim = null;
        break;
}

$usuariosFiltro = [];
$equipesFiltro = [];
$visibleUserIds = buscarIdsUsuariosVisiveis($pdo, $usuario, true);
$todosUsuariosEquipe = buscarUsuariosDaEquipe($pdo, $titularId, true);
$subEquipesDashboard = buscarSubEquipesDaConta($pdo, $titularId, true);

if (usuarioEhTitular($usuario)) {
    $usuariosFiltro = $todosUsuariosEquipe;
    $equipesFiltro[] = [
        'id' => 'team:all',
        'nome' => 'Toda a equipe',
        'usuarios' => $visibleUserIds,
    ];

    foreach ($subEquipesDashboard as $subEquipe) {
        $idsSubEquipe = array_values(array_unique(array_filter(array_map(
            static function (array $item) use ($subEquipe): int {
                if ((int) ($item['sub_equipe_id'] ?? 0) === (int) $subEquipe['id']) {
                    return (int) $item['id'];
                }

                if ((int) ($subEquipe['lider_id'] ?? 0) === (int) $item['id']) {
                    return (int) $item['id'];
                }

                return 0;
            },
            $todosUsuariosEquipe
        ))));

        if (!empty($idsSubEquipe)) {
            $equipesFiltro[] = [
                'id' => 'team:' . (int) $subEquipe['id'],
                'nome' => $subEquipe['nome'],
                'usuarios' => $idsSubEquipe,
            ];
        }
    }

    $idsEquipePrincipal = array_values(array_map(
        static fn (array $item): int => (int) $item['id'],
        array_filter($todosUsuariosEquipe, static fn (array $item): bool => empty($item['sub_equipe_id']))
    ));

    if (!empty($idsEquipePrincipal)) {
        $equipesFiltro[] = [
            'id' => 'team:main',
            'nome' => 'Equipe principal',
            'usuarios' => $idsEquipePrincipal,
        ];
    }
} else {
    $usuariosFiltro = array_values(array_filter(
        $todosUsuariosEquipe,
        static fn (array $item): bool => in_array((int) $item['id'], $visibleUserIds, true)
    ));

    foreach (buscarSubEquipeIdsLideradas($pdo, $usuarioId, $titularId) as $subEquipeId) {
        $membrosSubEquipe = array_values(array_filter(
            $todosUsuariosEquipe,
            static fn (array $item): bool => (int) ($item['sub_equipe_id'] ?? 0) === $subEquipeId
        ));

        if (!empty($membrosSubEquipe)) {
            $nomeSubEquipe = $membrosSubEquipe[0]['sub_equipe_nome'] ?? 'Minha sub-equipe';
            $equipesFiltro[] = [
                'id' => 'team:' . $subEquipeId,
                'nome' => $nomeSubEquipe,
                'usuarios' => array_map(static fn (array $item): int => (int) $item['id'], $membrosSubEquipe),
            ];
        }
    }
}

$filtroSelecionado = $_GET['responsavel'] ?? 'todos';
if ($filtroSelecionado === 'team:me') {
    $filtroSelecionado = 'team:all';
}
$scopeUserIds = $visibleUserIds;
$scopeResumoIds = $visibleUserIds;
$scopeTipo = 'todos';
$scopeUsuarioId = null;
$escopoLabel = count($visibleUserIds) > 1 ? 'Todos os membros visíveis' : $nomeUsuario;

if (!usuarioEhTitular($usuario) && count($visibleUserIds) === 1) {
    $filtroSelecionado = 'user:' . $usuarioId;
    $scopeUserIds = [$usuarioId];
    $scopeResumoIds = [$usuarioId];
    $scopeTipo = 'user';
    $scopeUsuarioId = $usuarioId;
    $escopoLabel = $nomeUsuario;
} elseif (str_starts_with($filtroSelecionado, 'user:')) {
    $usuarioFiltroId = (int) substr($filtroSelecionado, 5);

    foreach ($usuariosFiltro as $usuarioFiltro) {
        if ((int) $usuarioFiltro['id'] === $usuarioFiltroId) {
            $scopeUserIds = [$usuarioFiltroId];
            $scopeResumoIds = [$usuarioFiltroId];
            $scopeTipo = 'user';
            $scopeUsuarioId = $usuarioFiltroId;
            $escopoLabel = $usuarioFiltro['nome'];
            break;
        }
    }

    if ($scopeTipo !== 'user') {
        $filtroSelecionado = 'todos';
    }
} elseif (str_starts_with($filtroSelecionado, 'team:')) {
    foreach ($equipesFiltro as $equipeFiltro) {
        if ($equipeFiltro['id'] === $filtroSelecionado) {
            $scopeUserIds = $equipeFiltro['usuarios'];
            $scopeResumoIds = $equipeFiltro['usuarios'];
            $scopeTipo = 'team';
            $escopoLabel = $equipeFiltro['nome'];
            break;
        }
    }

    if ($scopeTipo !== 'team') {
        $filtroSelecionado = 'todos';
    }
}

[$whereBase, $paramsBase] = buildTaskScope('t', $cargoUsuario, $visibleUserIds, $scopeUserIds, $dataInicio, $dataFim);

$totais = fetchOneAssoc(
    $pdo,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN t.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
        SUM(CASE WHEN t.status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
        SUM(CASE WHEN t.status = 'pendente' AND t.prazo < ? THEN 1 ELSE 0 END) AS atrasadas
     FROM tarefas t
     WHERE $whereBase",
    array_merge([$hoje], $paramsBase)
);

$total = (int) ($totais['total'] ?? 0);
$pendentes = (int) ($totais['pendentes'] ?? 0);
$concluidas = (int) ($totais['concluidas'] ?? 0);
$atrasadas = (int) ($totais['atrasadas'] ?? 0);
$percentualConclusao = $total > 0 ? (int) round(($concluidas / $total) * 100) : 0;
$percentualPendencia = $total > 0 ? (int) round(($pendentes / $total) * 100) : 0;
$percentualAtraso = $total > 0 ? (int) round(($atrasadas / $total) * 100) : 0;

$tarefasUrgentes = fetchAllAssoc(
    $pdo,
    "SELECT
        t.id,
        t.titulo,
        t.prazo,
        t.status,
        COALESCE(a.nome, c.nome) AS responsavel_nome,
        CASE
            WHEN t.prazo < ? THEN 'atrasada'
            WHEN t.prazo = ? THEN 'hoje'
            ELSE 'proxima'
        END AS urgencia
     FROM tarefas t
     LEFT JOIN usuarios c ON c.id = t.usuario_id
     LEFT JOIN usuarios a ON a.id = t.atribuida_para
     WHERE $whereBase
       AND t.status = 'pendente'
     ORDER BY
        CASE
            WHEN t.prazo < ? THEN 0
            WHEN t.prazo = ? THEN 1
            ELSE 2
        END,
        t.prazo ASC,
        t.titulo ASC
     LIMIT 6",
    array_merge([$hoje, $hoje], $paramsBase, [$hoje, $hoje])
);

$idsPessoasResumo = $scopeTipo === 'user' && $scopeUsuarioId !== null ? [$scopeUsuarioId] : $scopeResumoIds;
$placeholdersPessoas = '';
$paramsPessoas = [];

if (!empty($idsPessoasResumo)) {
    $placeholdersPessoas = implode(', ', array_fill(0, count($idsPessoasResumo), '?'));
    $paramsPessoas = $idsPessoasResumo;
}

$resumoPessoas = [];
if ($placeholdersPessoas !== '') {
    [$whereEquipe, $paramsEquipe] = buildTaskScope('t', $cargoUsuario, $visibleUserIds, $scopeUserIds, $dataInicio, $dataFim);

    $resumoPessoas = fetchAllAssoc(
        $pdo,
        "SELECT
            u.id,
            u.nome,
            u.cargo,
            u.funcao_equipe,
            u.sub_equipe_id,
            se.nome AS sub_equipe_nome,
            COUNT(t.id) AS total,
            SUM(CASE WHEN t.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN t.status = 'concluida' THEN 1 ELSE 0 END) AS concluidas,
            SUM(CASE WHEN t.status = 'pendente' AND t.prazo < ? THEN 1 ELSE 0 END) AS atrasadas
         FROM usuarios u
         LEFT JOIN sub_equipes se ON se.id = u.sub_equipe_id
         LEFT JOIN tarefas t
            ON (t.usuario_id = u.id OR t.atribuida_para = u.id)
           AND $whereEquipe
         WHERE u.id IN ($placeholdersPessoas)
         GROUP BY u.id, u.nome, u.cargo, u.funcao_equipe, u.sub_equipe_id, se.nome
         ORDER BY pendentes DESC, atrasadas DESC, total DESC, u.nome ASC",
        array_merge([$hoje], $paramsEquipe, $paramsPessoas)
    );
}

$periodoDescricao = match ($periodo) {
    'hoje' => 'Hoje',
    'semana' => 'Semana atual',
    'mes' => 'Mês atual',
    'tudo' => 'Todo o período',
    'personalizado' => ($dataInicio === null && $dataFim === null)
        ? 'Intervalo personalizado'
        : (($dataInicio && $dataFim)
            ? formatarData($dataInicio) . ' até ' . formatarData($dataFim)
            : (($dataInicio && !$dataFim) ? 'A partir de ' . formatarData($dataInicio) : 'Até ' . formatarData($dataFim))),
    default => 'Semana atual',
};

$mensagemPerformance = 'Bom ritmo para o período selecionado.';
if ($atrasadas > 0 && $percentualAtraso >= 30) {
    $mensagemPerformance = 'Há atraso acumulado e vale priorizar o backlog.';
} elseif ($pendentes > 0 && $percentualConclusao < 40) {
    $mensagemPerformance = 'Ainda existe bastante trabalho aberto neste período.';
} elseif ($total > 0 && $percentualConclusao >= 70) {
    $mensagemPerformance = 'Ótimo avanço. A maior parte das entregas já foi concluída.';
} elseif ($total === 0) {
    $mensagemPerformance = 'Nenhuma tarefa encontrada para os filtros escolhidos.';
}

$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$dataFormatada = $diasSemana[(int) date('w')] . ', ' . date('d') . ' de ' . $meses[(int) date('n')] . ' de ' . date('Y');
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | TaskBlue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include __DIR__ . '/includes/partials/header.php'; ?>

    <main class="dashboard-container">
        <section class="welcome-hero">
            <div class="welcome-text">
                <span class="welcome-date"><?= htmlspecialchars($dataFormatada) ?></span>
                <h1>Olá, <?= htmlspecialchars(explode(' ', trim($nomeUsuario))[0]) ?>!</h1>
                <p>Veja o que merece atenção no dashboard.</p>
            </div>

            <div class="hero-selection">
                <div class="hero-selection-item">
                    <span>Período</span>
                    <strong><?= htmlspecialchars($periodoDescricao) ?></strong>
                </div>
                <div class="hero-selection-item">
                    <span>Visualização</span>
                    <strong><?= htmlspecialchars($escopoLabel) ?></strong>
                </div>
            </div>
        </section>

        <section class="filters-card">
            <div class="filters-topbar">
                <div class="filters-title">
                    <h2>Filtros</h2>
                    <p>Escolha o período e quem você quer acompanhar.</p>
                </div>
                <a href="dashboard.php" class="filters-reset dashboard-ajax-link">Limpar</a>
            </div>

            <form method="GET" class="dashboard-filter-form" id="dashboardFilterForm">
                <input type="hidden" name="periodo" id="dashboardPeriodo" value="<?= htmlspecialchars($periodo) ?>">

                <div class="filters-layout">
                    <section class="filter-panel">
                        <label class="filter-label">Período</label>
                        <div class="period-chips">
                            <button type="button" class="period-chip <?= $periodo === 'hoje' ? 'active' : '' ?>" data-periodo="hoje">Dia</button>
                            <button type="button" class="period-chip <?= $periodo === 'semana' ? 'active' : '' ?>" data-periodo="semana">Semana</button>
                            <button type="button" class="period-chip <?= $periodo === 'mes' ? 'active' : '' ?>" data-periodo="mes">Mês</button>
                            <button type="button" class="period-chip <?= $periodo === 'tudo' ? 'active' : '' ?>" data-periodo="tudo">Tudo</button>
                            <button type="button" class="period-chip <?= $periodo === 'personalizado' ? 'active' : '' ?>" data-periodo="personalizado">Personalizado</button>
                        </div>

                        <div class="custom-range <?= $periodo === 'personalizado' ? 'is-active' : '' ?>">
                            <label>
                                <span class="filter-subtitle">De</span>
                                <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio ?? '') ?>" class="filter-date">
                            </label>
                            <label>
                                <span class="filter-subtitle">Até</span>
                                <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim ?? '') ?>" class="filter-date">
                            </label>
                            <button type="submit" class="btn-filter-apply">Aplicar</button>
                        </div>
                    </section>

                    <section class="filter-panel">
                        <label class="filter-label" for="responsavel">Visualização</label>
                        <?php if (count($visibleUserIds) > 1): ?>
                            <select name="responsavel" id="responsavel" class="filter-select" data-dashboard-autosubmit>
                                <option value="todos" <?= $filtroSelecionado === 'todos' ? 'selected' : '' ?>>
                                    Todos os membros visíveis
                                </option>

                                <?php if (!empty($equipesFiltro)): ?>
                                    <optgroup label="Sub-equipes e áreas">
                                        <?php foreach ($equipesFiltro as $equipeFiltro): ?>
                                            <option value="<?= htmlspecialchars($equipeFiltro['id']) ?>" <?= $filtroSelecionado === $equipeFiltro['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($equipeFiltro['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <optgroup label="Pessoas">
                                    <?php foreach ($usuariosFiltro as $usuarioFiltro): ?>
                                        <option value="user:<?= (int) $usuarioFiltro['id'] ?>" <?= $filtroSelecionado === 'user:' . $usuarioFiltro['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($usuarioFiltro['nome']) ?>
                                            <?php if (!empty($usuarioFiltro['funcao_equipe'])): ?>
                                                - <?= htmlspecialchars($usuarioFiltro['funcao_equipe']) ?>
                                            <?php elseif (!empty($usuarioFiltro['sub_equipe_nome'])): ?>
                                                - <?= htmlspecialchars($usuarioFiltro['sub_equipe_nome']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="responsavel" value="<?= htmlspecialchars($filtroSelecionado) ?>">
                            <div class="filter-readonly"><?= htmlspecialchars($escopoLabel) ?></div>
                        <?php endif; ?>

                        <div class="filter-summary">
                            <div class="summary-pill">
                                <span>Período</span>
                                <strong><?= htmlspecialchars($periodoDescricao) ?></strong>
                            </div>
                            <div class="summary-pill">
                                <span>Visualização</span>
                                <strong><?= htmlspecialchars($escopoLabel) ?></strong>
                            </div>
                        </div>
                    </section>
                </div>
            </form>
        </section>

        <div class="dashboard-main-grid">
            <div class="dashboard-left">
                <div class="stats-grid">
                    <article class="stat-card stat-total">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $total ?></span>
                            <span class="stat-label">Total no período</span>
                            <small><?= htmlspecialchars($escopoLabel) ?></small>
                        </div>
                    </article>

                    <article class="stat-card stat-pending">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $pendentes ?></span>
                            <span class="stat-label">Pendentes</span>
                            <small><?= $percentualPendencia ?>% do total</small>
                        </div>
                    </article>

                    <article class="stat-card stat-overdue">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86 1.82 18A2 2 0 0 0 3.53 21h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $atrasadas ?></span>
                            <span class="stat-label">Atrasadas</span>
                            <small><?= $percentualAtraso ?>% pressionando o prazo</small>
                        </div>
                    </article>

                    <article class="stat-card stat-done">
                        <div class="stat-icon-wrapper">
                            <svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-value"><?= $concluidas ?></span>
                            <span class="stat-label">Concluídas</span>
                            <small><?= $percentualConclusao ?>% de avanço</small>
                        </div>
                    </article>
                </div>

                <div class="insights-grid">
                    <section class="progress-card">
                        <div class="progress-circle-container">
                            <div class="progress-circle" style="--progress: <?= $percentualConclusao ?>">
                                <div class="progress-circle-inner">
                                    <span class="progress-percent"><?= $percentualConclusao ?>%</span>
                                    <span class="progress-text">concluído</span>
                                </div>
                            </div>
                        </div>

                        <div class="progress-legend">
                            <span class="section-kicker">Progresso</span>
                            <h3>Panorama do período</h3>
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
                    </section>

                    <section class="momentum-card">
                        <span class="section-kicker">Pulso</span>
                        <h3>Como está a carga agora</h3>

                        <div class="momentum-row">
                            <div class="momentum-copy">
                                <strong>Execução</strong>
                                <span><?= $percentualConclusao ?>% de conclusão</span>
                            </div>
                            <div class="momentum-bar">
                                <span style="width: <?= $percentualConclusao ?>%"></span>
                            </div>
                        </div>

                        <div class="momentum-row">
                            <div class="momentum-copy">
                                <strong>Backlog</strong>
                                <span><?= $percentualPendencia ?>% ainda pendente</span>
                            </div>
                            <div class="momentum-bar warning">
                                <span style="width: <?= $percentualPendencia ?>%"></span>
                            </div>
                        </div>

                        <div class="momentum-row">
                            <div class="momentum-copy">
                                <strong>Risco de prazo</strong>
                                <span><?= $percentualAtraso ?>% atrasado</span>
                            </div>
                            <div class="momentum-bar danger">
                                <span style="width: <?= $percentualAtraso ?>%"></span>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="team-card">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Equipe</span>
                            <h2><?= $scopeTipo === 'user' ? 'Leitura individual' : 'Comparativo rápido' ?></h2>
                        </div>
                    </div>

                    <?php if (!empty($resumoPessoas)): ?>
                        <div class="team-list">
                            <?php foreach ($resumoPessoas as $pessoa): ?>
                                <?php
                                $totalPessoa = (int) ($pessoa['total'] ?? 0);
                                $concluidasPessoa = (int) ($pessoa['concluidas'] ?? 0);
                                $percentualPessoa = $totalPessoa > 0 ? (int) round(($concluidasPessoa / $totalPessoa) * 100) : 0;
                                $iniciais = strtoupper(substr($pessoa['nome'], 0, 1) . (strpos($pessoa['nome'], ' ') !== false ? substr(strrchr($pessoa['nome'], ' '), 1, 1) : ''));
                                $queryPessoa = montarQueryString([
                                    'periodo' => $periodo,
                                    'responsavel' => 'user:' . $pessoa['id'],
                                    'data_inicio' => $periodo === 'personalizado' ? $dataInicio : null,
                                    'data_fim' => $periodo === 'personalizado' ? $dataFim : null,
                                ]);
                                ?>
                                <a class="team-item dashboard-ajax-link" href="dashboard.php?<?= htmlspecialchars($queryPessoa) ?>">
                                    <div class="team-avatar"><?= htmlspecialchars($iniciais) ?></div>
                                    <div class="team-main">
                                        <div class="team-name-row">
                                            <strong><?= htmlspecialchars($pessoa['nome']) ?></strong>
                                            <span>
                                                <?= htmlspecialchars($pessoa['funcao_equipe'] ?: montarRotuloCargo($pessoa['cargo'])) ?>
                                                <?php if (!empty($pessoa['sub_equipe_nome'])): ?>
                                                    · <?= htmlspecialchars($pessoa['sub_equipe_nome']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="team-meta">
                                            <span><?= (int) ($pessoa['pendentes'] ?? 0) ?> pendentes</span>
                                            <span><?= (int) ($pessoa['atrasadas'] ?? 0) ?> atrasadas</span>
                                            <span><?= $percentualPessoa ?>% concluído</span>
                                        </div>
                                        <div class="team-progress">
                                            <span style="width: <?= $percentualPessoa ?>%"></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Ninguém apareceu neste período.</p>
                            <span>Troque o filtro para ampliar a visualização.</span>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="dashboard-right">
                <section class="urgent-card">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Prioridades</span>
                            <h2>Próximas tarefas</h2>
                        </div>
                        <a href="tarefas.php" class="see-all">Ver tarefas</a>
                    </div>

                    <?php if (!empty($tarefasUrgentes)): ?>
                        <div class="urgent-timeline">
                            <?php foreach ($tarefasUrgentes as $tarefa): ?>
                                <div class="timeline-item <?= htmlspecialchars($tarefa['urgencia']) ?>">
                                    <div class="timeline-marker">
                                        <?php if ($tarefa['urgencia'] === 'atrasada'): ?>
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                        <?php elseif ($tarefa['urgencia'] === 'hoje'): ?>
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 11h-2V7h2zm0 4h-2v-2h2z"/></svg>
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="8"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <h4><?= htmlspecialchars($tarefa['titulo']) ?></h4>
                                        <div class="timeline-meta">
                                            <span class="timeline-date"><?= formatarData($tarefa['prazo']) ?></span>
                                            <span class="timeline-status <?= htmlspecialchars($tarefa['urgencia']) ?>"><?= htmlspecialchars(calcularDias($tarefa['prazo'])) ?></span>
                                        </div>
                                        <small><?= htmlspecialchars($tarefa['responsavel_nome'] ?? 'Sem responsável') ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Nenhuma pendência urgente por aqui.</p>
                            <span>Este filtro está limpo ou já foi bem resolvido.</span>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="shortcuts-card">
                    <div class="card-header">
                        <div>
                            <span class="section-kicker">Ações rápidas</span>
                            <h2>Próximos passos</h2>
                        </div>
                    </div>

                    <div class="shortcuts-list">
                        <a href="tarefas.php" class="shortcut-item">
                            <div class="shortcut-icon blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </div>
                            <div class="shortcut-text">
                                <span class="shortcut-title">Gerenciar tarefas</span>
                                <span class="shortcut-desc">Entrar na lista completa</span>
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
                                <span class="shortcut-desc">Revisar itens arquivados</span>
                            </div>
                            <svg class="shortcut-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>

                        <?php if ($cargoUsuario === 'administrador'): ?>
                            <a href="gerenciar_usuarios.php" class="shortcut-item">
                                <div class="shortcut-icon green">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <div class="shortcut-text">
                                    <span class="shortcut-title">Gerenciar equipe</span>
                                    <span class="shortcut-desc">Editar membros e vínculos</span>
                                </div>
                                <svg class="shortcut-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>
