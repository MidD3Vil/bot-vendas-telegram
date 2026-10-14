<?php
require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        header('Location: index.php');
        exit;
    }
    $error = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Painel PIX Bot</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.5);
        }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; text-align: center; }
        .subtitle { text-align: center; color: #94a3b8; margin-bottom: 2rem; font-size: 0.9rem; }
        label { display: block; margin-bottom: 0.4rem; font-size: 0.875rem; color: #cbd5e1; }
        input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #475569;
            border-radius: 8px;
            background: #0f172a;
            color: #f1f5f9;
            font-size: 1rem;
            margin-bottom: 1.25rem;
        }
        input:focus { outline: none; border-color: #3b82f6; }
        button {
            width: 100%;
            padding: 0.85rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #2563eb; }
        .error {
            background: #7f1d1d;
            color: #fecaca;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Painel Admin</h1>
        <p class="subtitle">Telegram PIX Bot</p>

        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>E-mail</label>
            <input type="email" name="email" required autofocus placeholder="admin@localhost.com">

            <label>Senha</label>
            <input type="password" name="password" required placeholder="••••••••">

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
