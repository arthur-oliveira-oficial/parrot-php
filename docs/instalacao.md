# Instalação do Parrot PHP

## Requisitos

- **PHP 8.4+** (obrigatório)
- **Composer**
- **MySQL/MariaDB** ou outro banco de dados suportado

### FrankenPHP: Versões ZTS

O FrankenPHP **obrigatoriamente** precisa de PHP com ZTS (Thread Safety). Instale as versões `php-zts-*`:

```bash
# Dependências ZTS já instaladas no seu sistema:
php-zts-cli        # Linha de comando
php-zts-embed      # Biblioteca embed
php-zts-mysqlnd    # MySQL native driver
php-zts-pdo        # PDO
php-zts-pdo-mysql  # PDO MySQL

# Para instalar (se necessário):
sudo apt install -y php8.5-zts php8.5-zts-cli php8.5-zts-embed php8.5-zts-mysqlnd php8.5-zts-pdo php8.5-zts-pdo-mysql
```

---

## Instalação Rápida

```bash
# 1. Instalar dependências do sistema
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mbstring php8.4-json php8.4-ctype php8.4-pdo php8.4-mysql php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl composer nginx

# 2. Instalar dependências do projeto
composer install

# 3. Configurar .env
cp .env.example .env
```

---

## Escolha o Servidor

### Opção 1: FrankenPHP (Recomendado)

Servidor moderno com melhor performance.

```bash
# Instalar Caddy com FrankenPHP
curl https://frankenphp.dev/install.sh | sh
```

Crie o arquivo `Caddyfile` na raiz do projeto:

```caddy
{
    frankenphp
}

:8080 {
    root * public/
    php_server
}

```

Iniciar:
```bash
frankenphp run
```

### Opção 2: Nginx + PHP-FPM

```bash
sudo apt install -y nginx
```

Crie `/etc/nginx/sites-available/parrot-php`:

```nginx
server {
    listen 80;
    server_name localhost;
    root /caminho/para/parrot-php/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Ativar:
```bash
sudo ln -s /etc/nginx/sites-available/parrot-php /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

---

## Configuração .env

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=parrot_php
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# JWT (use uma chave forte!)
JWT_SECRET=sua_chave_secreta_aqui
```

---

## Produção: Ajustes Obrigatórios

Antes de colocar em produção, faça estas alterações:

```bash
# 1. Altere o .env:
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=chave_muito_segura_aqui  # Gere com: openssl rand -base64 32

# 2. Otimize o Composer para produção:
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

---

## Verificação

```bash
php -v
composer --version
curl http://localhost:8080
```

---

## Problemas Comuns

| Problema | Solução |
|----------|---------|
| PHP não encontrado | `sudo apt install php8.4` |
| Extensão PDO missing | `sudo apt install php8.4-pdo` |
| Banco de dados não conecta | Verifique credenciais no .env |
| Porta em uso | Altere a porta no Caddyfile ou Nginx |

---

## Próximos Passos

1. Configure o banco de dados no `.env`
2. Execute `composer install`
3. Inicie o servidor
4. Acesse `http://localhost:8080`
