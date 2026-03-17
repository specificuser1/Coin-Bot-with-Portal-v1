import discord
from discord.ui import View, Button
from datetime import datetime
import database as db
from utils import load_config, format_coins, format_uptime, is_admin, error_embed, success_embed, warning_embed, info_embed, anti_spam, is_account_old_enough
import asyncio

# ══════════════════════════════════════════════════════════
#                    PUBLIC PANEL
# ══════════════════════════════════════════════════════════

async def build_public_panel_embed() -> discord.Embed:
    cfg = load_config()
    available_keys, used_keys = await db.get_key_stock()
    total_coins = await db.get_total_coins_all()
    paused = await db.is_bot_paused()

    status_text = "🔴 **Temporarily Paused**" if paused else "🟢 **Online & Active**"

    description = (
        f"## 📋 Rules\n"
        f"> 🎙️ Join a Voice Channel to start earning coins\n"
        f"> 💰 Earn **1 coin/minute** for staying in VC\n"
        f"> 🖥️ **Screen Share Bonus:** 1.5 coins/minute\n"
        f"> 🔇 No coins if you're **AFK or Deafened**\n"
        f"> 🚫 **Blacklisted** members cannot earn or redeem\n"
        f"> 📅 Account must be **4+ weeks old** to participate\n"
        f"> 🎁 Redeem a key for **90 coins** (Limit: 2/day)\n\n"
        f"## 📊 Live Statistics\n"
        f"> 🔑 **Keys in Stock:** `{available_keys}`\n"
        f"> 💎 **Total Coins Earned:** `{format_coins(total_coins)}`\n"
        f"> 🤖 **Bot Status:** {status_text}\n"
    )

    embed = discord.Embed(
        title="🪙 Coin System",
        description=description,
        color=int(cfg["embeds"]["color_main"], 16),
        timestamp=datetime.utcnow()
    )

    thumbnail = cfg["embeds"].get("public_thumbnail", "")
    if thumbnail and thumbnail != "https://i.imgur.com/your_thumbnail.png":
        embed.set_thumbnail(url=thumbnail)

    image = cfg["embeds"].get("public_image", "")
    if image and image != "https://i.imgur.com/your_image.png":
        embed.set_image(url=image)

    embed.set_footer(text=f"Programmed by {cfg['developer']} • Last Updated")
    return embed


