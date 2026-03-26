<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_COOKIE['remember_me'])) {
    $userId = (int) $_COOKIE['remember_me'];
    $user = find_user_by_id($db, $userId);

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        write_log('cookie_auto_login_success', ['email' => $user['email'], 'user_id' => $user['id']]);
        header('Location: index.php');
        exit;
    }

    setcookie('remember_me', '', time() - 3600, '/');
    write_log('cookie_auto_login_failed', ['user_id' => $userId]);
}

$error = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Cadastro realizado com sucesso. Faca login para continuar.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($email === '' || $password === '') {
        $error = 'Por favor, preencha todos os campos.';
        write_log('login_failed_empty_fields', ['email' => $email]);
    } else {
        $user = find_user_by_email($db, $email);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];

            if ($remember) {
                setcookie('remember_me', (string) $user['id'], time() + (86400 * 30), '/');
            }

            write_log('login_success', [
                'email' => $user['email'],
                'user_id' => $user['id'],
                'remember_me' => $remember,
            ]);

            header('Location: index.php');
            exit;
        }

        $error = 'Email ou senha incorretos.';
        write_log('login_failed_invalid_credentials', ['email' => $email]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Acesso ao Sistema</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Bem-vindo de volta</h2>
            <p class="subtitle">Faca login na sua conta para continuar</p>

            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="admin@example.com">
                </div>

                <div class="input-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required placeholder="********">
                </div>

                <div class="form-actions">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember"> Lembrar de mim
                    </label>
                </div>

                <button type="submit" class="btn">Entrar na Plataforma</button>
            </form>

            <div class="help-text">
                <p>Login padrao: admin@example.com / 123456</p>
            </div>

            <p class="auth-switch">
                Nao tem conta?
                <a href="register.php">Criar cadastro</a>
            </p>
        </div>
    </div>
</body>
</html>
