import express from 'express';
import dotenv from 'dotenv';
import { createBot, notifyPaymentConfirmed } from './bot.js';
import { markOrderPaid, query } from './db.js';

dotenv.config();

const PORT = process.env.PORT || 3000;
const BOT_TOKEN = process.env.BOT_TOKEN;

if (!BOT_TOKEN) {
  console.error('❌ BOT_TOKEN não configurado no .env');
  process.exit(1);
}

const bot = createBot(BOT_TOKEN);
const app = express();

app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true }));

app.get('/health', (req, res) => {
  res.json({ status: 'ok', time: new Date().toISOString() });
});

/**
 * Endpoint interno para o painel confirmar pagamento e notificar o usuário.
 * POST /internal/confirm-payment
 * Body: { order_id: 123, secret: "..." }
 */
app.post('/internal/confirm-payment', async (req, res) => {
  try {
    const { order_id, secret } = req.body;
    const expected = process.env.ADMIN_SECRET || '';

    if (!expected || secret !== expected) {
      return res.status(401).json({ error: 'unauthorized' });
    }

    const orderId = Number(order_id);
    if (!orderId) {
      return res.status(400).json({ error: 'order_id inválido' });
    }

    const rows = await query('SELECT * FROM orders WHERE id = ?', [orderId]);
    const order = rows[0];

    if (!order) {
      return res.status(404).json({ error: 'pedido não encontrado' });
    }

    if (order.status === 'paid') {
      return res.json({ ok: true, message: 'já estava pago' });
    }

    await markOrderPaid(orderId);

    const users = await query('SELECT telegram_id FROM users WHERE id = ?', [order.user_id]);
    if (users[0]?.telegram_id) {
      await notifyPaymentConfirmed(bot, { ...order, status: 'paid' }, users[0].telegram_id);
    }

    res.json({ ok: true, message: 'pagamento confirmado e usuário notificado' });
  } catch (err) {
    console.error('[confirm-payment]', err);
    res.status(500).json({ error: 'internal' });
  }
});

async function main() {
  bot.launch({ dropPendingUpdates: true }).then(() => {
    console.log('🤖 Bot Telegram iniciado (polling)');
  });

  app.listen(PORT, () => {
    console.log(`🌐 Servidor HTTP na porta ${PORT}`);
    console.log(`📡 Confirmação interna: POST /internal/confirm-payment`);
  });

  process.once('SIGINT', () => bot.stop('SIGINT'));
  process.once('SIGTERM', () => bot.stop('SIGTERM'));
}

main().catch((err) => {
  console.error('Falha ao iniciar:', err);
  process.exit(1);
});
