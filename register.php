<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Preencha todos os campos do cadastro.';
        write_log('register_failed_empty_fields', ['email' => $email]);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um email valido.';
        write_log('register_failed_invalid_email', ['email' => $email]);
    } elseif (strlen($password) < 6) {
        $error = 'A senha precisa ter pelo menos 6 caracteres.';
        write_log('register_failed_short_password', ['email' => $email]);
    } elseif ($password !== $confirmPassword) {
        $error = 'As senhas nao coincidem.';
        write_log('register_failed_password_mismatch', ['email' => $email]);
    } elseif (find_user_by_email($db, $email)) {
        $error = 'Este email ja esta cadastrado.';
        write_log('register_failed_duplicate_email', ['email' => $email]);
    } else {
        $user = create_user($db, $email, $password);

        if ($user) {
            write_log('register_success', ['email' => $user['email'], 'user_id' => $user['id']]);
            header('Location: login.php?registered=1');
            exit;
        }

        $error = 'Nao foi possivel concluir o cadastro.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Acesso ao Sistema</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Criar conta</h2>
            <p class="subtitle">Cadastre um novo usuario para acessar o sistema</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        placeholder="voce@example.com"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="input-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required placeholder="Minimo de 6 caracteres">
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirmar senha</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repita a senha">
                </div>

                <button type="submit" class="btn">Criar cadastro</button>
            </form>

            <p class="auth-switch">
                Ja tem conta?
                <a href="login.php">Voltar para o login</a>
            </p>
        </div>
    </div>
</body>
</html>
