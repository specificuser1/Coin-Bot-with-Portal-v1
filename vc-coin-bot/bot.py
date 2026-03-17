import discord
from discord.ext import commands, tasks
from discord.ui import View
import os
import json
import asyncio
from datetime import datetime
from dotenv import load_dotenv

import database as db
from utils import load_config, is_admin, format_coins, is_account_old_enough, error_embed, success_embed, warning_embed, info_embed, anti_spam
from panels import (
    build_public_panel_embed, build_admin_panel_embed,
    PublicPanelView, AdminPanelView,
    refresh_public_panel, refresh_admin_panel,
    log_action
)

load_dotenv()
TOKEN = os.getenv("DISCORD_TOKEN")

# ─── BOT SETUP ────────────────────────────────────────────────────────────────

def get_prefix(bot, message):
    cfg = load_config()
    return cfg.get("prefix", "!")

intents = discord.Intents.default()
intents.message_content = True
intents.members = True
intents.voice_states = True

bot = commands.Bot(command_prefix=get_prefix, intents=intents, help_command=None)

# ══════════════════════════════════════════════════════════
#                   BOT EVENTS
# ══════════════════════════════════════════════════════════

@bot.event
async def on_ready():
    cfg = load_config()
    print(f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print(f"  VC Coin Earner — Online!")
    print(f"  Logged in as: {bot.user}")
    print(f"  Programmed by: {cfg['developer']}")
    print(f"  Prefix: {cfg['prefix']}")
    print(f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")

    await db.init_db()
    await db.set_setting('start_time', datetime.utcnow().isoformat())

    # Set DND status
    await bot.change_presence(
        status=discord.Status.dnd,
        activity=discord.Activity(
            type=discord.ActivityType.watching,
            name=f"Programmed by {cfg['developer']}"
        )
    )

    # Register persistent views
    bot.add_view(PublicPanelView())
    bot.add_view(AdminPanelView())

    # Restore panels from DB
    await restore_panels()

    # Start coin earning loop
    if not coin_loop.is_running():
        coin_loop.start()

    # Start panel refresh loop
    if not panel_refresh_loop.is_running():
        panel_refresh_loop.start()

    print("[Bot] All systems ready!")


async def restore_panels():
    """Restore panel views after bot restart so buttons still work."""
    try:
        public_data = await db.get_panel_message("public")
        if public_data:
            channel_id, message_id = public_data
            channel = bot.get_channel(int(channel_id))
            if channel:
                try:
                    message = await channel.fetch_message(int(message_id))
                    embed = await build_public_panel_embed()
                    await message.edit(embed=embed, view=PublicPanelView())
                    print(f"[Panel] Public panel restored in #{channel.name}")
                except discord.NotFound:
                    print("[Panel] Public panel message not found, will resend on next !panel command.")
    except Exception as e:
        print(f"[Panel] Could not restore public panel: {e}")

    try:
        admin_data = await db.get_panel_message("admin")
        if admin_data:
            channel_id, message_id = admin_data
            channel = bot.get_channel(int(channel_id))
            if channel:
                try:
                    message = await channel.fetch_message(int(message_id))
                    embed = await build_admin_panel_embed(bot)
                    await message.edit(embed=embed, view=AdminPanelView())
                    print(f"[Panel] Admin panel restored in #{channel.name}")
                except discord.NotFound:
                    print("[Panel] Admin panel message not found, will resend on next !panel command.")
    except Exception as e:
        print(f"[Panel] Could not restore admin panel: {e}")


@bot.event
async def on_voice_state_update(member: discord.Member, before: discord.VoiceState, after: discord.VoiceState):
    if member.bot:
        return

    cfg = load_config()
    user_id = str(member.id)
    allowed_vcs = cfg["channels"].get("allowed_vc_ids", [])

    # ─── CHECK NEW MEMBER ACCOUNT AGE ─────────────────────
    if after.channel and not before.channel:
        created_at = member.created_at.replace(tzinfo=None)
        if not is_account_old_enough(created_at, cfg["account_age"]["minimum_weeks"]):
            if cfg["account_age"]["auto_blacklist"] and not await db.is_blacklisted(user_id):
                await db.add_to_blacklist(user_id, str(member), "Account too new (auto-blacklist)", "AutoSystem")
                await log_action(
                    bot,
                    f"🤖 **Auto-Blacklisted** | {member.mention} (`{member.id}`) — account age less than 4 weeks.",
                    "blacklist"
                )

    # ─── USER JOINED A VC ─────────────────────────────────
    if after.channel and not before.channel:
        if allowed_vcs and str(after.channel.id) not in allowed_vcs:
            return
        await db.start_vc_session(user_id)
        print(f"[VC] {member.name} joined #{after.channel.name}")

    # ─── USER LEFT VC ─────────────────────────────────────
    elif before.channel and not after.channel:
        await db.end_vc_session(user_id)
        print(f"[VC] {member.name} left VC")

    # ─── USER MOVED VC ────────────────────────────────────
    elif before.channel and after.channel and before.channel.id != after.channel.id:
        if allowed_vcs and str(after.channel.id) not in allowed_vcs:
            await db.end_vc_session(user_id)
        else:
            await db.start_vc_session(user_id)

    # ─── SCREEN SHARE STATE CHANGE ────────────────────────
    if after.channel:
        sharing = after.self_stream or False
        was_sharing = before.self_stream or False
        if sharing != was_sharing:
            await db.update_screen_share(user_id, sharing)
            print(f"[VC] {member.name} screen share: {'ON' if sharing else 'OFF'}")


@bot.event
async def on_command_error(ctx, error):
    cfg = load_config()
    if isinstance(error, commands.CommandNotFound):
        return
    elif isinstance(error, commands.MissingPermissions):
        await ctx.send(embed=error_embed("No Permission", "You don't have permission to use this command."), delete_after=10)
    elif isinstance(error, commands.MissingRequiredArgument):
        await ctx.send(embed=error_embed("Missing Argument", f"Missing required argument: `{error.param.name}`\nUse `{cfg['prefix']}help` for usage info."), delete_after=10)
    elif isinstance(error, commands.BadArgument):
        await ctx.send(embed=error_embed("Invalid Argument", "Invalid argument provided. Please check your input."), delete_after=10)
    elif isinstance(error, commands.CommandOnCooldown):
        await ctx.send(embed=warning_embed("Cooldown", f"This command is on cooldown. Try again in `{error.retry_after:.1f}s`."), delete_after=10)
    else:
        print(f"[Error] Unhandled error in {ctx.command}: {error}")
        await ctx.send(embed=error_embed("Error", f"An unexpected error occurred: `{type(error).__name__}`"), delete_after=10)


# ══════════════════════════════════════════════════════════
#                   BACKGROUND TASKS
# ══════════════════════════════════════════════════════════

@tasks.loop(seconds=60)
async def coin_loop():
    """Award coins every minute to eligible VC members."""
    try:
        if await db.is_bot_paused():
            return

        cfg = load_config()
        sessions = await db.get_vc_sessions()

        for user_id, join_time, is_sharing in sessions:
            # Try to find member in any guild
            member = None
            for guild in bot.guilds:
                m = guild.get_member(int(user_id))
                if m:
                    member = m
                    break

            if not member:
                continue

            # Check blacklist
            if await db.is_blacklisted(user_id):
                continue

            # Find their VC state
            vc_state = member.voice
            if not vc_state or not vc_state.channel:
                await db.end_vc_session(user_id)
                continue

            # AFK / Deafened check
            if vc_state.afk or vc_state.self_deaf or vc_state.deaf:
                continue

            # Alone in VC check (optional: skip solo members)
            # if len(vc_state.channel.members) < 2:
            #     continue

            # Calculate coins
            base = cfg["coins"]["per_minute_vc"]
            if is_sharing:
                coins_earned = base * cfg["coins"]["screen_share_multiplier"]
            else:
                coins_earned = base

            await db.add_coins(user_id, str(member), coins_earned)

        await refresh_public_panel(bot)

    except Exception as e:
        print(f"[CoinLoop] Error: {e}")


@coin_loop.before_loop
async def before_coin_loop():
    await bot.wait_until_ready()


@tasks.loop(minutes=5)
async def panel_refresh_loop():
    """Refresh admin panel every 5 minutes for live stats."""
    try:
        await refresh_admin_panel(bot)
    except Exception as e:
        print(f"[PanelLoop] Error: {e}")


@panel_refresh_loop.before_loop
async def before_panel_refresh():
    await bot.wait_until_ready()
    await asyncio.sleep(30)


# ══════════════════════════════════════════════════════════
#                   COMMANDS
# ══════════════════════════════════════════════════════════

# ─── PANEL COMMANDS ──────────────────────────────────────

@bot.command(name="panel")
async def send_panel(ctx):
    """Send/resend both panels."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can send panels."), delete_after=10)
        return

    await ctx.message.delete()

    # Public Panel
    public_ch_id = cfg["channels"].get("public_panel_channel_id")
    if public_ch_id and public_ch_id != "YOUR_PUBLIC_PANEL_CHANNEL_ID":
        public_ch = bot.get_channel(int(public_ch_id))
        if public_ch:
            embed = await build_public_panel_embed()
            msg = await public_ch.send(embed=embed, view=PublicPanelView())
            await db.save_panel_message("public", str(public_ch.id), str(msg.id))
            print(f"[Panel] Public panel sent in #{public_ch.name}")

    # Admin Panel
    admin_ch_id = cfg["channels"].get("admin_panel_channel_id")
    if admin_ch_id and admin_ch_id != "YOUR_ADMIN_PANEL_CHANNEL_ID":
        admin_ch = bot.get_channel(int(admin_ch_id))
        if admin_ch:
            embed = await build_admin_panel_embed(bot)
            msg = await admin_ch.send(embed=embed, view=AdminPanelView())
            await db.save_panel_message("admin", str(admin_ch.id), str(msg.id))
            print(f"[Panel] Admin panel sent in #{admin_ch.name}")

    temp = await ctx.send(embed=success_embed("Panels Sent", "Both panels have been sent/updated successfully!"))
    await asyncio.sleep(5)
    await temp.delete()


@bot.command(name="publicpanel")
async def send_public_panel(ctx):
    """Send public panel in current channel."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this."), delete_after=10)
        return
    await ctx.message.delete()
    embed = await build_public_panel_embed()
    msg = await ctx.send(embed=embed, view=PublicPanelView())
    await db.save_panel_message("public", str(ctx.channel.id), str(msg.id))


