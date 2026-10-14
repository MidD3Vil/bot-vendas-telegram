<?php
require_once __DIR__ . '/config.php';
requireLogin();

// Métricas
$stats = [
    'total_orders' => (int) db()->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'paid_orders'  => (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'paid'")->fetchColumn(),
    'pending'      => (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'revenue'      => (float) db()->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'paid'")->fetchColumn(),
    'users'        => (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

// Últimos 7 dias para gráfico
$chartData = db()->query("
    SELECT DATE(paid_at) as day, COUNT(*) as qtd, SUM(amount) as total
    FROM orders
    WHERE status = 'paid' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(paid_at)
    ORDER BY day
")->fetchAll();

$days = [];
$counts = [];
$totals = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('d/m', strtotime($d));
    $found = false;
    foreach ($chartData as $row) {
        if ($row['day'] === $d) {
            $counts[] = (int)$row['qtd'];
            $totals[] = (float)$row['total'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $counts[] = 0;
        $totals[] = 0;
    }
}

// Últimos pedidos
$recent = db()->query("
    SELECT o.*, u.first_name, u.username, u.telegram_id, p.name as product_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN products p ON p.id = o.product_id
    ORDER BY o.created_at DESC
    LIMIT 15
")->fetchAll();

$page = 'dashboard';
include __DIR__ . '/layout.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Receita total</div>
        <div class="stat-value green"><?= money($stats['revenue']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pedidos pagos</div>
        <div class="stat-value"><?= $stats['paid_orders'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pendentes</div>
        <div class="stat-value orange"><?= $stats['pending'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Clientes</div>
        <div class="stat-value"><?= $stats['users'] ?></div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h2>Pagamentos dos últimos 7 dias</h2>
    <canvas id="chart" height="100"></canvas>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h2>Últimos pedidos</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Produto</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $o): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td>
                        <?= e($o['first_name'] ?: '—') ?>
                        <?php if ($o['username']): ?>
                            <br><small>@<?= e($o['username']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($o['product_name'] ?: $o['description']) ?></td>
                    <td><?= money($o['amount']) ?></td>
                    <td>
                        <span class="badge badge-<?= $o['status'] ?>">
                            <?= strtoupper($o['status']) ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;">Nenhum pedido ainda</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($days) ?>,
        datasets: [{
            label: 'Pedidos pagos',
            data: <?= json_encode($counts) ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8' }, grid: { color: '#334155' } },
            x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
        }
    }
});
</script>

<?php include __DIR__ . '/layout_end.php'; ?>
