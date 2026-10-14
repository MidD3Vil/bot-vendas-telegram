<?php
require_once __DIR__ . '/config.php';
requireLogin();

$message = '';
$error = '';

// Confirmar pagamento manualmente
if (isset($_GET['confirm'])) {
    $orderId = (int)$_GET['confirm'];
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if ($order && $order['status'] !== 'paid') {
        $botUrl = rtrim(getSetting('bot_internal_url', 'http://127.0.0.1:3000'), '/');
        $secret = getSetting('admin_secret', '');

        // Marca no banco mesmo se a notificação falhar
        $upd = db()->prepare("UPDATE orders SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $upd->execute([$orderId]);

        if ($secret) {
            $payload = json_encode(['order_id' => $orderId, 'secret' => $secret]);
            $ch = curl_init($botUrl . '/internal/confirm-payment');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                $message = "Pedido #{$orderId} confirmado e cliente notificado.";
            } else {
                $message = "Pedido #{$orderId} marcado como pago. (Notificação Telegram: verifique se o bot está rodando e o ADMIN_SECRET confere)";
            }
        } else {
            $message = "Pedido #{$orderId} marcado como pago. Configure o admin_secret para notificar o cliente automaticamente.";
        }
    } elseif ($order && $order['status'] === 'paid') {
        $message = "Pedido #{$orderId} já estava pago.";
    } else {
        $error = 'Pedido não encontrado.';
    }
}

$statusFilter = $_GET['status'] ?? '';
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 30;
$offset = ($pageNum - 1) * $perPage;

$where = '1=1';
$params = [];
if (in_array($statusFilter, ['pending', 'paid', 'expired', 'cancelled', 'failed'], true)) {
    $where .= ' AND o.status = ?';
    $params[] = $statusFilter;
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM orders o WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "
    SELECT o.*, u.first_name, u.username, u.telegram_id, p.name as product_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN products p ON p.id = o.product_id
    WHERE $where
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page = 'orders';
$pageTitle = 'Pedidos';
include __DIR__ . '/layout.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <a href="?status=" class="btn btn-sm" style="background:<?= $statusFilter===''?'#3b82f6':'#475569' ?>">Todos</a>
    <a href="?status=paid" class="btn btn-sm" style="background:<?= $statusFilter==='paid'?'#22c55e':'#475569' ?>">Pagos</a>
    <a href="?status=pending" class="btn btn-sm" style="background:<?= $statusFilter==='pending'?'#f59e0b':'#475569' ?>">Pendentes</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Produto / Descrição</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Criado</th>
                    <th>Pago em</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td>
                        <?= e($o['first_name'] ?: '—') ?>
                        <?php if ($o['username']): ?><br><small>@<?= e($o['username']) ?></small><?php endif; ?>
                        <br><small style="color:#64748b"><?= $o['telegram_id'] ?></small>
                    </td>
                    <td><?= e($o['product_name'] ?: $o['description']) ?></td>
                    <td><?= money($o['amount']) ?></td>
                    <td><span class="badge badge-<?= $o['status'] ?>"><?= strtoupper($o['status']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td><?= $o['paid_at'] ? date('d/m/Y H:i', strtotime($o['paid_at'])) : '—' ?></td>
                    <td>
                        <?php if ($o['status'] === 'pending'): ?>
                            <a href="?confirm=<?= $o['id'] ?>&status=<?= e($statusFilter) ?>&p=<?= $pageNum ?>"
                               class="btn btn-sm"
                               style="background:#22c55e"
                               onclick="return confirm('Confirmar pagamento do pedido #<?= $o['id'] ?>?')">
                                Confirmar
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                <tr><td colspan="8" style="text-align:center;color:#94a3b8;">Nenhum pedido</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= e($statusFilter) ?>&p=<?= $i ?>" class="btn btn-sm" style="background:<?= $i===$pageNum?'#3b82f6':'#475569' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layout_end.php'; ?>
