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

// Buscar cargo do usuário logado
$sqlUsuario = "SELECT cargo FROM usuarios WHERE id = :usuario_id";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmtUsuario->execute();
$usuarioLogado = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

// Só administrador pode acessar
if ($usuarioLogado['cargo'] !== 'administrador') {
    $_SESSION['mensagem'] = 'Você não tem permissão para acessar esta página.';
    $_SESSION['tipo_mensagem'] = 'erro';
    header('Location: tarefas.php');
    exit;
}

// Mensagens flash
$mensagem = $_SESSION['mensagem'] ?? null;
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

// Gerar senha aleatória
function gerarSenha($tamanho = 10) {
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    $senha = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $senha;
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $cargo = $_POST['cargo'] ?? 'funcionario';
        $supervisor_id = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if ($cargo !== 'funcionario') {
            $supervisor_id = null;
        }

        if ($nome === '' || $email === '') {
            $_SESSION['mensagem'] = 'Nome e e-mail são obrigatórios!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensagem'] = 'E-mail inválido!';
            $_SESSION['tipo_mensagem'] = 'erro';
        } else {
            if ($acao === 'criar') {
                // Gera senha legível e depois faz o hash para salvar
                $senhaLegivel = gerarSenha(10);
                $senhaHash = password_hash($senhaLegivel, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO usuarios (nome, email, senha, cargo, supervisor_id, ativo) 
                        VALUES (:nome, :email, :senha, :cargo, :supervisor_id, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':senha', $senhaHash); // Salva o HASH, não a senha
                $mensagemExtra = " Senha gerada: <strong>$senhaLegivel</strong> (anote e informe ao usuário!)";
            } else {
                $sql = "UPDATE usuarios SET nome = :nome, email = :email, cargo = :cargo, supervisor_id = :supervisor_id 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $mensagemExtra = '';
            }

            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':cargo', $cargo);
            $stmt->bindParam(':supervisor_id', $supervisor_id, PDO::PARAM_INT | PDO::PARAM_NULL);
            $stmt->execute();

            $_SESSION['mensagem'] = ($acao === 'criar' ? 'Usuário criado com sucesso!' : 'Usuário atualizado!') . ($mensagemExtra ?? '');
            $_SESSION['tipo_mensagem'] = 'sucesso';
        }
    } elseif ($acao === 'reset_senha') {
        $id = (int)$_POST['id'];
        
        // Gera senha legível e depois faz o hash para salvar
        $senhaLegivel = gerarSenha(10);
        $senhaHash = password_hash($senhaLegivel, PASSWORD_DEFAULT);
        
        $sql = "UPDATE usuarios SET senha = :senha WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':senha', $senhaHash); // Salva o HASH
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['mensagem'] = "Senha resetada! Nova senha: <strong>$senhaLegivel</strong> (entregue ao usuário!)";
        $_SESSION['tipo_mensagem'] = 'sucesso';
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
    <title>Gerenciar Usuários | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="header-content">
            <div>
                <h1>Gerenciar Usuários</h1>
                <p>Óticas Mercês - Controle interno</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn-gerenciar">🏠 Dashboard</a>
            </div>            
            <div class="header-actions">
                <a href="tarefas.php" class="btn-voltar">← Voltar para Tarefas</a>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>
    </header>

    <main class="user-management-container">

        <!-- Mensagem de feedback -->
        <?php if ($mensagem): ?>
            <div class="mensagem <?= $tipo_mensagem ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <!-- Cabeçalho -->
        <div class="page-header">
            <h1>Gerenciamento de Usuários</h1>
            <p>Controle acessos, cargos e hierarquia da equipe.</p>
        </div>

        <!-- Barra de busca + botão -->
        <div class="controls-bar">
            <form method="GET" class="flex-1">
                <input type="text" name="busca" class="search-input" placeholder="Buscar por nome, e-mail ou cargo..." value="<?= htmlspecialchars($busca) ?>">
            </form>
            <button onclick="abrirModal('modalCriar')" class="btn-add">
                <span class="plus">+</span> Novo Usuário
            </button>
        </div>

        <!-- Tabela -->
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
                                    <button class="action-btn" title="Editar" onclick="abrirEditar(<?= $user['id'] ?>, '<?= addslashes($user['nome']) ?>', '<?= addslashes($user['email']) ?>', '<?= $user['cargo'] ?>', <?= $user['supervisor_id'] ?? 'null' ?>)">
                                        <svg class="icon-action" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="reset_senha">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="action-btn" title="Resetar Senha" onclick="return confirm('Resetar senha de <?= addslashes($user['nome']) ?>?')">
                                            <svg class="icon-action" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25l-2.25 2.25-1.5-1.5L6 18.75l-2.25-2.25L3.75 15l1.5-1.5L5.25 12l2.25-2.25c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                            </svg>
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="toggle_ativo">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="novo_ativo" value="<?= $user['ativo'] ? 0 : 1 ?>">
                                        <button type="submit" class="action-btn" title="<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?>" onclick="return confirm('<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?> <?= addslashes($user['nome']) ?>?')">
                                            <?php if ($user['ativo']): ?>
                                                <svg class="icon-action" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                                </svg>
                                            <?php else: ?>
                                                <svg class="icon-action" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
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