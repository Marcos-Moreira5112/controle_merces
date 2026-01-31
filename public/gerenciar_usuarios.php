<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];

// Buscar dados do usuário logado
$sqlUsuario = "SELECT nome, cargo FROM usuarios WHERE id = :usuario_id";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmtUsuario->execute();
$usuarioLogado = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

$nomeUsuario = $usuarioLogado['nome'];
$cargoUsuario = $usuarioLogado['cargo'];

// Só administrador pode acessar
if ($cargoUsuario !== 'administrador') {
    $_SESSION['mensagem'] = 'Você não tem permissão para acessar esta página.';
    $_SESSION['tipo_mensagem'] = 'erro';
    header('Location: tarefas.php');
    exit;
}

// Mensagens flash
$mensagem = $_SESSION['mensagem'] ?? null;
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $cargo = $_POST['cargo'] ?? 'funcionario';
        $supervisor_id = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;

        if ($cargo !== 'funcionario') {
            $supervisor_id = null;
        }

        if ($nome === '' || $email === '' || $senha === '') {
            $_SESSION['mensagem'] = 'Nome, e-mail e senha são obrigatórios!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensagem'] = 'E-mail inválido!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } elseif (strlen($senha) < 6) {
            $_SESSION['mensagem'] = 'A senha deve ter pelo menos 6 caracteres!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO usuarios (nome, email, senha, cargo, supervisor_id, ativo) 
                    VALUES (:nome, :email, :senha, :cargo, :supervisor_id, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':senha', $senhaHash);
            $stmt->bindParam(':cargo', $cargo);
            $stmt->bindParam(':supervisor_id', $supervisor_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['mensagem'] = 'Usuário criado com sucesso!';
            $_SESSION['tipo_mensagem'] = 'sucesso';
        }
    } elseif ($acao === 'editar') {
        $id = (int)$_POST['id'];
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $novaSenha = $_POST['nova_senha'] ?? '';
        $cargo = $_POST['cargo'] ?? 'funcionario';
        $supervisor_id = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;

        if ($cargo !== 'funcionario') {
            $supervisor_id = null;
        }

        if ($nome === '' || $email === '') {
            $_SESSION['mensagem'] = 'Nome e e-mail são obrigatórios!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensagem'] = 'E-mail inválido!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } elseif ($novaSenha !== '' && strlen($novaSenha) < 6) {
            $_SESSION['mensagem'] = 'A nova senha deve ter pelo menos 6 caracteres!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } else {
            if ($novaSenha !== '') {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, senha = :senha, cargo = :cargo, supervisor_id = :supervisor_id WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':senha', $senhaHash);
            } else {
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, cargo = :cargo, supervisor_id = :supervisor_id WHERE id = :id";
                $stmt = $pdo->prepare($sql);
            }
            
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':cargo', $cargo);
            $stmt->bindParam(':supervisor_id', $supervisor_id, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['mensagem'] = 'Usuário atualizado com sucesso!';
            $_SESSION['tipo_mensagem'] = 'sucesso';
        }
    } elseif ($acao === 'toggle_ativo') {
        $id = (int)$_POST['id'];
        $novoAtivo = (int)$_POST['novo_ativo'];
        $sql = "UPDATE usuarios SET ativo = :ativo WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ativo', $novoAtivo, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['mensagem'] = $novoAtivo === 1 ? 'Usuário ativado!' : 'Usuário desativado!';
        $_SESSION['tipo_mensagem'] = 'sucesso';
    }

    header('Location: gerenciar_usuarios.php');
    exit;
}

// Busca
$busca = trim($_GET['busca'] ?? '');
$where = "WHERE 1=1";
$params = [];
if ($busca !== '') {
    $where .= " AND (nome LIKE :busca OR email LIKE :busca OR cargo LIKE :busca)";
    $params[':busca'] = "%$busca%";
}

