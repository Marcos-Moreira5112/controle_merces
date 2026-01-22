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

});

