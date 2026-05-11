<?php
// usa as variáveis já criadas em tarefas.php:
// $filtro_busca, $filtro_status, $filtro_tipo, $filtro_sub_equipe, $filtro_usuario, $ordenar_por
// $usuariosFiltro, $subEquipesFiltro, $filtrosAtivos, $totalFiltrado
?>

<div class="tarefas-filtros-card">
    <div class="tarefas-filtros-top">
        <form method="GET" class="filtro-busca-form js-filtros-form">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filtro_status) ?>">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
            <input type="hidden" name="sub_equipe" value="<?= htmlspecialchars($filtro_sub_equipe) ?>">
            <input type="hidden" name="usuario" value="<?= htmlspecialchars($filtro_usuario) ?>">
            <input type="hidden" name="ordenar" value="<?= htmlspecialchars($ordenar_por) ?>">

            <div class="busca-wrapper">
                <svg class="busca-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>

                <input
                    type="text"
                    name="busca"
                    placeholder="Buscar tarefas..."
                    value="<?= htmlspecialchars($filtro_busca) ?>"
                    class="busca-input"
                >

                <?php if ($filtro_busca !== ''): ?>
                    <a href="<?= buildFilterUrl(['busca' => '']) ?>" class="busca-limpar" title="Limpar busca" aria-label="Limpar busca">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="tarefas-filtros-actions">
            <a
                href="tarefas.php"
                class="btn-limpar-filtros <?= $filtrosAtivos ? 'is-active' : 'is-inactive' ?>"
                <?= $filtrosAtivos ? '' : 'aria-disabled="true" tabindex="-1"' ?>
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3 6h18"/>
                    <path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                </svg>
                Limpar filtros
            </a>
        </div>
    </div>

    <div class="tarefas-filtros-grid">
        <div class="filtros-bloco">
            <span class="filtros-label">Status</span>
            <div class="filtros-botoes">
                <a href="<?= buildFilterUrl(['status' => 'todos']) ?>" class="filtro-btn <?= $filtro_status === 'todos' ? 'ativo' : '' ?>">Todos</a>

                <a href="<?= buildFilterUrl(['status' => 'pendente']) ?>" class="filtro-btn <?= $filtro_status === 'pendente' ? 'ativo' : '' ?>">
                    <span class="filtro-dot pendente"></span>
                    Pendentes
                </a>

                <a href="<?= buildFilterUrl(['status' => 'atrasada']) ?>" class="filtro-btn <?= $filtro_status === 'atrasada' ? 'ativo' : '' ?>">
                    <span class="filtro-dot atrasada"></span>
                    Atrasadas
                </a>

                <a href="<?= buildFilterUrl(['status' => 'concluida']) ?>" class="filtro-btn <?= $filtro_status === 'concluida' ? 'ativo' : '' ?>">
                    <span class="filtro-dot concluida"></span>
                    Concluídas
                </a>
            </div>
        </div>

        <div class="filtros-bloco">
            <span class="filtros-label">Tipo</span>
            <div class="filtros-botoes">
                <a href="<?= buildFilterUrl(['tipo' => 'todos']) ?>" class="filtro-btn <?= $filtro_tipo === 'todos' ? 'ativo' : '' ?>">Todos</a>

                <a href="<?= buildFilterUrl(['tipo' => 'normal']) ?>" class="filtro-btn <?= $filtro_tipo === 'normal' ? 'ativo' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                    Únicas
                </a>

                <a href="<?= buildFilterUrl(['tipo' => 'fixa']) ?>" class="filtro-btn <?= $filtro_tipo === 'fixa' ? 'ativo' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                    </svg>
                    Recorrentes
                </a>
            </div>
        </div>

        <div class="filtros-bloco filtros-bloco-select">
            <?php if (!empty($subEquipesFiltro)): ?>
                <div class="filtro-select-group">
                    <span class="filtros-label">Sub-equipe</span>
                    <select class="filtro-select js-filtro-select">
                        <option value="<?= buildFilterUrl(['sub_equipe' => 'todos', 'usuario' => 'todos']) ?>" <?= $filtro_sub_equipe === 'todos' ? 'selected' : '' ?>>
                            Todas visíveis
                        </option>
                        <?php foreach ($subEquipesFiltro as $subEquipe): ?>
                            <option value="<?= buildFilterUrl(['sub_equipe' => $subEquipe['id'], 'usuario' => 'todos']) ?>" <?= (string) $filtro_sub_equipe === (string) $subEquipe['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subEquipe['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($usuariosFiltro)): ?>
                <div class="filtro-select-group">
                    <span class="filtros-label">Membro</span>
                    <select class="filtro-select js-filtro-select">
                        <option value="<?= buildFilterUrl(['usuario' => 'todos']) ?>" <?= $filtro_usuario === 'todos' ? 'selected' : '' ?>>
                            Todos visíveis
                        </option>
                        <?php foreach ($usuariosFiltro as $u): ?>
                            <option value="<?= buildFilterUrl(['usuario' => $u['id']]) ?>" <?= $filtro_usuario == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nome']) ?>
                                <?php if (!empty($u['funcao_equipe'])): ?>
                                    - <?= htmlspecialchars($u['funcao_equipe']) ?>
                                <?php elseif (!empty($u['sub_equipe_nome'])): ?>
                                    - <?= htmlspecialchars($u['sub_equipe_nome']) ?>
                                <?php else: ?>
                                    - <?= htmlspecialchars(montarRotuloPapelEquipe($u)) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filtro-select-group">
                <span class="filtros-label">Ordenar</span>
                <select class="filtro-select js-filtro-select">
                    <option value="<?= buildFilterUrl(['ordenar' => 'vencimento_asc']) ?>" <?= $ordenar_por === 'vencimento_asc' ? 'selected' : '' ?>>
                        Vencimento ↑
                    </option>
                    <option value="<?= buildFilterUrl(['ordenar' => 'vencimento_desc']) ?>" <?= $ordenar_por === 'vencimento_desc' ? 'selected' : '' ?>>
                        Vencimento ↓
                    </option>
                    <option value="<?= buildFilterUrl(['ordenar' => 'criacao_desc']) ?>" <?= $ordenar_por === 'criacao_desc' ? 'selected' : '' ?>>
                        Mais recentes
                    </option>
                    <option value="<?= buildFilterUrl(['ordenar' => 'criacao_asc']) ?>" <?= $ordenar_por === 'criacao_asc' ? 'selected' : '' ?>>
                        Mais antigas
                    </option>
                </select>
            </div>
        </div>
    </div>

    <?php if ($filtrosAtivos): ?>
        <div class="filtros-resultado">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>

            <span>
                Mostrando <strong><?= $totalFiltrado ?></strong> tarefa<?= $totalFiltrado !== 1 ? 's' : '' ?>
                <?php if ($filtro_busca !== ''): ?>
                    para "<strong><?= htmlspecialchars($filtro_busca) ?></strong>"
                <?php endif; ?>
                <?php
                $nomeUsuarioFiltro = '';
                if ($filtro_usuario !== 'todos') {
                    foreach ($usuariosFiltro as $u) {
                        if ($u['id'] == $filtro_usuario) {
                            $nomeUsuarioFiltro = $u['nome'];
                            break;
                        }
                    }
                }
                ?>
                <?php
                $nomeSubEquipeFiltro = '';
                if ($filtro_sub_equipe !== 'todos') {
                    foreach ($subEquipesFiltro as $subEquipe) {
                        if ((string) $subEquipe['id'] === (string) $filtro_sub_equipe) {
                            $nomeSubEquipeFiltro = $subEquipe['nome'];
                            break;
                        }
                    }
                }
                ?>
                <?php if ($nomeSubEquipeFiltro !== ''): ?>
                    em <strong><?= htmlspecialchars($nomeSubEquipeFiltro) ?></strong>
                <?php endif; ?>
                <?php if ($nomeUsuarioFiltro !== ''): ?>
                    de <strong><?= htmlspecialchars($nomeUsuarioFiltro) ?></strong>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
</div>
