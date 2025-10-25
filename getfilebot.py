import asyncio
from aiogram import Bot, Dispatcher, types
from aiogram.filters import Command
from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup, ReplyKeyboardMarkup, KeyboardButton

# ============ CONFIG ============
BOT_TOKEN = "7567174638:AAGipkqvVma7kuzYeMUdhNdWAVdySPocCsk"
# ================================

bot = Bot(token=BOT_TOKEN)
dp = Dispatcher()

# ----- Inline Buttons Menu -----
def group_menu():
    kb = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="📱Youtube", callback_data="Premium"),
            InlineKeyboardButton(text="🔇YT Music", callback_data="ytmusic"),
            InlineKeyboardButton(text="🫵Config", callback_data="Config"),
],
        [
            InlineKeyboardButton(text="🔧MT Manager", callback_data="mtpro"),
            InlineKeyboardButton(text="📌Apkeditor", callback_data="apkeditor"),
            InlineKeyboardButton(text="⚙️Telegram", callback_data="telegram"),
            

        ],
        [
            
            InlineKeyboardButton(text="♻️Tempmail", callback_data="tempmail"),
            InlineKeyboardButton(text="🔞Vpn", callback_data="vpn"),
            InlineKeyboardButton(text="🛜Dns", callback_data="dns"),
        ],
        [
            InlineKeyboardButton(text="🔎BotLoby Apk", callback_data="botloby"),
            InlineKeyboardButton(text="🖇HttpCanary", callback_data="canary"),
            InlineKeyboardButton(text="🌐Firewall", callback_data="firewall"),

        ],
        [
            InlineKeyboardButton(text="✅Help Owner✅", callback_data="helpinfo"),
        ]
        ])
    return kb

# ----- Reply Keyboard with Start Button -----
start_keyboard = ReplyKeyboardMarkup(
    keyboard=[[KeyboardButton(text="Start")]],
    resize_keyboard=True,
    one_time_keyboard=True
)

# ----- Command: /start or /menu -----
@dp.message(Command(commands=["start", "menu"]))
async def cmd_start(message: types.Message):
    await message.answer(
        "👋 Welcome!♻️Get files system 2.0♻️ Press start ",
        reply_markup=start_keyboard
    )

# ----- Handle Start Button Press -----
@dp.message(lambda message: message.text.lower() == "start")
async def send_menu(message: types.Message):
    await message.answer(
        "♻️Get files system 2.0♻️Choose an option: if you need files",
        reply_markup=group_menu()
    )

# ----- Handle Inline Button Clicks -----
@dp.callback_query()
async def handle_buttons(callback: types.CallbackQuery):
    data = callback.data

    if data == "Premium":
        await callback.message.answer("#Premium")
    elif data == "ytmusic":
        await callback.message.answer("#ytmusic")
    elif data == "Config":
        await callback.message.answer("#Config")

    elif data == "mtpro":
        await callback.message.answer("#mtpro")
    elif data == "apkeditor":
        await callback.message.answer("#apkeditor")

    elif data == "telegram":
        await callback.message.answer("#telegram")
    elif data == "tempmail":
        await callback.message.answer("#tempmail")

    elif data == "vpn":
        await callback.message.answer("#vpn")
    elif data == "dns":
        await callback.message.answer("#dns")

    elif data == "botloby":
        await callback.message.answer("#botloby")
    elif data == "canary":
        await callback.message.answer("#canary")


    elif data == "firewall":
        await callback.message.answer("#firewall")
    elif data == "helpinfo":
        await callback.message.answer("#danger")


    await callback.answer()  # close loading animation

# ----- Start Bot -----
async def main():
    print("🤖Bot started successfully...")
    await dp.start_polling(bot)

if __name__ == "__main__":
    asyncio.run(main())
