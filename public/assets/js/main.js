console.log("js abriu normal");
document.addEventListener('DOMContentLoaded', () => {

    // Confirmar exclusão
    const deleteLinks = document.querySelectorAll('.acao-delete');

    deleteLinks.forEach(link => {
        link.addEventListener('click', event => {
            const confirmar = confirm('Tem certeza que deseja excluir esta tarefa?');
            if (!confirmar) {
                event.preventDefault();
            }
        });
    });
    
});

document.addEventListener('DOMContentLoaded', () => {

    const modal = document.getElementById('modalObservacoes');
    const modalTexto = document.getElementById('modalObservacoesTexto');
    const modalTarefaId = document.getElementById('modalTarefaId');
    const btnFechar = document.getElementById('fecharModal');

    // Abrir modal
    document.querySelectorAll('.btn-observacoes').forEach(btn => {
        btn.addEventListener('click', () => {
            const tarefaId = btn.dataset.id;
            const observacoes = btn.dataset.observacoes || '';

            modalTarefaId.value = tarefaId;
            modalTexto.value = observacoes;

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