@bot.command(name="adminpanel")
async def send_admin_panel(ctx):
    """Send admin panel in current channel."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this."), delete_after=10)
        return
    await ctx.message.delete()
    embed = await build_admin_panel_embed(bot)
    msg = await ctx.send(embed=embed, view=AdminPanelView())
    await db.save_panel_message("admin", str(ctx.channel.id), str(msg.id))


# ─── COIN COMMANDS ───────────────────────────────────────

@bot.command(name="coins")
async def check_coins_cmd(ctx, member: discord.Member = None):
    """Check coins for yourself or a member."""
    cfg = load_config()
    if not anti_spam.check(ctx.author.id, cfg["anti_spam"]["max_commands_per_minute"]):
        await ctx.send(embed=error_embed("Slow Down!", "Too many commands! Please wait."), delete_after=10)
        return

    target = member or ctx.author
    coins = await db.get_coins(str(target.id))
    daily_count = await db.get_daily_redeem_count(str(target.id))
    blacklisted = await db.is_blacklisted(str(target.id))

    embed = discord.Embed(
        title="💰 Coin Balance",
        color=int(cfg["embeds"]["color_main"], 16),
        timestamp=datetime.utcnow()
    )
    embed.set_thumbnail(url=target.display_avatar.url)
    embed.add_field(name="👤 Member", value=target.mention, inline=True)
    embed.add_field(name="💎 Coins", value=f"`{format_coins(coins)}`", inline=True)
    embed.add_field(name="🎁 Daily Redeems", value=f"`{daily_count}/2`", inline=True)
    embed.add_field(name="🚫 Blacklisted", value="Yes ❌" if blacklisted else "No ✅", inline=True)
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    await ctx.send(embed=embed)


@bot.command(name="addcoins")
async def add_coins_cmd(ctx, member: discord.Member, amount: float):
    """Admin: Add coins to a member."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this command."), delete_after=10)
        return
    if amount <= 0:
        await ctx.send(embed=error_embed("Invalid Amount", "Amount must be positive."), delete_after=10)
        return

    await db.add_coins(str(member.id), str(member), amount)
    await ctx.send(embed=success_embed("Coins Added", f"Added `{format_coins(amount)}` coins to {member.mention}."))
    await log_action(bot, f"➕ **Coins Added** | {ctx.author.mention} added `{format_coins(amount)}` coins to {member.mention}", "general")


