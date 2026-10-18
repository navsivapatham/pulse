#!/usr/bin/env python3
"""Pulse reminder bot (Telegram).

- Sends each active routine's reminder AT its scheduled_time (not ahead of it): daily
  ones every day, weekly ones on their ISO weekday, monthly ones on their month_day.
- If a message was pre-written for that routine and date (routine_messages, via the API),
  sends that text; otherwise the plain template. A missing message never means no ping.
- Routines with a checkin_time get a next-morning check-in about the previous day.
- A short "done"-style reply marks the routine just reminded complete, through the API.
- "remind" captures the next message to the inbox.

Every send is recorded in routine_messages, so a restart never re-sends.

Testing:  --dry-run prints instead of sending or writing; --now fakes the clock;
          --once runs one check and exits without reading Telegram;
          --reply TEXT handles TEXT as if it had arrived from the chat.
"""

import argparse
import html
import os
import re
import signal
import sqlite3
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

import requests

try:
    from dotenv import load_dotenv
    load_dotenv(Path(__file__).parent / ".env")
except ImportError:
    pass

DB_PATH = os.getenv("PULSE_DB_PATH", str(Path(__file__).parent / "backend/database/database.sqlite"))
BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN")
CHAT_ID = os.getenv("TELEGRAM_CHAT_ID")
API_URL = os.getenv("PULSE_API_URL", "http://127.0.0.1:8080").rstrip("/")
API_TOKEN = os.getenv("PULSE_API_TOKEN")
CHECK_INTERVAL = int(os.getenv("CHECK_INTERVAL", "20"))
LATE_GRACE = int(os.getenv("LATE_GRACE_MINUTES", "10"))  # does not cross midnight
REPLY_WINDOW = int(os.getenv("REPLY_WINDOW_HOURS", "4"))
MSG_POLL = 3

REMIND_TRIGGERS = {"remind", "reminder", "remind me", "r"}

DONE_PHRASES = {
    "done", "did it", "finished", "complete", "completed", "✅", "👍", "✔️",
    "in bed", "lights out", "lights off", "screens off", "phone out", "devices out",
    "goodnight", "good night",
}
# Stripped from either end of a reply before matching, so "ok in bed now" counts but
# "not in bed yet" does not.
FILLER = {"ok", "okay", "yep", "yes", "yeah", "and", "now", "already", "all", "just"}

_running = True
_update_offset = 0
_awaiting_reminder = {}  # chat_id -> True
_dry_run = False
_now_override = None


def _signal_handler(sig, frame):
    global _running
    _running = False


def _now():
    return _now_override or datetime.now()


def _format_time(t):
    h, m = map(int, t.split(":"))
    return f"{h % 12 or 12}:{m:02d} {'PM' if h >= 12 else 'AM'}"


def _minutes(hm):
    h, m = map(int, hm.split(":"))
    return h * 60 + m


def _db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def _send(text, chat_id=None):
    """Send and return Telegram's message_id, or None on failure."""
    if _dry_run:
        print(f"[dry-run] send → {text}")
        return 0
    try:
        resp = requests.post(
            f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage",
            json={"chat_id": chat_id or CHAT_ID, "text": text, "parse_mode": "HTML"},
            timeout=10,
        )
        data = resp.json()
    except Exception as e:
        print(f"Send failed: {type(e).__name__}")
        return None
    if not data.get("ok"):
        print(f"Send failed: {data.get('description')}")
        return None
    return data["result"]["message_id"]


def _get_user_id():
    conn = sqlite3.connect(DB_PATH)
    row = conn.execute("SELECT id FROM users ORDER BY id LIMIT 1").fetchone()
    conn.close()
    return row[0] if row else 1


def _save_to_inbox(body):
    user_id = _get_user_id()
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    if _dry_run:
        print(f"[dry-run] inbox ← {body}")
        return
    conn = sqlite3.connect(DB_PATH)
    conn.execute(
        "INSERT INTO inbox_items (user_id, body, status, created_at, updated_at) VALUES (?, ?, 'pending', ?, ?)",
        (user_id, body, now, now),
    )
    conn.commit()
    conn.close()


# ── outgoing ────────────────────────────────────────────────────────────────

ROUTINE_COLUMNS = "id, title, cadence, scheduled_time, checkin_time, weekday, month_day"


def _is_completed(conn, routine_id, date):
    return conn.execute(
        "SELECT 1 FROM routine_completions WHERE routine_id = ? AND completed_date = ?",
        (routine_id, date),
    ).fetchone() is not None


def _period_start(cadence, day):
    """First day of the period a completion counts for (matches StreakService)."""
    if cadence == "weekly":
        return day - timedelta(days=day.isoweekday() - 1)
    if cadence == "monthly":
        return day.replace(day=1)
    return day


