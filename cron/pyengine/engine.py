"""
engine.py — Background Cron Execution Engine
─────────────────────────────────────────────
• Reads job config from DB table `cron_jobs` (or falls back to built-ins)
• Runs each job in its own thread (non-blocking)
• Writes run status + output to `cron_runs` table
• Sends report emails via mailer.py
• Designed for < 10 minute total runtime on full dataset

CLI:
  python -m pyengine.engine                         → run all due jobs
  python -m pyengine.engine --job written_premium   → run one specific job
  python -m pyengine.engine --job ageing --email    → run + force email
  python -m pyengine.engine --list                  → list available jobs
  python -m pyengine.engine --job anomaly --fix     → anomaly with auto-fix
"""

from __future__ import annotations
import sys
import json
import threading
import traceback
from datetime import datetime
from pathlib  import Path

from .utils import get_logger, Timer

log = get_logger('engine')

# ─────────────────────────────────────────────────────────────────────────────
# JOB REGISTRY
# ─────────────────────────────────────────────────────────────────────────────
JOBS: dict[str, dict] = {
    'written_premium': {
        'label':       'Gross Written Premium Report',
        'module':      'pyengine.reports.written_premium',
        'fn':          'run',
        'schedule':    '0 6 1 * *',   # 1st of month, 06:00
        'email':       True,
        'report_type': 'written_premium',
    },
    'ageing': {
        'label':       'Debtors Ageing Report',
        'module':      'pyengine.reports.ageing',
        'fn':          'run',
        'schedule':    '0 7 * * 1',   # Every Monday 07:00
        'email':       True,
        'report_type': 'ageing',
    },
    'anomaly': {
        'label':       'Finance Anomaly Detection',
        'module':      'pyengine.reports.anomaly',
        'fn':          'run',
        'schedule':    '0 5 * * *',   # Daily 05:00
        'email':       True,
        'report_type': 'anomaly',
    },
    'premium_anomaly': {
        'label':       'Premium Anomaly Detection',
        'module':      'pyengine.reports.premium_anomaly',
        'fn':          'run',
        'schedule':    '0 6 * * 3',   # Wednesdays 06:00
        'email':       True,
        'report_type': 'premium_anomaly',
    },
    'payment_anomaly': {
        'label':       'Payment Anomaly Detection',
        'module':      'pyengine.reports.payment_anomaly',
        'fn':          'run',
        'schedule':    '0 6 * * *',   # Daily 06:00
        'email':       True,
        'report_type': 'payment_anomaly',
    },
    'kyc_compliance_report': {
        'label':       'KYC Compliance Report',
        'module':      'pyengine.reports.kyc_compliance_report',
        'fn':          'run',
        'schedule':    '0 7 * * 2',   # Tuesdays 07:00
        'email':       True,
        'report_type': 'kyc_compliance_report',
    },
    'reinsurance_anomaly': {
        'label':       'Reinsurance Anomaly Report',
        'module':      'pyengine.reports.reinsurance_anomaly',
        'fn':          'run',
        'schedule':    '0 7 * * 5',   # Fridays 07:00
        'email':       True,
        'report_type': 'reinsurance_anomaly',
    },
    'policy_audit': {
        'label':       'Policy Completeness Audit',
        'module':      'pyengine.reports.policy_audit',
        'fn':          'run',
        'schedule':    '30 5 * * *',  # Daily 05:30
        'email':       True,
        'report_type': 'policy_audit',
    },
    'collections_report': {
        'label':       'Collections Report',
        'module':      'pyengine.reports.collections_report',
        'fn':          'run',
        'schedule':    '0 8 * * 1',   # Mondays 08:00
        'email':       True,
        'report_type': 'collections_report',
    },
    'claims_anomaly': {
        'label':       'Claims Anomaly Detection',
        'module':      'pyengine.reports.claims_anomaly',
        'fn':          'run',
        'schedule':    '0 7 * * 4',   # Thursdays 07:00
        'email':       True,
        'report_type': 'claims_anomaly',
    },
}


def _import_fn(module: str, fn: str):
    """Dynamically import a function from a module string."""
    import importlib
    mod = importlib.import_module(module)
    return getattr(mod, fn)


def _start_run(job_key: str) -> int | None:
    """
    Insert a 'running' row at job start.
    Returns the new row's ID so _finish_run() can update it, or None on failure.
    """
    try:
        from .db import execute, fetchone
        execute(
            """INSERT INTO cron_runs (job_key, status, started_at, created_at)
               VALUES (%s, 'running', NOW(), NOW())
            """,
            (job_key,)
        )
        row = fetchone("SELECT LAST_INSERT_ID() AS id")
        return row['id'] if row else None
    except Exception as e:
        log.warning(f"Could not write start record for {job_key}: {e}")
        return None


