<?php
require_once __DIR__ . '/config.php';
requireLogin();

$clients = db()->query("
    SELECT u.*,
           COUNT(o.id) as total_orders,
           SUM(CASE WHEN o.status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
           COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.amount ELSE 0 END), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$page = 'clients';
$pageTitle = 'Clientes';
include __DIR__ . '/layout.php';
?>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Telegram ID</th>
                    <th>Nome</th>
                    <th>Username</th>
                    <th>Pedidos</th>
                    <th>Pagos</th>
                    <th>Total gasto</th>
                    <th>Cadastro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= $c['telegram_id'] ?></td>
                    <td><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '—') ?></td>
                    <td><?= $c['username'] ? '@' . e($c['username']) : '—' ?></td>
                    <td><?= $c['total_orders'] ?></td>
                    <td><?= $c['paid_orders'] ?></td>
                    <td><?= money($c['total_spent']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;">Nenhum cliente</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout_end.php'; ?>
