# Sistema de Protocolo Interno

Sistema web profissional de **Gestão de Protocolos e Proposições Legislativas** para Câmaras Municipais, em arquitetura **SaaS multi-Câmaras** (banco compartilhado com isolamento lógico por `institution_id`).

## Stack

- PHP 8.3+ / Laravel 12
- MySQL 8 (produção) · SQLite (desenvolvimento local)
- Blade + Tailwind CSS v4 (Vite)
- RBAC (perfis e permissões), Laravel Storage privado, DomPDF, filas com driver `database`

## Requisitos

- PHP >= 8.2 com extensões: `pdo_mysql`, `zip`, `mbstring`, `openssl`, `fileinfo`, `gd` (ou `imagick`)
- Composer 2
- Node.js 18+ (apenas para build dos assets)
- MySQL 8 (produção)

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
php artisan migrate --seed --force
```

### Variáveis essenciais (.env)

```env
APP_NAME="Sistema de Protocolo Interno"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com.br
APP_TIMEZONE=America/Fortaleza
APP_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=protocolo
DB_USERNAME=usuario
DB_PASSWORD=senha

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=local
```

> O disco `local` mantém anexos e backups **fora da pasta pública** (`storage/app/private`).

### Credenciais iniciais

- Super Admin: definido por `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` (padrão `superadmin@protocolo.local` / `Admin@12345`).
- Ambiente local (`APP_ENV=local`) cria também a "Câmara Demo" com usuários de teste.

> Altere a senha do Super Admin imediatamente após o primeiro acesso.

## Agendador (Cron)

O backup automático depende do Scheduler. Adicione ao Cron:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

O comando `php artisan backup:run` gera, de hora em hora, os backups das Câmaras cuja configuração estiver no horário/frequência definidos. Use `--force` para execução manual imediata.

## Implantação

### cPanel (hospedagem compartilhada)

1. Envie o projeto para uma pasta **fora** de `public_html` (ex.: `/home/usuario/protocolo`).
2. Aponte o domínio/subdomínio para `protocolo/public`.
3. Em `/home/usuario/public_html`, crie um `.htaccess` (ou use o recurso "Document Root" do painel) redirecionando para a pasta `public`.
4. Crie o banco MySQL e o usuário pelo painel; ajuste o `.env`.
5. Execute via Terminal do cPanel (ou SSH):
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   npm install && npm run build   # ou envie a pasta public/build já compilada
   php artisan migrate --seed --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
6. Ajuste permissões de escrita em `storage/` e `bootstrap/cache/`.
7. Configure o Cron conforme a seção anterior.

> O `mysqldump` costuma estar disponível no cPanel; caso contrário, o sistema reporta erro claro ao tentar gerar o backup do banco.

### VPS (Ubuntu + Nginx)

```bash
sudo apt install php8.3-fpm php8.3-mysql php8.3-zip php8.3-mbstring php8.3-xml mysql-server nginx
```

- Aponte o `root` do servidor Nginx para `.../protocolo/public`.
- Configure o Query Worker se usar filas em background: `php artisan queue:work --daemon`.
- Configure o Cron do Scheduler (seção anterior).
- Para performance e segurança, rode `php artisan optimize` e ative HTTPS (Let's Encrypt).

## Manutenção

```bash
php artisan migrate --force        # aplicar migrations
php artisan config:cache           # cachear configuração
php artisan queue:work             # processar filas
```

## Backup e Restauração

Disponível na **Central de Backup** do painel:

- Backup por **Câmara** ou da **Plataforma** (todas as Câmaras), com manifesto e hash SHA-256.
- Geração manual, automática (Scheduler) e restauração a partir de arquivo `.zip`.
- Arquivos armazenados em `storage/app/private/backups` (fora da área pública).

---

Desenvolvido por **W-Tech Solutions**.
