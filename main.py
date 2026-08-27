import logging
import sqlite3
import os
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.fsm.state import State, StatesGroup
from aiogram.fsm.context import FSMContext

# --- CONFIGURAÇÕES ---
TOKEN = os.getenv("BOT_TOKEN")
ADMIN_ID = int(os.getenv("ADMIN_ID"))
PIX_CHAVE = "bdb5a10b-0890-43cd-a95e-bc3fb0844bb6" 

logging.basicConfig(level=logging.INFO)
bot = Bot(token=TOKEN)
dp = Dispatcher()

# --- BANCO DE DADOS ---
def init_db():
    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()
    cursor.execute('''CREATE TABLE IF NOT EXISTS estoque 
                      (id INTEGER PRIMARY KEY AUTOINCREMENT, categoria TEXT, conta TEXT)''')
    conn.commit()
    conn.close()

def add_to_stock(cat, account):
    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()
    cursor.execute("INSERT INTO estoque (categoria, conta) VALUES (?, ?)", (cat, account))
    conn.commit()
    conn.close()

def get_account(cat):
    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()
    cursor.execute("SELECT id, conta FROM estoque WHERE categoria = ? LIMIT 1", (cat,))
    res = cursor.fetchone()
    if res:
        item_id, account = res
        cursor.execute("DELETE FROM estoque WHERE id = ?", (item_id,))
        conn.commit()
    conn.close()
    return account

def get_categories():
    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()
    cursor.execute("SELECT DISTINCT categoria FROM estoque")
    cats = [row[0] for row in cursor.fetchall()]
    conn.close()
    return cats

class Form(StatesGroup):
    waiting_for_cat = State()
    waiting_for_account = State()

@dp.message(Command("start"))
async def start(message: types.Message):
    cats = get_categories()
    text = "👋 Bem-vindo à Loja de Contas!\n\nEscolha uma categoria abaixo:"
    if not cats:
        await message.answer("❌ O estoque está vazio no momento.")
        return
    keyboard = InlineKeyboardMarkup(inline_keyboard=[[InlineKeyboardButton(text=cat, callback_data=f"buy_{cat}")] for cat in cats])
    await message.answer(text, reply_markup=keyboard)

@dp.callback_query(F.data.startswith("buy_"))
async def buy_process(callback: types.CallbackQuery):
    cat = callback.data.split("_")[1]
    account = get_account(cat)
    if not account:
        await callback.answer("❌ Esta categoria esgotou!", show_alert=True)
        return
    text = (f"✅ Conta de **{cat}** disponível!\n\n"
            f"💰 Valor: Combine com o Admin\n"
            f"🔑 Chave PIX: `{PIX_CHAVE}`\n\n"
            f"Após pagar, envie o comprovante aqui no chat.")
    await callback.message.answer(text, parse_mode="Markdown")
    await bot.send_message(ADMIN_ID, f"⚠️ Pedido de {cat} feito por @{callback.from_user.username}")
    await callback.answer()

@dp.message(Command("admin"))
async def admin_panel(message: types.Message):
    if message.from_user.id != ADMIN_ID: return
    await message.answer("🛠 Painel Admin:\n/add - Adicionar conta\n/estoque - Ver categorias")

@dp.message(Command("add"))
async def add_start(message: types.Message, state: FSMContext):
    if message.from_user.id != ADMIN_ID: return
    await message.answer("Digite a categoria (ex: Netflix):")
    await state.set_state(Form.waiting_for_cat)

@dp.message(Form.waiting_for_cat)
async def add_cat(message: types.Message, state: FSMContext):
    await state.update_data(cat=message.text)
    await message.answer("Agora envie a conta no formato login:senha")
    await state.set_state(Form.waiting_for_account)

@dp.message(Form.waiting_for_account)
async def add_final(message: types.Message, state: FSMContext):
    data = await state.get_data()
    add_to_stock(data['cat'], message.text)
    await message.answer(f"✅ Conta adicionada em {data['cat']}!")
    await state.clear()

@dp.message(Command("estoque"))
async def show_stock(message: types.Message):
    if message.from_user.id != ADMIN_ID: return
    cats = get_categories()
    await message.answer(f"Categorias no estoque: {', '.join(cats) if cats else 'Nenhuma'}")

async def main():
    init_db()
    await dp.start_polling(bot)

if __name__ == "__main__":
    import asyncio
    asyncio.run(main())
