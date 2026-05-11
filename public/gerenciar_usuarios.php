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
require_once __DIR__ . '/includes/auth_helpers.php';

function redirecionarComMensagem(string $mensagem, string $tipo = 'erro'): void
{
    $_SESSION['mensagem'] = $mensagem;
    $_SESSION['tipo_mensagem'] = $tipo;
    header('Location: gerenciar_usuarios.php');
    exit;
}

function normalizarIdOpcional(mixed $valor): ?int
{
    $id = (int) ($valor ?? 0);
    return $id > 0 ? $id : null;
}

function validarSubEquipeDaConta(PDO $pdo, ?int $subEquipeId, int $titularId): ?int
{
    if ($subEquipeId === null) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM sub_equipes WHERE id = :id AND titular_id = :titular_id LIMIT 1");
    $stmt->execute([
        ':id' => $subEquipeId,
        ':titular_id' => $titularId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ? $subEquipeId : null;
}

function validarMembroDaConta(PDO $pdo, ?int $membroId, int $titularId): ?int
{
    if ($membroId === null) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE (id = :titular_id OR titular_id = :titular_id)
          AND id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $membroId,
        ':titular_id' => $titularId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ? $membroId : null;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$usuarioLogado = buscarUsuarioAutenticado($pdo, $usuarioId);

if (!$usuarioLogado) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!usuarioEhTitular($usuarioLogado)) {
    $_SESSION['mensagem'] = 'Apenas a conta titular pode gerenciar a equipe.';
    $_SESSION['tipo_mensagem'] = 'erro';
    header('Location: tarefas.php');
    exit;
}

$titularId = obterTitularIdUsuario($usuarioLogado);
$nomeUsuario = $usuarioLogado['nome'];
$cargoUsuario = $usuarioLogado['cargo'];
$nomeEquipe = obterNomeEquipe($usuarioLogado);

$mensagem = $_SESSION['mensagem'] ?? null;
$tipoMensagem = $_SESSION['tipo_mensagem'] ?? null;
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'criar') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $subEquipeId = validarSubEquipeDaConta($pdo, normalizarIdOpcional($_POST['sub_equipe_id'] ?? null), $titularId);
            $funcaoEquipe = trim($_POST['funcao_equipe'] ?? '');

            if ($nome === '' || $email === '' || $senha === '') {
                redirecionarComMensagem('Nome, e-mail e senha são obrigatórios.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirecionarComMensagem('E-mail inválido.');
            }

            if (strlen($senha) < 6) {
                redirecionarComMensagem('A senha deve ter pelo menos 6 caracteres.');
            }

            $stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
            $stmtExiste->execute([':email' => $email]);
            if ((int) $stmtExiste->fetchColumn() > 0) {
                redirecionarComMensagem('Já existe uma pessoa cadastrada com este e-mail.');
            }

            $stmtCriar = $pdo->prepare(
                "INSERT INTO usuarios (nome, equipe_nome, email, senha, cargo, supervisor_id, titular_id, sub_equipe_id, funcao_equipe, ativo)
                 VALUES (:nome, NULL, :email, :senha, 'funcionario', :supervisor_id, :titular_id, :sub_equipe_id, :funcao_equipe, 1)"
            );
            $stmtCriar->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                ':supervisor_id' => $titularId,
                ':titular_id' => $titularId,
                ':sub_equipe_id' => $subEquipeId,
                ':funcao_equipe' => $funcaoEquipe !== '' ? $funcaoEquipe : null,
            ]);

            redirecionarComMensagem('Membro criado com sucesso.', 'sucesso');
        }

        if ($acao === 'editar') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $novaSenha = $_POST['nova_senha'] ?? '';
            $subEquipeId = validarSubEquipeDaConta($pdo, normalizarIdOpcional($_POST['sub_equipe_id'] ?? null), $titularId);
            $funcaoEquipe = trim($_POST['funcao_equipe'] ?? '');

            if ($id <= 0 || $id === $titularId) {
                redirecionarComMensagem('Membro inválido para edição.');
            }

            if ($nome === '' || $email === '') {
                redirecionarComMensagem('Nome e e-mail são obrigatórios.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirecionarComMensagem('E-mail inválido.');
            }

            if ($novaSenha !== '' && strlen($novaSenha) < 6) {
                redirecionarComMensagem('A nova senha deve ter pelo menos 6 caracteres.');
            }

            $stmtEquipe = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND titular_id = :titular_id LIMIT 1");
            $stmtEquipe->execute([':id' => $id, ':titular_id' => $titularId]);
            if (!$stmtEquipe->fetch(PDO::FETCH_ASSOC)) {
                redirecionarComMensagem('Esse membro não pertence à sua equipe.');
            }

            $stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id <> :id");
            $stmtExiste->execute([':email' => $email, ':id' => $id]);
            if ((int) $stmtExiste->fetchColumn() > 0) {
                redirecionarComMensagem('Outra pessoa já está usando este e-mail.');
            }

            if ($novaSenha !== '') {
                $stmtEditar = $pdo->prepare(
                    "UPDATE usuarios
                     SET nome = :nome, email = :email, senha = :senha, supervisor_id = :supervisor_id, titular_id = :titular_id, sub_equipe_id = :sub_equipe_id, funcao_equipe = :funcao_equipe
                     WHERE id = :id"
                );
                $stmtEditar->bindValue(':senha', password_hash($novaSenha, PASSWORD_DEFAULT));
            } else {
                $stmtEditar = $pdo->prepare(
                    "UPDATE usuarios
                     SET nome = :nome, email = :email, supervisor_id = :supervisor_id, titular_id = :titular_id, sub_equipe_id = :sub_equipe_id, funcao_equipe = :funcao_equipe
                     WHERE id = :id"
                );
            }

            $stmtEditar->bindValue(':nome', $nome);
            $stmtEditar->bindValue(':email', $email);
            $stmtEditar->bindValue(':supervisor_id', $titularId, PDO::PARAM_INT);
            $stmtEditar->bindValue(':titular_id', $titularId, PDO::PARAM_INT);
            $stmtEditar->bindValue(':sub_equipe_id', $subEquipeId, $subEquipeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtEditar->bindValue(':funcao_equipe', $funcaoEquipe !== '' ? $funcaoEquipe : null);
            $stmtEditar->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtEditar->execute();

            redirecionarComMensagem('Membro atualizado com sucesso.', 'sucesso');
        }

        if ($acao === 'criar_sub_equipe') {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $liderId = validarMembroDaConta($pdo, normalizarIdOpcional($_POST['lider_id'] ?? null), $titularId);

            if ($nome === '') {
                redirecionarComMensagem('O nome da sub-equipe é obrigatório.');
            }

            $stmtSubEquipe = $pdo->prepare(
                "INSERT INTO sub_equipes (titular_id, nome, descricao, lider_id, ativo)
                 VALUES (:titular_id, :nome, :descricao, :lider_id, 1)"
            );
            $stmtSubEquipe->execute([
                ':titular_id' => $titularId,
                ':nome' => $nome,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':lider_id' => $liderId,
            ]);

            redirecionarComMensagem('Sub-equipe criada com sucesso.', 'sucesso');
        }

        if ($acao === 'editar_sub_equipe') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $liderId = validarMembroDaConta($pdo, normalizarIdOpcional($_POST['lider_id'] ?? null), $titularId);
            $ativo = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

            if ($id <= 0 || validarSubEquipeDaConta($pdo, $id, $titularId) === null) {
                redirecionarComMensagem('Sub-equipe inválida.');
            }

            if ($nome === '') {
                redirecionarComMensagem('O nome da sub-equipe é obrigatório.');
            }

            $stmtSubEquipe = $pdo->prepare(
                "UPDATE sub_equipes
                 SET nome = :nome, descricao = :descricao, lider_id = :lider_id, ativo = :ativo
                 WHERE id = :id AND titular_id = :titular_id"
            );
            $stmtSubEquipe->execute([
                ':nome' => $nome,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':lider_id' => $liderId,
                ':ativo' => $ativo,
                ':id' => $id,
                ':titular_id' => $titularId,
            ]);

            redirecionarComMensagem('Sub-equipe atualizada com sucesso.', 'sucesso');
        }

        if ($acao === 'toggle_ativo') {
            $id = (int) ($_POST['id'] ?? 0);
            $novoAtivo = (int) ($_POST['novo_ativo'] ?? 0);

            if ($id <= 0 || $id === $titularId) {
                redirecionarComMensagem('Membro inválido para alteração de status.');
            }

            $stmtEquipe = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND titular_id = :titular_id LIMIT 1");
            $stmtEquipe->execute([':id' => $id, ':titular_id' => $titularId]);
            if (!$stmtEquipe->fetch(PDO::FETCH_ASSOC)) {
                redirecionarComMensagem('Esse membro não pertence à sua equipe.');
            }

            $stmtStatus = $pdo->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id");
            $stmtStatus->execute([':ativo' => $novoAtivo, ':id' => $id]);

            redirecionarComMensagem($novoAtivo === 1 ? 'Membro ativado.' : 'Membro desativado.', 'sucesso');
        }
    } catch (PDOException $e) {
        redirecionarComMensagem('Não foi possível salvar agora. Confira os dados e tente novamente.');
    }

    redirecionarComMensagem('Ação inválida.');
}

