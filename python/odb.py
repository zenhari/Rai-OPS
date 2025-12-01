# pip install requests mysql-connector-python python-dateutil

import json
import requests
import mysql.connector as mysql
from mysql.connector import errorcode
from datetime import datetime, timedelta, timezone

URL = "https://airmaestro.raimonairways.net/api/reports/45"
PARAMS = {
    "format": "JSON",
    "AsAttachment": "FALSE",
    "Param22": "2025-08-08",
}
HEADERS = {
    "Authorization": "_)zMJ1c94D0XS1UTsYUw(5NR5,'P2'12k5!46S,'0(eyz2c_K_q3_6XeK3(hy,,Q"
}

DB_CFG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "YOUR_DB_USER",
    "password": "YOUR_DB_PASSWORD",
    "database": "raimon_fleet",
    "autocommit": False,
    "charset": "utf8mb4",
    "use_unicode": True,
}

# نگاشت اختیاری username -> user_id در سیستم شما
CREATED_BY_MAP = {
    # مثال:
    # "seraj.m": 37,
    # "moradzadeh.m": 24,
}
DEFAULT_SYSTEM_USER_ID = 1  # اگر پیدا نشد

def map_priority(alert_status_name: str) -> str:
    if not alert_status_name:
        return "normal"
    name = alert_status_name.strip().lower()
    if name in ("critical", "crit"):
        return "critical"
    if name in ("high", "urgent", "warning"):
        return "urgent"
    return "normal"

def parse_created_at(s: str) -> datetime | None:
    if not s:
        return None
    # نمونه ورودی: "2025-04-12T10:49:31.26"
    # به ISO ثانیه تبدیل می‌کنیم:
    try:
        # افزودن Z اختیاری نیست؛ بدون timezone ذخیره می‌کنیم
        # اگر میلی‌ثانیه دو رقمی باشد، پایتون هندل می‌کند
        return datetime.fromisoformat(s.replace("Z", ""))
    except Exception:
        return None

def fetch_airmaestro_rows():
    resp = requests.get(URL, params=PARAMS, headers=HEADERS, timeout=60)
    resp.raise_for_status()
    data = resp.json()
    if not isinstance(data, list):
        raise ValueError("Unexpected JSON shape: expected a list of objects.")
    return data

def group_by_alert(rows):
    """
    خروجی: dict[AlertID] = {
        'title': ..., 'body': ..., 'status_name': ...,
        'created_by_username': ..., 'created_at': datetime|None,
        'roles': set([...])
    }
    """
    grouped = {}
    for r in rows:
        alert_id = r.get("AlertID")
        if alert_id is None:
            # اگر بعضی ردیف‌ها AlertID ندارند، ردشان کن
            continue

        g = grouped.setdefault(alert_id, {
            "title": r.get("AlertTitle"),
            "body": r.get("AlertBody"),
            "status_name": r.get("AlertStatusName"),
            "created_by_username": r.get("CreatedBy"),
            "created_at": parse_created_at(r.get("CreatedAt")),
            "roles": set(),
        })

        # اگر عنوان/بدنه خالی بود ولی در این ردیف هست، جایگزین کن
        if not g["title"] and r.get("AlertTitle"):
            g["title"] = r.get("AlertTitle")
        if not g["body"] and r.get("AlertBody"):
            g["body"] = r.get("AlertBody")
        if not g["status_name"] and r.get("AlertStatusName"):
            g["status_name"] = r.get("AlertStatusName")
        if not g["created_by_username"] and r.get("CreatedBy"):
            g["created_by_username"] = r.get("CreatedBy")
        if not g["created_at"]:
            g["created_at"] = parse_created_at(r.get("CreatedAt"))

        dept = r.get("DepartmentName")
        if dept:
            g["roles"].add(dept.strip())

    return grouped

def ensure_indexes(cur):
    """
    برای جلوگیری از درج تکراری، یک ایندکس یکتا روی (title, created_at) می‌گذاریم.
    اگر از قبل وجود داشته باشد، خطا را نادیده می‌گیریم.
    """
    try:
        cur.execute(
            "ALTER TABLE `odb_notifications` "
            "ADD UNIQUE KEY `uq_title_created_at` (`title`, `created_at`)"
        )
    except mysql.Error as e:
        # اگر قبلاً ساخته شده یا تکراری بود، مشکلی نیست
        pass

def insert_notifications(grouped):
    cnx = mysql.connect(**DB_CFG)
    try:
        cur = cnx.cursor()
        ensure_indexes(cur)

        sql = (
            "INSERT IGNORE INTO `odb_notifications` "
            "(`title`, `message`, `file_path`, `priority`, `target_roles`, "
            "`created_by`, `is_active`, `expires_at`, `created_at`)"
            " VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)"
        )

        inserted = 0
        for alert_id, g in grouped.items():
            title = (g["title"] or "").strip() or f"Alert #{alert_id}"
            priority = map_priority(g.get("status_name"))
            roles = sorted(g["roles"]) if g["roles"] else []
            target_roles_json = json.dumps(roles, ensure_ascii=False)

            created_by_username = g.get("created_by_username") or ""
            created_by = CREATED_BY_MAP.get(created_by_username, DEFAULT_SYSTEM_USER_ID)

            created_at = g.get("created_at")
            # سیاست انقضا: 30 روز بعد از created_at (می‌توانید None بگذارید)
            expires_at = (created_at + timedelta(days=30)) if created_at else None

            # پیام: بدنه اصلی + هدر خلاصه‌شده
            body = (g.get("body") or "").strip()
            header = f"[AirMaestro AlertID: {alert_id} | Status: {g.get('status_name') or 'N/A'}]"
            message = f"{header}\n\n{body}" if body else header

            params = (
                title,
                message,
                None,                 # file_path
                priority,
                target_roles_json,    # JSON array
                created_by,
                1,                    # is_active
                expires_at,
                created_at or datetime.now(),
            )

            cur.execute(sql, params)
            if cur.rowcount > 0:
                inserted += 1

        cnx.commit()
        print(f"Inserted {inserted} notifications into raimon_fleet.odb_notifications")

    except mysql.Error as e:
        cnx.rollback()
        if e.errno == errorcode.ER_NO_SUCH_TABLE:
            print("Error: Table `odb_notifications` not found in database `raimon_fleet`.")
        else:
            print("MySQL Error:", e)
        raise
    finally:
        try:
            cur.close()
        except:
            pass
        cnx.close()

if __name__ == "__main__":
    rows = fetch_airmaestro_rows()
    grouped = group_by_alert(rows)
    insert_notifications(grouped)
