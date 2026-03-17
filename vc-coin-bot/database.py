import aiosqlite
import asyncio
from datetime import datetime, date
import os

DB_PATH = "data/bot_data.db"

async def init_db():
    os.makedirs("data", exist_ok=True)
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            CREATE TABLE IF NOT EXISTS coins (
                user_id TEXT PRIMARY KEY,
                username TEXT,
                coins REAL DEFAULT 0,
                total_earned REAL DEFAULT 0,
                last_updated TEXT
            )
        """)
        await db.execute("""
            CREATE TABLE IF NOT EXISTS blacklist (
                user_id TEXT PRIMARY KEY,
                username TEXT,
                reason TEXT,
                added_by TEXT,
                added_at TEXT
            )
        """)
        await db.execute("""
            CREATE TABLE IF NOT EXISTS keys (
                key_value TEXT PRIMARY KEY,
                added_at TEXT,
                added_by TEXT,
                is_used INTEGER DEFAULT 0,
                used_by TEXT,
                used_at TEXT
            )
        """)
        await db.execute("""
            CREATE TABLE IF NOT EXISTS redeem_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT,
                key_value TEXT,
                redeemed_at TEXT,
                redeem_date TEXT
            )
        """)
        await db.execute("""
            CREATE TABLE IF NOT EXISTS bot_settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        """)
        await db.execute("""
            CREATE TABLE IF NOT EXISTS panel_messages (
                panel_type TEXT PRIMARY KEY,
                channel_id TEXT,
                message_id TEXT
            )
        """)
        await db.execute("""
            CREATE TABLE IF NOT EXISTS vc_sessions (
                user_id TEXT PRIMARY KEY,
                join_time TEXT,
                is_screen_sharing INTEGER DEFAULT 0
            )
        """)
        # Default bot settings
        await db.execute("""
            INSERT OR IGNORE INTO bot_settings (key, value) VALUES ('bot_paused', '0')
        """)
        await db.execute("""
            INSERT OR IGNORE INTO bot_settings (key, value) VALUES ('start_time', ?)
        """, (datetime.utcnow().isoformat(),))
        await db.commit()

# ─── COIN FUNCTIONS ─────────────────────────────────────────────────────────

async def get_coins(user_id: str) -> float:
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT coins FROM coins WHERE user_id = ?", (user_id,)) as cursor:
            row = await cursor.fetchone()
            return row[0] if row else 0.0

async def get_total_coins_all() -> float:
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT SUM(coins) FROM coins") as cursor:
            row = await cursor.fetchone()
            return row[0] if row and row[0] else 0.0

async def get_all_coins():
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT user_id, username, coins, total_earned FROM coins ORDER BY coins DESC") as cursor:
            return await cursor.fetchall()

async def add_coins(user_id: str, username: str, amount: float):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            INSERT INTO coins (user_id, username, coins, total_earned, last_updated)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(user_id) DO UPDATE SET
                coins = coins + ?,
                total_earned = total_earned + ?,
                username = ?,
                last_updated = ?
        """, (user_id, username, amount, amount, datetime.utcnow().isoformat(),
              amount, amount, username, datetime.utcnow().isoformat()))
        await db.commit()

async def deduct_coins(user_id: str, amount: float) -> bool:
    current = await get_coins(user_id)
    if current < amount:
        return False
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("UPDATE coins SET coins = coins - ? WHERE user_id = ?", (amount, user_id))
        await db.commit()
    return True

# ─── BLACKLIST FUNCTIONS ─────────────────────────────────────────────────────

async def is_blacklisted(user_id: str) -> bool:
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT 1 FROM blacklist WHERE user_id = ?", (user_id,)) as cursor:
            return bool(await cursor.fetchone())

