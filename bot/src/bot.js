import { Telegraf, Markup } from 'telegraf';
import { v4 as uuidv4 } from 'uuid';
import {
  getOrCreateUser,
  getActiveProducts,
  getProduct,
  createOrder,
  updateOrderPix,
  getUserOrders,
  getSetting,
  query
} from './db.js';

export function createBot(token) {
  const bot = new Telegraf(token);

  function formatMoney(value) {
    return Number(value).toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    });
  }

  async function sendWelcome(ctx) {
    await getOrCreateUser(ctx.from);
    let message = (await getSetting('welcome_message')) || 'Olá! Escolha uma opção:';
    message = message.replace('{name}', ctx.from.first_name || 'cliente');

    const welcomeImage = await getSetting('welcome_image');

    const keyboard = Markup.inlineKeyboard([
      [Markup.button.callback('🛒 Comprar', 'menu_buy')],
      [Markup.button.callback('💰 Valor personalizado', 'menu_custom')],
      [Markup.button.callback('📜 Meus pagamentos', 'menu_history')],
      [Markup.button.callback('❓ Suporte', 'menu_support')]
    ]);

    if (welcomeImage) {
      try {
        await ctx.replyWithPhoto(welcomeImage, {
          caption: message,
          parse_mode: 'Markdown',
          ...keyboard
        });
        return;
      } catch (e) {
        console.error('Erro ao enviar imagem de boas-vindas:', e.message);
      }
    }

    await ctx.reply(message, { parse_mode: 'Markdown', ...keyboard });
  }

  bot.start(async (ctx) => {
    await sendWelcome(ctx);
  });

  bot.command('menu', async (ctx) => {
    await sendWelcome(ctx);
  });

  bot.command('help', async (ctx) => {
    await ctx.reply(
      '📌 *Como usar o bot*\n\n' +
        '1\\. Clique em *Comprar* e escolha um produto\n' +
        '2\\. Ou escolha *Valor personalizado*\n' +
        '3\\. Pague o PIX gerado\n' +
        '4\\. Após o pagamento, aguarde a confirmação\n\n' +
        'Use /menu para voltar ao início\\.',
      { parse_mode: 'MarkdownV2' }
    );
  });

  bot.action('menu_buy', async (ctx) => {
    await ctx.answerCbQuery();
    const products = await getActiveProducts();

    if (!products.length) {
      await ctx.editMessageText('Nenhum produto disponível no momento.');
      return;
    }

    const buttons = products.map((p) => [
      Markup.button.callback(`${p.name} - ${formatMoney(p.price)}`, `buy_${p.id}`)
    ]);
    buttons.push([Markup.button.callback('« Voltar', 'back_home')]);

    await ctx.editMessageText('🛒 *Escolha um produto:*', {
      parse_mode: 'Markdown',
      ...Markup.inlineKeyboard(buttons)
    });
  });

  bot.action(/^buy_(\d+)$/, async (ctx) => {
    await ctx.answerCbQuery();
    const productId = Number(ctx.match[1]);
    const product = await getProduct(productId);

    if (!product) {
      await ctx.editMessageText('Produto não encontrado ou indisponível.');
      return;
    }

    const user = await getOrCreateUser(ctx.from);
    await generateAndSendPix(ctx, {
      user,
      productId: product.id,
      amount: product.price,
      description: product.name,
      customAmount: null
    });
  });

  bot.action('menu_custom', async (ctx) => {
    await ctx.answerCbQuery();
    const min = (await getSetting('min_custom_amount')) || '5.00';
    const max = (await getSetting('max_custom_amount')) || '5000.00';

    await ctx.editMessageText(
      `💰 *Valor personalizado*\n\nDigite o valor desejado (entre R$ ${min} e R$ ${max}):\n\nExemplo: \`49.90\``,
      {
        parse_mode: 'Markdown',
        ...Markup.inlineKeyboard([[Markup.button.callback('« Voltar', 'back_home')]])
      }
    );

    ctx.session = ctx.session || {};
    ctx.session.awaitingCustomAmount = true;
  });

  bot.on('text', async (ctx, next) => {
    ctx.session = ctx.session || {};
    if (!ctx.session.awaitingCustomAmount) {
      return next();
    }

    ctx.session.awaitingCustomAmount = false;
    const text = ctx.message.text.replace(',', '.').trim();
    const amount = parseFloat(text);

    const min = parseFloat((await getSetting('min_custom_amount')) || '5');
    const max = parseFloat((await getSetting('max_custom_amount')) || '5000');

    if (isNaN(amount) || amount < min || amount > max) {
      await ctx.reply(
        `❌ Valor inválido. Digite um valor entre R$ ${min.toFixed(2)} e R$ ${max.toFixed(2)}.`
      );
      return;
    }

    const user = await getOrCreateUser(ctx.from);
    await generateAndSendPix(ctx, {
      user,
      productId: null,
      amount,
      description: `Pagamento personalizado - R$ ${amount.toFixed(2)}`,
      customAmount: amount
    });
  });

  bot.action('menu_history', async (ctx) => {
    await ctx.answerCbQuery();
    const user = await getOrCreateUser(ctx.from);
    const orders = await getUserOrders(user.id, 10);

    if (!orders.length) {
      await ctx.editMessageText('Você ainda não possui pagamentos.', {
        ...Markup.inlineKeyboard([[Markup.button.callback('« Voltar', 'back_home')]])
      });
      return;
    }

    let text = '📜 *Seus últimos pagamentos:*\n\n';
    for (const o of orders) {
      const statusEmoji =
        o.status === 'paid' ? '✅' : o.status === 'pending' ? '⏳' : '❌';
      const name = o.product_name || 'Personalizado';
      text += `${statusEmoji} #${o.id} - ${name}\n`;
      text += `   Valor: ${formatMoney(o.amount)} | ${o.status}\n`;
      text += `   ${new Date(o.created_at).toLocaleString('pt-BR')}\n\n`;
    }

    await ctx.editMessageText(text, {
      parse_mode: 'Markdown',
      ...Markup.inlineKeyboard([[Markup.button.callback('« Voltar', 'back_home')]])
    });
  });

  bot.action('menu_support', async (ctx) => {
    await ctx.answerCbQuery();
    const support = (await getSetting('support_username')) || '';
    let text = '❓ *Suporte*\n\n';
    if (support) {
      text += `Fale conosco: @${support.replace('@', '')}`;
    } else {
      text += 'Entre em contato com o administrador.';
    }

    await ctx.editMessageText(text, {
      parse_mode: 'Markdown',
      ...Markup.inlineKeyboard([[Markup.button.callback('« Voltar', 'back_home')]])
    });
  });

  bot.action('back_home', async (ctx) => {
    await ctx.answerCbQuery();
    await sendWelcome(ctx);
  });

  /**
   * Gera pedido e envia instruções de pagamento PIX (chave configurável)
   * Sem integração com gateway externo.
   */
  async function generateAndSendPix(ctx, { user, productId, amount, description, customAmount }) {
    const loadingMsg = await ctx.reply('⏳ Gerando cobrança, aguarde...');

    try {
      const externalId = `tg_${user.telegram_id}_${Date.now()}_${uuidv4().slice(0, 8)}`;
      const expiresAt = new Date(Date.now() + 30 * 60 * 1000);

      const orderId = await createOrder({
        userId: user.id,
        productId,
        customAmount,
        amount,
        description,
        externalId,
        expiresAt
      });

      const pixKey = (await getSetting('pix_key')) || '';
      const pixKeyType = (await getSetting('pix_key_type')) || 'ALEATORIA';
      const pixHolder = (await getSetting('pix_holder_name')) || 'Recebedor';

      await updateOrderPix(orderId, {
        gatewayTransactionId: null,
        copyPaste: pixKey || null,
        qrCodeBase64: null,
        qrcodeUrl: null
      });

      try {
        await ctx.telegram.deleteMessage(ctx.chat.id, loadingMsg.message_id);
      } catch (_) {}

      if (!pixKey) {
        await ctx.reply(
          '❌ A chave PIX ainda não foi configurada pelo administrador.\nTente novamente mais tarde.'
        );
        return;
      }

      const amountStr = Number(amount).toFixed(2).replace('.', ',');

      const caption =
        `💳 *Cobrança gerada!*\n\n` +
        `📦 ${description}\n` +
        `💰 Valor: *${formatMoney(amount)}*\n` +
        `🆔 Pedido: \`#${orderId}\`\n\n` +
        `📌 *Pague via PIX*\n` +
        `Chave: \`${pixKey}\`\n` +
        `Tipo: ${pixKeyType}\n` +
        `Favorecido: ${pixHolder}\n\n` +
        `📝 Na descrição do PIX coloque: \`PEDIDO ${orderId}\`\n\n` +
        `⏳ Após o pagamento, a confirmação será feita em breve.\n` +
        `Válido por 30 minutos.`;

      await ctx.reply(caption, {
        parse_mode: 'Markdown',
        ...Markup.inlineKeyboard([
          [Markup.button.callback('📋 Ver chave PIX', `show_pix_${orderId}`)],
          [Markup.button.callback('« Menu', 'back_home')]
        ])
      });

      await ctx.reply(
        `🔑 *Chave PIX:*\n\n\`${pixKey}\`\n\n💰 Valor: *R$ ${amountStr}*\n📝 Descrição sugerida: \`PEDIDO ${orderId}\`\n\nToque na chave acima para copiar.`,
        { parse_mode: 'Markdown' }
      );
    } catch (err) {
      console.error('Erro ao gerar cobrança:', err.message);
      try {
        await ctx.telegram.deleteMessage(ctx.chat.id, loadingMsg.message_id);
      } catch (_) {}
      await ctx.reply(
        '❌ Não foi possível gerar a cobrança no momento.\nTente novamente em instantes ou contate o suporte.'
      );
    }
  }

  bot.action(/^show_pix_(\d+)$/, async (ctx) => {
    await ctx.answerCbQuery();
    const orderId = Number(ctx.match[1]);
    const rows = await query(
      'SELECT pix_copy_paste, amount, description FROM orders WHERE id = ?',
      [orderId]
    );
    const order = rows[0];
    if (!order) {
      await ctx.reply('Pedido não encontrado.');
      return;
    }

    const pixKey = order.pix_copy_paste || (await getSetting('pix_key')) || '';
    if (!pixKey) {
      await ctx.reply('Chave PIX não configurada.');
      return;
    }

    const amountStr = Number(order.amount).toFixed(2).replace('.', ',');
    await ctx.reply(
      `🔑 *Chave PIX:*\n\n\`${pixKey}\`\n\n💰 Valor: *R$ ${amountStr}*\n📝 Descrição: \`PEDIDO ${orderId}\`\n\nToque na chave para copiar.`,
      { parse_mode: 'Markdown' }
    );
  });

  bot.catch((err, ctx) => {
    console.error(`Erro no bot para ${ctx.updateType}:`, err);
  });

  return bot;
}

export async function notifyPaymentConfirmed(bot, order, userTelegramId) {
  try {
    let message =
      (await getSetting('paid_message')) ||
      '✅ *Pagamento confirmado!*\n\nValor: R$ {amount}\nPedido: #{order_id}\n\nObrigado!';

    message = message
      .replace('{amount}', Number(order.amount).toFixed(2).replace('.', ','))
      .replace('{order_id}', order.id)
      .replace('{description}', order.description || '');

    const paidImage = await getSetting('paid_image');

    if (paidImage) {
      await bot.telegram.sendPhoto(userTelegramId, paidImage, {
        caption: message,
        parse_mode: 'Markdown'
      });
    } else {
      await bot.telegram.sendMessage(userTelegramId, message, { parse_mode: 'Markdown' });
    }
  } catch (err) {
    console.error('Erro ao notificar pagamento:', err.message);
  }
}
