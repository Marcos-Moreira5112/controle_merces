<?php

function getDicasTarefas(): array
{
    return [
        [
            'icone' => '💡',
            'titulo' => 'Filtros rápidos',
            'texto' => 'Use os <strong>filtros</strong> acima para encontrar tarefas rapidamente por status ou tipo.'
        ],
        [
            'icone' => '🔄',
            'titulo' => 'Tarefas recorrentes',
            'texto' => 'Tarefas <strong>recorrentes</strong> se renovam automaticamente todo mês após o prazo.'
        ],
        [
            'icone' => '✅',
            'titulo' => 'Marcar como concluída',
            'texto' => 'Clique no <strong>círculo</strong> ao lado da tarefa para marcá-la como concluída.'
        ],
        [
            'icone' => '📝',
            'titulo' => 'Observações',
            'texto' => 'Use o botão de <strong>editar</strong> para adicionar observações importantes às tarefas.'
        ],
        [
            'icone' => '📅',
            'titulo' => 'Atalhos de data',
            'texto' => 'Use os botões <strong>Hoje</strong>, <strong>Amanhã</strong> ou <strong>+7 dias</strong> para definir prazos rapidamente.'
        ],
        [
            'icone' => '🗂️',
            'titulo' => 'Histórico',
            'texto' => 'Tarefas arquivadas vão para o <strong>Histórico</strong>, onde podem ser restauradas ou excluídas.'
        ],
        [
            'icone' => '↩️',
            'titulo' => 'Desfazer exclusão',
            'texto' => 'Arquivou sem querer? Clique em <strong>Desfazer</strong> no aviso que aparece para recuperar a tarefa.'
        ],
        [
            'icone' => '🔍',
            'titulo' => 'Busca inteligente',
            'texto' => 'Digite qualquer parte do <strong>título</strong> da tarefa na busca para encontrá-la rapidamente.'
        ],
        [
            'icone' => '👥',
            'titulo' => 'Atribuir tarefas',
            'texto' => 'Titulares podem <strong>atribuir tarefas</strong> para membros da equipe no formulário de criação.'
        ],
        [
            'icone' => '📊',
            'titulo' => 'Ordenação',
            'texto' => 'Use a <strong>ordenação</strong> para ver primeiro as tarefas mais urgentes ou mais recentes.'
        ]
    ];
}

function getDicaAleatoriaTarefas(): array
{
    $dicas = getDicasTarefas();

    return $dicas[array_rand($dicas)];
}
