console.log("main.js carregado corretamente");

function getTarefasContainer() {
    return document.querySelector('.tarefas-container');
}

async function carregarPagina(url, { pushState = true } = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Falha ao carregar a página');
        }

        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const novoContainer = doc.querySelector('.tarefas-container');
        if (!novoContainer) {
            throw new Error('Container não encontrado');
        }

        const containerAtual = getTarefasContainer();
        if (!containerAtual) {
            window.location.href = url;
            return;
        }

        containerAtual.innerHTML = novoContainer.innerHTML;
        document.title = doc.title || document.title;

        const targetUrl = new URL(url, window.location.href).href;
        if (pushState && window.location.href !== targetUrl) {
            window.history.pushState({}, '', url);
        }
    } catch (error) {
        window.location.href = url;
    }
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function atualizarContadores() {
    document.querySelectorAll('.tasks-section').forEach(section => {
        const lista = section.querySelector('.tasks-list');
        const contador = section.querySelector('.section-count');

        if (!lista || !contador) return;

        const tarefas = lista.querySelectorAll('.task-item:not(.arquivando)');
        contador.textContent = tarefas.length;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // ═══════════════════════════════════════════════════════════════
    // MODAL DE OBSERVAÇÕES
    // ═══════════════════════════════════════════════════════════════
    const modal = document.getElementById('modalObservacoes');
    const textarea = document.getElementById('modalObservacoesTexto');
    const tarefaIdInput = document.getElementById('modalTarefaId');
    const btnFechar = document.getElementById('fecharModal');
    const btnCancelar = document.getElementById('cancelarModal');

    const fecharModal = () => {
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    // Abrir modal (delegado)
    document.addEventListener('click', (event) => {
        const botaoObservacoes = event.target.closest('.btn-observacoes');
        if (!botaoObservacoes) return;

        const id = botaoObservacoes.dataset.id;
        const observacoes = botaoObservacoes.dataset.observacoes || '';

        if (tarefaIdInput) tarefaIdInput.value = id;
        if (textarea) textarea.value = observacoes;
        if (modal) modal.classList.remove('hidden');
    });

    // Fechar modal
    if (btnFechar) btnFechar.addEventListener('click', fecharModal);
    if (btnCancelar) btnCancelar.addEventListener('click', fecharModal);

    document.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal-overlay')) {
            fecharModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            fecharModal();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // BOTÕES DE DATA RÁPIDA
    // ═══════════════════════════════════════════════════════════════
    function getInputPrazo() {
        return document.getElementById('prazo');
    }

    function updateActiveButton() {
        const inputPrazo = getInputPrazo();
        if (!inputPrazo) return;

        const botoesData = document.querySelectorAll('.date-shortcut-btn');
        const selectedDate = inputPrazo.value;
        const hoje = formatDate(new Date());
        const amanha = formatDate(new Date(Date.now() + 86400000));
        const semana = formatDate(new Date(Date.now() + 7 * 86400000));

        botoesData.forEach(btn => {
            btn.classList.remove('active');
            const days = parseInt(btn.dataset.days, 10);
            let btnDate = '';

            if (days === 0) btnDate = hoje;
            else if (days === 1) btnDate = amanha;
            else if (days === 7) btnDate = semana;

            if (selectedDate === btnDate) {
                btn.classList.add('active');
            }
        });
    }

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.date-shortcut-btn');
        if (!btn) return;

        const days = parseInt(btn.dataset.days, 10);
        const date = new Date();
        date.setDate(date.getDate() + days);
        const inputPrazo = getInputPrazo();

        if (inputPrazo) {
            inputPrazo.value = formatDate(date);
            updateActiveButton();
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target.id === 'prazo') {
            updateActiveButton();
        }
    });

    if (getInputPrazo()) {
        updateActiveButton();
    }

    // ═══════════════════════════════════════════════════════════════
    // SISTEMA DE ARQUIVAR COM TOAST DE DESFAZER
    // ═══════════════════════════════════════════════════════════════
    const toastDesfazer = document.getElementById('toastDesfazer');
    const btnDesfazerAction = document.getElementById('btnDesfazer');
    let tarefaArquivadaId = null;
    let tarefaArquivadaElement = null;
    let toastTimeout = null;

    function mostrarToastDesfazer(id, elemento) {
        if (!toastDesfazer) return;

        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }

        tarefaArquivadaId = id;
        tarefaArquivadaElement = elemento;

        const progress = toastDesfazer.querySelector('.toast-progress');
        if (progress) {
            progress.style.animation = 'none';
            progress.offsetHeight;
            progress.style.animation = 'progressShrink 5s linear forwards';
        }

        toastDesfazer.classList.remove('hidden');

        toastTimeout = setTimeout(() => {
            esconderToastDesfazer(false);
        }, 5000);
    }

    function esconderToastDesfazer(desfazer = false) {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }

        if (toastDesfazer) {
            toastDesfazer.classList.add('hidden');
        }

        if (!desfazer && tarefaArquivadaElement) {
            tarefaArquivadaElement.remove();
            atualizarContadores();
        }

        tarefaArquivadaId = null;
        tarefaArquivadaElement = null;
    }

    document.addEventListener('click', async (event) => {
        const btnArquivar = event.target.closest('.btn-arquivar');
        if (!btnArquivar) return;

        event.preventDefault();

        const id = btnArquivar.dataset.id;
        const taskItem = btnArquivar.closest('.task-item');

        try {
            const response = await fetch('ajax/arquivar_tarefa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id })
            });

            const data = await response.json();

            if (data.success) {
                if (taskItem) {
                    taskItem.classList.add('arquivando');
                    setTimeout(() => {
                        mostrarToastDesfazer(id, taskItem);
                    }, 150);
                }
            } else {
                alert('Erro ao arquivar tarefa: ' + (data.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao arquivar tarefa. Tente novamente.');
        }
    });

    if (btnDesfazerAction) {
        btnDesfazerAction.addEventListener('click', async () => {
            if (!tarefaArquivadaId) return;

            try {
                const response = await fetch('ajax/desarquivar_tarefa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: tarefaArquivadaId })
                });

                const data = await response.json();

                if (data.success) {
                    if (tarefaArquivadaElement) {
                        tarefaArquivadaElement.classList.remove('arquivando');
                    }
                    esconderToastDesfazer(true);
                } else {
                    alert('Erro ao desfazer: ' + (data.message || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao desfazer. Tente novamente.');
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // CONFIRMAÇÃO PARA EXCLUSÃO PERMANENTE (histórico)
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href*="acao=deletar"]');
        if (!link) return;

        event.preventDefault();

        const confirmacao = confirm(
            '⚠️ ATENÇÃO! Isso excluirá a tarefa PERMANENTEMENTE.\n\n' +
            'Esta ação não pode ser desfeita!\n\n' +
            'Deseja continuar?'
        );

        if (confirmacao) {
            window.location.href = link.href;
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // AUTO-HIDE TOAST DE MENSAGEM FLASH
    // ═══════════════════════════════════════════════════════════════
    const toastFlash = document.querySelector('.toast.sucesso, .toast.erro');
    if (toastFlash) {
        setTimeout(() => {
            toastFlash.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => {
                toastFlash.remove();
            }, 300);
        }, 4000);
    }

    // ═══════════════════════════════════════════════════════════════
    // MENU HAMBÚRGUER DO HEADER
    // ═══════════════════════════════════════════════════════════════
    const menuToggle = document.getElementById('menuToggle');
    const headerMenu = document.querySelector('.header-menu');

    if (menuToggle && headerMenu) {
        const closeMenu = () => {
            headerMenu.classList.remove('open');
            menuToggle.setAttribute('aria-expanded', 'false');
        };

        menuToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = headerMenu.classList.toggle('open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', (event) => {
            if (!headerMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }
});

// Adicionar keyframe para slideOut se não existir
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
`;
document.head.appendChild(style);

// ═══════════════════════════════════════════════════════════════
// TOGGLE DE STATUS VIA AJAX
// ═══════════════════════════════════════════════════════════════
document.addEventListener('click', async (event) => {
    const botao = event.target.closest('.btn-toggle-status');
    if (!botao) return;

    event.preventDefault();

    const tarefaId = botao.dataset.id;
    if (!tarefaId) return;

    botao.disabled = true;

    try {
        const formData = new FormData();
        formData.append('id', tarefaId);

        const response = await fetch('ajax/toggle_status_tarefa.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Falha ao alterar status.');
        }

        const taskItem = botao.closest('.task-item');
        const statusAnterior = taskItem && taskItem.classList.contains('atrasada')
            ? 'atrasada'
            : null;

        if (taskItem) {
            taskItem.classList.remove('concluida', 'atrasada', 'hoje', 'futura');
            taskItem.classList.add(data.status_visual);

            const title = data.novo_status === 'concluida'
                ? 'Reabrir tarefa'
                : 'Marcar como concluída';
            botao.title = title;

            if (data.novo_status === 'concluida') {
                botao.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                `;

                const badge = taskItem.querySelector('.task-badge');
                if (badge) badge.remove();
            } else {
                botao.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                `;

                const taskHeader = taskItem.querySelector('.task-header');
                const existingBadge = taskItem.querySelector('.task-badge');

                if (!existingBadge && taskHeader && data.status_visual !== 'futura') {
                    const badge = document.createElement('span');
                    badge.className = `task-badge ${data.status_visual === 'atrasada' ? 'overdue' : 'today'}`;

                    if (data.status_visual === 'atrasada') {
                        badge.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                            ${data.dias_atraso}d
                        `;
                    } else {
                        badge.textContent = 'Hoje';
                    }

                    taskHeader.appendChild(badge);
                }
            }
        }

        const atualizarNumero = (labelText, delta) => {
            const cards = document.querySelectorAll('.mini-stat');
            cards.forEach((card) => {
                const label = card.querySelector('.mini-stat-label');
                const value = card.querySelector('.mini-stat-value');

                if (!label || !value) return;

                if (label.textContent.trim() === labelText) {
                    const atual = parseInt(value.textContent.trim(), 10) || 0;
                    value.textContent = Math.max(0, atual + delta);
                }
            });
        };

        if (data.novo_status === 'concluida') {
            atualizarNumero('Pendentes', -1);
            atualizarNumero('Concluídas', 1);
            if (statusAnterior === 'atrasada') {
                atualizarNumero('Atrasadas', -1);
            }
        } else {
            atualizarNumero('Pendentes', 1);
            atualizarNumero('Concluídas', -1);
            if (data.status_visual === 'atrasada') {
                atualizarNumero('Atrasadas', 1);
            }
        }
    } catch (error) {
        alert(error.message);
    } finally {
        botao.disabled = false;
    }
});

// ═══════════════════════════════════════════════════════════════
// FILTROS E ORDENAÇÃO SEM RELOAD
// ═══════════════════════════════════════════════════════════════
document.addEventListener('submit', (event) => {
    const form = event.target.closest('.tarefas-filtros-card .filtro-busca-form');
    if (!form) return;

    event.preventDefault();

    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    carregarPagina(`${window.location.pathname}?${params.toString()}`);
});

document.addEventListener('change', (event) => {
    const select = event.target.closest('.tarefas-filtros-card .filtro-select');
    if (!select) return;

    const url = select.value;
    if (url) {
        carregarPagina(url);
    }
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('.tarefas-filtros-card a.filtro-btn, .tarefas-filtros-card a.btn-limpar-filtros, .tarefas-filtros-card a.busca-limpar');
    if (!link) return;

    event.preventDefault();
    carregarPagina(link.href);
});

window.addEventListener('popstate', () => {
    if (document.querySelector('.tarefas-container')) {
        carregarPagina(window.location.href, { pushState: false });
    }
    if (document.querySelector('.dashboard-container')) {
        carregarDashboard(window.location.href, { pushState: false, preserveScroll: true });
    }
});

// Dashboard interativo
function getDashboardContainer() {
    return document.querySelector('.dashboard-container');
}

async function carregarDashboard(url, { pushState = true, preserveScroll = true } = {}) {
    const containerAtual = getDashboardContainer();
    if (!containerAtual) {
        window.location.href = url;
        return;
    }

    const currentScrollY = window.scrollY;

    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Falha ao carregar o dashboard');
        }

        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const novoContainer = doc.querySelector('.dashboard-container');

        if (!novoContainer) {
            throw new Error('Dashboard não encontrado');
        }

        containerAtual.innerHTML = novoContainer.innerHTML;
        document.title = doc.title || document.title;

        const targetUrl = new URL(url, window.location.href).href;
        if (pushState && window.location.href !== targetUrl) {
            window.history.pushState({}, '', url);
        }

        inicializarDashboardInterativo();

        if (preserveScroll) {
            window.scrollTo({ top: currentScrollY, behavior: 'auto' });
        }
    } catch (error) {
        window.location.href = url;
    }
}

