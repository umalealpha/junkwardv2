# ── Build stage ──────────────────────────────────────────────
FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Baked at build time via GitHub Actions secret
ARG VITE_API_URL=https://graphite-v2-api.alphadirect.co.bw
ARG VITE_GRAPHITE_URL=https://graphite-v2-fe.alphadirect.co.bw

ENV VITE_API_URL=$VITE_API_URL
ENV VITE_GRAPHITE_URL=$VITE_GRAPHITE_URL

RUN npm run build

# ── Production stage ─────────────────────────────────────────
FROM nginx:alpine AS production

COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget -q --spider http://localhost/ || exit 1

CMD ["nginx", "-g", "daemon off;"]
