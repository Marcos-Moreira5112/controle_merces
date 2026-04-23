<?php
$pageTitle = $pageTitle ?? 'Óticas Mercês';
$activePage = $activePage ?? '';
$nomeUsuario = $nomeUsuario ?? '';
$cargoUsuario = $cargoUsuario ?? 'funcionario';
?>

<header class="site-header">
    <div class="header-content">
        <div class="header-brand">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>

            <?php if (!empty($nomeUsuario)): ?>
                <p>
                    Óticas Mercês • <?= htmlspecialchars($nomeUsuario) ?>
                    <span class="badge-cargo <?= htmlspecialchars($cargoUsuario) ?>">
                        <?= ucfirst($cargoUsuario) ?>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 12l9-9 9 9"/>
                        <path d="M5 10v10h14V10"/>
                        <path d="M9 20v-6h6v6"/>
                    </svg>
                    Dashboard
                </a>

                <a href="tarefas.php" class="btn-gerenciar <?= $activePage === 'tarefas' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                    Tarefas
                </a>

                <a href="historico.php" class="btn-gerenciar <?= $activePage === 'historico' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    Histórico
                </a>

                <a href="conta.php" class="btn-gerenciar <?= $activePage === 'conta' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Minha Conta
                </a>

                <?php if ($cargoUsuario === 'administrador'): ?>
                    <a href="gerenciar_usuarios.php" class="btn-gerenciar <?= $activePage === 'usuarios' ? 'active' : '' ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Usuários
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="btn-logout">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M10 17l5-5-5-5"/>
                        <path d="M15 12H3"/>
                        <path d="M21 3v18"/>
                    </svg>
                    Sair
                </a>
            </nav>
        </div>
    </div>
</header>
