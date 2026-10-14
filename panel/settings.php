<?php
require_once __DIR__ . '/config.php';
requireLogin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'bot_token',
        'pix_key',
        'pix_key_type',
        'pix_holder_name',
        'welcome_message',
        'welcome_image',
        'paid_message',
        'paid_image',
        'support_username',
        'min_custom_amount',
        'max_custom_amount',
        'bot_internal_url',
        'admin_secret'
    ];

    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            setSetting($key, trim($_POST[$key]));
        }
    }

    if (!empty($_FILES['welcome_image_file']['tmp_name'])) {
        $ext = pathinfo($_FILES['welcome_image_file']['name'], PATHINFO_EXTENSION);
        $filename = 'welcome_' . time() . '.' . $ext;
        $dest = UPLOAD_DIR . $filename;
        if (move_uploaded_file($_FILES['welcome_image_file']['tmp_name'], $dest)) {
            setSetting('welcome_image', '/uploads/' . $filename);
        }
    }
    if (!empty($_FILES['paid_image_file']['tmp_name'])) {
        $ext = pathinfo($_FILES['paid_image_file']['name'], PATHINFO_EXTENSION);
        $filename = 'paid_' . time() . '.' . $ext;
        $dest = UPLOAD_DIR . $filename;
        if (move_uploaded_file($_FILES['paid_image_file']['tmp_name'], $dest)) {
            setSetting('paid_image', '/uploads/' . $filename);
        }
    }

    $message = 'Configurações salvas!';
}

$settings = [];
$rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$page = 'settings';
$pageTitle = 'Configurações';
include __DIR__ . '/layout.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Telegram Bot</h2>
        <label>Token do Bot (BotFather) — use também no .env do bot</label>
        <input type="text" name="bot_token" value="<?= e($settings['bot_token'] ?? '') ?>" placeholder="123456:ABC...">

        <label>Username do suporte (sem @)</label>
        <input type="text" name="support_username" value="<?= e($settings['support_username'] ?? '') ?>" placeholder="seu_suporte">
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Chave PIX (recebimento)</h2>
        <p style="font-size:0.85rem;color:#94a3b8;margin-bottom:1rem">
            O bot envia esta chave aos clientes. Você confirma o pagamento manualmente no painel.
        </p>
        <label>Chave PIX</label>
        <input type="text" name="pix_key" value="<?= e($settings['pix_key'] ?? '') ?>" placeholder="CPF, e-mail, telefone ou chave aleatória">

        <div class="form-row">
            <div>
                <label>Tipo da chave</label>
                <select name="pix_key_type">
                    <?php
                    $types = ['CPF' => 'CPF', 'CNPJ' => 'CNPJ', 'EMAIL' => 'E-mail', 'TELEFONE' => 'Telefone', 'ALEATORIA' => 'Aleatória'];
                    $current = $settings['pix_key_type'] ?? 'ALEATORIA';
                    foreach ($types as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $current === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Nome do favorecido</label>
                <input type="text" name="pix_holder_name" value="<?= e($settings['pix_holder_name'] ?? '') ?>" placeholder="Seu nome ou empresa">
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Integração painel ↔ bot (notificação)</h2>
        <label>URL interna do bot (ex: http://127.0.0.1:3000)</label>
        <input type="text" name="bot_internal_url" value="<?= e($settings['bot_internal_url'] ?? 'http://127.0.0.1:3000') ?>">

        <label>Admin Secret (mesmo valor do ADMIN_SECRET no .env do bot)</label>
        <input type="password" name="admin_secret" value="<?= e($settings['admin_secret'] ?? '') ?>" placeholder="segredo forte">
        <p style="font-size:0.8rem;color:#94a3b8;margin-top:-0.5rem">
            Usado quando você clica em "Confirmar pagamento" no painel para notificar o cliente no Telegram.
        </p>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Mensagens do Bot</h2>
        <label>Mensagem de boas-vindas (use {name})</label>
        <textarea name="welcome_message" rows="4"><?= e($settings['welcome_message'] ?? '') ?></textarea>

        <label>URL da imagem de boas-vindas</label>
        <input type="url" name="welcome_image" value="<?= e($settings['welcome_image'] ?? '') ?>" placeholder="https://...">
        <input type="file" name="welcome_image_file" accept="image/*,video/*">

        <label>Mensagem de pagamento aprovado (use {amount}, {order_id})</label>
        <textarea name="paid_message" rows="4"><?= e($settings['paid_message'] ?? '') ?></textarea>

        <label>URL da imagem de pagamento aprovado</label>
        <input type="url" name="paid_image" value="<?= e($settings['paid_image'] ?? '') ?>">
        <input type="file" name="paid_image_file" accept="image/*">
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Valores personalizados</h2>
        <div class="form-row">
            <div>
                <label>Valor mínimo (R$)</label>
                <input type="text" name="min_custom_amount" value="<?= e($settings['min_custom_amount'] ?? '5.00') ?>">
            </div>
            <div>
                <label>Valor máximo (R$)</label>
                <input type="text" name="max_custom_amount" value="<?= e($settings['max_custom_amount'] ?? '5000.00') ?>">
            </div>
        </div>
    </div>

    <button type="submit" class="btn">Salvar configurações</button>
</form>

<div class="card" style="margin-top: 1.5rem;">
    <h2>⚠️ Importante</h2>
    <p style="color:#94a3b8;font-size:0.9rem;line-height:1.6">
        • Configure a <strong>chave PIX</strong> acima para o bot enviar aos clientes.<br>
        • O <strong>BOT_TOKEN</strong> e o <strong>ADMIN_SECRET</strong> também devem estar no arquivo <code>.env</code> do bot Node.js.<br>
        • Confirme os pagamentos em <strong>Pedidos</strong> → botão "Confirmar". O cliente será notificado no Telegram.<br>
        • Senha padrão do admin: <strong>admin123</strong> (e-mail: admin@localhost.com) — altere imediatamente!
    </p>
</div>

<?php include __DIR__ . '/layout_end.php'; ?>