// Listar usuários
$sqlUsuarios = "SELECT id, nome, email, cargo, supervisor_id, ativo, created_at 
                FROM usuarios $where ORDER BY nome ASC";
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
foreach ($params as $key => $value) {
    $stmtUsuarios->bindValue($key, $value);
}
$stmtUsuarios->execute();
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Listar supervisores
$sqlSupervisores = "SELECT id, nome FROM usuarios WHERE cargo = 'supervisor' ORDER BY nome ASC";
$stmtSupervisores = $pdo->prepare($sqlSupervisores);
$stmtSupervisores->execute();
$supervisores = $stmtSupervisores->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Usuários | TaskBlue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div>
                <h1>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    Usuários
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
                <a href="historico.php" class="btn-nav">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Histórico
                </a>
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

    <main class="user-management-container">

        <?php if ($mensagem): ?>
            <div class="mensagem <?= $tipo_mensagem ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Gerenciamento de Usuários</h2>
            <p>Controle acessos, cargos e hierarquia da equipe.</p>
        </div>

        <div class="controls-bar">
            <form method="GET" class="flex-1">
                <input type="text" name="busca" class="search-input" placeholder="Buscar por nome, e-mail ou cargo..." value="<?= htmlspecialchars($busca) ?>">
            </form>
            <button type="button" onclick="abrirModal('modalCriar')" class="btn-add">
                <span class="plus">+</span> Novo Usuário
            </button>
        </div>

        <div class="table-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Cargo</th>
                        <th>Supervisor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $user): 
                        $iniciais = strtoupper(substr($user['nome'], 0, 1) . (strpos($user['nome'], ' ') ? substr(strrchr($user['nome'], ' '), 1, 1) : ''));
                        $cargoClass = strtolower($user['cargo']);
                        
                        $supervisorNome = '—';
                        if ($user['supervisor_id']) {
                            $sqlSup = "SELECT nome FROM usuarios WHERE id = :sup_id";
                            $stmtSup = $pdo->prepare($sqlSup);
                            $stmtSup->bindParam(':sup_id', $user['supervisor_id'], PDO::PARAM_INT);
                            $stmtSup->execute();
                            $sup = $stmtSup->fetch(PDO::FETCH_ASSOC);
                            if ($sup) {
                                $supervisorNome = htmlspecialchars($sup['nome']);
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="avatar <?= $cargoClass ?>"><?= $iniciais ?></div>
                                    <div class="user-details">
                                        <div class="user-name"><?= htmlspecialchars($user['nome']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-cargo <?= $cargoClass ?>">
                                    <?= ucfirst($user['cargo']) ?>
                                </span>
                            </td>
                            <td><?= $supervisorNome ?></td>
                            <td>
                                <div class="status">
                                    <div class="status-dot <?= $user['ativo'] ? 'ativo' : 'inativo' ?>"></div>
                                    <?= $user['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </div>
                            </td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="action-btn" title="Editar" onclick="abrirEditar(<?= $user['id'] ?>, '<?= addslashes($user['nome']) ?>', '<?= addslashes($user['email']) ?>', '<?= $user['cargo'] ?>', <?= $user['supervisor_id'] ?? 'null' ?>)">
                                        <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>

                                    <form method="POST">
                                        <input type="hidden" name="acao" value="toggle_ativo">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="novo_ativo" value="<?= $user['ativo'] ? 0 : 1 ?>">
                                        <button type="submit" class="action-btn" title="<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?>" onclick="return confirm('<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?> <?= addslashes($user['nome']) ?>?')">
                                            <?php if ($user['ativo']): ?>
                                                <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <line x1="23" y1="11" x2="17" y2="11"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <line x1="17" y1="11" x2="23" y2="11"/>
                                                    <line x1="20" y1="8" x2="20" y2="14"/>
                                                </svg>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <!-- Modal Criar Usuário -->
    <div id="modalCriar" class="modal hidden">
        <div class="modal-content">
            <h3>Criar Novo Usuário</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                
                <label>Nome Completo</label>
                <input type="text" name="nome" required>

                <label>E-mail</label>
                <input type="email" name="email" required>

                <label>Senha</label>
                <input type="password" name="senha" required minlength="6" placeholder="Mínimo 6 caracteres">

                <label>Cargo</label>
                <select name="cargo" id="cargoCriar" onchange="toggleSupervisor('cargoCriar', 'divSupervisorCriar')">
                    <option value="funcionario">Funcionário</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="administrador">Administrador</option>
                </select>

                <div id="divSupervisorCriar">
                    <label>Supervisor</label>
                    <select name="supervisor_id">
                        <option value="">Nenhum</option>
                        <?php foreach ($supervisores as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalCriar')">Cancelar</button>
                    <button type="submit">Criar Usuário</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div id="modalEditar" class="modal hidden">
        <div class="modal-content">
            <h3>Editar Usuário</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editId">
                
                <label>Nome Completo</label>
                <input type="text" name="nome" id="editNome" required>

                <label>E-mail</label>
                <input type="email" name="email" id="editEmail" required>

                <label>Nova Senha <small style="color: #666; font-weight: normal;">(deixe em branco para manter a atual)</small></label>
                <input type="password" name="nova_senha" id="editSenha" minlength="6" placeholder="Mínimo 6 caracteres">

                <label>Cargo</label>
                <select name="cargo" id="editCargo" onchange="toggleSupervisor('editCargo', 'divSupervisorEdit')">
                    <option value="funcionario">Funcionário</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="administrador">Administrador</option>
                </select>

                <div id="divSupervisorEdit">
                    <label>Supervisor</label>
                    <select name="supervisor_id" id="editSupervisor">
                        <option value="">Nenhum</option>
                        <?php foreach ($supervisores as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalEditar')">Cancelar</button>
                    <button type="submit">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function fecharModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.add('hidden');
            }
        }

        function toggleSupervisor(cargoId, divId) {
            const cargo = document.getElementById(cargoId).value;
            const divSupervisor = document.getElementById(divId);
            divSupervisor.style.display = (cargo === 'funcionario') ? 'block' : 'none';
        }

        function abrirEditar(id, nome, email, cargo, supervisorId) {
            document.getElementById('editId').value = id;
            document.getElementById('editNome').value = nome;
            document.getElementById('editEmail').value = email;
            document.getElementById('editSenha').value = '';
            document.getElementById('editCargo').value = cargo;
            
            if (supervisorId) {
                document.getElementById('editSupervisor').value = supervisorId;
            } else {
                document.getElementById('editSupervisor').value = '';
            }
            
            toggleSupervisor('editCargo', 'divSupervisorEdit');
            abrirModal('modalEditar');
        }
    </script>
</body>
</html>