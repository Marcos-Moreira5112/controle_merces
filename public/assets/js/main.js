console.log("main.js carregado corretamente");

document.addEventListener('DOMContentLoaded', () => {

    // ═══════════════════════════════════════════════════════════════
    // MODAL DE OBSERVAÇÕES
    // ═══════════════════════════════════════════════════════════════
    const modal = document.getElementById('modalObservacoes');
    const textarea = document.getElementById('modalObservacoesTexto');
    const tarefaIdInput = document.getElementById('modalTarefaId');
    const btnFechar = document.getElementById('fecharModal');
    const btnCancelar = document.getElementById('cancelarModal');
    const modalOverlay = document.querySelector('.modal-overlay');

    // Abrir modal
    document.querySelectorAll('.btn-observacoes').forEach(botao => {
        botao.addEventListener('click', () => {
            const id = botao.dataset.id;
            const observacoes = botao.dataset.observacoes || '';

            tarefaIdInput.value = id;
            textarea.value = observacoes;

            modal.classList.remove('hidden');
        });
    });

    // Fechar modal - botão X
    if (btnFechar) {
        btnFechar.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
    }

    // Fechar modal - botão Cancelar
    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
    }

    // Fechar modal - clicando no overlay
    if (modalOverlay) {
        modalOverlay.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
    }

    // Fechar modal - tecla ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // BOTÕES DE DATA RÁPIDA
    // ═══════════════════════════════════════════════════════════════
    const inputPrazo = document.getElementById('prazo');
    const botoesData = document.querySelectorAll('.date-shortcut-btn');

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function updateActiveButton() {
        if (!inputPrazo) return;
        
        const selectedDate = inputPrazo.value;
        const hoje = formatDate(new Date());
        const amanha = formatDate(new Date(Date.now() + 86400000));
        const semana = formatDate(new Date(Date.now() + 7 * 86400000));

        botoesData.forEach(btn => {
            btn.classList.remove('active');
            const days = parseInt(btn.dataset.days);
            let btnDate;
            
            if (days === 0) btnDate = hoje;
            else if (days === 1) btnDate = amanha;
            else if (days === 7) btnDate = semana;
            
            if (selectedDate === btnDate) {
                btn.classList.add('active');
            }
        });
    }

    botoesData.forEach(btn => {
        btn.addEventListener('click', () => {
            const days = parseInt(btn.dataset.days);
            const date = new Date();
            date.setDate(date.getDate() + days);
            
            if (inputPrazo) {
                inputPrazo.value = formatDate(date);
                updateActiveButton();
            }
        });
    });

    // Atualizar botão ativo quando mudar a data manualmente
    if (inputPrazo) {
        inputPrazo.addEventListener('change', updateActiveButton);
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
        // Limpar timeout anterior se existir
        if (toastTimeout) {
            clearTimeout(toastTimeout);
        }

        tarefaArquivadaId = id;
        tarefaArquivadaElement = elemento;

        // Resetar animação da barra de progresso
        const progress = toastDesfazer.querySelector('.toast-progress');
        if (progress) {
            progress.style.animation = 'none';
            progress.offsetHeight; // Trigger reflow
            progress.style.animation = 'progressShrink 5s linear forwards';
        }

        toastDesfazer.classList.remove('hidden');

        // Auto-esconder após 5 segundos
        toastTimeout = setTimeout(() => {
            esconderToastDesfazer(false);
        }, 5000);
    }

    function esconderToastDesfazer(desfazer = false) {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }

        toastDesfazer.classList.add('hidden');

        if (!desfazer && tarefaArquivadaElement) {
            // Remover elemento definitivamente se não desfez
            tarefaArquivadaElement.remove();
            
            // Atualizar contadores na página (opcional - pode dar reload)
            atualizarContadores();
        }

        tarefaArquivadaId = null;
        tarefaArquivadaElement = null;
    }

    function atualizarContadores() {
        // Atualizar contagem de tarefas nas seções
        document.querySelectorAll('.tasks-section').forEach(section => {
            const lista = section.querySelector('.tasks-list');
            const contador = section.querySelector('.section-count');
            if (lista && contador) {
                const tarefas = lista.querySelectorAll('.task-item:not(.arquivando)');
                contador.textContent = tarefas.length;
            }
        });
    }

    // Botões de arquivar
    document.querySelectorAll('.btn-arquivar').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            const id = btn.dataset.id;
            const taskItem = btn.closest('.task-item');

            try {
                // Fazer requisição AJAX para arquivar
                const response = await fetch('ajax/arquivar_tarefa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });

                const data = await response.json();

                if (data.success) {
                    // Animar saída do elemento
                    taskItem.classList.add('arquivando');
                    
                    // Mostrar toast após animação começar
                    setTimeout(() => {
                        mostrarToastDesfazer(id, taskItem);
                    }, 150);
                } else {
                    alert('Erro ao arquivar tarefa: ' + (data.message || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao arquivar tarefa. Tente novamente.');
            }
        });
    });

    // Botão de desfazer
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
                    // Restaurar elemento
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
    document.querySelectorAll('a[href*="acao=deletar"]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            
            const confirmacao = confirm('⚠️ ATENÇÃO! Isso excluirá a tarefa PERMANENTEMENTE.\n\nEsta ação não pode ser desfeita!\n\nDeseja continuar?');
            
            if (confirmacao) {
                window.location.href = link.href;
            }
        });
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

        // Atualiza contador do topo, se existir
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
            if (data.status_visual === 'atrasada') {
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