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

// Buscar dados do usuário logado
$sqlUsuario = "SELECT nome, cargo FROM usuarios WHERE id = :id";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->bindParam(':id', $usuario_id, PDO::PARAM_INT);
$stmtUsuario->execute();
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

$nomeUsuario = $usuario['nome'];
$cargoUsuario = $usuario['cargo'];

// Sistema de mensagens flash
$mensagem = $_SESSION['mensagem'] ?? null;
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

// AUTO-EXCLUSÃO: Deletar tarefas arquivadas há mais de 7 dias
// Respeita hierarquia de cargos
if ($cargoUsuario === 'administrador') {
    $sqlLimparHistorico = "
        DELETE FROM tarefas
        WHERE arquivada = 1
          AND data_arquivamento IS NOT NULL
          AND data_arquivamento < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    $stmtLimpar = $pdo->prepare($sqlLimparHistorico);
} elseif ($cargoUsuario === 'supervisor') {
    $sqlLimparHistorico = "
        DELETE t FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.arquivada = 1
          AND t.data_arquivamento IS NOT NULL
          AND t.data_arquivamento < DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND (t.usuario_id = :usuario_id OR u.supervisor_id = :usuario_id2)
    ";
    $stmtLimpar = $pdo->prepare($sqlLimparHistorico);
    $stmtLimpar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtLimpar->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
} else {
    $sqlLimparHistorico = "
        DELETE FROM tarefas
        WHERE usuario_id = :usuario_id
          AND arquivada = 1
          AND data_arquivamento IS NOT NULL
          AND data_arquivamento < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    $stmtLimpar = $pdo->prepare($sqlLimparHistorico);
    $stmtLimpar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
}
$stmtLimpar->execute();

// RESTAURAR - volta tarefa para ativa
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'restaurar') {
    $tarefa_id = (int) $_GET['id'];

    // Verificar permissão antes de restaurar
    if ($cargoUsuario === 'administrador') {
        $sqlRestaurar = "
            UPDATE tarefas
            SET arquivada = 0, data_arquivamento = NULL
            WHERE id = :id
        ";
        $stmtRestaurar = $pdo->prepare($sqlRestaurar);
        $stmtRestaurar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    } elseif ($cargoUsuario === 'supervisor') {
        $sqlRestaurar = "
            UPDATE tarefas t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            SET t.arquivada = 0, t.data_arquivamento = NULL
            WHERE t.id = :id
              AND (t.usuario_id = :usuario_id OR u.supervisor_id = :usuario_id2)
        ";
        $stmtRestaurar = $pdo->prepare($sqlRestaurar);
        $stmtRestaurar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
        $stmtRestaurar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmtRestaurar->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    } else {
        $sqlRestaurar = "
            UPDATE tarefas
            SET arquivada = 0, data_arquivamento = NULL
            WHERE id = :id AND usuario_id = :usuario_id
        ";
        $stmtRestaurar = $pdo->prepare($sqlRestaurar);
        $stmtRestaurar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
        $stmtRestaurar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }
    $stmtRestaurar->execute();

    $_SESSION['mensagem'] = 'Tarefa restaurada com sucesso!';
    $_SESSION['tipo_mensagem'] = 'sucesso';

    header('Location: historico.php');
    exit;
}

// DELETAR PERMANENTEMENTE
if (isset($_GET['acao'], $_GET['id']) && $_GET['acao'] === 'deletar') {
    $tarefa_id = (int) $_GET['id'];

    if ($cargoUsuario === 'administrador') {
        $sqlDeletar = "DELETE FROM tarefas WHERE id = :id AND arquivada = 1";
        $stmtDeletar = $pdo->prepare($sqlDeletar);
        $stmtDeletar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
    } elseif ($cargoUsuario === 'supervisor') {
        $sqlDeletar = "
            DELETE t FROM tarefas t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            WHERE t.id = :id
              AND t.arquivada = 1
              AND (t.usuario_id = :usuario_id OR u.supervisor_id = :usuario_id2)
        ";
        $stmtDeletar = $pdo->prepare($sqlDeletar);
        $stmtDeletar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
        $stmtDeletar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmtDeletar->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    } else {
        $sqlDeletar = "
            DELETE FROM tarefas
            WHERE id = :id AND usuario_id = :usuario_id AND arquivada = 1
        ";
        $stmtDeletar = $pdo->prepare($sqlDeletar);
        $stmtDeletar->bindParam(':id', $tarefa_id, PDO::PARAM_INT);
        $stmtDeletar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    }
    $stmtDeletar->execute();

    $_SESSION['mensagem'] = 'Tarefa excluída permanentemente!';
    $_SESSION['tipo_mensagem'] = 'sucesso';

    header('Location: historico.php');
    exit;
}