class PublicPanelView(View):
    def __init__(self):
        super().__init__(timeout=None)

    @discord.ui.button(label="💰 Check My Coins", style=discord.ButtonStyle.primary, custom_id="public_check_coins")
    async def check_coins_btn(self, interaction: discord.Interaction, button: Button):
        cfg = load_config()
        if not anti_spam.check(interaction.user.id, cfg["anti_spam"]["max_commands_per_minute"]):
            await interaction.response.send_message(
                embed=error_embed("Slow Down!", "You're using commands too fast. Please wait a moment."),
                ephemeral=True
            )
            return

        blacklisted = await db.is_blacklisted(str(interaction.user.id))
        coins = await db.get_coins(str(interaction.user.id))
        daily_count = await db.get_daily_redeem_count(str(interaction.user.id))

        embed = discord.Embed(
            title="💰 Your Coin Balance",
            color=int(cfg["embeds"]["color_main"], 16),
            timestamp=datetime.utcnow()
        )
        embed.set_thumbnail(url=interaction.user.display_avatar.url)
        embed.add_field(name="👤 Member", value=interaction.user.mention, inline=True)
        embed.add_field(name="💎 Coins", value=f"`{format_coins(coins)}`", inline=True)
        embed.add_field(name="🎁 Daily Redeems Used", value=f"`{daily_count}/2`", inline=True)
        embed.add_field(name="🚫 Blacklisted", value="Yes ❌" if blacklisted else "No ✅", inline=True)
        embed.set_footer(text=f"Programmed by {cfg['developer']}")

        await interaction.response.send_message(embed=embed, ephemeral=True)

    @discord.ui.button(label="🎁 Get Key", style=discord.ButtonStyle.success, custom_id="public_get_key")
    async def get_key_btn(self, interaction: discord.Interaction, button: Button):
        cfg = load_config()
        if not anti_spam.check(interaction.user.id, cfg["anti_spam"]["max_commands_per_minute"]):
            await interaction.response.send_message(
                embed=error_embed("Slow Down!", "You're using commands too fast. Please wait a moment."),
                ephemeral=True
            )
            return

        # Check if bot is paused
        if await db.is_bot_paused():
            await interaction.response.send_message(
                embed=warning_embed("Bot Paused", "The bot is temporarily paused. Key redemption is currently disabled."),
                ephemeral=True
            )
            return

        user_id = str(interaction.user.id)

        # Check account age
        created_at = interaction.user.created_at.replace(tzinfo=None)
        if not is_account_old_enough(created_at, cfg["account_age"]["minimum_weeks"]):
            if cfg["account_age"]["auto_blacklist"]:
                await db.add_to_blacklist(user_id, str(interaction.user), "Account too new (auto-blacklist)", "AutoSystem")
                await interaction.response.send_message(
                    embed=error_embed("Account Too New", "Your account is less than 4 weeks old and has been automatically blacklisted."),
                    ephemeral=True
                )
                return

        # Check blacklist
        if await db.is_blacklisted(user_id):
            await interaction.response.send_message(
                embed=error_embed("Blacklisted", "You are blacklisted and cannot redeem keys."),
                ephemeral=True
            )
            return

        # Check coins
        coins_needed = cfg["coins"]["coins_per_key"]
        current_coins = await db.get_coins(user_id)
        if current_coins < coins_needed:
            needed = coins_needed - current_coins
            await interaction.response.send_message(
                embed=error_embed("Insufficient Coins", f"You need **{format_coins(coins_needed)} coins** to redeem a key.\nYou currently have `{format_coins(current_coins)}` coins.\nYou need `{format_coins(needed)}` more coins."),
                ephemeral=True
            )
            return

        # Check daily redeem limit
        daily_count = await db.get_daily_redeem_count(user_id)
        daily_limit = cfg["coins"]["daily_redeem_limit"]
        if daily_count >= daily_limit:
            await interaction.response.send_message(
                embed=error_embed("Daily Limit Reached", f"You have reached the daily redeem limit of **{daily_limit} keys**.\nCome back tomorrow!"),
                ephemeral=True
            )
            return

        # Get available key
        key = await db.get_available_key()
        if not key:
            await interaction.response.send_message(
                embed=error_embed("No Keys Available", "There are no keys in stock right now. Please try again later."),
                ephemeral=True
            )
            return

        # Deduct coins & mark key used
        await db.deduct_coins(user_id, coins_needed)
        await db.mark_key_used(key, user_id)
        await db.add_redeem_history(user_id, key)

        # Send key via DM
        key_embed = discord.Embed(
            title="🎁 Your Redeemed Key",
            description=f"Here is your key:\n```\n{key}\n```",
            color=int(cfg["embeds"]["color_success"], 16),
            timestamp=datetime.utcnow()
        )
        key_embed.add_field(name="💰 Coins Spent", value=f"`{format_coins(coins_needed)}`", inline=True)
        key_embed.add_field(name="💎 Remaining Coins", value=f"`{format_coins(current_coins - coins_needed)}`", inline=True)
        key_embed.set_footer(text=f"Programmed by {cfg['developer']}")

        try:
            await interaction.user.send(embed=key_embed)
            await interaction.response.send_message(
                embed=success_embed("Key Sent!", f"Your key has been sent to your DMs! 📬\nCoins deducted: `{format_coins(coins_needed)}`"),
                ephemeral=True
            )
        except discord.Forbidden:
            await interaction.response.send_message(
                embed=discord.Embed(
                    title="🎁 Your Key",
                    description=f"```\n{key}\n```\n*(Your DMs are closed, key shown here)*",
                    color=int(cfg["embeds"]["color_success"], 16)
                ),
                ephemeral=True
            )

        # Log to log channel
        await log_action(
            interaction.client,
            f"🎁 **Key Redeemed** | {interaction.user.mention} (`{interaction.user.id}`) redeemed a key. Coins deducted: `{format_coins(coins_needed)}`",
            "redeem"
        )

        # Update public panel
        await refresh_public_panel(interaction.client)

    @discord.ui.button(label="📊 Leaderboard", style=discord.ButtonStyle.secondary, custom_id="public_leaderboard")
    async def leaderboard_btn(self, interaction: discord.Interaction, button: Button):
        cfg = load_config()
        if not anti_spam.check(interaction.user.id, cfg["anti_spam"]["max_commands_per_minute"]):
            await interaction.response.send_message(
                embed=error_embed("Slow Down!", "You're using commands too fast."),
                ephemeral=True
            )
            return

        all_coins = await db.get_all_coins()
        if not all_coins:
            await interaction.response.send_message(
                embed=info_embed("Leaderboard", "No coin data yet!"),
                ephemeral=True
            )
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

        embed.description = "\n".join(lines) if lines else "No data available."
        embed.set_footer(text=f"Programmed by {cfg['developer']}")
        await interaction.response.send_message(embed=embed, ephemeral=True)