async def add_to_blacklist(user_id: str, username: str, reason: str, added_by: str):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            INSERT OR REPLACE INTO blacklist (user_id, username, reason, added_by, added_at)
            VALUES (?, ?, ?, ?, ?)
        """, (user_id, username, reason, added_by, datetime.utcnow().isoformat()))
        await db.commit()

async def remove_from_blacklist(user_id: str) -> bool:
    async with aiosqlite.connect(DB_PATH) as db:
        cursor = await db.execute("DELETE FROM blacklist WHERE user_id = ?", (user_id,))
        await db.commit()
        return cursor.rowcount > 0

async def get_blacklist():
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT user_id, username, reason, added_by, added_at FROM blacklist") as cursor:
            return await cursor.fetchall()

# ─── KEY FUNCTIONS ────────────────────────────────────────────────────────────

async def add_keys(keys: list, added_by: str) -> int:
    added = 0
    async with aiosqlite.connect(DB_PATH) as db:
        for key in keys:
            key = key.strip()
            if not key:
                continue
            try:
                await db.execute("""
                    INSERT OR IGNORE INTO keys (key_value, added_at, added_by)
                    VALUES (?, ?, ?)
                """, (key, datetime.utcnow().isoformat(), added_by))
                added += 1
            except Exception:
                pass
        await db.commit()
    return added

async def get_available_key():
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT key_value FROM keys WHERE is_used = 0 LIMIT 1") as cursor:
            row = await cursor.fetchone()
            return row[0] if row else None

async def mark_key_used(key_value: str, user_id: str):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            UPDATE keys SET is_used = 1, used_by = ?, used_at = ?
            WHERE key_value = ?
        """, (user_id, datetime.utcnow().isoformat(), key_value))
        await db.commit()

async def get_key_stock():
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT COUNT(*) FROM keys WHERE is_used = 0") as cursor:
            available = (await cursor.fetchone())[0]
        async with db.execute("SELECT COUNT(*) FROM keys WHERE is_used = 1") as cursor:
            used = (await cursor.fetchone())[0]
        return available, used

# ─── REDEEM HISTORY ──────────────────────────────────────────────────────────

async def get_daily_redeem_count(user_id: str) -> int:
    today = date.today().isoformat()
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("""
            SELECT COUNT(*) FROM redeem_history
            WHERE user_id = ? AND redeem_date = ?
        """, (user_id, today)) as cursor:
            row = await cursor.fetchone()
            return row[0] if row else 0

async def add_redeem_history(user_id: str, key_value: str):
    today = date.today().isoformat()
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            INSERT INTO redeem_history (user_id, key_value, redeemed_at, redeem_date)
            VALUES (?, ?, ?, ?)
        """, (user_id, key_value, datetime.utcnow().isoformat(), today))
        await db.commit()

# ─── BOT SETTINGS ─────────────────────────────────────────────────────────────

async def get_setting(key: str) -> str:
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT value FROM bot_settings WHERE key = ?", (key,)) as cursor:
            row = await cursor.fetchone()
            return row[0] if row else None

async def set_setting(key: str, value: str):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            INSERT OR REPLACE INTO bot_settings (key, value) VALUES (?, ?)
        """, (key, value))
        await db.commit()

async def is_bot_paused() -> bool:
    val = await get_setting('bot_paused')
    return val == '1'

async def set_bot_paused(paused: bool):
    await set_setting('bot_paused', '1' if paused else '0')

# ─── PANEL MESSAGE PERSISTENCE ───────────────────────────────────────────────

async def save_panel_message(panel_type: str, channel_id: str, message_id: str):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            INSERT OR REPLACE INTO panel_messages (panel_type, channel_id, message_id)
            VALUES (?, ?, ?)
        """, (panel_type, channel_id, message_id))
        await db.commit()

async def get_panel_message(panel_type: str):
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("""
            SELECT channel_id, message_id FROM panel_messages WHERE panel_type = ?
        """, (panel_type,)) as cursor:
            return await cursor.fetchone()

# ─── VC SESSION FUNCTIONS ─────────────────────────────────────────────────────

async def start_vc_session(user_id: str):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            INSERT OR REPLACE INTO vc_sessions (user_id, join_time, is_screen_sharing)
            VALUES (?, ?, 0)
        """, (user_id, datetime.utcnow().isoformat()))
        await db.commit()

async def end_vc_session(user_id: str):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("DELETE FROM vc_sessions WHERE user_id = ?", (user_id,))
        await db.commit()

async def update_screen_share(user_id: str, sharing: bool):
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute("""
            UPDATE vc_sessions SET is_screen_sharing = ? WHERE user_id = ?
        """, (1 if sharing else 0, user_id))
        await db.commit()

async def get_vc_sessions():
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute("SELECT user_id, join_time, is_screen_sharing FROM vc_sessions") as cursor:
            return await cursor.fetchall()
