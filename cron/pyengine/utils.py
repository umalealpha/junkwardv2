"""
utils.py — Shared utilities: logging, formatting, timing, file output.
"""
import os
import sys
import time
import logging
from datetime import datetime
from pathlib import Path

# ── Output directory ──────────────────────────────────────────────────────────
# In production (ECS): /var/www/html/storage/app/reports/
# Locally (Windows dev): cron/storage/app/reports/
# Both the cron container and backend container mount the same EFS path, so
# reports written here are readable by the backend for download.
_base = Path(os.path.dirname(__file__)) / '..'
OUTPUT_DIR = _base / 'storage' / 'app' / 'reports'
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

LOG_DIR = _base / 'storage' / 'logs'
LOG_DIR.mkdir(parents=True, exist_ok=True)


def get_logger(name: str) -> logging.Logger:
    """Return a logger that writes to stdout + rotating daily log file."""
    logger = logging.getLogger(name)
    if logger.handlers:
        return logger

    logger.setLevel(logging.DEBUG)
    fmt = logging.Formatter('[%(asctime)s] [%(levelname)s] %(message)s',
                            datefmt='%Y-%m-%d %H:%M:%S')

    # Console — force UTF-8 on Windows to handle any unicode in messages
    import io
    stream = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace') \
             if hasattr(sys.stdout, 'buffer') else sys.stdout
    ch = logging.StreamHandler(stream)
    ch.setFormatter(fmt)
    logger.addHandler(ch)

    # File
    log_file = LOG_DIR / f"{name}_{datetime.now().strftime('%Y%m%d')}.log"
    fh = logging.FileHandler(log_file, encoding='utf-8')
    fh.setFormatter(fmt)
    logger.addHandler(fh)

    return logger


class Timer:
    """Simple context-manager timer."""
    def __enter__(self):
        self._start = time.perf_counter()
        return self

    def __exit__(self, *_):
        self.elapsed = time.perf_counter() - self._start

    @property
    def seconds(self) -> float:
        return round(self.elapsed, 2)

    def __str__(self):
        s = self.elapsed
        return f"{s:.1f}s" if s < 60 else f"{s/60:.1f}min"


def bwp(n) -> str:
    """Format number as Botswana Pula currency string."""
    try:
        v = float(n or 0)
        return f"P{v:,.2f}"
    except (ValueError, TypeError):
        return "P0.00"


def pct(n, total) -> str:
    """Format as percentage string."""
    try:
        return f"{float(n or 0) / float(total or 1) * 100:.1f}%"
    except (ValueError, ZeroDivisionError):
        return "0.0%"


def safe_float(v, default=0.0) -> float:
    try:
        return float(v or default)
    except (ValueError, TypeError):
        return default


def report_path(name: str, ext: str = 'xlsx') -> Path:
    """Return a dated path inside storage/reports/."""
    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
    return OUTPUT_DIR / f"{name}_{ts}.{ext}"


PRODUCT_NAMES = {
    7:  'CAR',
    8:  'PAR',
    16: 'EAR',
    17: 'DOMG',
    18: 'CARP',
    19: 'PARP',
    20: 'DOMP',
    22: 'PARP',
}

DOMCOM_PRODUCTS = [7, 8, 16,17,18,20,22]
