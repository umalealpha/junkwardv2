# Graphite PDF Service — Puppeteer + Chromium on Debian slim
FROM node:20-bookworm-slim

# Install Chromium and the font packages needed for decent rendering
# (ttf-mscorefonts-installer is contrib so we enable that first).
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      chromium \
      ca-certificates \
      fonts-liberation \
      fonts-dejavu-core \
      fonts-noto \
      fonts-noto-cjk \
      fonts-noto-color-emoji \
      libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
      libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
      libgbm1 libpango-1.0-0 libasound2 libatspi2.0-0 libx11-xcb1 \
      dumb-init \
 && rm -rf /var/lib/apt/lists/*

ENV CHROME_PATH=/usr/bin/chromium \
    NODE_ENV=production \
    PORT=3000

WORKDIR /app

# Install deps first for cache efficiency
COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund

COPY server.js ./

# Non-root user for safety
RUN useradd --create-home --shell /bin/bash pdf \
 && chown -R pdf:pdf /app
USER pdf

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD node -e "require('http').get('http://127.0.0.1:'+(process.env.PORT||3000)+'/health',r=>process.exit(r.statusCode===200?0:1)).on('error',()=>process.exit(1))"

# dumb-init so Chromium zombies get reaped
ENTRYPOINT ["dumb-init", "--"]
CMD ["node", "server.js"]
