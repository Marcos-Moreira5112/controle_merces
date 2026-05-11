<?php

function garantirEstruturaHierarquia(PDO $pdo): void
{
    static $executado = false;

    if ($executado) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sub_equipes (
            id int(11) NOT NULL AUTO_INCREMENT,
            titular_id int(11) NOT NULL,
            nome varchar(150) NOT NULL,
            descricao varchar(255) DEFAULT NULL,
            lider_id int(11) DEFAULT NULL,
            ativo tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY idx_sub_equipes_titular_id (titular_id),
            KEY idx_sub_equipes_lider_id (lider_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    adicionarColunaSeNaoExistir($pdo, 'usuarios', 'sub_equipe_id', 'int(11) DEFAULT NULL AFTER titular_id');
    adicionarColunaSeNaoExistir($pdo, 'usuarios', 'funcao_equipe', 'varchar(120) DEFAULT NULL AFTER sub_equipe_id');

    $executado = true;
}

function adicionarColunaSeNaoExistir(PDO $pdo, string $tabela, string $coluna, string $definicao): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :tabela
          AND COLUMN_NAME = :coluna
    ");
    $stmt->execute([
        ':tabela' => $tabela,
        ':coluna' => $coluna,
    ]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
    }
}

function buscarUsuarioAutenticado(PDO $pdo, int $usuarioId): array|false
{
    garantirEstruturaHierarquia($pdo);

    $stmt = $pdo->prepare(
        "SELECT
            u.id,
            u.nome,
            u.equipe_nome,
            u.email,
            u.cargo,
            u.supervisor_id,
            u.titular_id,
            u.sub_equipe_id,
            u.funcao_equipe,
            u.ativo,
            u.created_at,
            t.nome AS titular_nome,
            t.equipe_nome AS titular_equipe_nome,
            se.nome AS sub_equipe_nome,
            se.lider_id AS sub_equipe_lider_id,
            (
                SELECT COUNT(*)
                FROM sub_equipes se_lider
                WHERE se_lider.titular_id = COALESCE(u.titular_id, u.id)
                  AND se_lider.lider_id = u.id
                  AND se_lider.ativo = 1
            ) AS total_sub_equipes_lideradas
         FROM usuarios u
         LEFT JOIN usuarios t ON t.id = u.titular_id
         LEFT JOIN sub_equipes se ON se.id = u.sub_equipe_id
         WHERE u.id = ?
         LIMIT 1"
    );
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        return false;
    }

    if (empty($usuario['titular_id'])) {
        if (($usuario['cargo'] ?? '') === 'administrador') {
            $usuario['titular_id'] = (int) $usuario['id'];
            $usuario['titular_nome'] = $usuario['nome'];
            $usuario['titular_equipe_nome'] = $usuario['equipe_nome'];
        } elseif (!empty($usuario['supervisor_id'])) {
            $usuario['titular_id'] = (int) $usuario['supervisor_id'];
        }
    } else {
        $usuario['titular_id'] = (int) $usuario['titular_id'];
    }

    return $usuario;
}

function obterTitularIdUsuario(array $usuario): int
{
    return (int) ($usuario['titular_id'] ?? $usuario['id'] ?? 0);
}

function usuarioEhTitular(array $usuario): bool
{
    return ($usuario['cargo'] ?? '') === 'administrador';
}

function montarRotuloCargo(string $cargo): string
{
    return $cargo === 'administrador' ? 'Titular' : 'Membro';
}

function montarRotuloPapelEquipe(array $usuario): string
{
    if (usuarioEhTitular($usuario)) {
        return 'Titular';
    }

    if ((int) ($usuario['total_sub_equipes_lideradas'] ?? 0) > 0) {
        return 'Líder';
    }

    return 'Membro';
}

function obterNomeEquipe(array $usuario): string
{
    if (usuarioEhTitular($usuario)) {
        $nomeEquipe = trim((string) ($usuario['equipe_nome'] ?? ''));
        return $nomeEquipe !== '' ? $nomeEquipe : $usuario['nome'];
    }

    $nomeEquipeTitular = trim((string) ($usuario['titular_equipe_nome'] ?? ''));
    if ($nomeEquipeTitular !== '') {
        return $nomeEquipeTitular;
    }

    $nomeTitular = trim((string) ($usuario['titular_nome'] ?? ''));
    if ($nomeTitular !== '') {
        return 'Equipe de ' . $nomeTitular;
    }

    return 'Equipe';
}

