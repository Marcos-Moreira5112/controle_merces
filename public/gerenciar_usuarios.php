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

function bindNullableInt(PDOStatement $stmt, string $param, ?int $value): void
{
    if ($value === null) {
        $stmt->bindValue($param, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($param, $value, PDO::PARAM_INT);
}

function redirecionarComMensagem(string $mensagem, string $tipo = 'erro'): void
{
    $_SESSION['mensagem'] = $mensagem;
    $_SESSION['tipo_mensagem'] = $tipo;
    header('Location: gerenciar_usuarios.php');
    exit;
}

$usuario_id = (int) $_SESSION['usuario_id'];

$sqlUsuario = "SELECT nome, cargo FROM usuarios WHERE id = :usuario_id";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmtUsuario->execute();
$usuarioLogado = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

if (!$usuarioLogado) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$nomeUsuario = $usuarioLogado['nome'];
$cargoUsuario = $usuarioLogado['cargo'];

if ($cargoUsuario !== 'administrador') {
    $_SESSION['mensagem'] = 'Você não tem permissão para acessar esta página.';
    $_SESSION['tipo_mensagem'] = 'erro';
    header('Location: tarefas.php');
    exit;
}

$mensagem = $_SESSION['mensagem'] ?? null;
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'criar') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $cargo = $_POST['cargo'] ?? 'funcionario';
            $supervisor_id = !empty($_POST['supervisor_id']) ? (int) $_POST['supervisor_id'] : null;

            if ($cargo !== 'funcionario') {
                $supervisor_id = null;
            }

            if ($nome === '' || $email === '' || $senha === '') {
                redirecionarComMensagem('Nome, e-mail e senha são obrigatórios!');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirecionarComMensagem('E-mail inválido!');
            }

            if (strlen($senha) < 6) {
                redirecionarComMensagem('A senha deve ter pelo menos 6 caracteres!');
            }

            $stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
            $stmtExiste->bindValue(':email', $email);
            $stmtExiste->execute();

            if ((int) $stmtExiste->fetchColumn() > 0) {
                redirecionarComMensagem('Já existe um usuário cadastrado com este e-mail.');
            }

            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nome, email, senha, cargo, supervisor_id, ativo)
                    VALUES (:nome, :email, :senha, :cargo, :supervisor_id, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nome', $nome);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':senha', $senhaHash);
            $stmt->bindValue(':cargo', $cargo);
            bindNullableInt($stmt, ':supervisor_id', $supervisor_id);
            $stmt->execute();

            redirecionarComMensagem('Usuário criado com sucesso!', 'sucesso');
        }

        if ($acao === 'editar') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $novaSenha = $_POST['nova_senha'] ?? '';
            $cargo = $_POST['cargo'] ?? 'funcionario';
            $supervisor_id = !empty($_POST['supervisor_id']) ? (int) $_POST['supervisor_id'] : null;

            if ($cargo !== 'funcionario') {
                $supervisor_id = null;
            }

            if ($id <= 0) {
                redirecionarComMensagem('Usuário inválido para edição.');
            }

            if ($nome === '' || $email === '') {
                redirecionarComMensagem('Nome e e-mail são obrigatórios!');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirecionarComMensagem('E-mail inválido!');
            }

            if ($novaSenha !== '' && strlen($novaSenha) < 6) {
                redirecionarComMensagem('A nova senha deve ter pelo menos 6 caracteres!');
            }

            $stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id");
            $stmtExiste->bindValue(':email', $email);
            $stmtExiste->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtExiste->execute();

            if ((int) $stmtExiste->fetchColumn() > 0) {
                redirecionarComMensagem('Outro usuário já está usando este e-mail.');
            }

            if ($novaSenha !== '') {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios
                        SET nome = :nome, email = :email, senha = :senha, cargo = :cargo, supervisor_id = :supervisor_id
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':senha', $senhaHash);
            } else {
                $sql = "UPDATE usuarios
                        SET nome = :nome, email = :email, cargo = :cargo, supervisor_id = :supervisor_id
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
            }

            $stmt->bindValue(':nome', $nome);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':cargo', $cargo);
            bindNullableInt($stmt, ':supervisor_id', $supervisor_id);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            redirecionarComMensagem('Usuário atualizado com sucesso!', 'sucesso');
        }

        if ($acao === 'toggle_ativo') {
            $id = (int) ($_POST['id'] ?? 0);
            $novoAtivo = (int) ($_POST['novo_ativo'] ?? 0);

            if ($id <= 0) {
                redirecionarComMensagem('Usuário inválido para alteração de status.');
            }

            $sql = "UPDATE usuarios SET ativo = :ativo WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':ativo', $novoAtivo, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            redirecionarComMensagem($novoAtivo === 1 ? 'Usuário ativado!' : 'Usuário desativado!', 'sucesso');
        }
    } catch (PDOException $e) {
        redirecionarComMensagem('Não foi possível salvar o usuário agora. Confira os dados e tente novamente.');
    }

    redirecionarComMensagem('Ação inválida.');
}

