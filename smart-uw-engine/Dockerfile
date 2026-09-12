# smart-uw-engine sidecar — runs the schedule-extraction engine as its own
# container so the Graphite PHP image (php:8.4-fpm-alpine) stays unchanged.
# The Laravel SmartUnderwritingExtractJob calls it over HTTP (SMARTUW_ENGINE_URL),
# mirroring the existing Node pdf-service sidecar pattern.
FROM python:3.12-slim

# pyxlsb/pdfplumber/openpyxl are pure-python wheels; no system build deps needed.
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

ENV PORT=8099
EXPOSE 8099

# Healthcheck hits the engine's /health endpoint.
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD python3 -c "import urllib.request,sys; sys.exit(0 if urllib.request.urlopen('http://localhost:8099/health',timeout=4).status==200 else 1)"

CMD ["python3", "server.py"]