def _is_completed_for_period(conn, routine, day):
    return conn.execute(
        """SELECT 1 FROM routine_completions
           WHERE routine_id = ? AND completed_date >= ? AND completed_date <= ?""",
        (routine["id"], _period_start(routine["cadence"], day).isoformat(), day.isoformat()),
    ).fetchone() is not None


def _fires_on(routine, day):
    """weekday is ISO (1 = Monday … 7 = Sunday), as the API and UI use it."""
    if routine["cadence"] == "weekly":
        return routine["weekday"] == day.isoweekday()
    if routine["cadence"] == "monthly":
        return routine["month_day"] == day.day
    return True


def _scheduled_routines(conn):
    return conn.execute(
        f"SELECT {ROUTINE_COLUMNS} FROM routines WHERE is_active = 1 AND scheduled_time IS NOT NULL"
    ).fetchall()


def _deliver(conn, routine_id, date, kind, fallback):
    """Send the pre-written message for (routine, date, kind) if there is one, else
    `fallback`, and record the send. Does nothing if it was already sent."""
    row = conn.execute(
        "SELECT id, body, sent_at FROM routine_messages WHERE routine_id = ? AND message_date = ? AND kind = ?",
        (routine_id, date, kind),
    ).fetchone()
    if row and row["sent_at"]:
        return

    if row and row["body"]:
        text, source = html.escape(row["body"].strip(), quote=False), "generated"
    else:
        text, source = fallback, "plain"

    message_id = _send(text)
    if message_id is None:
        return  # not recorded, so the next check retries within the grace window

    print(f"Sent {kind} ({source}): routine {routine_id} for {date}")
    if _dry_run:
        return

    stamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    if row:
        conn.execute(
            "UPDATE routine_messages SET sent_at = ?, telegram_message_id = ?, source = ?, updated_at = ? WHERE id = ?",
            (stamp, message_id, source, stamp, row["id"]),
        )
    else:
        conn.execute(
            """INSERT INTO routine_messages
               (routine_id, message_date, kind, body, source, sent_at, telegram_message_id, created_at, updated_at)
               VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)""",
            (routine_id, date, kind, source, stamp, message_id, stamp, stamp),
        )
    conn.commit()


def _due(hm, now_min):
    return 0 <= now_min - _minutes(hm) <= LATE_GRACE


def _checkin_fallback(conn, routine, today, yesterday, now_hm):
    if _is_completed(conn, routine["id"], yesterday):
        streak = conn.execute(
            "SELECT current_streak FROM routine_streaks WHERE routine_id = ?", (routine["id"],)
        ).fetchone()
        n = streak["current_streak"] if streak else 1
        text = f"☀️ Last night: <b>{html.escape(routine['title'], quote=False)}</b> ✓ — {n} in a row."
    else:
        text = (f"☀️ Fresh start today. <b>{html.escape(routine['title'], quote=False)}</b> "
                f"again tonight at {_format_time(routine['scheduled_time'])}.")

    first = min(
        (r for r in _scheduled_routines(conn) if r["scheduled_time"] >= now_hm and _fires_on(r, today)),
        key=lambda r: r["scheduled_time"], default=None,
    )
    if first:
        text += f"\nFirst up: {html.escape(first['title'], quote=False)} at {_format_time(first['scheduled_time'])}."
    return text


def _check_routines():
    now = _now()
    today = now.strftime("%Y-%m-%d")
    yesterday = (now - timedelta(days=1)).strftime("%Y-%m-%d")
    now_hm = now.strftime("%H:%M")
    now_min = _minutes(now_hm)

    conn = _db()
    for r in _scheduled_routines(conn):
        if not _fires_on(r, now.date()):
            continue

        if _due(r["scheduled_time"], now_min) and not _is_completed_for_period(conn, r, now.date()):
            plain = f"⏰ <b>{html.escape(r['title'], quote=False)}</b> — {_format_time(r['scheduled_time'])}"
            _deliver(conn, r["id"], today, "reminder", plain)

        # Check-ins are about "last night", so only daily routines get one.
        if r["cadence"] == "daily" and r["checkin_time"] and _due(r["checkin_time"], now_min):
            _deliver(conn, r["id"], today, "checkin", _checkin_fallback(conn, r, now.date(), yesterday, now_hm))

    conn.close()


# ── incoming ────────────────────────────────────────────────────────────────

def _normalize(text):
    words = re.sub(r"[.!,?]+", " ", text.lower()).split()
    while words and words[0] in FILLER:
        words.pop(0)
    while words and words[-1] in FILLER:
        words.pop()
    return " ".join(words)


