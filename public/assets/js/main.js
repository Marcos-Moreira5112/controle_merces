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
