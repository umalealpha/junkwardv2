"""
mailer.py — Report delivery via SMTP
Reads stakeholder list from DB table `report_stakeholders`.
Falls back to env-configured recipients if table doesn't exist yet.
"""

import os
import smtplib
import ssl
from email.mime.multipart  import MIMEMultipart
from email.mime.text        import MIMEText
from email.mime.base        import MIMEBase
from email                  import encoders
from pathlib                import Path
from datetime               import datetime
from dotenv                 import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

SMTP_HOST  = os.getenv('MAIL_HOST',       'smtp.gmail.com')
SMTP_PORT  = int(os.getenv('MAIL_PORT',   '587'))
SMTP_USER  = os.getenv('MAIL_USERNAME',   '')
SMTP_PASS  = os.getenv('MAIL_PASSWORD',   '')
MAIL_FROM  = os.getenv('MAIL_FROM_ADDRESS', SMTP_USER)
MAIL_NAME  = os.getenv('MAIL_FROM_NAME',  'Graphite Finance Engine')

# Fallback recipients from env (comma-separated)
_fallback_recipients = [
    r.strip() for r in os.getenv('REPORT_RECIPIENTS', '').split(',')
    if r.strip()
]


def get_recipients(report_type: str) -> list[str]:
    """
    Fetch stakeholder emails from DB for this report type.
    Falls back to env REPORT_RECIPIENTS if table/data missing.
    """
    try:
        from .db import fetch_all
        rows = fetch_all(
            "SELECT email FROM report_stakeholders "
            "WHERE report_type = %s AND active = 1",
            (report_type,)
        )
        emails = [r['email'] for r in rows if r.get('email')]
        return emails or _fallback_recipients
    except Exception:
        return _fallback_recipients


def send_report(
    report_type: str,
    subject: str,
    body_html: str,
    attachment_path: str | None = None,
    extra_recipients: list[str] | None = None,
) -> bool:
    """
    Send a report email with optional Excel attachment.
    Returns True if sent successfully.
    """
    if not SMTP_USER or not SMTP_PASS:
        print("[MAILER] SMTP not configured — skipping email delivery")
        return False

    recipients = get_recipients(report_type) + (extra_recipients or [])
    if not recipients:
        print(f"[MAILER] No recipients for {report_type} — skipping")
        return False

    msg = MIMEMultipart('alternative')
    msg['Subject'] = subject
    msg['From']    = f"{MAIL_NAME} <{MAIL_FROM}>"
    msg['To']      = ', '.join(recipients)

    msg.attach(MIMEText(body_html, 'html'))

    # Attach file if provided
    if attachment_path and Path(attachment_path).exists():
        fname = Path(attachment_path).name
        with open(attachment_path, 'rb') as f:
            part = MIMEBase('application', 'octet-stream')
            part.set_payload(f.read())
        encoders.encode_base64(part)
        part.add_header('Content-Disposition', f'attachment; filename="{fname}"')
        msg.attach(part)

    try:
        ctx = ssl.create_default_context()
        with smtplib.SMTP(SMTP_HOST, SMTP_PORT) as server:
            server.ehlo()
            server.starttls(context=ctx)
            server.login(SMTP_USER, SMTP_PASS)
            server.sendmail(MAIL_FROM, recipients, msg.as_string())
        print(f"[MAILER] Sent '{subject}' → {recipients}")
        return True
    except Exception as e:
        print(f"[MAILER] ERROR sending email: {e}")
        return False


def build_report_html(summary: dict, report_name: str) -> str:
    """Build a clean HTML email body from a summary dict."""
    rows_html = ''.join(
        f"<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;font-weight:500'>{k.replace('_',' ').title()}</td>"
        f"<td style='padding:6px 12px;border-bottom:1px solid #eee'>{v}</td></tr>"
        for k, v in summary.items()
        if not k.startswith('output_') and k != 'report'
    )
    return f"""
    <html><body style="font-family:Arial,sans-serif;color:#333;max-width:700px;margin:auto">
      <div style="background:#1a3c5e;color:white;padding:20px 30px;border-radius:8px 8px 0 0">
        <h2 style="margin:0">📊 {report_name}</h2>
        <p style="margin:4px 0 0;opacity:0.8">Generated: {datetime.now().strftime('%Y-%m-%d %H:%M')}</p>
      </div>
      <div style="border:1px solid #ddd;border-top:none;padding:20px;border-radius:0 0 8px 8px">
        <table style="width:100%;border-collapse:collapse">
          {rows_html}
        </table>
        <p style="margin-top:20px;color:#666;font-size:12px">
          Full report attached. This is an automated message from the Graphite Finance Engine.
        </p>
      </div>
    </body></html>
    """
