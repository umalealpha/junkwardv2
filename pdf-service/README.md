# Graphite PDF Service

Standalone HTML→PDF microservice for Graphite, powered by Puppeteer + Chromium.
Replaces in-process `wkhtmltopdf` (barryvdh/laravel-snappy) for all PDF generation.

## Why

- `wkhtmltopdf` is unmaintained and crashes under load.
- Running Chromium inside the Laravel FPM container makes PHP images huge.
- A separate PDF service scales horizontally and isolates resource use.

## Endpoints

### `GET /health`
Liveness probe. Returns:
```json
{ "ok": true, "browser": true, "active": 0, "queued": 0, "totalRequests": 42, ... }
```

### `POST /render`
Render HTML or a URL to PDF.

**Request** (JSON body):
```json
{
  "html": "<html>…</html>",
  "options": {
    "format": "A4",
    "landscape": false,
    "printBackground": true,
    "margin": { "top": "10mm", "right": "10mm", "bottom": "10mm", "left": "10mm" },
    "filename": "policy-schedule.pdf"
  }
}
```

Either `html` or `url` is required.

**Response**: `application/pdf` binary. `X-Render-Ms` header reports render time.

**Auth**: set `X-PDF-API-KEY: <secret>` header (must match `PDF_API_KEY` env var).

## Local dev

```bash
npm install
PDF_API_KEY=dev-key CHROME_PATH=$(which chromium) npm start
```

Then:
```bash
curl -X POST http://localhost:3000/render \
  -H "Content-Type: application/json" \
  -H "X-PDF-API-KEY: dev-key" \
  -d '{"html":"<h1>Hello</h1>"}' \
  --output out.pdf
```

## Docker

```bash
docker build -t graphite-pdf-service .
docker run -p 3000:3000 -e PDF_API_KEY=dev-key graphite-pdf-service
```

## Env vars

| Var | Default | Purpose |
|-----|---------|---------|
| `PORT` | `3000` | HTTP port |
| `PDF_API_KEY` | *(unset)* | Shared secret. If unset, auth is disabled (dev only). |
| `CHROME_PATH` | `/usr/bin/chromium` | Path to Chromium binary |
| `MAX_RENDER_MS` | `120000` | Per-request timeout (ms) |
| `MAX_CONCURRENT` | `2` | Simultaneous page renders per container |

## Deployment

ECS Fargate task definition: `graphite-pdf-service`.
Service discovery via Cloud Map at `pdf-service.graphite.local:3000`.
See `ecs/pdf-service-taskdef.json` in the deployment-package repo root.