# ══════════════════════════════════════════════════════════
#                    ADMIN PANEL
# ══════════════════════════════════════════════════════════

async def build_admin_panel_embed(bot) -> discord.Embed:
    cfg = load_config()
    available_keys, used_keys = await db.get_key_stock()
    blacklist = await db.get_blacklist()
    paused = await db.is_bot_paused()
    start_time = await db.get_setting('start_time')
    uptime = format_uptime(start_time) if start_time else "Unknown"
    latency = round(bot.latency * 1000)
    status_text = "🔴 Paused" if paused else "🟢 Active"

    description = (
        f"## 🤖 Bot Status\n"
        f"> **Status:** {status_text}\n"
        f"> **Uptime:** `{uptime}`\n"
        f"> **Latency:** `{latency}ms`\n\n"
        f"## 🔑 Key Statistics\n"
        f"> **Available Keys:** `{available_keys}`\n"
        f"> **Redeemed Keys:** `{used_keys}`\n"
        f"> **Total Keys:** `{available_keys + used_keys}`\n\n"
        f"## 🚫 Blacklist\n"
        f"> **Blacklisted Members:** `{len(blacklist)}`\n\n"
        f"## ⚙️ Admin Commands\n"
        f"> `{cfg['prefix']}addcoins @user amount` — Add coins\n"
        f"> `{cfg['prefix']}removecoins @user amount` — Remove coins\n"
        f"> `{cfg['prefix']}setcoins @user amount` — Set coins\n"
        f"> `{cfg['prefix']}resetcoins @user` — Reset coins\n"
        f"> `{cfg['prefix']}forceredeem @user` — Force redeem\n"
        f"> `{cfg['prefix']}panel` — Resend panels\n"
        f"> `{cfg['prefix']}reload` — Reload config\n"
    )

    embed = discord.Embed(
        title="⚙️ Coin Admin Panel",
        description=description,
        color=int(cfg["embeds"]["color_admin"], 16),
        timestamp=datetime.utcnow()
    )
    embed.set_footer(text=f"Programmed by {cfg['developer']} • Last Updated")
    return embed


class AddKeysModal(discord.ui.Modal, title="➕ Add Keys (Bulk)"):
    keys_input = discord.ui.TextInput(
        label="Enter Keys (one per line)",
        style=discord.TextStyle.paragraph,
        placeholder="KEY-XXXX-XXXX\nKEY-YYYY-YYYY\n...",
        required=True,
        max_length=4000
    )

    async def on_submit(self, interaction: discord.Interaction):
        cfg = load_config()
        keys_list = [k.strip() for k in self.keys_input.value.split("\n") if k.strip()]
        added = await db.add_keys(keys_list, str(interaction.user))

        embed = success_embed("Keys Added", f"Successfully added **{added}** keys to the stock.")
        await interaction.response.send_message(embed=embed, ephemeral=True)

        await log_action(
            interaction.client,
            f"➕ **Keys Added** | {interaction.user.mention} added `{added}` keys to stock.",
            "admin"
        )
        await refresh_admin_panel(interaction.client)


