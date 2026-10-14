<?php
if (!isset($page)) $page = '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel - Telegram PIX Bot</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --border: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --primary: #3b82f6;
            --green: #22c55e;
            --orange: #f59e0b;
            --red: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 240px;
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 1.5rem 0;
            flex-shrink: 0;
        }
        .sidebar .brand {
            padding: 0 1.5rem 1.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }
        .sidebar a {
            display: block;
            padding: 0.7rem 1.5rem;
            color: var(--muted);
            text-decoration: none;
            transition: all 0.15s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #0f172a;
            color: var(--primary);
        }
        .main {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-x: auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .header h1 { font-size: 1.5rem; }
        .header .user { color: var(--muted); font-size: 0.9rem; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
        }
        .stat-label { color: var(--muted); font-size: 0.85rem; margin-bottom: 0.4rem; }
        .stat-value { font-size: 1.6rem; font-weight: 700; }
        .stat-value.green { color: var(--green); }
        .stat-value.orange { color: var(--orange); }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        .card h2 { font-size: 1.1rem; margin-bottom: 1rem; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 500; font-size: 0.8rem; text-transform: uppercase; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-paid { background: #14532d; color: #86efac; }
        .badge-pending { background: #78350f; color: #fcd34d; }
        .badge-expired, .badge-cancelled, .badge-failed { background: #7f1d1d; color: #fca5a5; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            color: white;
            background: var(--primary);
        }
        .btn:hover { opacity: 0.9; }
        .btn-danger { background: var(--red); }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
        form label { display: block; margin-bottom: 0.3rem; font-size: 0.875rem; color: var(--muted); }
        form input, form textarea, form select {
            width: 100%;
            padding: 0.6rem 0.9rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg);
            color: var(--text);
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        form input:focus, form textarea:focus { outline: none; border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-success { background: #14532d; color: #86efac; }
        .alert-error { background: #7f1d1d; color: #fca5a5; }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border); }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">💳 PIX Bot</div>
        <a href="index.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="orders.php" class="<?= $page === 'orders' ? 'active' : '' ?>">📋 Pedidos</a>
        <a href="clients.php" class="<?= $page === 'clients' ? 'active' : '' ?>">👥 Clientes</a>
        <a href="products.php" class="<?= $page === 'products' ? 'active' : '' ?>">🛒 Produtos</a>
        <a href="settings.php" class="<?= $page === 'settings' ? 'active' : '' ?>">⚙️ Configurações</a>
        <a href="logout.php" style="margin-top: 2rem; color: #f87171;">Sair</a>
    </aside>
    <main class="main">
        <div class="header">
            <h1><?= e($pageTitle ?? ucfirst($page)) ?></h1>
            <div class="user"><?= e($_SESSION['admin_name'] ?? '') ?></div>
        </div>
