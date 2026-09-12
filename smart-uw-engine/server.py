"""
server.py — smart-uw-engine as an HTTP sidecar.

Why a sidecar (PR #1119 review, 2026-06-11): the Graphite backend image is
php:8.4-fpm-alpine — it has no Python and does not copy the engine in. Rather
than bolt Python + the engine + Ollama into the PHP container, we run the
engine as its OWN small container and the Laravel job calls it over HTTP
(same pattern as the existing Node pdf-service). The PHP image stays unchanged.

Endpoints (stdlib http.server — no Flask/uvicorn dependency):
  GET  /health                      -> {"ok": true}
  POST /extract?ext=xlsb&local=1    -> raw file bytes in body
                                       -> {"source_file","segment_count","risks":[...]}

`local=1` forces the local model (PII-safe). Without it the engine self-routes
per segment (PII -> local, commercial -> Gemini when GEMINI_API_KEY is set).

Run:  python3 server.py            # binds 0.0.0.0:8099 (PORT env to change)
"""
from __future__ import annotations
import json, os, re, sys, tempfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import extract as _extract

PORT = int(os.environ.get("PORT", "8099"))
ALLOWED_EXT = {"xlsx", "xls", "xlsb", "pdf"}
ALLOWED_PROVIDER = {"gemini", "deepseek"}
MAX_BYTES = int(os.environ.get("SMARTUW_MAX_UPLOAD_BYTES", str(30 * 1024 * 1024)))

# Strip anything credential-shaped from error text before it leaves the engine.
# Defence in depth: provider exceptions are caught upstream (complete() falls
# back to local), but never echo a key into an HTTP body the caller persists.
_SECRET = re.compile(r"(sk-[\w\-]{6,}|AIza[\w\-]{6,}|AQ\.[\w\-]{6,}|Bearer\s+\S+)", re.I)


def _redact(s: str) -> str:
    return _SECRET.sub("***", s)


class Handler(BaseHTTPRequestHandler):
    def _send(self, code, obj):
        body = json.dumps(obj, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if urlparse(self.path).path == "/health":
            return self._send(200, {"ok": True})
        return self._send(404, {"error": "not found"})

    def do_POST(self):
        parts = urlparse(self.path)
        if parts.path != "/extract":
            return self._send(404, {"error": "not found"})
        q = parse_qs(parts.query)
        ext = (q.get("ext", ["xlsx"])[0] or "xlsx").lower().lstrip(".")
        if ext not in ALLOWED_EXT:
            return self._send(422, {"error": f"unsupported ext: {ext}"})
        force_local = q.get("local", ["0"])[0] in ("1", "true", "yes")
        # Commercial provider choice (gemini|deepseek) + optional key, passed
        # per-request by the Laravel caller from the Credentials Vault so the CFO
        # can switch the commercial "AI brain" without a redeploy. PII still
        # always routes local (providers.route_for); absent -> engine env default.
        provider = (q.get("provider", [""])[0] or "").lower() or None
        if provider and provider not in ALLOWED_PROVIDER:
            return self._send(422, {"error": f"unsupported provider: {provider}"})
        commercial_key = self.headers.get("X-Commercial-Key") or None

        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0:
            return self._send(400, {"error": "empty body"})
        if length > MAX_BYTES:
            return self._send(413, {"error": "file too large"})
        data = self.rfile.read(length)

        tmp = tempfile.NamedTemporaryFile(delete=False, suffix="." + ext)
        try:
            tmp.write(data); tmp.close()
            result = _extract.extract_file(tmp.name, force_local=force_local,
                                           provider=provider,
                                           commercial_key=commercial_key)
            return self._send(200, result)
        except Exception as e:
            return self._send(500, {"error": _redact(f"{type(e).__name__}: {e}")})
        finally:
            try:
                os.unlink(tmp.name)
            except OSError:
                pass

    def log_message(self, *a):  # quiet; rely on the caller's logs
        pass


if __name__ == "__main__":
    srv = ThreadingHTTPServer(("0.0.0.0", PORT), Handler)
    print(f"smart-uw-engine sidecar listening on :{PORT}", flush=True)
    srv.serve_forever()