@bot.command(name="removecoins")
async def remove_coins_cmd(ctx, member: discord.Member, amount: float):
    """Admin: Remove coins from a member."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this command."), delete_after=10)
        return
    if amount <= 0:
        await ctx.send(embed=error_embed("Invalid Amount", "Amount must be positive."), delete_after=10)
        return

    success = await db.deduct_coins(str(member.id), amount)
    if success:
        await ctx.send(embed=success_embed("Coins Removed", f"Removed `{format_coins(amount)}` coins from {member.mention}."))
        await log_action(bot, f"➖ **Coins Removed** | {ctx.author.mention} removed `{format_coins(amount)}` coins from {member.mention}", "general")
    else:
        current = await db.get_coins(str(member.id))
        await ctx.send(embed=error_embed("Insufficient Coins", f"{member.mention} only has `{format_coins(current)}` coins."), delete_after=10)


@bot.command(name="setcoins")
async def set_coins_cmd(ctx, member: discord.Member, amount: float):
    """Admin: Set coins for a member."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this command."), delete_after=10)
        return

    import aiosqlite
    async with aiosqlite.connect(db.DB_PATH) as dbase:
        await dbase.execute("""
            INSERT INTO coins (user_id, username, coins, total_earned, last_updated)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(user_id) DO UPDATE SET coins = ?, username = ?, last_updated = ?
        """, (str(member.id), str(member), amount, amount, datetime.utcnow().isoformat(),
              amount, str(member), datetime.utcnow().isoformat()))
        await dbase.commit()

    await ctx.send(embed=success_embed("Coins Set", f"Set coins for {member.mention} to `{format_coins(amount)}`."))
    await log_action(bot, f"⚙️ **Coins Set** | {ctx.author.mention} set {member.mention}'s coins to `{format_coins(amount)}`", "general")


