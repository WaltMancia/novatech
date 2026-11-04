# syntax=docker/dockerfile:1
FROM node:26-alpine AS frontend
WORKDIR /build
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM nginxinc/nginx-unprivileged:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=frontend /build/dist /usr/share/nginx/html
# La imagen base ya corre como "nginx" (uid 101); se declara para que quede explícito y auditable.
USER nginx
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/ || exit 1
