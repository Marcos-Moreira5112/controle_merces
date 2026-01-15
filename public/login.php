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
    <title>Login</title>
</head>
<body>

<h1>Login</h1>

<?php if ($erro): ?>
    <p style="color:red"><?= $erro ?></p>
<?php endif; ?>

<form method="POST">
    <label>
        Email:<br>
        <input type="email" name="email" required>
    </label>
    <br><br>

    <label>
        Senha:<br>
        <input type="password" name="senha" required>
    </label>
    <br><br>

    <button type="submit">Entrar</button>
</form>

</body>
</html>