@bot.command(name="resetcoins")
async def reset_coins_cmd(ctx, member: discord.Member):
    """Admin: Reset a member's coins to 0."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this command."), delete_after=10)
        return

    import aiosqlite
    async with aiosqlite.connect(db.DB_PATH) as dbase:
        await dbase.execute("UPDATE coins SET coins = 0 WHERE user_id = ?", (str(member.id),))
        await dbase.commit()

    await ctx.send(embed=success_embed("Coins Reset", f"Reset coins for {member.mention} to `0`."))
    await log_action(bot, f"🔄 **Coins Reset** | {ctx.author.mention} reset {member.mention}'s coins to 0", "general")


# ─── BLACKLIST COMMANDS ──────────────────────────────────

@bot.command(name="blacklist")
async def blacklist_cmd(ctx, user_id: str, *, reason: str = "No reason provided"):
    """Admin: Add a member to blacklist by ID."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this command."), delete_after=10)
        return

    try:
        user = await bot.fetch_user(int(user_id))
        uname = str(user)
    except Exception:
        uname = f"Unknown ({user_id})"

    if await db.is_blacklisted(user_id):
        await ctx.send(embed=warning_embed("Already Blacklisted", f"User `{user_id}` is already blacklisted."), delete_after=10)
        return

    await db.add_to_blacklist(user_id, uname, reason, str(ctx.author))
    await ctx.send(embed=success_embed("Blacklisted", f"User `{uname}` (`{user_id}`) has been blacklisted.\n**Reason:** {reason}"))
    await log_action(bot, f"🚫 **Blacklisted** | {ctx.author.mention} blacklisted `{uname}` (`{user_id}`). Reason: {reason}", "blacklist")


@bot.command(name="unblacklist")
async def unblacklist_cmd(ctx, user_id: str):
    """Admin: Remove a member from blacklist by ID."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this command."), delete_after=10)
        return

    removed = await db.remove_from_blacklist(user_id)
    if removed:
        await ctx.send(embed=success_embed("Removed", f"User `{user_id}` has been removed from the blacklist."))
        await log_action(bot, f"✅ **Unblacklisted** | {ctx.author.mention} removed `{user_id}` from blacklist.", "blacklist")
    else:
        await ctx.send(embed=error_embed("Not Found", f"User `{user_id}` is not in the blacklist."), delete_after=10)


@bot.command(name="blacklistcheck")
async def blacklist_check_cmd(ctx, user_id: str):
    """Check if a user is blacklisted."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can use this."), delete_after=10)
        return

    blacklisted = await db.is_blacklisted(user_id)
    if blacklisted:
        bl = await db.get_blacklist()
        for uid, uname, reason, added_by, added_at in bl:
            if uid == user_id:
                embed = error_embed("Blacklisted", f"User `{uname}` (`{uid}`) is blacklisted.\n**Reason:** {reason}\n**Added by:** {added_by}\n**Date:** {added_at[:10]}")
                await ctx.send(embed=embed)
                return
    await ctx.send(embed=success_embed("Not Blacklisted", f"User `{user_id}` is not blacklisted."))


