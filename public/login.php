<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/config/conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    $sql = "
        SELECT id, senha
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $senha === $usuario['senha']) {
        // Login OK
        $_SESSION['usuario_id'] = $usuario['id'];
        header('Location: tarefas.php');
        exit;
    } else {
        $erro = 'Email ou senha inválidos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login | Óticas Mercês</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="login-page">

    <main class="login-container">

        <header class="login-header">
            <h1>Óticas Mercês</h1>
            <p>Controle interno</p>
        </header>

        <section class="login-card">

            <h2>Acesso ao sistema</h2>

            <form method="POST">

                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="campo">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>

                <button type="submit">Entrar</button>

            </form>

        </section>

    </main>

</body>
</html>