def _reply_target(conn, reply_to_message_id):
    """The (routine, date, title) a done-reply refers to: the reminder it quotes, else the
    most recently scheduled routine within REPLY_WINDOW. Never an older one, so a
    repeated "done" can't spill onto a different routine."""
    if reply_to_message_id:
        row = conn.execute(
            """SELECT m.routine_id, m.message_date, r.title FROM routine_messages m
               JOIN routines r ON r.id = m.routine_id
               WHERE m.telegram_message_id = ? AND m.kind = 'reminder'""",
            (reply_to_message_id,),
        ).fetchone()
        if row:
            return row["routine_id"], row["message_date"], row["title"]

    now = _now()
    window_start = now - timedelta(hours=REPLY_WINDOW)
    candidates = []
    for r in _scheduled_routines(conn):
        h, m = map(int, r["scheduled_time"].split(":"))
        for day in (now.date(), now.date() - timedelta(days=1)):
            if not _fires_on(r, day):
                continue
            at = datetime(day.year, day.month, day.day, h, m)
            if window_start <= at <= now:
                candidates.append((at, r["id"], day.isoformat(), r["title"]))

    if not candidates:
        return None
    _, routine_id, date, title = max(candidates)
    return routine_id, date, title


def _complete_via_api(routine_id, date):
    if _dry_run:
        print(f"[dry-run] POST {API_URL}/api/routines/{routine_id}/complete date={date}")
        return True
    try:
        resp = requests.post(
            f"{API_URL}/api/routines/{routine_id}/complete",
            json={"date": date},
            headers={"Authorization": f"Bearer {API_TOKEN}", "Accept": "application/json"},
            timeout=10,
        )
    except Exception as e:
        print(f"Complete failed: {type(e).__name__}")
        return False
    if resp.status_code != 200:
        print(f"Complete failed: HTTP {resp.status_code}")
        return False
    return True


def _handle_done(chat_id, reply_to_message_id):
    conn = _db()
    target = _reply_target(conn, reply_to_message_id)
    conn.close()

    if not target:
        _send("Nothing open to mark done right now.", chat_id)
        return

    routine_id, date, title = target
    conn = _db()
    already = _is_completed(conn, routine_id, date)
    conn.close()
    if already:
        _send(f"Already logged ✓ — <b>{html.escape(title, quote=False)}</b>.", chat_id)
        return

    if not API_TOKEN or not _complete_via_api(routine_id, date):
        _send(f"Couldn't log <b>{html.escape(title, quote=False)}</b> — tick it in Pulse.", chat_id)
        return

    when = "" if date == _now().strftime("%Y-%m-%d") else f" for {datetime.fromisoformat(date):%a %b %-d}"
    _send(f"✅ <b>{html.escape(title, quote=False)}</b> logged{when}.", chat_id)
    print(f"Completed via reply: routine {routine_id} for {date}")


def _handle_text(chat_id, text, reply_to_message_id=None):
    text = text.strip()
    if chat_id in _awaiting_reminder:
        del _awaiting_reminder[chat_id]
        _save_to_inbox(text)
        _send("✅ Saved to your inbox.", chat_id)
        print(f"Inbox: {text}")
    elif text.lower() in REMIND_TRIGGERS:
        _awaiting_reminder[chat_id] = True
        _send("What would you like me to remind you?", chat_id)
    elif _normalize(text) in DONE_PHRASES:
        _handle_done(chat_id, reply_to_message_id)


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

        _handle_text(chat_id, msg["text"], (msg.get("reply_to_message") or {}).get("message_id"))


def main():
    global _dry_run, _now_override

    parser = argparse.ArgumentParser(description="Pulse reminder bot (Telegram)")
    parser.add_argument("--dry-run", action="store_true", help="print instead of sending or writing")
    parser.add_argument("--now", help='fake the clock, e.g. "2026-10-18 23:00"')
    parser.add_argument("--once", action="store_true", help="one routine check, then exit (never reads Telegram)")
    parser.add_argument("--reply", help="handle this text as if it came from the chat, then exit")
    args = parser.parse_args()

    _dry_run = args.dry_run
    if args.now:
        _now_override = datetime.strptime(args.now, "%Y-%m-%d %H:%M")

    if not BOT_TOKEN or not CHAT_ID:
        print("TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID required")
        sys.exit(1)

    if args.reply is not None:
        _handle_text(str(CHAT_ID), args.reply)
        return
    if args.once:
        _check_routines()
        return

    signal.signal(signal.SIGTERM, _signal_handler)
    signal.signal(signal.SIGINT, _signal_handler)

    if not API_TOKEN:
        print("PULSE_API_TOKEN not set — done-replies will not be logged")
    print(f"Pulse bot — routines checked every {CHECK_INTERVAL}s (on time, {LATE_GRACE}min grace), "
          f"messages every {MSG_POLL}s")

    last_routine_check = 0

    while _running:
        try:
            _poll_messages()

            now = time.time()
            if now - last_routine_check >= CHECK_INTERVAL:
                _check_routines()
                last_routine_check = now
        except Exception as e:
            print(f"Error: {e}")

        time.sleep(MSG_POLL)


if __name__ == "__main__":
    main()
