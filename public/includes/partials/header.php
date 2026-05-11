<?php
$pageTitle = $pageTitle ?? 'TaskBlue';
$activePage = $activePage ?? '';
$nomeUsuario = $nomeUsuario ?? '';
$nomeEquipe = $nomeEquipe ?? 'Equipe';
$cargoUsuario = $cargoUsuario ?? 'funcionario';

if (!function_exists('montarRotuloCargo')) {
    function montarRotuloCargo(string $cargo): string
    {
        return $cargo === 'administrador' ? 'Titular' : 'Membro';
    }
}
?>

<header class="site-header">
    <div class="header-content">
        <div class="header-brand">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>

            <?php if (!empty($nomeUsuario)): ?>
                <p>
                    <?= htmlspecialchars($nomeEquipe) ?> • <?= htmlspecialchars($nomeUsuario) ?>
                    <span class="badge-cargo <?= htmlspecialchars($cargoUsuario) ?>">
                        <?= htmlspecialchars(montarRotuloCargo($cargoUsuario)) ?>
                    </span>
                </p>
            <?php endif; ?>
        </div>

        <div class="header-menu">
            <button
                type="button"
                class="menu-toggle"
                aria-expanded="false"
                aria-controls="headerDropdownMenu"
                id="menuToggle"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="header-dropdown" id="headerDropdownMenu">
                <a href="dashboard.php" class="btn-gerenciar <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    Dashboard
                </a>

                <a href="tarefas.php" class="btn-gerenciar <?= $activePage === 'tarefas' ? 'active' : '' ?>">
                    Tarefas
                </a>

                <a href="historico.php" class="btn-gerenciar <?= $activePage === 'historico' ? 'active' : '' ?>">
                    Histórico
                </a>

                <a href="conta.php" class="btn-gerenciar <?= $activePage === 'conta' ? 'active' : '' ?>">
                    Minha Conta
                </a>

                <?php if ($cargoUsuario === 'administrador'): ?>
                    <a href="gerenciar_usuarios.php" class="btn-gerenciar <?= $activePage === 'usuarios' ? 'active' : '' ?>">
                        Equipe
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="btn-logout">
                    Sair
                </a>
            </nav>
        </div>
    </div>
</header>