$busca = trim($_GET['busca'] ?? '');
$where = "WHERE 1=1";
$params = [];

if ($busca !== '') {
    $where .= " AND (nome LIKE :busca OR email LIKE :busca OR cargo LIKE :busca)";
    $params[':busca'] = "%$busca%";
}

$sqlUsuarios = "SELECT u.id, u.nome, u.email, u.cargo, u.supervisor_id, u.ativo, u.created_at, s.nome AS supervisor_nome
                FROM usuarios u
                LEFT JOIN usuarios s ON s.id = u.supervisor_id
                $where
                ORDER BY u.nome ASC";
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
foreach ($params as $key => $value) {
    $stmtUsuarios->bindValue($key, $value);
}
$stmtUsuarios->execute();
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

$sqlSupervisores = "SELECT id, nome FROM usuarios WHERE cargo = 'supervisor' ORDER BY nome ASC";
$stmtSupervisores = $pdo->prepare($sqlSupervisores);
$stmtSupervisores->execute();
$supervisores = $stmtSupervisores->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Usuários';
$activePage = 'usuarios';
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
    <?php include __DIR__ . '/includes/partials/header.php'; ?>

    <main class="user-management-container">
        <?php if ($mensagem): ?>
            <div class="mensagem <?= htmlspecialchars($tipo_mensagem ?? 'sucesso') ?>">
                <?= htmlspecialchars($mensagem) ?>
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
                    <?php foreach ($usuarios as $user): ?>
                        <?php
                        $iniciais = strtoupper(substr($user['nome'], 0, 1) . (strpos($user['nome'], ' ') ? substr(strrchr($user['nome'], ' '), 1, 1) : ''));
                        $cargoClass = strtolower($user['cargo']);
                        $supervisorNome = $user['supervisor_nome'] ? htmlspecialchars($user['supervisor_nome']) : '—';
                        ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="avatar <?= htmlspecialchars($cargoClass) ?>"><?= htmlspecialchars($iniciais) ?></div>
                                    <div class="user-details">
                                        <div class="user-name"><?= htmlspecialchars($user['nome']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-cargo <?= htmlspecialchars($cargoClass) ?>">
                                    <?= htmlspecialchars(ucfirst($user['cargo'])) ?>
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
                                    <button
                                        type="button"
                                        class="action-btn"
                                        title="Editar"
                                        data-user-id="<?= (int) $user['id'] ?>"
                                        data-user-nome="<?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-user-email="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-user-cargo="<?= htmlspecialchars($user['cargo'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-user-supervisor="<?= $user['supervisor_id'] !== null ? (int) $user['supervisor_id'] : '' ?>"
                                        onclick="abrirEditar(this)"
                                    >
                                        <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>

                                    <form method="POST">
                                        <input type="hidden" name="acao" value="toggle_ativo">
                                        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="novo_ativo" value="<?= $user['ativo'] ? 0 : 1 ?>">
                                        <button type="submit" class="action-btn" title="<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?>" onclick="return confirm('<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?> <?= htmlspecialchars(addslashes($user['nome']), ENT_QUOTES, 'UTF-8') ?>?')">
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

    <div id="modalCriar" class="modal hidden">
        <div class="modal-content user-modal-content">
            <div class="user-modal-header">
                <h3>Criar Novo Usuário</h3>
                <p>Cadastre um novo acesso com cargo e supervisor definidos.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="criar">

                <div class="user-form-grid">
                    <div class="form-field form-field-full">
                        <label for="criarNome">Nome Completo</label>
                        <input type="text" name="nome" id="criarNome" required>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="criarEmail">E-mail</label>
                        <input type="email" name="email" id="criarEmail" required>
                    </div>

                    <div class="form-field">
                        <label for="criarSenha">Senha</label>
                        <input type="password" name="senha" id="criarSenha" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>

                    <div class="form-field">
                        <label for="cargoCriar">Cargo</label>
                        <select name="cargo" id="cargoCriar" onchange="toggleSupervisor('cargoCriar', 'divSupervisorCriar')">
                            <option value="funcionario">Funcionário</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>

                    <div id="divSupervisorCriar" class="form-field form-field-full">
                        <label for="criarSupervisor">Supervisor</label>
                        <select name="supervisor_id" id="criarSupervisor">
                            <option value="">Nenhum</option>
                            <?php foreach ($supervisores as $sup): ?>
                                <option value="<?= (int) $sup['id'] ?>"><?= htmlspecialchars($sup['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalCriar')">Cancelar</button>
                    <button type="submit">Criar Usuário</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditar" class="modal hidden">
        <div class="modal-content user-modal-content">
            <div class="user-modal-header">
                <h3>Editar Usuário</h3>
                <p>Atualize dados, permissões e vínculo hierárquico sem sair da listagem.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editId">

                <div class="user-form-grid">
                    <div class="form-field form-field-full">
                        <label for="editNome">Nome Completo</label>
                        <input type="text" name="nome" id="editNome" required>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="editEmail">E-mail</label>
                        <input type="email" name="email" id="editEmail" required>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="editSenha">Nova Senha <small class="field-hint">(deixe em branco para manter a atual)</small></label>
                        <input type="password" name="nova_senha" id="editSenha" minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>

                    <div class="form-field">
                        <label for="editCargo">Cargo</label>
                        <select name="cargo" id="editCargo" onchange="toggleSupervisor('editCargo', 'divSupervisorEdit')">
                            <option value="funcionario">Funcionário</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="administrador">Administrador</option>
                        </select>
                    </div>

                    <div id="divSupervisorEdit" class="form-field">
                        <label for="editSupervisor">Supervisor</label>
                        <select name="supervisor_id" id="editSupervisor">
                            <option value="">Nenhum</option>
                            <?php foreach ($supervisores as $sup): ?>
                                <option value="<?= (int) $sup['id'] ?>"><?= htmlspecialchars($sup['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
        };

        function toggleSupervisor(cargoId, divId) {
            const cargo = document.getElementById(cargoId).value;
            const divSupervisor = document.getElementById(divId);
            divSupervisor.style.display = cargo === 'funcionario' ? 'block' : 'none';
        }

        function abrirEditar(button) {
            document.getElementById('editId').value = button.dataset.userId || '';
            document.getElementById('editNome').value = button.dataset.userNome || '';
            document.getElementById('editEmail').value = button.dataset.userEmail || '';
            document.getElementById('editSenha').value = '';
            document.getElementById('editCargo').value = button.dataset.userCargo || 'funcionario';
            document.getElementById('editSupervisor').value = button.dataset.userSupervisor || '';

            toggleSupervisor('editCargo', 'divSupervisorEdit');
            abrirModal('modalEditar');
        }

        toggleSupervisor('cargoCriar', 'divSupervisorCriar');
        toggleSupervisor('editCargo', 'divSupervisorEdit');
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
