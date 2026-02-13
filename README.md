# 🎭 Coup — Jogo de Blefe e Política em Tempo Real

Jogo de cartas multiplayer online baseado no jogo de tabuleiro **Coup**, construído com **Laravel 12**, **Vue 3**, **Laravel Reverb** (WebSockets) e containerizado com **Docker** para deploy na **AWS ECS**.

## 📋 Stack

| Camada       | Tecnologia                              |
| ------------ | --------------------------------------- |
| Backend      | Laravel 12 · PHP 8.3                    |
| Frontend     | Vue 3 · TailwindCSS 4 · AnimeJS 4      |
| WebSocket    | Laravel Reverb · Laravel Echo · Pusher  |
| Banco        | PostgreSQL 16                           |
| Build        | Vite 7 · Node 20                        |
| Infra        | Docker · Nginx · Supervisor · AWS ECS   |

---

## 🚀 Quick Start (Docker)

### Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) ≥ 24
- [Docker Compose](https://docs.docker.com/compose/) ≥ 2.20

### 1. Clonar e configurar

```bash
git clone <repo-url> coup
cd coup
```

> **⚠️ Nota:** O arquivo `.env.docker` já está configurado e será usado automaticamente pelo Docker Compose. **Não** substitua seu `.env` local.

### 2. Ajustar variáveis (opcional)

Edite o `.env.docker` se necessário:

| Variável              | Descrição                                    | Padrão        |
| --------------------- | -------------------------------------------- | ------------- |
| `DB_DATABASE`         | Nome do banco PostgreSQL                     | `coup`        |
| `DB_USERNAME`         | Usuário do banco                             | `coup`        |
| `DB_PASSWORD`         | Senha do banco                               | `secret`      |
| `APP_KEY`             | Gerada automaticamente se vazia              | (auto)        |
| `VITE_REVERB_HOST`    | Host público para WebSocket (browser → nginx)| `localhost`   |
| `VITE_REVERB_PORT`    | Porta pública do WebSocket                   | `80`          |

> **💡 Nota sobre APP_KEY:** Se deixar vazia, será gerada automaticamente no primeiro boot. Para usar uma key específica, gere com `php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"` e cole no `.env.docker`.

### 3. Build e subir

```bash
docker compose up -d --build
```

Isso cria 3 containers:

| Container    | Imagem               | Função                              |
| ------------ | -------------------- | ----------------------------------- |
| `coup-nginx` | nginx:1.27-alpine    | Reverse proxy + WebSocket proxy     |
| `coup-app`   | PHP 8.3 FPM + Reverb | Laravel API + Reverb WebSocket      |
| `coup-db`    | postgres:16-alpine   | Banco de dados                      |

### 4. Acessar

Abra `http://localhost` no navegador.

### 5. Logs

```bash
# Todos os containers
docker compose logs -f

# Apenas o app
docker compose logs -f app

# Apenas o banco
docker compose logs -f db
```

### 6. Parar

```bash
docker compose down

# Para apagar o volume do banco (CUIDADO: perde dados!)
docker compose down -v
```

---

## 🔧 Build Args (Customização)

Ao fazer build para um domínio/IP diferente (ex: AWS), passe os args do Vite:

```bash
docker compose build \
  --build-arg VITE_REVERB_HOST=meu-dominio.com \
  --build-arg VITE_REVERB_PORT=80 \
  --build-arg VITE_REVERB_SCHEME=http
```

Esses valores são incorporados no JavaScript durante o build e definem para onde o browser conecta o WebSocket.

---

## ☁️ Deploy na AWS ECS

### Arquitetura

```
Internet → ALB (porta 80) → ECS Task
                              ├── coup-nginx  (porta 80)
                              ├── coup-app    (porta 9000 + 8080)
                              └── coup-db     (porta 5432)
```

> **Nota:** Para produção, recomenda-se usar **Amazon RDS** em vez do container PostgreSQL.

### Passo a Passo

#### 1. Push das imagens para ECR

```bash
# Login no ECR
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin <ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com

# Criar repositórios
aws ecr create-repository --repository-name coup-app
aws ecr create-repository --repository-name coup-nginx

# Build e push do app
docker build -t coup-app \
  --build-arg VITE_REVERB_HOST=<SEU_DOMINIO_OU_IP> \
  --build-arg VITE_REVERB_PORT=80 \
  .
docker tag coup-app:latest <ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com/coup-app:latest
docker push <ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com/coup-app:latest

# Build e push do nginx (precisa de contexto para o config)
docker build -t coup-nginx -f- . <<'EOF'
FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
EOF
docker tag coup-nginx:latest <ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com/coup-nginx:latest
docker push <ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com/coup-nginx:latest
```

#### 2. Task Definition (ECS)

Crie uma Task Definition com 3 containers:

**Container `app`:**
- Imagem: `<ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com/coup-app:latest`
- Porta: 9000 (TCP), 8080 (TCP)
- CPU: 512 · Memória: 1024 MB
- Variáveis de ambiente: copiar do `.env.docker`
- Health check: `CMD-SHELL, php -r 'exit(0);'`

**Container `nginx`:**
- Imagem: `<ACCOUNT_ID>.dkr.ecr.us-east-1.amazonaws.com/coup-nginx:latest`
- Porta: 80 (TCP) — mapeada no ALB
- CPU: 256 · Memória: 512 MB
- Depends on: `app` (HEALTHY)
- Volumes from: `app` (para acessar `/var/www/html/public`)

**Container `db`:**
- Imagem: `postgres:16-alpine`
- Porta: 5432 (TCP)
- CPU: 256 · Memória: 512 MB
- Variáveis: `POSTGRES_DB=coup`, `POSTGRES_USER=coup`, `POSTGRES_PASSWORD=<SENHA_SEGURA>`
- EFS volume para persistência (recomendado) ou migrar para RDS

#### 3. Service & ALB

- Criar ALB com Target Group apontando para porta 80 do container nginx
- Configurar **Stickiness** no Target Group (WebSocket precisa manter conexão)
- Security Group: liberar porta 80 (HTTP) e 443 (HTTPS se usar certificado)

#### 4. Variáveis importantes para ECS

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<ALB_DNS>
VITE_REVERB_HOST=<ALB_DNS>
VITE_REVERB_PORT=80
DB_HOST=db          # Se usar container; ou endpoint RDS
DB_PASSWORD=<SENHA_SEGURA>
```

---

## 🏗️ Estrutura Docker

```
coup/
├── Dockerfile                         # Multi-stage: Node (frontend) + PHP (backend)
├── docker-compose.yml                 # 3 services: nginx, app, db
├── .env.docker                        # Template de variáveis para Docker
├── .dockerignore                      # Arquivos ignorados no build
└── docker/
    ├── nginx/
    │   └── default.conf               # Nginx: reverse proxy + WebSocket
    ├── php/
    │   ├── php-custom.ini             # PHP production settings
    │   └── www.conf                   # PHP-FPM pool config
    ├── supervisor/
    │   └── supervisord.conf           # Supervisor: php-fpm + reverb
    └── entrypoint.sh                  # Migrations, key gen, cache
```

---

## 🎮 Sobre o Jogo

**Coup** é um jogo de blefe para 2–6 jogadores. Cada jogador começa com 2 cartas (influências) e 2 moedas. O objetivo é eliminar as influências dos outros jogadores.

### Personagens

| Personagem  | Ação                    | Bloqueia              |
| ----------- | ----------------------- | --------------------- |
| Duque       | Taxar (+3 moedas)       | Ajuda Externa         |
| Assassino   | Assassinar (3 moedas)   | —                     |
| Capitão     | Roubar (+2 do alvo)     | Roubo                 |
| Embaixador  | Trocar cartas           | Roubo                 |
| Condessa    | —                       | Assassinato           |

### Ações

- **Renda:** +1 moeda (não pode ser bloqueada)
- **Ajuda Externa:** +2 moedas (pode ser bloqueada pelo Duque)
- **Golpe de Estado:** -7 moedas, elimina uma influência (obrigatório com 10+ moedas)

---

## 🛠️ Desenvolvimento Local (sem Docker)

Para desenvolvimento local com hot-reload e acesso via IP na LAN:

### 1. Configurar o IP local

Edite o `.env` e configure `DEV_HOST` com o IP da sua máquina:

```bash
# Se é a primeira vez, copie o exemplo
cp .env.example .env

# Windows: descobrir IP local
ipconfig | findstr IPv4

# Linux/Mac
ip addr show | grep inet
# ou
ifconfig | grep inet
```

No `.env`:
```env
DEV_HOST=192.168.1.100  # Seu IP aqui
```

### 2. Instalar dependências

```bash
# Primeira vez
composer install
npm install
php artisan key:generate
php artisan migrate

# Rodar tudo de uma vez (concurrently)
npm run full

# Acesse via http://<SEU_DEV_HOST>:8000
# Exemplo: http://192.168.1.100:8000
```

### Comandos individuais (alternativa)

```bash
# Terminal 1: Laravel API
php artisan serve --host=0.0.0.0

# Terminal 2: Reverb WebSocket
php artisan reverb:start --host=0.0.0.0 --port=8080

# Terminal 3: Vite HMR
npm run dev
```

### Diferenças entre ambientes

| Aspecto          | Desenvolvimento Local              | Docker                     |
| ---------------- | ---------------------------------- | -------------------------- |
| **Arquivo env**  | `.env` (SQLite, `DEV_HOST` config) | `.env.docker` (PostgreSQL) |
| **Banco**        | SQLite                             | PostgreSQL 16              |
| **WebSocket**    | Direto na porta 8080               | Proxy via Nginx `/app/`    |
| **Hot reload**   | ✅ Vite HMR                         | ❌ (precisa rebuild)       |
| **IP/Host**      | Configurável via `DEV_HOST`        | `localhost` (nginx)        |
| **Quando usar**  | Desenvolvimento + testes           | Produção + AWS ECS         |

> **💡 Dica:** Mantenha `.env` para desenvolvimento e `.env.docker` para Docker/produção. O Docker Compose já está configurado para carregar `.env.docker` automaticamente via `env_file`.

---

## 📄 Licença

MIT