class BlacklistAddModal(discord.ui.Modal, title="🚫 Add to Blacklist"):
    user_id_input = discord.ui.TextInput(
        label="Member ID",
        placeholder="Enter Discord User ID",
        required=True,
        max_length=20
    )
    reason_input = discord.ui.TextInput(
        label="Reason",
        placeholder="Reason for blacklisting",
        required=False,
        max_length=200
    )

    async def on_submit(self, interaction: discord.Interaction):
        cfg = load_config()
        uid = self.user_id_input.value.strip()
        reason = self.reason_input.value.strip() or "No reason provided"

        try:
            member = await interaction.client.fetch_user(int(uid))
            uname = str(member)
        except Exception:
            uname = f"Unknown ({uid})"

        already = await db.is_blacklisted(uid)
        if already:
            await interaction.response.send_message(
                embed=warning_embed("Already Blacklisted", f"User `{uid}` is already blacklisted."),
                ephemeral=True
            )
            return

        await db.add_to_blacklist(uid, uname, reason, str(interaction.user))
        embed = success_embed("Blacklisted", f"User `{uname}` (`{uid}`) has been blacklisted.\n**Reason:** {reason}")
        await interaction.response.send_message(embed=embed, ephemeral=True)

        await log_action(
            interaction.client,
            f"🚫 **Blacklisted** | {interaction.user.mention} blacklisted `{uname}` (`{uid}`). Reason: {reason}",
            "blacklist"
        )
        await refresh_admin_panel(interaction.client)


class BlacklistRemoveModal(discord.ui.Modal, title="✅ Remove from Blacklist"):
    user_id_input = discord.ui.TextInput(
        label="Member ID",
        placeholder="Enter Discord User ID to remove",
        required=True,
        max_length=20
    )

    async def on_submit(self, interaction: discord.Interaction):
        uid = self.user_id_input.value.strip()
        removed = await db.remove_from_blacklist(uid)

        if removed:
            embed = success_embed("Removed from Blacklist", f"User `{uid}` has been removed from the blacklist.")
            await log_action(
                interaction.client,
                f"✅ **Unblacklisted** | {interaction.user.mention} removed `{uid}` from blacklist.",
                "blacklist"
            )
        else:
            embed = error_embed("Not Found", f"User `{uid}` was not found in the blacklist.")

        await interaction.response.send_message(embed=embed, ephemeral=True)
        await refresh_admin_panel(interaction.client)


