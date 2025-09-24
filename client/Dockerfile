## Build stage: compile React (Vite) assets
FROM node:22-alpine AS build

WORKDIR /app

# Install deps first for better layer caching (pnpm via Corepack)
COPY package.json pnpm-lock.yaml* ./
RUN corepack enable && corepack prepare pnpm@9 --activate && \
    if [ -f pnpm-lock.yaml ]; then pnpm install --frozen-lockfile --prod=false; else pnpm install --no-frozen-lockfile --prod=false; fi

# Copy source
COPY . .

# Ensure no host node_modules override; reinstall clean with dev deps
RUN rm -rf node_modules && \
    corepack enable && corepack prepare pnpm@9 --activate && \
    if [ -f pnpm-lock.yaml ]; then pnpm install --frozen-lockfile --prod=false; else pnpm install --no-frozen-lockfile --prod=false; fi

# Accept API base URL at build time (default to http://localhost:8080)
ARG VITE_API_BASE_URL=http://localhost:8080
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL

# Build (Vite handles TS transpilation; skip separate tsc)
RUN pnpm run build

## Runtime stage: serve with nginx
FROM nginx:1.27-alpine AS runtime

WORKDIR /usr/share/nginx/html

# Remove default nginx static content
RUN rm -rf ./*

# Copy built assets
COPY --from=build /app/dist/ .

# Copy custom nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD wget -qO- http://127.0.0.1/ | grep -q '<!doctype html>' || exit 1

CMD ["nginx", "-g", "daemon off;"]


