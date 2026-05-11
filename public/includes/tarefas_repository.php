<?php

require_once __DIR__ . '/auth_helpers.php';

function buscarUsuarioLogadoParaTarefas(PDO $pdo, int $usuarioId): array
{
    return buscarUsuarioAutenticado($pdo, $usuarioId) ?: [];
}

function buscarUsuariosDisponiveisParaAtribuicao(PDO $pdo, string $cargoUsuario, int $usuarioId): array
{
    $usuario = buscarUsuarioAutenticado($pdo, $usuarioId);
    if (!$usuario) {
        return [];
    }

    $idsVisiveis = buscarIdsUsuariosVisiveis($pdo, $usuario, true);
    $idsVisiveis = array_values(array_filter(
        $idsVisiveis,
        static fn (int $id): bool => $id !== $usuarioId
    ));

    if (empty($idsVisiveis)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($idsVisiveis), '?'));
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.nome,
            u.email,
            u.cargo,
            u.sub_equipe_id,
            u.funcao_equipe,
            se.nome AS sub_equipe_nome,
            (
                SELECT COUNT(*)
                FROM sub_equipes se_lider
                WHERE se_lider.titular_id = COALESCE(u.titular_id, u.id)
                  AND se_lider.lider_id = u.id
                  AND se_lider.ativo = 1
            ) AS total_sub_equipes_lideradas
        FROM usuarios u
        LEFT JOIN sub_equipes se ON se.id = u.sub_equipe_id
        WHERE u.id IN ($placeholders)
        ORDER BY nome ASC
    ");
    $stmt->execute($idsVisiveis);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarUsuariosFiltroTarefas(PDO $pdo, string $cargoUsuario, int $usuarioId): array
{
    $usuario = buscarUsuarioAutenticado($pdo, $usuarioId);
    if (!$usuario) {
        return [];
    }

    $idsVisiveis = buscarIdsUsuariosVisiveis($pdo, $usuario, true);
    if (empty($idsVisiveis)) {
        return [];
    }

    if (usuarioEhTitular($usuario)) {
        return buscarUsuariosDaEquipe($pdo, obterTitularIdUsuario($usuario), true);
    }

    $placeholders = implode(', ', array_fill(0, count($idsVisiveis), '?'));
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.nome,
            u.email,
            u.cargo,
            u.sub_equipe_id,
            u.funcao_equipe,
            se.nome AS sub_equipe_nome,
            (
                SELECT COUNT(*)
                FROM sub_equipes se_lider
                WHERE se_lider.titular_id = COALESCE(u.titular_id, u.id)
                  AND se_lider.lider_id = u.id
                  AND se_lider.ativo = 1
            ) AS total_sub_equipes_lideradas
        FROM usuarios u
        LEFT JOIN sub_equipes se ON se.id = u.sub_equipe_id
        WHERE u.id IN ($placeholders)
        ORDER BY nome ASC
    ");
    $stmt->execute($idsVisiveis);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function criarTarefa(PDO $pdo, array $dados): void
{
    $stmt = $pdo->prepare("
        INSERT INTO tarefas (titulo, prazo, status, tipo, usuario_id, atribuida_para, titular_id)
        VALUES (:titulo, :prazo, 'pendente', :tipo, :usuario_id, :atribuida_para, :titular_id)
    ");
    $stmt->bindValue(':titulo', $dados['titulo']);
    $stmt->bindValue(':prazo', $dados['prazo']);
    $stmt->bindValue(':tipo', $dados['tipo']);
    $stmt->bindValue(':usuario_id', $dados['usuario_id'], PDO::PARAM_INT);
    $stmt->bindValue(':titular_id', $dados['titular_id'], PDO::PARAM_INT);

    if ($dados['atribuida_para'] === null) {
        $stmt->bindValue(':atribuida_para', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':atribuida_para', $dados['atribuida_para'], PDO::PARAM_INT);
    }

    $stmt->execute();
}

function buscarPermissaoTarefa(PDO $pdo, int $tarefaId): ?array
{
    $stmt = $pdo->prepare("SELECT usuario_id, atribuida_para, titular_id FROM tarefas WHERE id = :id");
    $stmt->bindValue(':id', $tarefaId, PDO::PARAM_INT);
    $stmt->execute();
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    return $tarefa ?: null;
}

function alternarStatusTarefa(PDO $pdo, int $tarefaId): void
{
    $stmt = $pdo->prepare("
        UPDATE tarefas
        SET status = CASE
            WHEN status != 'concluida' THEN 'concluida'
            ELSE 'pendente'
        END
        WHERE id = :id
    ");
    $stmt->bindValue(':id', $tarefaId, PDO::PARAM_INT);
    $stmt->execute();
}

function processarRecorrenciaTarefasFixas(PDO $pdo, int $usuarioId, DateTime $hoje): void
{
    $usuario = buscarUsuarioAutenticado($pdo, $usuarioId);
    if (!$usuario) {
        return;
    }

    $titularId = obterTitularIdUsuario($usuario);
    $stmt = $pdo->prepare("
        SELECT *
        FROM tarefas
        WHERE tipo = 'fixa'
          AND status = 'pendente'
          AND usuario_id = :usuario_id
          AND titular_id = :titular_id
    ");
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':titular_id', $titularId, PDO::PARAM_INT);
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tarefa) {
        $prazo = new DateTime($tarefa['prazo']);

        if ($prazo >= $hoje) {
            continue;
        }

        $proximoPrazo = clone $prazo;
        $proximoPrazo->modify('+1 month');
        $mes = (int) $proximoPrazo->format('n');
        $ano = (int) $proximoPrazo->format('Y');

        $stmtExiste = $pdo->prepare("
            SELECT COUNT(*)
            FROM tarefas
            WHERE tipo = 'fixa'
              AND usuario_id = :usuario_id
              AND titular_id = :titular_id
              AND titulo = :titulo
              AND mes_referencia = :mes
              AND ano_referencia = :ano
        ");
        $stmtExiste->execute([
            ':usuario_id' => $usuarioId,
            ':titular_id' => $titularId,
            ':titulo' => $tarefa['titulo'],
            ':mes' => $mes,
            ':ano' => $ano,
        ]);

        if ($stmtExiste->fetchColumn() > 0) {
            continue;
        }

        $origemId = $tarefa['tarefa_origem_id'] ?? $tarefa['id'];
        $stmtInsert = $pdo->prepare("
            INSERT INTO tarefas (
                titulo, prazo, status, tipo, usuario_id, titular_id,
                mes_referencia, ano_referencia, tarefa_origem_id
            ) VALUES (
                :titulo, :prazo, 'pendente', 'fixa', :usuario_id, :titular_id,
                :mes, :ano, :origem
            )
        ");
        $stmtInsert->execute([
            ':titulo' => $tarefa['titulo'],
            ':prazo' => $proximoPrazo->format('Y-m-d'),
            ':usuario_id' => $usuarioId,
            ':titular_id' => $titularId,
            ':mes' => $mes,
            ':ano' => $ano,
            ':origem' => $origemId,
        ]);
    }
}

function buscarTarefasFiltradas(PDO $pdo, string $cargoUsuario, int $usuarioId, array $filtros): array
{
    $usuario = buscarUsuarioAutenticado($pdo, $usuarioId);
    if (!$usuario) {
        return [];
    }

    $titularId = obterTitularIdUsuario($usuario);
    $whereExtra = '';
    $params = [':titular_id' => $titularId];

    if ($filtros['busca'] !== '') {
        $whereExtra .= " AND t.titulo LIKE :busca";
        $params[':busca'] = '%' . $filtros['busca'] . '%';
    }

    if ($filtros['tipo'] === 'normal') {
        $whereExtra .= " AND t.tipo = 'normal'";
    } elseif ($filtros['tipo'] === 'fixa') {
        $whereExtra .= " AND t.tipo = 'fixa'";
    }

    if ($filtros['status'] === 'pendente') {
        $whereExtra .= " AND t.status = 'pendente'";
    } elseif ($filtros['status'] === 'concluida') {
        $whereExtra .= " AND t.status = 'concluida'";
    }

    if (($filtros['sub_equipe'] ?? 'todos') !== 'todos' && is_numeric($filtros['sub_equipe'])) {
        $subEquipeId = (int) $filtros['sub_equipe'];
        $idsVisiveisSubEquipe = buscarIdsUsuariosVisiveis($pdo, $usuario, true);

        if (!empty($idsVisiveisSubEquipe)) {
            $placeholdersVisiveis = implode(', ', array_fill(0, count($idsVisiveisSubEquipe), '?'));
            $stmtSubEquipe = $pdo->prepare("
                SELECT id
                FROM usuarios
                WHERE id IN ($placeholdersVisiveis)
                  AND sub_equipe_id = ?
            ");
            $stmtSubEquipe->execute(array_merge($idsVisiveisSubEquipe, [$subEquipeId]));
            $idsSubEquipe = array_map('intval', $stmtSubEquipe->fetchAll(PDO::FETCH_COLUMN));

            if (empty($idsSubEquipe)) {
                return [];
            }

            $placeholdersCriador = [];
            $placeholdersAtribuido = [];
            foreach ($idsSubEquipe as $index => $idSubEquipe) {
                $paramCriador = ':subequipe_criador_' . $index;
                $paramAtribuido = ':subequipe_atribuido_' . $index;
                $placeholdersCriador[] = $paramCriador;
                $placeholdersAtribuido[] = $paramAtribuido;
                $params[$paramCriador] = $idSubEquipe;
                $params[$paramAtribuido] = $idSubEquipe;
            }

            $whereExtra .= " AND (t.usuario_id IN (" . implode(', ', $placeholdersCriador) . ")
                          OR t.atribuida_para IN (" . implode(', ', $placeholdersAtribuido) . "))";
        }
    }

    if ($filtros['usuario'] !== 'todos' && is_numeric($filtros['usuario'])) {
        $filtroUsuarioId = (int) $filtros['usuario'];
        $idsVisiveisFiltro = buscarIdsUsuariosVisiveis($pdo, $usuario, true);

        if (in_array($filtroUsuarioId, $idsVisiveisFiltro, true)) {
            $whereExtra .= " AND (t.usuario_id = :filtro_usuario_id OR t.atribuida_para = :filtro_usuario_id2)";
            $params[':filtro_usuario_id'] = $filtroUsuarioId;
            $params[':filtro_usuario_id2'] = $filtroUsuarioId;
        }
    }

    $ordemSql = match ($filtros['ordenar']) {
        'vencimento_desc' => 't.prazo DESC',
        'criacao_asc' => 't.id ASC',
        'criacao_desc' => 't.id DESC',
        default => 't.prazo ASC',
    };

    $sql = "
        SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
               t.usuario_id, t.atribuida_para,
               u.nome AS criador_nome,
               ua.nome AS atribuido_nome
        FROM tarefas t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
        WHERE t.arquivada = 0
          AND t.titular_id = :titular_id
    ";

    if (!usuarioEhTitular($usuario)) {
        $idsVisiveis = buscarIdsUsuariosVisiveis($pdo, $usuario, true);

        if (empty($idsVisiveis)) {
            return [];
        }

        $placeholdersCriador = [];
        $placeholdersAtribuido = [];
        foreach ($idsVisiveis as $index => $idVisivel) {
            $paramCriador = ':visivel_criador_' . $index;
            $paramAtribuido = ':visivel_atribuido_' . $index;
            $placeholdersCriador[] = $paramCriador;
            $placeholdersAtribuido[] = $paramAtribuido;
            $params[$paramCriador] = $idVisivel;
            $params[$paramAtribuido] = $idVisivel;
        }

        $listaCriadores = implode(', ', $placeholdersCriador);
        $listaAtribuidos = implode(', ', $placeholdersAtribuido);
        $sql .= " AND (t.usuario_id IN ($listaCriadores) OR t.atribuida_para IN ($listaAtribuidos))";
    }

    $sql .= " {$whereExtra} ORDER BY {$ordemSql}";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
