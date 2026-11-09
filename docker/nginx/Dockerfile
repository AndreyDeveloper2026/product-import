# syntax=docker/dockerfile:1
# Stage 1: сборка Angular. Stage 2: nginx со статикой и прокси на php-fpm.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package*.json ./
RUN npm install --no-audit --no-fund
COPY frontend/ ./
RUN npm run build -- --configuration production

FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/dist/frontend/browser /usr/share/nginx/html
