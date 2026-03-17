# 🪙 VC Coin Earner Bot
**Programmed by SUBHAN**

A full-featured Discord bot that rewards members with coins for spending time in Voice Channels, with a key redemption system, admin panel, and anti-abuse features.

---

## 📁 File Structure

```
vc-coin-bot/
├── bot.py              # Main bot file
├── database.py         # SQLite database layer
├── panels.py           # Panel embeds + button views
├── utils.py            # Helpers, anti-spam, embed builders
├── config.json         # ⚙️ Full bot configuration
├── .env                # 🔐 Bot token (never share!)
├── requirements.txt    # Python dependencies
├── Procfile            # Railway deployment
├── runtime.txt         # Python version for Railway
├── .gitignore          # Git ignore rules
└── data/
    └── bot_data.db     # SQLite database (auto-created)
```

---

## ⚙️ Setup

### 1. Bot Token
Edit `.env`:
```
DISCORD_TOKEN=your_actual_bot_token_here
```

### 2. Configure `config.json`
Fill in these important fields:

```json
{
  "prefix": "!",
  "channels": {
    "log_channel_id": "CHANNEL_ID_FOR_LOGS",
    "blacklist_log_channel_id": "CHANNEL_ID_FOR_BLACKLIST_LOGS",
    "public_panel_channel_id": "CHANNEL_ID_FOR_PUBLIC_PANEL",
    "admin_panel_channel_id": "CHANNEL_ID_FOR_ADMIN_PANEL",
    "allowed_vc_ids": ["VC_ID_1", "VC_ID_2"]  // Leave empty [] for ALL VCs
  },
  "roles": {
    "admin_role_ids": ["YOUR_ADMIN_ROLE_ID"]
  },
  "embeds": {
    "public_thumbnail": "https://your-image-url.com/thumb.png",
    "public_image": "https://your-image-url.com/image.png"
  }
}
```

### 3. Bot Permissions Required
- Read/Send Messages
- Embed Links
- Read Message History
- View Channels
- Connect (Voice)
- Use External Emojis
- Add Reactions

### 4. Enable Intents in Discord Developer Portal
- ✅ PRESENCE INTENT
- ✅ SERVER MEMBERS INTENT  
- ✅ MESSAGE CONTENT INTENT

---

## 🚀 Railway Deployment

1. Create account at [railway.app](https://railway.app)
2. Create new project → **Deploy from GitHub**
3. Push your bot files to a GitHub repo
4. In Railway: go to **Variables** → add:
   - `DISCORD_TOKEN` = your bot token
5. Railway will auto-detect `Procfile` and start the bot

> **Note:** Railway gives you a persistent volume — your SQLite DB will survive restarts.

---

## 💰 Coin Logic

| Action | Coins/Min |
|--------|-----------|
| In VC (normal) | 1.0 |
| Screen Sharing | 1.5 |
| AFK / Deafened | 0 (no coins) |
| Blacklisted | 0 (no coins) |

- **Key Redemption:** 90 coins per key
- **Daily Limit:** 2 keys per day
- **Account Age:** Must be 4+ weeks old (auto-blacklists new accounts)

---

## 🖥️ Panels

### Public Panel (`!publicpanel` or `!panel`)
- Shows rules, live key stock, total coins earned
- Buttons: **Check My Coins**, **Get Key**, **Leaderboard**

### Admin Panel (`!adminpanel` or `!panel`)
- Shows bot status, uptime, latency, key stats, blacklist count
- Buttons: **Add Keys**, **Check Stock**, **View Blacklist**, **Add Blacklist**, **Remove Blacklist**, **Start/Stop Bot**, **All Coins**, **Refresh**, **Bot Stats**

> Panels **persist across restarts** — buttons always work!

---

## 📋 Commands

### User Commands
| Command | Description |
|---------|-------------|
| `!coins [@user]` | Check coin balance |
| `!redeem <key>` | Redeem a key |
| `!leaderboard` | Top coin earners |
| `!help` | Show all commands |

### Admin Commands
| Command | Description |
|---------|-------------|
| `!panel` | Send/refresh both panels |
| `!publicpanel` | Send public panel in current channel |
| `!adminpanel` | Send admin panel in current channel |
| `!addcoins @user amount` | Add coins to member |
| `!removecoins @user amount` | Remove coins from member |
| `!setcoins @user amount` | Set member's coins |
| `!resetcoins @user` | Reset member's coins to 0 |
| `!blacklist <id> [reason]` | Blacklist a user by ID |
| `!unblacklist <id>` | Remove user from blacklist |
| `!blacklistcheck <id>` | Check if user is blacklisted |
| `!stock` | Check key stock |
| `!pause` | Toggle bot pause on/off |
| `!vcstatus` | View active VC sessions |
| `!reload` | Reload config.json |

---

## 🔒 Anti-Abuse Features

- **Anti-Spam:** Max 5 commands/minute per user
- **Account Age Gate:** Accounts < 4 weeks auto-blacklisted
- **AFK/Deaf Detection:** No coins when AFK or deafened
- **Blacklist System:** Full blacklist with ID-based management
- **Daily Redeem Limit:** 2 keys per day per user
- **Duplicate Key Prevention:** Keys can only be used once

---

## 📊 Database (SQLite)

Auto-created at `data/bot_data.db` with these tables:
- `coins` — Member coin balances
- `blacklist` — Blacklisted members
- `keys` — All keys (available + used)
- `redeem_history` — Redemption log
- `bot_settings` — Bot state (pause, start time)
- `panel_messages` — Panel IDs for persistence
- `vc_sessions` — Active VC tracking

---

*Programmed by SUBHAN*