// Buscar tarefas arquivadas (respeitando hierarquia)
if ($cargoUsuario === 'administrador') {
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes, 
               t.created_at, t.data_arquivamento, t.usuario_id,
               u.nome AS nome_usuario
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.arquivada = 1
        ORDER BY t.data_arquivamento DESC
    ";
    $stmt = $pdo->prepare($sql);
} elseif ($cargoUsuario === 'supervisor') {
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes, 
               t.created_at, t.data_arquivamento, t.usuario_id,
               u.nome AS nome_usuario
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.arquivada = 1
          AND (t.usuario_id = :usuario_id OR u.supervisor_id = :usuario_id2)
        ORDER BY t.data_arquivamento DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
} else {
    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes, 
               t.created_at, t.data_arquivamento, t.usuario_id,
               u.nome AS nome_usuario
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.usuario_id = :usuario_id
          AND t.arquivada = 1
        ORDER BY t.data_arquivamento DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
}

$stmt->execute();
$tarefasArquivadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar tarefas por data de arquivamento
$tarefasAgrupadas = [];
foreach ($tarefasArquivadas as $tarefa) {
    $dataArq = $tarefa['data_arquivamento'] 
        ? date('Y-m-d', strtotime($tarefa['data_arquivamento'])) 
        : 'sem-data';
    $tarefasAgrupadas[$dataArq][] = $tarefa;
}

