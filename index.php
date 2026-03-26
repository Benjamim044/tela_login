<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['remember_me'])) {
        $userId = (int) $_COOKIE['remember_me'];
        $user = find_user_by_id($db, $userId);

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            write_log('protected_page_cookie_login_success', ['email' => $user['email'], 'user_id' => $user['id']]);
        } else {
            setcookie('remember_me', '', time() - 3600, '/');
            write_log('protected_page_access_denied_invalid_cookie', ['user_id' => $userId]);
            header('Location: login.php');
            exit;
        }
    } else {
        write_log('protected_page_access_denied_no_session');
        header('Location: login.php');
        exit;
    }
}

$email = $_SESSION['email'];
write_log('protected_page_access_granted', ['email' => $email, 'user_id' => $_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Principal - Sistema</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h2>Sistema Seguro</h2>
            <nav>
                <a href="logout.php" class="btn-logout">Sair da Conta</a>
            </nav>
        </header>

        <main class="dashboard-main">
            <div class="welcome-card">
                <h1>Seja muito bem-vindo!</h1>
                <p>Voce esta acessando como: <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong></p>

                <div class="info-box">
                    <h3>Status das Configuracoes</h3>
                    <p>OK: Sessao do PHP ativada com sucesso.</p>
                    <p>OK: Banco de dados SQLite3 funcional.</p>
                    <p>OK: Acessos sendo escritos no arquivo <code>access.log</code>.</p>
                    <p>OK: O recurso de lembrete por cookie esta operante.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
