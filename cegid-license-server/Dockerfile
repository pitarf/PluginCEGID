# Multi-stage Dockerfile otimizado para Next.js 16 em ARM64 / x86_64
FROM node:20-alpine AS base

# Instala dependências necessárias para compilação nativa se preciso
RUN apk add --no-cache libc6-compat openssl
WORKDIR /app

# 1. Instalação de Dependências
FROM base AS deps
COPY package.json package-lock.json ./
COPY prisma ./prisma/
RUN npm ci

# 2. Build da Aplicação
FROM base AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# Gera o client do Prisma antes do build do Next.js
ENV DATABASE_URL="postgresql://placeholder:placeholder@localhost:5432/placeholder"
RUN npx prisma generate
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

# 3. Imagem Final de Execução
FROM base AS runner
WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME="0.0.0.0"

RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nextjs

# Copia arquivos estáticos e build standalone
COPY --from=builder /app/public ./public
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/prisma ./prisma

USER nextjs

EXPOSE 3000

CMD ["sh", "-c", "npx prisma db push && npm start"]