// Função para formatar data do grupo
function formatarDataGrupo($data) {
    if ($data === 'sem-data') return 'Data não registrada';
    
    $hoje = new DateTime();
    $dataObj = new DateTime($data);
    $diff = $hoje->diff($dataObj)->days;
    
    if ($dataObj->format('Y-m-d') === $hoje->format('Y-m-d')) {
        return 'Arquivadas hoje';
    } elseif ($diff === 1) {
        return 'Arquivadas ontem';
    } elseif ($diff < 7) {
        return 'Arquivadas há ' . $diff . ' dias';
    } else {
        return 'Arquivadas em ' . $dataObj->format('d/m/Y');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico | TaskBlue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/historico.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div>
                <h1>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Histórico
                </h1>
                <p>Óticas Mercês • <?= htmlspecialchars($nomeUsuario) ?>
                    <span class="badge-cargo <?= $cargoUsuario ?>"><?= ucfirst($cargoUsuario) ?></span>
                </p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn-nav">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Dashboard
                </a>
                <a href="tarefas.php" class="btn-nav">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                    </svg>
                    Tarefas
                </a>
                <?php if ($cargoUsuario === 'administrador'): ?>
                    <a href="gerenciar_usuarios.php" class="btn-nav">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                        Usuários
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Sair
                </a>
            </div>
        </div>
    </header>

    <!-- Mensagens de feedback -->
    <?php if ($mensagem): ?>
        <div class="mensagem-toast <?= $tipo_mensagem ?>">
            <?php if ($tipo_mensagem === 'sucesso'): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            <?php endif; ?>
            <span><?= htmlspecialchars($mensagem) ?></span>
        </div>
    <?php endif; ?>

    <main>
        <div class="historico-container">
            
            <!-- Cabeçalho da seção -->
            <div class="historico-header">
                <div class="historico-titulo">
                    <h2>Tarefas Arquivadas</h2>
                    <span class="contador"><?= count($tarefasArquivadas) ?> <?= count($tarefasArquivadas) === 1 ? 'tarefa' : 'tarefas' ?></span>
                </div>
                
                <div class="aviso-expiracao">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span>Tarefas são excluídas automaticamente após <strong>7 dias</strong></span>
                </div>
            </div>

            <?php if (count($tarefasArquivadas) === 0): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="64" height="64">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                            <rect x="9" y="3" width="6" height="4" rx="1"/>
                            <path d="M9 14l2 2 4-4"/>
                        </svg>
                    </div>
                    <h3>Nenhuma tarefa arquivada</h3>
                    <p>Quando você arquivar tarefas, elas aparecerão aqui por 7 dias antes de serem excluídas permanentemente.</p>
                    <a href="tarefas.php" class="btn-voltar-tarefas">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Voltar para Tarefas
                    </a>
                </div>
            <?php else: ?>
                <!-- Lista agrupada por data -->
                <div class="historico-lista">
                    <?php foreach ($tarefasAgrupadas as $dataGrupo => $tarefas): ?>
                        <div class="grupo-data">
                            <div class="grupo-header">
                                <span class="grupo-titulo"><?= formatarDataGrupo($dataGrupo) ?></span>
                                <span class="grupo-linha"></span>
                            </div>
                            
                            <div class="grupo-tarefas">
                                <?php foreach ($tarefas as $tarefa): ?>
                                    <?php
                                    // Calcular dias restantes até exclusão
                                    $diasRestantes = 7;
                                    $urgencia = '';
                                    if ($tarefa['data_arquivamento']) {
                                        $dataArquivamento = new DateTime($tarefa['data_arquivamento']);
                                        $hoje = new DateTime();
                                        $diasArquivada = $hoje->diff($dataArquivamento)->days;
                                        $diasRestantes = max(0, 7 - $diasArquivada);
                                        
                                        if ($diasRestantes <= 1) {
                                            $urgencia = 'critico';
                                        } elseif ($diasRestantes <= 3) {
                                            $urgencia = 'alerta';
                                        }
                                    }
                                    $porcentagemRestante = ($diasRestantes / 7) * 100;
                                    ?>
                                    <div class="tarefa-arquivada <?= $urgencia ?>">
                                        <div class="tarefa-main">
                                            <div class="tarefa-info">
                                                <h3 class="tarefa-titulo"><?= htmlspecialchars($tarefa['titulo']) ?></h3>
                                                
                                                <div class="tarefa-meta">
                                                    <span class="meta-item">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                                        </svg>
                                                        Prazo: <?= date('d/m/Y', strtotime($tarefa['prazo'])) ?>
                                                    </span>
                                                    
                                                    <span class="badge-status <?= $tarefa['status'] ?>">
                                                        <?= $tarefa['status'] === 'concluida' ? 'Concluída' : 'Pendente' ?>
                                                    </span>
                                                    
                                                    <?php if ($tarefa['tipo'] === 'fixa'): ?>
                                                        <span class="badge-tipo">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                                                                <polyline points="23 4 23 10 17 10"/>
                                                                <polyline points="1 20 1 14 7 14"/>
                                                                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                                                            </svg>
                                                            Recorrente
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($cargoUsuario !== 'funcionario' && $tarefa['usuario_id'] != $usuario_id): ?>
                                                        <span class="meta-item usuario">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                                                <circle cx="12" cy="7" r="4"/>
                                                            </svg>
                                                            <?= htmlspecialchars($tarefa['nome_usuario']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($tarefa['observacoes'])): ?>
                                                    <div class="tarefa-observacoes">
                                                        <p><?= nl2br(htmlspecialchars(mb_strimwidth($tarefa['observacoes'], 0, 150, '...'))) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="tarefa-expiracao">
                                                <div class="expiracao-info <?= $urgencia ?>">
                                                    <?php if ($diasRestantes === 0): ?>
                                                        <span class="expiracao-texto">Expira hoje</span>
                                                    <?php elseif ($diasRestantes === 1): ?>
                                                        <span class="expiracao-texto">Expira amanhã</span>
                                                    <?php else: ?>
                                                        <span class="expiracao-texto"><?= $diasRestantes ?> dias restantes</span>
                                                    <?php endif; ?>
                                                    <div class="expiracao-barra">
                                                        <div class="expiracao-progresso" style="width: <?= $porcentagemRestante ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="tarefa-acoes">
                                            <a href="?acao=restaurar&id=<?= $tarefa['id'] ?>" class="btn-restaurar" title="Restaurar tarefa">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                    <polyline points="1 4 1 10 7 10"/>
                                                    <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
                                                </svg>
                                                Restaurar
                                            </a>
                                            <button type="button" class="btn-excluir" onclick="confirmarExclusao(<?= $tarefa['id'] ?>, '<?= htmlspecialchars(addslashes($tarefa['titulo'])) ?>')" title="Excluir permanentemente">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                                </svg>
                                                Excluir
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal de Confirmação -->
    <div id="modal-confirmacao" class="modal-overlay">
        <div class="modal-confirmacao">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <h3>Excluir permanentemente?</h3>
            <p>A tarefa "<span id="modal-tarefa-nome"></span>" será excluída permanentemente. Esta ação não pode ser desfeita.</p>
            <div class="modal-acoes">
                <button type="button" class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
                <a id="link-confirmar-exclusao" href="#" class="btn-confirmar-excluir">Excluir</a>
            </div>
        </div>
    </div>

    <script>
        // Modal de confirmação
        function confirmarExclusao(id, titulo) {
            const modal = document.getElementById('modal-confirmacao');
            const nomeSpan = document.getElementById('modal-tarefa-nome');
            const linkConfirmar = document.getElementById('link-confirmar-exclusao');
            
            nomeSpan.textContent = titulo;
            linkConfirmar.href = '?acao=deletar&id=' + id;
            modal.classList.add('ativo');
            document.body.style.overflow = 'hidden';
        }
        
        function fecharModal() {
            const modal = document.getElementById('modal-confirmacao');
            modal.classList.remove('ativo');
            document.body.style.overflow = '';
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('modal-confirmacao').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModal();
            }
        });
        
        // Auto-hide da mensagem toast
        const toast = document.querySelector('.mensagem-toast');
        if (toast) {
            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    </script>
</body>
</html>