function buscarUsuariosDaEquipe(PDO $pdo, int $titularId, bool $somenteAtivos = false): array
{
    garantirEstruturaHierarquia($pdo);

    $sql = "
        SELECT
            u.id,
            u.nome,
            u.equipe_nome,
            u.email,
            u.cargo,
            u.supervisor_id,
            u.titular_id,
            u.sub_equipe_id,
            u.funcao_equipe,
            u.ativo,
            u.created_at,
            se.nome AS sub_equipe_nome,
            (
                SELECT COUNT(*)
                FROM sub_equipes se_lider
                WHERE se_lider.titular_id = :titular_id_lider
                  AND se_lider.lider_id = u.id
                  AND se_lider.ativo = 1
            ) AS total_sub_equipes_lideradas
        FROM usuarios u
        LEFT JOIN sub_equipes se ON se.id = u.sub_equipe_id
        WHERE (u.id = :titular_id OR u.titular_id = :titular_id)
    ";

    if ($somenteAtivos) {
        $sql .= " AND u.ativo = 1";
    }

    $sql .= " ORDER BY CASE WHEN u.id = :titular_id_ordem THEN 0 ELSE 1 END, se.nome ASC, u.nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':titular_id', $titularId, PDO::PARAM_INT);
    $stmt->bindValue(':titular_id_lider', $titularId, PDO::PARAM_INT);
    $stmt->bindValue(':titular_id_ordem', $titularId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarSubEquipesDaConta(PDO $pdo, int $titularId, bool $somenteAtivas = false): array
{
    garantirEstruturaHierarquia($pdo);

    $sql = "
        SELECT se.*, u.nome AS lider_nome
        FROM sub_equipes se
        LEFT JOIN usuarios u ON u.id = se.lider_id
        WHERE se.titular_id = :titular_id
    ";

    if ($somenteAtivas) {
        $sql .= " AND se.ativo = 1";
    }

    $sql .= " ORDER BY se.nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':titular_id' => $titularId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarSubEquipeIdsLideradas(PDO $pdo, int $usuarioId, int $titularId): array
{
    garantirEstruturaHierarquia($pdo);

    $stmt = $pdo->prepare("
        SELECT id
        FROM sub_equipes
        WHERE titular_id = :titular_id
          AND lider_id = :usuario_id
          AND ativo = 1
    ");
    $stmt->execute([
        ':titular_id' => $titularId,
        ':usuario_id' => $usuarioId,
    ]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function buscarIdsUsuariosVisiveis(PDO $pdo, array $usuario, bool $somenteAtivos = false): array
{
    garantirEstruturaHierarquia($pdo);

    $usuarioId = (int) ($usuario['id'] ?? 0);
    $titularId = obterTitularIdUsuario($usuario);

    if ($usuarioId <= 0 || $titularId <= 0) {
        return [];
    }

    if (usuarioEhTitular($usuario)) {
        $usuarios = buscarUsuariosDaEquipe($pdo, $titularId, $somenteAtivos);
        return array_map(static fn (array $item): int => (int) $item['id'], $usuarios);
    }

    $idsVisiveis = [$usuarioId];
    $subEquipeIds = buscarSubEquipeIdsLideradas($pdo, $usuarioId, $titularId);

    if (!empty($subEquipeIds)) {
        $placeholders = implode(', ', array_fill(0, count($subEquipeIds), '?'));
        $params = array_merge([$titularId], $subEquipeIds);

        $sql = "
            SELECT id
            FROM usuarios
            WHERE titular_id = ?
              AND sub_equipe_id IN ($placeholders)
        ";

        if ($somenteAtivos) {
            $sql .= " AND ativo = 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $idsVisiveis = array_merge($idsVisiveis, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    return array_values(array_unique($idsVisiveis));
}

function usuarioPodeAcessarTarefa(PDO $pdo, array $usuario, array $tarefa): bool
{
    $usuarioId = (int) ($usuario['id'] ?? 0);
    $titularId = obterTitularIdUsuario($usuario);
    $tarefaTitularId = (int) ($tarefa['titular_id'] ?? 0);

    if ($titularId > 0 && $tarefaTitularId > 0 && $titularId !== $tarefaTitularId) {
        return false;
    }

    if (usuarioEhTitular($usuario)) {
        return $titularId > 0 && $tarefaTitularId === $titularId;
    }

    $tarefaUsuarioId = (int) ($tarefa['usuario_id'] ?? 0);
    $tarefaAtribuidaPara = (int) ($tarefa['atribuida_para'] ?? 0);

    if ($tarefaUsuarioId === $usuarioId || $tarefaAtribuidaPara === $usuarioId) {
        return true;
    }

    $idsVisiveis = buscarIdsUsuariosVisiveis($pdo, $usuario, true);

    return in_array($tarefaUsuarioId, $idsVisiveis, true)
        || ($tarefaAtribuidaPara > 0 && in_array($tarefaAtribuidaPara, $idsVisiveis, true));
}