# ─── REDEEM COMMAND ──────────────────────────────────────

@bot.command(name="redeem")
@commands.cooldown(1, 10, commands.BucketType.user)
async def redeem_cmd(ctx, key: str = None):
    """Redeem a key to get coins."""
    cfg = load_config()
    if not anti_spam.check(ctx.author.id, cfg["anti_spam"]["max_commands_per_minute"]):
        await ctx.send(embed=error_embed("Slow Down!", "You're using commands too fast."), delete_after=10)
        return

    if await db.is_bot_paused():
        await ctx.send(embed=warning_embed("Bot Paused", "The bot is temporarily paused. Redemption disabled."), delete_after=10)
        return

    if key is None:
        await ctx.send(embed=error_embed("No Key", f"Please provide a key. Usage: `{cfg['prefix']}redeem YOUR_KEY`"), delete_after=10)
        return

    user_id = str(ctx.author.id)

    # Account age
    created_at = ctx.author.created_at.replace(tzinfo=None)
    if not is_account_old_enough(created_at, cfg["account_age"]["minimum_weeks"]):
        await ctx.send(embed=error_embed("Account Too New", "Your account must be at least 4 weeks old to redeem keys."), delete_after=10)
        return

    if await db.is_blacklisted(user_id):
        await ctx.send(embed=error_embed("Blacklisted", "You are blacklisted and cannot redeem keys."), delete_after=10)
        return

    daily = await db.get_daily_redeem_count(user_id)
    if daily >= cfg["coins"]["daily_redeem_limit"]:
        await ctx.send(embed=error_embed("Daily Limit", f"You've reached the daily redeem limit of {cfg['coins']['daily_redeem_limit']} keys."), delete_after=10)
        return

    # Verify key exists and is unused
    import aiosqlite
    async with aiosqlite.connect(db.DB_PATH) as dbase:
        async with dbase.execute("SELECT is_used FROM keys WHERE key_value = ?", (key,)) as cursor:
            row = await cursor.fetchone()

    if not row:
        await ctx.send(embed=error_embed("Invalid Key", "This key does not exist."), delete_after=10)
        return
    if row[0] == 1:
        await ctx.send(embed=error_embed("Key Already Used", "This key has already been redeemed."), delete_after=10)
        return

    coins_reward = cfg["coins"]["coins_per_key"]
    await db.mark_key_used(key, user_id)
    await db.add_redeem_history(user_id, key)
    await db.add_coins(user_id, str(ctx.author), coins_reward)

    new_total = await db.get_coins(user_id)
    await ctx.send(embed=success_embed("Key Redeemed! 🎉", f"Key redeemed successfully!\n💰 Coins added: `{format_coins(coins_reward)}`\n💎 New balance: `{format_coins(new_total)}`"))
    await log_action(bot, f"🎁 **Key Redeemed** | {ctx.author.mention} redeemed a key via command.", "general")
    await refresh_public_panel(bot)


# ─── MISC COMMANDS ───────────────────────────────────────

@bot.command(name="help")
async def help_cmd(ctx):
    """Show all commands."""
    cfg = load_config()
    p = cfg["prefix"]
    embed = discord.Embed(
        title="📋 VC Coin Earner — Help",
        color=int(cfg["embeds"]["color_main"], 16),
        timestamp=datetime.utcnow()
    )
    embed.add_field(
        name="👤 User Commands",
        value=(
            f"`{p}coins [@user]` — Check coin balance\n"
            f"`{p}redeem <key>` — Redeem a key\n"
            f"`{p}leaderboard` — View top coin earners\n"
            f"`{p}help` — Show this menu"
        ),
        inline=False
    )
    embed.add_field(
        name="⚙️ Admin Commands",
        value=(
            f"`{p}panel` — Send/refresh both panels\n"
            f"`{p}publicpanel` — Send public panel here\n"
            f"`{p}adminpanel` — Send admin panel here\n"
            f"`{p}addcoins @user amount` — Add coins\n"
            f"`{p}removecoins @user amount` — Remove coins\n"
            f"`{p}setcoins @user amount` — Set coins\n"
            f"`{p}resetcoins @user` — Reset coins\n"
            f"`{p}blacklist <id> [reason]` — Blacklist user\n"
            f"`{p}unblacklist <id>` — Remove from blacklist\n"
            f"`{p}blacklistcheck <id>` — Check blacklist status\n"
            f"`{p}reload` — Reload config\n"
            f"`{p}pause` — Toggle bot pause\n"
            f"`{p}stock` — Check key stock"
        ),
        inline=False
    )
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    await ctx.send(embed=embed)


