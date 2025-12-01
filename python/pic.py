#!/usr/bin/env python3
"""
pic.py — Fill flights.LSP with users.id by matching FirstName/LastName.

Safe defaults for XAMPP:
  host=localhost, db=raimon_fleet, user=root, password=""

Usage (dry-run):
  python pic.py

Apply changes:
  python pic.py --apply

Override DB settings:
  python pic.py --db raimon_fleet --user root --password "" --host localhost --port 3306 --apply
"""

import argparse
import sys
import pymysql
from typing import Dict, List, Tuple

# ---------- Config ----------
DEFAULT_HOST = "localhost"
DEFAULT_PORT = 3306
DEFAULT_DB   = "raimon_fleet"
DEFAULT_USER = "root"
DEFAULT_PASS = ""
BATCH_SIZE   = 1000


def normalize(s: str) -> str:
    """Trim, collapse spaces, lowercase (Unicode-safe)."""
    if not s:
        return ""
    return " ".join(s.strip().split()).lower()


def get_connection(host: str, port: int, user: str, password: str, db: str):
    return pymysql.connect(
        host=host, port=port, user=user, password=password, database=db,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor, autocommit=False
    )


def build_user_map(cur) -> Tuple[Dict[Tuple[str, str], List[int]], int, int]:
    """Return (name_map, total_users, duplicate_keys_count)."""
    cur.execute("""
        SELECT id, first_name, last_name
        FROM users
        WHERE status = 'active'
    """)
    rows = cur.fetchall()
    name_map: Dict[Tuple[str, str], List[int]] = {}
    for r in rows:
        key = (normalize(r["first_name"]), normalize(r["last_name"]))
        name_map.setdefault(key, []).append(r["id"])
    dup_count = sum(1 for v in name_map.values() if len(v) > 1)
    return name_map, len(rows), dup_count


def process_flights(cur, name_map: Dict[Tuple[str, str], List[int]], apply: bool) -> dict:
    stats = {
        "scanned": 0,
        "updated": 0,
        "no_name": 0,
        "no_match": 0,
        "ambiguous": 0,
        "batches": 0,
    }

    offset = 0
    while True:
        cur.execute(
            """
            SELECT id, FirstName, LastName, LSP
            FROM flights
            WHERE (LSP IS NULL OR LSP = 0)
              AND (FirstName IS NOT NULL AND TRIM(FirstName) <> '')
              AND (LastName  IS NOT NULL AND TRIM(LastName)  <> '')
            ORDER BY id
            LIMIT %s OFFSET %s
            """,
            (BATCH_SIZE, offset),
        )
        flights = cur.fetchall()
        if not flights:
            break

        for f in flights:
            stats["scanned"] += 1
            fn = normalize(f["FirstName"])
            ln = normalize(f["LastName"])
            if not fn or not ln:
                stats["no_name"] += 1
                continue

            key = (fn, ln)
            user_ids = name_map.get(key)
            if not user_ids:
                stats["no_match"] += 1
                continue
            if len(user_ids) != 1:
                stats["ambiguous"] += 1
                continue

            uid = user_ids[0]
            if apply:
                cur.execute("UPDATE flights SET LSP=%s WHERE id=%s", (uid, f["id"]))
            stats["updated"] += 1

        offset += BATCH_SIZE
        stats["batches"] += 1

    return stats


def main():
    ap = argparse.ArgumentParser(description="Fill flights.LSP from users.id by FirstName/LastName match.")
    ap.add_argument("--host", default=DEFAULT_HOST)
    ap.add_argument("--port", type=int, default=DEFAULT_PORT)
    ap.add_argument("--db",   default=DEFAULT_DB)
    ap.add_argument("--user", default=DEFAULT_USER)
    ap.add_argument("--password", default=DEFAULT_PASS)
    ap.add_argument("--apply", action="store_true", help="Write changes (otherwise dry-run)")
    args = ap.parse_args()

    apply = args.apply
    mode = "APPLY" if apply else "DRY-RUN"
    print(f"[pic] Mode: {mode}")
    print(f"[pic] DB: {args.user}@{args.host}:{args.port}/{args.db}")

    try:
        conn = get_connection(args.host, args.port, args.user, args.password, args.db)
    except Exception as e:
        print(f"[pic] ERROR: cannot connect to DB: {e}", file=sys.stderr)
        sys.exit(1)

    try:
        with conn.cursor() as cur:
            # Build user map
            name_map, total_users, dup_keys = build_user_map(cur)
            print(f"[pic] Loaded users: {total_users} | duplicate name-keys: {dup_keys}")

            # Process flights
            stats = process_flights(cur, name_map, apply)
            if apply:
                conn.commit()

        print(
            "[pic] Done | "
            f"batches={stats['batches']} scanned={stats['scanned']} "
            f"updated={stats['updated']} no_match={stats['no_match']} "
            f"ambiguous={stats['ambiguous']} no_name={stats['no_name']}"
        )
        if not apply:
            print("[pic] Dry-run complete: no database changes were written.")
    except KeyboardInterrupt:
        print("\n[pic] Interrupted by user.")
        conn.rollback()
        sys.exit(130)
    except Exception as e:
        print(f"[pic] ERROR: {e}", file=sys.stderr)
        conn.rollback()
        sys.exit(1)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
