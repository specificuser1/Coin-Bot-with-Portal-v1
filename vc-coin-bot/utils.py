import discord
import json
from datetime import datetime
from collections import defaultdict
import time

# ─── CONFIG LOADER ───────────────────────────────────────────────────────────

def load_config():
    with open("config.json", "r") as f:
        return json.load(f)

def save_config(config: dict):
    with open("config.json", "w") as f:
        json.dump(config, f, indent=2)

# ─── ANTI-SPAM ────────────────────────────────────────────────────────────────

class AntiSpam:
    def __init__(self):
        self.user_commands = defaultdict(list)

    def check(self, user_id: int, max_per_minute: int = 5) -> bool:
        now = time.time()
        self.user_commands[user_id] = [t for t in self.user_commands[user_id] if now - t < 60]
        if len(self.user_commands[user_id]) >= max_per_minute:
            return False
        self.user_commands[user_id].append(now)
        return True

anti_spam = AntiSpam()

# ─── EMBED BUILDERS ──────────────────────────────────────────────────────────

def error_embed(title: str, description: str) -> discord.Embed:
    cfg = load_config()
    embed = discord.Embed(
        title=f"❌ {title}",
        description=description,
        color=int(cfg["embeds"]["color_error"], 16),
        timestamp=datetime.utcnow()
    )
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    return embed

def success_embed(title: str, description: str) -> discord.Embed:
    cfg = load_config()
    embed = discord.Embed(
        title=f"✅ {title}",
        description=description,
        color=int(cfg["embeds"]["color_success"], 16),
        timestamp=datetime.utcnow()
    )
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    return embed

def warning_embed(title: str, description: str) -> discord.Embed:
    cfg = load_config()
    embed = discord.Embed(
        title=f"⚠️ {title}",
        description=description,
        color=int(cfg["embeds"]["color_warning"], 16),
        timestamp=datetime.utcnow()
    )
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    return embed

def info_embed(title: str, description: str) -> discord.Embed:
    cfg = load_config()
    embed = discord.Embed(
        title=f"ℹ️ {title}",
        description=description,
        color=int(cfg["embeds"]["color_main"], 16),
        timestamp=datetime.utcnow()
    )
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    return embed

# ─── TIME HELPERS ─────────────────────────────────────────────────────────────

def format_uptime(start_time_str: str) -> str:
    start = datetime.fromisoformat(start_time_str)
    delta = datetime.utcnow() - start
    hours, rem = divmod(int(delta.total_seconds()), 3600)
    minutes, seconds = divmod(rem, 60)
    days = hours // 24
    hours = hours % 24
    if days > 0:
        return f"{days}d {hours}h {minutes}m {seconds}s"
    elif hours > 0:
        return f"{hours}h {minutes}m {seconds}s"
    else:
        return f"{minutes}m {seconds}s"

def is_account_old_enough(created_at: datetime, weeks: int = 4) -> bool:
    age = datetime.utcnow() - created_at.replace(tzinfo=None)
    return age.days >= (weeks * 7)

def format_coins(coins: float) -> str:
    return f"{coins:,.1f}".rstrip('0').rstrip('.')

# ─── PERMISSION CHECKS ───────────────────────────────────────────────────────

def is_admin(member: discord.Member, config: dict) -> bool:
    admin_role_ids = [int(r) for r in config["roles"]["admin_role_ids"] if r != "YOUR_ADMIN_ROLE_ID"]
    if member.guild_permissions.administrator:
        return True
    for role in member.roles:
        if role.id in admin_role_ids:
            return True
    return False
