"""
db.py — Database connection pool for pyengine
Uses PyMySQL with connection pooling via a simple context manager.
Designed for high-throughput read/write on MariaDB 11.4 (AWS RDS af-south-1).
"""
import os
import pymysql
import pymysql.cursors
from contextlib import contextmanager
from dotenv import load_dotenv

# Load .env from parent cron directory
_env_path = os.path.join(os.path.dirname(__file__), '..', '.env')
load_dotenv(_env_path)

DB_CONFIG = {
    'host':     os.getenv('DB_HOST',     'localhost'),
    'port':     int(os.getenv('DB_PORT', '3306')),
    'db':       os.getenv('DB_DATABASE', 'Graphite_live'),
    'user':     os.getenv('DB_USERNAME', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'charset':  'utf8mb4',
    'autocommit': True,
    'connect_timeout': 30,
    'read_timeout':    300,   # 5 min — heavy reports need time
    'write_timeout':   60,
    'cursorclass': pymysql.cursors.DictCursor,
}


@contextmanager
def get_conn(readonly: bool = True):
    """Context manager — yields a pymysql connection, auto-closes."""
    conn = pymysql.connect(**DB_CONFIG)
    try:
        yield conn
    finally:
        conn.close()


def fetchone(sql: str, params=None) -> dict | None:
    """Execute a SELECT and return the first row as a dict, or None."""
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.fetchone()


def fetch_all(sql: str, params=None) -> list[dict]:
    """Execute a SELECT and return all rows as list of dicts."""
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.fetchall()


def fetch_df(sql: str, params=None):
    """Execute a SELECT and return a pandas DataFrame."""
    import pandas as pd
    rows = fetch_all(sql, params)
    return pd.DataFrame(rows) if rows else pd.DataFrame()


def execute(sql: str, params=None) -> int:
    """Execute INSERT/UPDATE/DELETE, return affected rows."""
    with get_conn(readonly=False) as conn:
        conn.autocommit = False
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            rows = cur.rowcount
        conn.commit()
        return rows


def execute_many(sql: str, rows: list) -> int:
    """Bulk INSERT via executemany, returns affected rows."""
    if not rows:
        return 0
    with get_conn(readonly=False) as conn:
        conn.autocommit = False
        with conn.cursor() as cur:
            cur.executemany(sql, rows)
            affected = cur.rowcount
        conn.commit()
        return affected
