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