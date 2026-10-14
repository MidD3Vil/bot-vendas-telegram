import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT) || 3306,
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'telegram_pix_bot',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  charset: 'utf8mb4'
});

export async function query(sql, params = []) {
  const [rows] = await pool.execute(sql, params);
  return rows;
}

export async function getSetting(key) {
  const rows = await query('SELECT setting_value FROM settings WHERE setting_key = ?', [key]);
  return rows[0]?.setting_value ?? null;
}

export async function setSetting(key, value) {
  await query(
    `INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)`,
    [key, value]
  );
}

export async function getOrCreateUser(telegramUser) {
  const existing = await query('SELECT * FROM users WHERE telegram_id = ?', [telegramUser.id]);
  if (existing.length) {
    await query(
      'UPDATE users SET username = ?, first_name = ?, last_name = ?, updated_at = NOW() WHERE telegram_id = ?',
      [telegramUser.username || null, telegramUser.first_name || null, telegramUser.last_name || null, telegramUser.id]
    );
    return existing[0];
  }

  const result = await query(
    'INSERT INTO users (telegram_id, username, first_name, last_name) VALUES (?, ?, ?, ?)',
    [telegramUser.id, telegramUser.username || null, telegramUser.first_name || null, telegramUser.last_name || null]
  );

  const rows = await query('SELECT * FROM users WHERE id = ?', [result.insertId]);
  return rows[0];
}

export async function getActiveProducts() {
  return query('SELECT * FROM products WHERE active = 1 ORDER BY sort_order ASC, id ASC');
}

export async function getProduct(id) {
  const rows = await query('SELECT * FROM products WHERE id = ? AND active = 1', [id]);
  return rows[0] || null;
}

export async function createOrder({ userId, productId, customAmount, amount, description, externalId, expiresAt }) {
  const result = await query(
    `INSERT INTO orders 
     (user_id, product_id, custom_amount, amount, description, status, external_transaction_id, expires_at)
     VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)`,
    [userId, productId, customAmount, amount, description, externalId, expiresAt]
  );
  return result.insertId;
}

export async function updateOrderPix(orderId, data) {
  await query(
    `UPDATE orders SET 
      gateway_transaction_id = ?,
      pix_copy_paste = ?,
      qrcode_base64 = ?,
      qrcode_url = ?,
      updated_at = NOW()
     WHERE id = ?`,
    [data.gatewayTransactionId, data.copyPaste, data.qrCodeBase64, data.qrcodeUrl, orderId]
  );
}

export async function getOrderByExternalId(externalId) {
  const rows = await query('SELECT * FROM orders WHERE external_transaction_id = ?', [externalId]);
  return rows[0] || null;
}

export async function getOrderByGatewayId(gatewayId) {
  const rows = await query('SELECT * FROM orders WHERE gateway_transaction_id = ?', [gatewayId]);
  return rows[0] || null;
}

export async function markOrderPaid(orderId) {
  await query(
    `UPDATE orders SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'`,
    [orderId]
  );
}

export async function getUserOrders(userId, limit = 10) {
  return query(
    `SELECT o.*, p.name as product_name 
     FROM orders o 
     LEFT JOIN products p ON p.id = o.product_id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC
     LIMIT ?`,
    [userId, limit]
  );
}

export async function logWebhook(payload, headers) {
  await query('INSERT INTO webhook_logs (payload, headers) VALUES (?, ?)', [
    JSON.stringify(payload),
    JSON.stringify(headers)
  ]);
}

export default pool;