$busca = trim($_GET['busca'] ?? '');
$usuarios = buscarUsuariosDaEquipe($pdo, $titularId);
$subEquipes = buscarSubEquipesDaConta($pdo, $titularId);

if ($busca !== '') {
    $termoBusca = strtolower($busca);
    $usuarios = array_values(array_filter($usuarios, static function (array $user) use ($termoBusca): bool {
        $campos = [
            $user['nome'] ?? '',
            $user['email'] ?? '',
            $user['funcao_equipe'] ?? '',
            $user['sub_equipe_nome'] ?? '',
            montarRotuloPapelEquipe($user),
        ];

        return str_contains(strtolower(implode(' ', $campos)), $termoBusca);
    }));
}

$pageTitle = 'Equipe';
$activePage = 'usuarios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Equipe | TaskBlue</title>
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
            <div class="mensagem <?= htmlspecialchars($tipoMensagem ?? 'sucesso') ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2><?= htmlspecialchars($nomeEquipe) ?></h2>
            <p>Organize a equipe em sub-equipes, defina líderes e limite a visibilidade das tarefas por área.</p>
        </div>

        <div class="controls-bar">
            <form method="GET" class="flex-1">
                <input type="text" name="busca" class="search-input" placeholder="Buscar por nome, e-mail, função ou sub-equipe..." value="<?= htmlspecialchars($busca) ?>">
            </form>
            <button type="button" onclick="abrirModal('modalCriarSubEquipe')" class="btn-add btn-add-secondary">
                <span class="plus">+</span> Nova sub-equipe
            </button>
            <button type="button" onclick="abrirModal('modalCriar')" class="btn-add">
                <span class="plus">+</span> Novo membro
            </button>
        </div>

        <section class="hierarchy-section">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">Estrutura</span>
                    <h3>Sub-equipes</h3>
                </div>
            </div>

            <?php if (empty($subEquipes)): ?>
                <div class="empty-state compact">
                    <p>Nenhuma sub-equipe criada ainda.</p>
                    <span>Crie áreas como Financeiro, RH, Marketing ou qualquer grupo do seu fluxo.</span>
                </div>
            <?php else: ?>
                <div class="subteam-grid">
                    <?php foreach ($subEquipes as $subEquipe): ?>
                        <?php
                        $membrosSubEquipe = array_values(array_filter(
                            buscarUsuariosDaEquipe($pdo, $titularId),
                            static fn (array $user): bool => (int) ($user['sub_equipe_id'] ?? 0) === (int) $subEquipe['id']
                        ));
                        ?>
                        <article class="subteam-card <?= (int) $subEquipe['ativo'] === 1 ? '' : 'inactive' ?>">
                            <div>
                                <h4><?= htmlspecialchars($subEquipe['nome']) ?></h4>
                                <p><?= htmlspecialchars($subEquipe['descricao'] ?: 'Sem descrição') ?></p>
                            </div>
                            <div class="subteam-meta">
                                <span>Líder: <?= htmlspecialchars($subEquipe['lider_nome'] ?: 'Não definido') ?></span>
                                <span><?= count($membrosSubEquipe) ?> <?= count($membrosSubEquipe) === 1 ? 'membro' : 'membros' ?></span>
                            </div>
                            <button
                                type="button"
                                class="subteam-edit"
                                data-sub-id="<?= (int) $subEquipe['id'] ?>"
                                data-sub-nome="<?= htmlspecialchars($subEquipe['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                data-sub-descricao="<?= htmlspecialchars($subEquipe['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-sub-lider="<?= (int) ($subEquipe['lider_id'] ?? 0) ?>"
                                data-sub-ativo="<?= (int) $subEquipe['ativo'] ?>"
                                onclick="abrirEditarSubEquipe(this)"
                            >
                                Editar
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="table-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Pessoa</th>
                        <th>Função</th>
                        <th>Sub-equipe</th>
                        <th>Papel</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $user): ?>
                        <?php
                        $iniciais = strtoupper(substr($user['nome'], 0, 1) . (strpos($user['nome'], ' ') ? substr(strrchr($user['nome'], ' '), 1, 1) : ''));
                        $cargoClass = strtolower($user['cargo']);
                        $ehTitular = (int) $user['id'] === $titularId;
                        $papelEquipe = montarRotuloPapelEquipe($user);
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
                                <?= htmlspecialchars($user['funcao_equipe'] ?: ($ehTitular ? 'Titular da conta' : 'Não definida')) ?>
                            </td>
                            <td><?= htmlspecialchars($user['sub_equipe_nome'] ?: 'Equipe principal') ?></td>
                            <td>
                                <span class="badge-cargo <?= htmlspecialchars($cargoClass) ?>">
                                    <?= htmlspecialchars($papelEquipe) ?>
                                </span>
                            </td>
                            <td>
                                <div class="status">
                                    <div class="status-dot <?= $user['ativo'] ? 'ativo' : 'inativo' ?>"></div>
                                    <?= $user['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($ehTitular): ?>
                                    <div class="actions">
                                        <a href="conta.php" class="action-btn" title="Editar conta titular">
                                            <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="actions">
                                        <button
                                            type="button"
                                            class="action-btn"
                                            title="Editar"
                                            data-user-id="<?= (int) $user['id'] ?>"
                                            data-user-nome="<?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-user-email="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-user-sub-equipe="<?= (int) ($user['sub_equipe_id'] ?? 0) ?>"
                                            data-user-funcao="<?= htmlspecialchars($user['funcao_equipe'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="abrirEditar(this)"
                                        >
                                            <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>

                                        <form method="POST">
                                            <input type="hidden" name="acao" value="toggle_ativo">
                                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                            <input type="hidden" name="novo_ativo" value="<?= $user['ativo'] ? 0 : 1 ?>">
                                            <button type="submit" class="action-btn" title="<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                                <svg class="icon-action" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <?php if ($user['ativo']): ?>
                                                        <line x1="23" y1="11" x2="17" y2="11"/>
                                                    <?php else: ?>
                                                        <line x1="17" y1="11" x2="23" y2="11"/>
                                                        <line x1="20" y1="8" x2="20" y2="14"/>
                                                    <?php endif; ?>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalCriarSubEquipe" class="modal user-modal hidden">
        <div class="modal-content user-modal-content">
            <div class="user-modal-header">
                <h3>Criar sub-equipe</h3>
                <p>Use sub-equipes para separar áreas, setores, squads ou qualquer grupo com visibilidade própria.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="criar_sub_equipe">
                <div class="user-form-grid">
                    <div class="form-field form-field-full">
                        <label for="criarSubNome">Nome</label>
                        <input type="text" name="nome" id="criarSubNome" required placeholder="Ex: Financeiro, RH, Marketing">
                    </div>
                    <div class="form-field form-field-full">
                        <label for="criarSubDescricao">Descrição</label>
                        <input type="text" name="descricao" id="criarSubDescricao" placeholder="Ex: Rotinas financeiras e cobranças">
                    </div>
                    <div class="form-field form-field-full">
                        <label for="criarSubLider">Líder</label>
                        <select name="lider_id" id="criarSubLider">
                            <option value="">Sem líder definido</option>
                            <?php foreach (buscarUsuariosDaEquipe($pdo, $titularId, true) as $membro): ?>
                                <option value="<?= (int) $membro['id'] ?>"><?= htmlspecialchars($membro['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalCriarSubEquipe')">Cancelar</button>
                    <button type="submit">Criar sub-equipe</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditarSubEquipe" class="modal user-modal hidden">
        <div class="modal-content user-modal-content">
            <div class="user-modal-header">
                <h3>Editar sub-equipe</h3>
                <p>Alterar o líder muda quem pode monitorar as tarefas desse grupo.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="editar_sub_equipe">
                <input type="hidden" name="id" id="editSubId">
                <div class="user-form-grid">
                    <div class="form-field form-field-full">
                        <label for="editSubNome">Nome</label>
                        <input type="text" name="nome" id="editSubNome" required>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editSubDescricao">Descrição</label>
                        <input type="text" name="descricao" id="editSubDescricao">
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editSubLider">Líder</label>
                        <select name="lider_id" id="editSubLider">
                            <option value="">Sem líder definido</option>
                            <?php foreach (buscarUsuariosDaEquipe($pdo, $titularId, true) as $membro): ?>
                                <option value="<?= (int) $membro['id'] ?>"><?= htmlspecialchars($membro['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editSubAtivo">Status</label>
                        <select name="ativo" id="editSubAtivo">
                            <option value="1">Ativa</option>
                            <option value="0">Inativa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalEditarSubEquipe')">Cancelar</button>
                    <button type="submit">Salvar sub-equipe</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalCriar" class="modal user-modal hidden">
        <div class="modal-content user-modal-content">
            <div class="user-modal-header">
                <h3>Criar novo membro</h3>
                <p>Esse acesso ficará vinculado a <?= htmlspecialchars($nomeEquipe) ?> e poderá entrar com login próprio.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="user-form-grid">
                    <div class="form-field form-field-full">
                        <label for="criarNome">Nome completo</label>
                        <input type="text" name="nome" id="criarNome" required>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="criarEmail">E-mail</label>
                        <input type="email" name="email" id="criarEmail" required>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="criarFuncao">Função na equipe</label>
                        <input type="text" name="funcao_equipe" id="criarFuncao" placeholder="Ex: Designer, Analista fiscal, Gestor de tráfego">
                    </div>
                    <div class="form-field form-field-full">
                        <label for="criarSubEquipe">Sub-equipe</label>
                        <select name="sub_equipe_id" id="criarSubEquipe">
                            <option value="">Equipe principal</option>
                            <?php foreach ($subEquipes as $subEquipe): ?>
                                <option value="<?= (int) $subEquipe['id'] ?>"><?= htmlspecialchars($subEquipe['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="criarSenha">Senha</label>
                        <input type="password" name="senha" id="criarSenha" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalCriar')">Cancelar</button>
                    <button type="submit">Criar membro</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalEditar" class="modal user-modal hidden">
        <div class="modal-content user-modal-content">
            <div class="user-modal-header">
                <h3>Editar membro</h3>
                <p>Atualize os dados de acesso desse membro da equipe <?= htmlspecialchars($nomeEquipe) ?>.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editId">
                <div class="user-form-grid">
                    <div class="form-field form-field-full">
                        <label for="editNome">Nome completo</label>
                        <input type="text" name="nome" id="editNome" required>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editEmail">E-mail</label>
                        <input type="email" name="email" id="editEmail" required>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editFuncao">Função na equipe</label>
                        <input type="text" name="funcao_equipe" id="editFuncao" placeholder="Ex: Designer, Analista fiscal, Gestor de tráfego">
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editSubEquipe">Sub-equipe</label>
                        <select name="sub_equipe_id" id="editSubEquipe">
                            <option value="">Equipe principal</option>
                            <?php foreach ($subEquipes as $subEquipe): ?>
                                <option value="<?= (int) $subEquipe['id'] ?>"><?= htmlspecialchars($subEquipe['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="editSenha">Nova senha <small class="field-hint">(deixe em branco para manter a atual)</small></label>
                        <input type="password" name="nova_senha" id="editSenha" minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="fecharModal('modalEditar')">Cancelar</button>
                    <button type="submit">Salvar alterações</button>
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

        function abrirEditar(button) {
            document.getElementById('editId').value = button.dataset.userId || '';
            document.getElementById('editNome').value = button.dataset.userNome || '';
            document.getElementById('editEmail').value = button.dataset.userEmail || '';
            document.getElementById('editFuncao').value = button.dataset.userFuncao || '';
            document.getElementById('editSubEquipe').value = button.dataset.userSubEquipe || '';
            document.getElementById('editSenha').value = '';
            abrirModal('modalEditar');
        }

        function abrirEditarSubEquipe(button) {
            document.getElementById('editSubId').value = button.dataset.subId || '';
            document.getElementById('editSubNome').value = button.dataset.subNome || '';
            document.getElementById('editSubDescricao').value = button.dataset.subDescricao || '';
            document.getElementById('editSubLider').value = button.dataset.subLider === '0' ? '' : (button.dataset.subLider || '');
            document.getElementById('editSubAtivo').value = button.dataset.subAtivo || '1';
            abrirModal('modalEditarSubEquipe');
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