class AdminPanelView(View):
    def __init__(self):
        super().__init__(timeout=None)

    async def _check_admin(self, interaction: discord.Interaction) -> bool:
        cfg = load_config()
        if not is_admin(interaction.user, cfg):
            await interaction.response.send_message(
                embed=error_embed("No Permission", "You need admin permissions to use this panel."),
                ephemeral=True
            )
            return False
        return True

    @discord.ui.button(label="➕ Add Keys", style=discord.ButtonStyle.success, custom_id="admin_add_keys", row=0)
    async def add_keys_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        await interaction.response.send_modal(AddKeysModal())

    @discord.ui.button(label="📦 Check Stock", style=discord.ButtonStyle.primary, custom_id="admin_check_stock", row=0)
    async def check_stock_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        cfg = load_config()
        available, used = await db.get_key_stock()
        total = available + used
        embed = discord.Embed(
            title="📦 Key Stock Status",
            color=int(cfg["embeds"]["color_main"], 16),
            timestamp=datetime.utcnow()
        )
        embed.add_field(name="✅ Available", value=f"`{available}`", inline=True)
        embed.add_field(name="🔓 Redeemed", value=f"`{used}`", inline=True)
        embed.add_field(name="📊 Total", value=f"`{total}`", inline=True)
        embed.set_footer(text=f"Programmed by {cfg['developer']}")
        await interaction.response.send_message(embed=embed, ephemeral=True)

    @discord.ui.button(label="🚫 View Blacklist", style=discord.ButtonStyle.secondary, custom_id="admin_view_blacklist", row=0)
    async def view_blacklist_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        cfg = load_config()
        blacklist = await db.get_blacklist()
        if not blacklist:
            await interaction.response.send_message(
                embed=info_embed("Blacklist", "The blacklist is currently empty."),
                ephemeral=True
            )
            return

        embed = discord.Embed(
            title="🚫 Blacklisted Members",
            color=int(cfg["embeds"]["color_error"], 16),
            timestamp=datetime.utcnow()
        )
        lines = []
        for uid, uname, reason, added_by, added_at in blacklist[:20]:
            lines.append(f"• <@{uid}> (`{uid}`) — {reason}")
        embed.description = "\n".join(lines)
        if len(blacklist) > 20:
            embed.set_footer(text=f"Showing 20/{len(blacklist)} entries | Programmed by {cfg['developer']}")
        else:
            embed.set_footer(text=f"Programmed by {cfg['developer']}")
        await interaction.response.send_message(embed=embed, ephemeral=True)

    @discord.ui.button(label="🚫 Add Blacklist", style=discord.ButtonStyle.danger, custom_id="admin_add_blacklist", row=1)
    async def add_blacklist_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        await interaction.response.send_modal(BlacklistAddModal())

    @discord.ui.button(label="✅ Remove Blacklist", style=discord.ButtonStyle.success, custom_id="admin_remove_blacklist", row=1)
    async def remove_blacklist_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        await interaction.response.send_modal(BlacklistRemoveModal())

    @discord.ui.button(label="⏸️ Start/Stop Bot", style=discord.ButtonStyle.danger, custom_id="admin_toggle_bot", row=1)
    async def toggle_bot_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        cfg = load_config()
        currently_paused = await db.is_bot_paused()
        new_state = not currently_paused
        await db.set_bot_paused(new_state)

        if new_state:
            embed = warning_embed("Bot Paused", "The bot has been **temporarily paused**.\nCoin earning and key redemption are disabled.\nPress the button again to resume.")
        else:
            embed = success_embed("Bot Resumed", "The bot has been **resumed**.\nCoin earning and key redemption are now active.")

        await interaction.response.send_message(embed=embed, ephemeral=True)
        await log_action(
            interaction.client,
            f"{'⏸️ **Bot Paused**' if new_state else '▶️ **Bot Resumed**'} | Action by {interaction.user.mention}",
            "admin"
        )
        await refresh_admin_panel(interaction.client)
        await refresh_public_panel(interaction.client)

    @discord.ui.button(label="📊 All Coins", style=discord.ButtonStyle.secondary, custom_id="admin_all_coins", row=2)
    async def all_coins_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        cfg = load_config()
        all_data = await db.get_all_coins()
        if not all_data:
            await interaction.response.send_message(
                embed=info_embed("Coin Data", "No coin data available yet."),
                ephemeral=True
            )
            return

        embed = discord.Embed(
            title="💰 All Members Coin Data",
            color=int(cfg["embeds"]["color_main"], 16),
            timestamp=datetime.utcnow()
        )
        lines = []
        for uid, uname, coins, total in all_data[:15]:
            lines.append(f"• <@{uid}> — `{format_coins(coins)}` coins (Total earned: `{format_coins(total)}`)")
        embed.description = "\n".join(lines)
        embed.set_footer(text=f"Programmed by {cfg['developer']}")
        await interaction.response.send_message(embed=embed, ephemeral=True)

    @discord.ui.button(label="🔄 Refresh Panel", style=discord.ButtonStyle.primary, custom_id="admin_refresh", row=2)
    async def refresh_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        await refresh_admin_panel(interaction.client)
        await refresh_public_panel(interaction.client)
        await interaction.response.send_message(
            embed=success_embed("Refreshed", "Both panels have been refreshed."),
            ephemeral=True
        )

    @discord.ui.button(label="📈 Bot Stats", style=discord.ButtonStyle.secondary, custom_id="admin_stats", row=2)
    async def stats_btn(self, interaction: discord.Interaction, button: Button):
        if not await self._check_admin(interaction):
            return
        cfg = load_config()
        start_time = await db.get_setting('start_time')
        uptime = format_uptime(start_time) if start_time else "Unknown"
        latency = round(interaction.client.latency * 1000)
        available, used = await db.get_key_stock()
        blacklist = await db.get_blacklist()
        total_coins = await db.get_total_coins_all()
        all_members = await db.get_all_coins()
        paused = await db.is_bot_paused()

        embed = discord.Embed(
            title="📈 Full Bot Statistics",
            color=int(cfg["embeds"]["color_admin"], 16),
            timestamp=datetime.utcnow()
        )
        embed.add_field(name="🤖 Status", value="🔴 Paused" if paused else "🟢 Active", inline=True)
        embed.add_field(name="⏱️ Uptime", value=f"`{uptime}`", inline=True)
        embed.add_field(name="📡 Latency", value=f"`{latency}ms`", inline=True)
        embed.add_field(name="🔑 Available Keys", value=f"`{available}`", inline=True)
        embed.add_field(name="🔓 Redeemed Keys", value=f"`{used}`", inline=True)
        embed.add_field(name="🚫 Blacklisted", value=f"`{len(blacklist)}`", inline=True)
        embed.add_field(name="💰 Total Coins (All)", value=f"`{format_coins(total_coins)}`", inline=True)
        embed.add_field(name="👥 Members Tracked", value=f"`{len(all_members)}`", inline=True)
        embed.add_field(name="🏠 Servers", value=f"`{len(interaction.client.guilds)}`", inline=True)
        embed.set_footer(text=f"Programmed by {cfg['developer']}")
        await interaction.response.send_message(embed=embed, ephemeral=True)