def _finish_run(
    run_id: int | None,
    job_key: str,
    status: str,
    summary: dict,
    elapsed: str,
    output_file: str | None,
):
    """
    Update the run row started by _start_run() with final results.
    Falls back to a fresh INSERT if run_id is None (e.g. DB was unavailable at start).
    """
    try:
        from .db import execute
        if run_id:
            execute(
                """UPDATE cron_runs
                   SET status=%s, summary=%s, output_file=%s, elapsed=%s, finished_at=NOW()
                   WHERE id=%s
                """,
                (status, json.dumps(summary), output_file, elapsed, run_id)
            )
        else:
            # Fallback: insert complete row if we never got a start ID
            execute(
                """INSERT INTO cron_runs
                   (job_key, status, summary, output_file, elapsed, started_at, finished_at, created_at)
                   VALUES (%s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                """,
                (job_key, status, json.dumps(summary), output_file, elapsed)
            )
    except Exception as e:
        log.warning(f"Could not write finish record for {job_key}: {e}")


def run_job(
    job_key: str,
    send_email: bool = False,
    extra_kwargs: dict | None = None,
) -> dict:
    """
    Execute a single job synchronously.
    Returns: {success, summary, path, elapsed}
    """
    job = JOBS.get(job_key)
    if not job:
        log.error(f"Unknown job: {job_key}")
        return {'success': False, 'error': f'Unknown job: {job_key}'}

    log.info(f">> Starting job: {job['label']}")
    kwargs = extra_kwargs or {}

    # Record start timestamp immediately
    run_id = _start_run(job_key)

    with Timer() as t:
        try:
            fn = _import_fn(job['module'], job['fn'])
            result = fn(**kwargs)
            success = True
        except Exception as exc:
            tb = traceback.format_exc()
            log.error(f"Job {job_key} FAILED: {exc}\n{tb}")
            result  = {'summary': {'error': str(exc)}, 'path': None}
            success = False

    summary = result.get('summary', {})
    path    = result.get('path')
    elapsed = str(t)

    log.info(f"{'OK' if success else 'FAIL'}  Job {job_key} completed in {elapsed}")

    # Record finish timestamp + results
    _finish_run(run_id, job_key, 'ok' if success else 'error', summary, elapsed, path)

    # Email delivery
    if success and (send_email or job.get('email')) and path:
        try:
            from .mailer import send_report, build_report_html
            html = build_report_html(summary, job['label'])
            send_report(
                report_type=job['report_type'],
                subject=f"[Graphite] {job['label']} — {datetime.now().strftime('%Y-%m-%d')}",
                body_html=html,
                attachment_path=path,
            )
        except Exception as me:
            log.warning(f"Email delivery failed: {me}")

    return {
        'success': success,
        'summary': summary,
        'path':    path,
        'elapsed': elapsed,
    }


def run_all(send_email: bool = False):
    """Run all registered jobs sequentially (safe for cron servers)."""
    log.info(f"=== PyEngine Run All ({len(JOBS)} jobs) ===")
    results = {}
    with Timer() as total:
        for key in JOBS:
            results[key] = run_job(key, send_email=send_email)
    log.info(f"=== All jobs complete in {total} ===")
    return results


# ─────────────────────────────────────────────────────────────────────────────
# CLI ENTRY POINT
# ─────────────────────────────────────────────────────────────────────────────
def main():
    args = sys.argv[1:]

    if '--list' in args:
        print("\nAvailable jobs:")
        for k, v in JOBS.items():
            print(f"  {k:<22} — {v['label']}  [{v['schedule']}]")
        return

    job_idx   = args.index('--job')   if '--job'   in args else -1
    job_key   = args[job_idx + 1]     if job_idx != -1     else None
    send_email = '--email' in args
    auto_fix   = '--fix'   in args
    dry_run    = '--dry-run' in args

    extra = {}
    if auto_fix:  extra['auto_fix'] = True
    if dry_run:   extra['dry_run']  = True

    # Period overrides for written_premium
    pf_idx = args.index('--from') if '--from' in args else -1
    pt_idx = args.index('--to')   if '--to'   in args else -1
    if pf_idx != -1: extra['period_from'] = args[pf_idx + 1]
    if pt_idx != -1: extra['period_to']   = args[pt_idx + 1]

    if job_key:
        result = run_job(job_key, send_email=send_email, extra_kwargs=extra or None)
        if result.get('path'):
            print(f"\nReport saved: {result['path']}")
    else:
        run_all(send_email=send_email)


if __name__ == '__main__':
    main()
