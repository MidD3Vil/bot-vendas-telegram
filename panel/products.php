<?php
require_once __DIR__ . '/config.php';
requireLogin();

$message = '';
$error = '';

// Criar / Editar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
    $active = isset($_POST['active']) ? 1 : 0;
    $sort = (int)($_POST['sort_order'] ?? 0);

    if ($name && $price > 0) {
        if ($action === 'create') {
            $stmt = db()->prepare('INSERT INTO products (name, description, price, active, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $description, $price, $active, $sort]);
            $message = 'Produto criado!';
        } elseif ($action === 'update' && $id) {
            $stmt = db()->prepare('UPDATE products SET name=?, description=?, price=?, active=?, sort_order=? WHERE id=?');
            $stmt->execute([$name, $description, $price, $active, $sort, $id]);
            $message = 'Produto atualizado!';
        }
    } else {
        $error = 'Nome e preço são obrigatórios.';
    }
}

// Toggle / Delete
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    db()->prepare('UPDATE products SET active = NOT active WHERE id = ?')->execute([$id]);
    header('Location: products.php');
    exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    header('Location: products.php');
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$products = db()->query('SELECT * FROM products ORDER BY sort_order, id')->fetchAll();

$page = 'products';
$pageTitle = 'Produtos';
include __DIR__ . '/layout.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom: 1.5rem;">
    <h2><?= $edit ? 'Editar produto' : 'Novo produto' ?></h2>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

        <div class="form-row">
            <div>
                <label>Nome</label>
                <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>">
            </div>
            <div>
                <label>Preço (R$)</label>
                <input type="text" name="price" required value="<?= e($edit['price'] ?? '') ?>" placeholder="19.90">
            </div>
        </div>
        <label>Descrição</label>
        <textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
        <div class="form-row">
            <div>
                <label>Ordem</label>
                <input type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? '0') ?>">
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;padding-top:1.5rem;">
                <input type="checkbox" name="active" id="active" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?> style="width:auto">
                <label for="active" style="margin:0">Ativo</label>
            </div>
        </div>
        <button type="submit" class="btn"><?= $edit ? 'Salvar' : 'Criar produto' ?></button>
        <?php if ($edit): ?>
            <a href="products.php" class="btn" style="background:#475569;margin-left:0.5rem">Cancelar</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>Lista de produtos</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Preço</th>
                    <th>Ordem</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= money($p['price']) ?></td>
                    <td><?= $p['sort_order'] ?></td>
                    <td>
                        <span class="badge <?= $p['active'] ? 'badge-paid' : 'badge-expired' ?>">
                            <?= $p['active'] ? 'ATIVO' : 'INATIVO' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm">Editar</a>
                        <a href="?toggle=<?= $p['id'] ?>" class="btn btn-sm" style="background:#64748b">Toggle</a>
                        <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout_end.php'; ?>
