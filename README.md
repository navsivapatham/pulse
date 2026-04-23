# Pulse

A self-hosted daily rhythm tracker. Manage routines, capture thoughts, review your inbox, and track one-off tasks — all from a clean web UI or via Telegram.

Built with Laravel 12, Vue 3, and SQLite.

## Features

- **Routines** — daily, weekly, and monthly habits with scheduled times, streak tracking, and color coding
- **Inbox** — quick-capture thoughts from the web or Telegram, then convert to routines or tasks during review
- **Tasks** — one-off to-dos with optional due dates and completion tracking
- **Dashboard** — daily completion ring, streak leaders, today's checklist sorted by scheduled time
- **Telegram bot** — message "remind" to capture thoughts on the go; receives routine reminders 45 minutes before they're due
- **Stats** — daily/weekly completion rates and streak leaderboard
- **Profile** — edit name, email, change password; CLI password reset for lockouts

## Architecture

```
backend/                → Laravel 12 API (Sanctum auth, SQLite)
frontend/               → Vue 3 SPA (Vite, Pinia, Tailwind CSS)
reminder.py             → Telegram bot + routine reminder daemon
```

### Backend API

| Endpoint | Description |
|---|---|
| `POST /api/register` | Create account |
| `POST /api/login` | Get auth token |
| `GET /api/routines` | List routines with today's completion status |
| `POST /api/routines/{id}/complete` | Mark routine done for today |
| `GET /api/inbox` | List inbox items |
| `POST /api/inbox` | Capture a thought |
| `POST /api/inbox/{id}/review` | Convert to routine/task, keep, or dismiss |
| `DELETE /api/inbox/{id}` | Delete inbox item |
| `GET /api/tasks` | List tasks |
| `PUT /api/profile` | Update name/email |
| `PUT /api/password` | Change password |

## Setup

### Prerequisites

- PHP 8.4+ with sqlite3, mbstring, xml, curl extensions
- Composer
- Node.js 20+
- Python 3.10+ (for the Telegram bot)

### Install

```bash
git clone https://github.com/navsivapatham/pulse.git
cd pulse

# Backend
cd backend
composer install
cp .env.example .env   # then edit with your settings
php artisan key:generate
php artisan migrate
php artisan db:seed     # optional — creates default routines
cd ..

# Frontend
cd frontend
npm install
npm run build           # production build to dist/
cd ..
```

### Configure

**Backend** (`backend/.env`):
```env
APP_ENV=production
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

**Telegram bot** (`.env` in project root):
```env
PULSE_DB_PATH=/absolute/path/to/database.sqlite
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
POLL_INTERVAL=900
LOOKAHEAD_MINUTES=45
```

### Run (development)

```bash
# Backend
cd backend && php artisan serve --port=8001

# Frontend
cd frontend && npm run dev

# Telegram bot
python3 reminder.py
```

### Run (production)

Serve the frontend with nginx, proxy `/api` to PHP-FPM:

```nginx
server {
    listen 8080;
    root /path/to/frontend/dist;
    index index.html;

    location /api {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /path/to/backend/public/index.php;
        include fastcgi_params;
        fastcgi_param REQUEST_URI $request_uri;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## Telegram Bot

The reminder daemon (`reminder.py`) does two things:

1. **Routine reminders** — polls every 15 minutes, sends a Telegram message when a routine is coming up within the next 45 minutes
2. **Inbox capture** — message the bot with "remind" or "reminder", it asks what to capture, then saves your reply to the Pulse inbox

## CLI Tools

```bash
# Reset a user's password
php artisan user:reset-password user@email.com newpassword
```

## License

MIT