# ══════════════════════════════════════════════════════════
#              PANEL REFRESH HELPERS
# ══════════════════════════════════════════════════════════

async def refresh_public_panel(bot):
    try:
        panel_data = await db.get_panel_message("public")
        if not panel_data:
            return
        channel_id, message_id = panel_data
        channel = bot.get_channel(int(channel_id))
        if not channel:
            return
        try:
            message = await channel.fetch_message(int(message_id))
            embed = await build_public_panel_embed()
            await message.edit(embed=embed, view=PublicPanelView())
        except discord.NotFound:
            pass
    except Exception as e:
        print(f"[Panel] Error refreshing public panel: {e}")


async def refresh_admin_panel(bot):
    try:
        panel_data = await db.get_panel_message("admin")
        if not panel_data:
            return
        channel_id, message_id = panel_data
        channel = bot.get_channel(int(channel_id))
        if not channel:
            return
        try:
            message = await channel.fetch_message(int(message_id))
            embed = await build_admin_panel_embed(bot)
            await message.edit(embed=embed, view=AdminPanelView())
        except discord.NotFound:
            pass
    except Exception as e:
        print(f"[Panel] Error refreshing admin panel: {e}")


async def log_action(bot, message: str, log_type: str = "general"):
    try:
        cfg = load_config()
        if log_type == "blacklist":
            channel_id = cfg["channels"].get("blacklist_log_channel_id")
        else:
            channel_id = cfg["channels"].get("log_channel_id")

        if not channel_id or channel_id in ["YOUR_LOG_CHANNEL_ID", "YOUR_BLACKLIST_LOG_CHANNEL_ID"]:
            return

        channel = bot.get_channel(int(channel_id))
        if channel:
            embed = discord.Embed(
                description=message,
                color=0x5865F2,
                timestamp=datetime.utcnow()
            )
            embed.set_footer(text="Coin System Logs")
            await channel.send(embed=embed)
    except Exception as e:
        print(f"[Log] Error sending log: {e}")
