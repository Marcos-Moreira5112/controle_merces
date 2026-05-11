<?php

function obterFiltrosTarefas(): array
{
    return [
        'busca' => trim($_GET['busca'] ?? ''),
        'status' => $_GET['status'] ?? 'todos',
        'tipo' => $_GET['tipo'] ?? 'todos',
        'sub_equipe' => $_GET['sub_equipe'] ?? 'todos',
        'usuario' => $_GET['usuario'] ?? 'todos',
        'ordenar' => $_GET['ordenar'] ?? 'vencimento_asc',
    ];
}

function buildFilterUrl(array $params = []): string
{
    return '?' . http_build_query(array_merge(obterFiltrosTarefas(), $params));
}

function formatarPrazo(string $prazo, string $statusVisual, int $diasAtraso = 0): string
{
    if ($statusVisual === 'atrasada') {
        return $diasAtraso . ' dia' . ($diasAtraso > 1 ? 's' : '') . ' de atraso';
    }

    if ($statusVisual === 'hoje') {
        return 'Vence hoje';
    }

    $hoje = new DateTime('today');
    $dataPrazo = new DateTime($prazo);
    $diff = $hoje->diff($dataPrazo)->days;

    if ($diff <= 7) {
        return 'em ' . $diff . ' dia' . ($diff > 1 ? 's' : '');
    }

    return date('d/m/Y', strtotime($prazo));
}

function nomeUsuarioFiltroSelecionado(array $usuariosFiltro, string $filtroUsuario): string
{
    foreach ($usuariosFiltro as $usuario) {
        if ((string) $usuario['id'] === $filtroUsuario) {
            return $usuario['nome'];
        }
    }

    return '';
}

function montarQueryStringFiltros(array $filtros): string
{
    return http_build_query([
        'busca' => $filtros['busca'],
        'status' => $filtros['status'],
        'tipo' => $filtros['tipo'],
        'sub_equipe' => $filtros['sub_equipe'],
        'usuario' => $filtros['usuario'],
        'ordenar' => $filtros['ordenar'],
    ]);
}