function inicializarDashboardInterativo() {
    const inputPeriodo = document.getElementById('dashboardPeriodo');
    const dateRange = document.querySelector('.custom-range');

    if (!inputPeriodo || !dateRange) return;

    const isCustom = inputPeriodo.value === 'personalizado';

    document.querySelectorAll('.period-chip').forEach((chip) => {
        chip.classList.toggle('active', chip.dataset.periodo === inputPeriodo.value);
    });

    dateRange.classList.toggle('is-active', isCustom);

    dateRange.querySelectorAll('input').forEach((input) => {
        input.disabled = !isCustom;
    });
}

document.addEventListener('click', (event) => {
    const chip = event.target.closest('.period-chip');
    if (!chip) return;

    const form = document.getElementById('dashboardFilterForm');
    const inputPeriodo = document.getElementById('dashboardPeriodo');
    if (!form || !inputPeriodo) return;

    event.preventDefault();
    inputPeriodo.value = chip.dataset.periodo || 'semana';
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    carregarDashboard(`${window.location.pathname}?${params.toString()}`);
});

document.addEventListener('change', (event) => {
    const autoSubmit = event.target.closest('[data-dashboard-autosubmit]');
    if (!autoSubmit) return;

    const form = autoSubmit.closest('form');
    if (form) {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        carregarDashboard(`${window.location.pathname}?${params.toString()}`);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('#dashboardFilterForm');
    if (!form) return;

    event.preventDefault();
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    carregarDashboard(`${window.location.pathname}?${params.toString()}`);
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('.dashboard-ajax-link');
    if (!link) return;

    event.preventDefault();
    carregarDashboard(link.href);
});

document.addEventListener('DOMContentLoaded', () => {
    inicializarDashboardInterativo();
});
