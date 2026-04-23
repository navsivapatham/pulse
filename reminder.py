#!/usr/bin/env python3

import os
import sys
import time
import signal
import sqlite3
import requests
from datetime import datetime, timedelta
from pathlib import Path

try:
    from dotenv import load_dotenv
    load_dotenv(Path(__file__).parent / ".env")
except ImportError:
    pass

DB_PATH = os.getenv("PULSE_DB_PATH", str(Path(__file__).parent / "backend/database/database.sqlite"))
BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN")
CHAT_ID = os.getenv("TELEGRAM_CHAT_ID")
POLL_INTERVAL = int(os.getenv("POLL_INTERVAL", "900"))
LOOKAHEAD = int(os.getenv("LOOKAHEAD_MINUTES", "45"))
MSG_POLL = 3

REMIND_TRIGGERS = {"remind", "reminder"}

_reminded = set()
_current_date = None
_running = True
_update_offset = 0
_awaiting_reminder = {}  # chat_id -> True


def _signal_handler(sig, frame):
    global _running
    _running = False


def _format_time(t):
    h, m = map(int, t.split(":"))
    return f"{h % 12 or 12}:{m:02d} {'PM' if h >= 12 else 'AM'}"


def _send(text, chat_id=None):
    requests.post(
        f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage",
        json={"chat_id": chat_id or CHAT_ID, "text": text, "parse_mode": "HTML"},
        timeout=10,
    )


def _get_user_id():
    conn = sqlite3.connect(DB_PATH)
    row = conn.execute("SELECT id FROM users ORDER BY id LIMIT 1").fetchone()
    conn.close()
    return row[0] if row else 1


def _save_to_inbox(body):
    user_id = _get_user_id()
    now = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    conn = sqlite3.connect(DB_PATH)
    conn.execute(
        "INSERT INTO inbox_items (user_id, body, status, created_at, updated_at) VALUES (?, ?, 'pending', ?, ?)",
        (user_id, body, now, now),
    )
    conn.commit()
    conn.close()


def _poll_messages():
    global _update_offset

    try:
        resp = requests.get(
            f"https://api.telegram.org/bot{BOT_TOKEN}/getUpdates",
            params={"offset": _update_offset, "timeout": 1},
            timeout=5,
        )
        data = resp.json()
    except Exception:
        return

    if not data.get("ok"):
        return

    for update in data.get("result", []):
        _update_offset = update["update_id"] + 1
        msg = update.get("message")
        if not msg or not msg.get("text"):
            continue

        chat_id = str(msg["chat"]["id"])
        if chat_id != str(CHAT_ID):
            continue

        text = msg["text"].strip()

        if chat_id in _awaiting_reminder:
            del _awaiting_reminder[chat_id]
            _save_to_inbox(text)
            _send("✅ Saved to your inbox.", chat_id)
            print(f"Inbox: {text}")
        elif text.lower() in REMIND_TRIGGERS:
            _awaiting_reminder[chat_id] = True
            _send("What would you like me to remind you?", chat_id)


def _check_routines():
    global _reminded, _current_date

    now = datetime.now()
    today = now.strftime("%Y-%m-%d")

    if _current_date != today:
        _reminded = set()
        _current_date = today

    now_hm = now.strftime("%H:%M")
    end_hm = (now + timedelta(minutes=LOOKAHEAD)).strftime("%H:%M")

    if end_hm < now_hm:
        end_hm = "23:59"

    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row

    rows = conn.execute(
        """
        SELECT r.id, r.title, r.scheduled_time
        FROM routines r
        WHERE r.is_active = 1
          AND r.cadence = 'daily'
          AND r.scheduled_time IS NOT NULL
          AND r.scheduled_time >= ?
          AND r.scheduled_time <= ?
        """,
        (now_hm, end_hm),
    ).fetchall()

    completed = {
        r["routine_id"]
        for r in conn.execute(
            "SELECT routine_id FROM routine_completions WHERE completed_date = ?",
            (today,),
        ).fetchall()
    }

    conn.close()

    for r in rows:
        if r["id"] in _reminded or r["id"] in completed:
            continue
        _reminded.add(r["id"])
        _send(f"⏰ <b>{r['title']}</b> — {_format_time(r['scheduled_time'])}")
        print(f"Reminded: {r['title']} at {r['scheduled_time']}")


def main():
    if not BOT_TOKEN or not CHAT_ID:
        print("TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID required")
        sys.exit(1)

    signal.signal(signal.SIGTERM, _signal_handler)
    signal.signal(signal.SIGINT, _signal_handler)

    print(f"Pulse bot — reminders every {POLL_INTERVAL}s ({LOOKAHEAD}min lookahead), messages every {MSG_POLL}s")

    last_routine_check = 0

    while _running:
        try:
            _poll_messages()

            now = time.time()
            if now - last_routine_check >= POLL_INTERVAL:
                _check_routines()
                last_routine_check = now
        except Exception as e:
            print(f"Error: {e}")

        time.sleep(MSG_POLL)


if __name__ == "__main__":
    main()
