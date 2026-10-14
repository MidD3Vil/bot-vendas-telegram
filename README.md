# Telegram PIX Bot - Sistema de Revenda

Sistema de cobrança via PIX no Telegram, com painel administrativo.
**Sem gateway externo** — usa chave PIX configurável + confirmação manual no painel.

## Tecnologias

- **Bot**: Node.js + Telegraf
- **Painel**: PHP 8.2+
- **Banco**: MySQL 8
- **Process Manager**: PM2

## Funcionalidades

### Bot Telegram
- Mensagem/imagem de boas-vindas personalizada
- Menu com Inline Keyboard
- Produtos pré-configurados
- Valor personalizado
- Envio da chave PIX + valor + número do pedido
- Histórico de pagamentos do usuário
- Notificação quando o admin confirma o pagamento

### Painel Admin
- Login seguro
- Dashboard com métricas e gráfico
- Lista de pedidos com botão **Confirmar**
- Gerenciamento de clientes e produtos
- Configuração da chave PIX e mensagens

---

## Fluxo de pagamento

1. Cliente escolhe produto ou valor personalizado
2. Bot cria o pedido (`pending`) e envia a **chave PIX** + valor + `PEDIDO #ID`
3. Cliente paga pelo app do banco
4. Você vê o PIX cair na conta e, no painel, clica em **Confirmar**
5. O sistema marca como pago e **notifica o cliente no Telegram**

---

## Instalação rápida

```bash
# Banco
mysql -u root -p < database/schema.sql

# Bot
cd bot
cp .env.example .env
nano .env   # BOT_TOKEN, DB_*, ADMIN_SECRET, BASE_URL
npm install
pm2 start src/index.js --name pix-bot
pm2 save
```

### Exemplo de `.env` do bot

```env
BOT_TOKEN=123456789:AAF...
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=sua_senha_mysql
DB_NAME=telegram_pix_bot
PORT=3000
BASE_URL=https://seudominio.com
ADMIN_SECRET=um_segredo_forte_igual_ao_painel
NODE_ENV=production
```

No painel (Configurações), preencha:
- **Chave PIX**
- **Tipo** e **Nome do favorecido**
- **bot_internal_url** → `http://127.0.0.1:3000`
- **admin_secret** → o mesmo valor do `ADMIN_SECRET` do `.env`

---

## Nginx (exemplo)

```nginx
server {
    listen 443 ssl http2;
    server_name seudominio.com;

    root /var/www/telegram-pix-bot/panel;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location /uploads {
        alias /var/www/telegram-pix-bot/uploads;
    }

    # Opcional: expor health do bot
    location /health {
        proxy_pass http://127.0.0.1:3000;
    }
}
```

---

## Acesso ao painel

- URL: `https://seudominio.com`
- E-mail: `admin@localhost.com`
- Senha: `admin123` ← **altere imediatamente**

---

## Estrutura

```
telegram-pix-bot/
├── bot/src/          # Bot + endpoint de confirmação
├── panel/            # Painel PHP
├── database/schema.sql
├── uploads/
└── README.md
```

## Comandos úteis

```bash
pm2 logs pix-bot
pm2 restart pix-bot
```

---

## Observação

Não há integração com MisticPay nem outro gateway. A cobrança é feita com a **sua chave PIX** e a liberação é **manual** no painel (com notificação automática ao cliente).

Se no futuro quiser gateway automático (QR dinâmico + webhook), dá para plugar de novo em cima desta base.
