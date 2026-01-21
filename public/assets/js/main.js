console.log("main.js carregado corretamente");

document.addEventListener('DOMContentLoaded', () => {

    const modal = document.getElementById('modalObservacoes');
    const modalTextarea = document.getElementById('modalObservacoesTexto');
    const modalTarefaId = document.getElementById('modalTarefaId');
    const btnFechar = document.getElementById('fecharModal');

    // Abrir modal
    document.querySelectorAll('.btn-observacoes').forEach(botao => {
        botao.addEventListener('click', () => {
            modalTarefaId.value = botao.dataset.id;
            modalTextarea.value = botao.dataset.observacoes || '';
            modal.classList.remove('hidden');
        });
    });

    // Fechar modal (botão cancelar)
    btnFechar.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    // Fechar clicando fora
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

});
