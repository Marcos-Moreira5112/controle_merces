<?php

function classificarTarefasParaExibicao(array $tarefas, string $filtroStatus, DateTime $hoje): array
{
    $tarefasNormais = [];
    $tarefasFixas = [];
    $contadorPendentes = 0;
    $contadorConcluidas = 0;
    $contadorAtrasadas = 0;
    $contadorHoje = 0;

    foreach ($tarefas as $tarefa) {
        $prazoTarefa = new DateTime($tarefa['prazo']);
        $statusVisual = 'futura';

        if ($tarefa['status'] === 'concluida') {
            $statusVisual = 'concluida';
            $contadorConcluidas++;
        } else {
            if ($prazoTarefa < $hoje) {
                $statusVisual = 'atrasada';
                $tarefa['dias_atraso'] = $hoje->diff($prazoTarefa)->days;
                $contadorAtrasadas++;
            } else {
                $contadorPendentes++;

                if ($prazoTarefa == $hoje) {
                    $statusVisual = 'hoje';
                    $contadorHoje++;
                }
            }
        }

        $tarefa['status_visual'] = $statusVisual;

        if ($filtroStatus === 'atrasada' && $statusVisual !== 'atrasada') {
            continue;
        }

        if ($tarefa['tipo'] === 'fixa') {
            $tarefasFixas[] = $tarefa;
        } else {
            $tarefasNormais[] = $tarefa;
        }
    }

    return [
        'tarefasNormais' => $tarefasNormais,
        'tarefasFixas' => $tarefasFixas,
        'contadorPendentes' => $contadorPendentes,
        'contadorConcluidas' => $contadorConcluidas,
        'contadorAtrasadas' => $contadorAtrasadas,
        'contadorHoje' => $contadorHoje,
        'totalFiltrado' => count($tarefasNormais) + count($tarefasFixas),
    ];
}

function filtrosDeTarefasEstaoAtivos(array $filtros): bool
{
    return $filtros['busca'] !== ''
        || $filtros['status'] !== 'todos'
        || $filtros['tipo'] !== 'todos'
        || $filtros['sub_equipe'] !== 'todos'
        || $filtros['usuario'] !== 'todos';
}
