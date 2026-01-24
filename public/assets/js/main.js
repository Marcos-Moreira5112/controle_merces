console.log("main.js carregado corretamente");

document.addEventListener('DOMContentLoaded', () => {

    const modal = document.getElementById('modalObservacoes');
    const textarea = document.getElementById('modalObservacoesTexto');
    const tarefaIdInput = document.getElementById('modalTarefaId');
    const btnFechar = document.getElementById('fecharModal');

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

    // Fechar modal
    btnFechar.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Fechar clicando fora do conteúdo
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    // Confirmação antes de excluir tarefa
    document.querySelectorAll('a[href*="acao=delete"]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            
            const confirmacao = confirm('Tem certeza que deseja excluir esta tarefa?');
            
            if (confirmacao) {
                window.location.href = link.href;
            }
        });
    });

    // Confirmação antes de excluir PERMANENTEMENTE (histórico)
    document.querySelectorAll('a[href*="acao=deletar"]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            
            const confirmacao = confirm('⚠️ ATENÇÃO! Isso excluirá a tarefa PERMANENTEMENTE.\n\nEsta ação não pode ser desfeita!\n\nDeseja continuar?');
            
            if (confirmacao) {
                window.location.href = link.href;
            }
        });
    });

});