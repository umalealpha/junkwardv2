# Alpha Brain sidecar — isolated Node service that runs ALONGSIDE Graphite V2.
# The core runner + inbox use only Node built-ins, so the image works even if
# the one optional native dep (better-sqlite3, used by the live notifier arm)
# is not installed.
FROM node:22-slim

WORKDIR /brain
COPY brain/ /brain/

# Optional deps are best-effort: if the better-sqlite3 prebuilt is unavailable
# the build still succeeds and the safe runner works.
RUN npm install --omit=dev || true

ENV BRAIN_ENABLED=false \
    BRAIN_LIVE_ARMS=false \
    BRAIN_PORT=8090 \
    BRAIN_DATA_DIR=/brain/data

EXPOSE 8090
CMD ["node", "server.js"]