@bot.command(name="leaderboard", aliases=["lb", "top"])
async def leaderboard_cmd(ctx):
    """Show top coin earners."""
    cfg = load_config()
    if not anti_spam.check(ctx.author.id, cfg["anti_spam"]["max_commands_per_minute"]):
        await ctx.send(embed=error_embed("Slow Down!", "Too many commands! Please wait."), delete_after=10)
        return

    all_coins = await db.get_all_coins()
    if not all_coins:
        await ctx.send(embed=info_embed("Leaderboard", "No coin data yet!"))
        return

    embed = discord.Embed(
        title="🏆 Coin Leaderboard",
        color=int(cfg["embeds"]["color_main"], 16),
        timestamp=datetime.utcnow()
    )
    medals = ["🥇", "🥈", "🥉"]
    lines = []
    for i, (uid, uname, coins, total) in enumerate(all_coins[:10]):
        medal = medals[i] if i < 3 else f"`#{i+1}`"
        lines.append(f"{medal} <@{uid}> — **{format_coins(coins)}** coins")
    embed.description = "\n".join(lines)
    embed.set_footer(text=f"Programmed by {cfg['developer']}")
    await ctx.send(embed=embed)


@bot.command(name="stock")
async def stock_cmd(ctx):
    """Check key stock."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can check stock."), delete_after=10)
        return
    available, used = await db.get_key_stock()
    embed = info_embed("Key Stock", f"✅ Available: `{available}`\n🔓 Redeemed: `{used}`\n📊 Total: `{available + used}`")
    await ctx.send(embed=embed)


@bot.command(name="pause")
async def pause_cmd(ctx):
    """Admin: Toggle bot pause."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can pause the bot."), delete_after=10)
        return

    currently_paused = await db.is_bot_paused()
    await db.set_bot_paused(not currently_paused)

    if not currently_paused:
        await ctx.send(embed=warning_embed("Bot Paused", "The bot has been temporarily paused. Coin earning and key redemption are disabled."))
        await log_action(bot, f"⏸️ **Bot Paused** | Action by {ctx.author.mention}", "admin")
    else:
        await ctx.send(embed=success_embed("Bot Resumed", "The bot has been resumed. Everything is active again."))
        await log_action(bot, f"▶️ **Bot Resumed** | Action by {ctx.author.mention}", "admin")

    await refresh_admin_panel(bot)
    await refresh_public_panel(bot)


@bot.command(name="reload")
async def reload_cmd(ctx):
    """Admin: Reload config."""
    cfg_before = load_config()
    if not is_admin(ctx.author, cfg_before):
        await ctx.send(embed=error_embed("No Permission", "Only admins can reload the config."), delete_after=10)
        return

    try:
        load_config()
        await ctx.send(embed=success_embed("Config Reloaded", "The configuration has been reloaded from `config.json`."))
    except Exception as e:
        await ctx.send(embed=error_embed("Reload Failed", f"Failed to reload config: `{e}`"))


@bot.command(name="vcstatus")
async def vc_status_cmd(ctx):
    """Admin: View current VC sessions."""
    cfg = load_config()
    if not is_admin(ctx.author, cfg):
        await ctx.send(embed=error_embed("No Permission", "Only admins can check VC sessions."), delete_after=10)
        return

    sessions = await db.get_vc_sessions()
    if not sessions:
        await ctx.send(embed=info_embed("VC Sessions", "No active VC sessions."))
        return

    lines = []
    for uid, join_time, is_sharing in sessions:
        share_str = "🖥️ Screen Sharing" if is_sharing else "🎙️ In VC"
        lines.append(f"• <@{uid}> — {share_str} (since `{join_time[11:16]} UTC`)")

    embed = info_embed("Active VC Sessions", "\n".join(lines))
    await ctx.send(embed=embed)


# ══════════════════════════════════════════════════════════
#                   MAIN ENTRY
# ══════════════════════════════════════════════════════════

async def main():
    os.makedirs("data", exist_ok=True)
    await db.init_db()
    async with bot:
        await bot.start(TOKEN)


if __name__ == "__main__":
    asyncio.run(main())
