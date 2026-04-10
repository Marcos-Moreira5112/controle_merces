<?php

function buscarUsuarioLogadoParaTarefas(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare("SELECT cargo, nome FROM usuarios WHERE id = :usuario_id");
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function buscarUsuariosDisponiveisParaAtribuicao(PDO $pdo, string $cargoUsuario, int $usuarioId): array
{
    if ($cargoUsuario !== 'administrador' && $cargoUsuario !== 'supervisor') {
        return [];
    }

    $sql = "
        SELECT id, nome, cargo
        FROM usuarios
        WHERE id != :usuario_id
    ";

    if ($cargoUsuario === 'supervisor') {
        $sql .= " AND supervisor_id = :supervisor_id";
    }

    $sql .= " ORDER BY nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);

    if ($cargoUsuario === 'supervisor') {
        $stmt->bindValue(':supervisor_id', $usuarioId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarUsuariosFiltroTarefas(PDO $pdo, string $cargoUsuario, int $usuarioId): array
{
    if ($cargoUsuario === 'administrador') {
        $stmt = $pdo->prepare("
            SELECT id, nome, cargo
            FROM usuarios
            ORDER BY cargo, nome ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($cargoUsuario === 'supervisor') {
        $stmt = $pdo->prepare("
            SELECT id, nome, cargo
            FROM usuarios
            WHERE id = :usuario_id OR supervisor_id = :supervisor_id
            ORDER BY cargo DESC, nome ASC
        ");
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':supervisor_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [];
}

function criarTarefa(PDO $pdo, array $dados): void
{
    $stmt = $pdo->prepare("
        INSERT INTO tarefas (titulo, prazo, status, tipo, usuario_id, atribuida_para)
        VALUES (:titulo, :prazo, 'pendente', :tipo, :usuario_id, :atribuida_para)
    ");
    $stmt->bindValue(':titulo', $dados['titulo']);
    $stmt->bindValue(':prazo', $dados['prazo']);
    $stmt->bindValue(':tipo', $dados['tipo']);
    $stmt->bindValue(':usuario_id', $dados['usuario_id'], PDO::PARAM_INT);

    if ($dados['atribuida_para'] === null) {
        $stmt->bindValue(':atribuida_para', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':atribuida_para', $dados['atribuida_para'], PDO::PARAM_INT);
    }

    $stmt->execute();
}

function buscarPermissaoTarefa(PDO $pdo, int $tarefaId): ?array
{
    $stmt = $pdo->prepare("SELECT usuario_id, atribuida_para FROM tarefas WHERE id = :id");
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
    $stmt = $pdo->prepare("
        SELECT *
        FROM tarefas
        WHERE tipo = 'fixa'
          AND status = 'pendente'
          AND usuario_id = :usuario_id
    ");
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
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
              AND titulo = :titulo
              AND mes_referencia = :mes
              AND ano_referencia = :ano
        ");
        $stmtExiste->execute([
            ':usuario_id' => $usuarioId,
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
                titulo, prazo, status, tipo, usuario_id,
                mes_referencia, ano_referencia, tarefa_origem_id
            ) VALUES (
                :titulo, :prazo, 'pendente', 'fixa', :usuario_id,
                :mes, :ano, :origem
            )
        ");
        $stmtInsert->execute([
            ':titulo' => $tarefa['titulo'],
            ':prazo' => $proximoPrazo->format('Y-m-d'),
            ':usuario_id' => $usuarioId,
            ':mes' => $mes,
            ':ano' => $ano,
            ':origem' => $origemId,
        ]);
    }
}

function buscarTarefasFiltradas(PDO $pdo, string $cargoUsuario, int $usuarioId, array $filtros): array
{
    $whereExtra = '';
    $params = [];

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

    if ($filtros['usuario'] !== 'todos' && is_numeric($filtros['usuario'])) {
        $whereExtra .= " AND (t.usuario_id = :filtro_usuario_id OR t.atribuida_para = :filtro_usuario_id2)";
        $params[':filtro_usuario_id'] = (int) $filtros['usuario'];
        $params[':filtro_usuario_id2'] = (int) $filtros['usuario'];
    }

    $ordemSql = match ($filtros['ordenar']) {
        'vencimento_desc' => 't.prazo DESC',
        'criacao_asc' => 't.id ASC',
        'criacao_desc' => 't.id DESC',
        default => 't.prazo ASC',
    };

    if ($cargoUsuario === 'administrador') {
        $stmt = $pdo->prepare("
            SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
                   t.usuario_id, t.atribuida_para,
                   u.nome as criador_nome,
                   ua.nome as atribuido_nome
            FROM tarefas t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
            WHERE t.arquivada = 0 {$whereExtra}
            ORDER BY {$ordemSql}
        ");
    } elseif ($cargoUsuario === 'supervisor') {
        $stmt = $pdo->prepare("
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
              {$whereExtra}
            ORDER BY {$ordemSql}
        ");
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id2', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id3', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id4', $usuarioId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.id, t.titulo, t.prazo, t.status, t.tipo, t.observacoes,
                   t.usuario_id, t.atribuida_para,
                   u.nome as criador_nome,
                   ua.nome as atribuido_nome
            FROM tarefas t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            LEFT JOIN usuarios ua ON t.atribuida_para = ua.id
            WHERE t.arquivada = 0
              AND (t.usuario_id = :usuario_id OR t.atribuida_para = :usuario_id2)
              {$whereExtra}
            ORDER BY {$ordemSql}
        ");
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id2', $usuarioId, PDO::PARAM_INT);
    }

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
