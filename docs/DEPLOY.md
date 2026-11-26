# Guia de Implantação

Este documento cobre a publicação do **Sistema de Protocolo Interno** em **VPS** (Nginx + PHP-FPM + MySQL)
e em **cPanel** (SQLite/PHP nativo). Os arquivos de exemplo estão em [`deploy/`](../deploy).

## Pré-requisitos

- PHP 8.2+ com as extensões: `pdo`, `pdo_mysql` ou `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd` ou `imagick` (PDFs com imagem)
- Composer 2
- Node.js 20+ (apenas para compilar os assets no deploy)
- MySQL 8 ou MariaDB (VPS) / SQLite (cPanel)
- HTTPS com certificado válido (obrigatório: cookies de sessão são `secure` em produção)

## 1. VPS (Nginx + PHP-FPM + MySQL)

```bash
# Código
cd /var/www
git clone https://github.com/wellingtonmacedos/sistemadeprotocolointerno.git protocolo
cd protocolo

# Dependências e assets
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Ambiente
cp .env.example .env
php artisan key:generate
# Edite o .env: APP_URL, APP_ENV=production, APP_DEBUG=false,
# DB_*, MAIL_*, TRUSTED_PROXIES, SUPER_ADMIN_PASSWORD, PROTOCOLO_MAX_UPLOAD_KB
# e BACKUP_ENCRYPTION_KEY (chave dedicada dos backups; gere com `php artisan key:generate --show`)

# Banco
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force   # perfis e permissões
php artisan db:seed --class=SuperAdminSeeder --force       # exige SUPER_ADMIN_PASSWORD no .env

# Caches de produção
php artisan optimize        # config + routes + views

# Permissões
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Nginx: use [`deploy/nginx.conf.example`](../deploy/nginx.conf.example).
Ajuste `client_max_body_size` para um valor maior que `PROTOCOLO_MAX_UPLOAD_KB`.

> `upload_max_filesize` e `post_max_size` do `php.ini` também devem comportar o limite de anexos.

### Fila e agendador

```bash
# Worker de fila (Supervisor)
cp deploy/supervisor.conf.example /etc/supervisor/conf.d/protocolo-worker.conf
supervisorctl reread && supervisorctl update

# Agendador (cron a cada minuto) — executa backups e processa a fila
crontab -e
* * * * * cd /var/www/protocolo && php artisan schedule:run >> /dev/null 2>&1
```

## 2. cPanel (SQLite / PHP nativo)

1. Envie os arquivos para fora de `public_html` e aponte o domínio/subdomínio para a pasta `public`.
2. No `.env`, use SQLite: `DB_CONNECTION=sqlite` e `DB_DATABASE=/caminho/absoluto/database/database.sqlite`.
3. Rode, pelo Terminal do cPanel (ou por uma tarefa cron pontual):

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan optimize
```

4. Configure **uma única** tarefa cron por minuto:

```
* * * * * /usr/bin/php /home/USUARIO/protocolo/artisan schedule:run >> /dev/null 2>&1
```

> Em cPanel não há Supervisor: a fila é processada pelo agendador
> (`queue:work --stop-when-empty`), que roda a cada minuto. O `mysqldump` costuma não
> estar disponível — nesse caso os backups usam o adaptador de SQLite.

## 3. Monitoramento

- **Health check:** `GET /up`
  Retorna `200 OK` quando a aplicação responde e a conexão com o banco está saudável;
  retorna `500` quando o banco está indisponível. Use-o no UptimeRobot, no balanceador ou em um cron:
  `curl -fsS https://seu-dominio/up || echo "sistema fora do ar"`
- **Logs:** `storage/logs/laravel-AAAA-MM-DD.log` (canal `daily`, retenção de `LOG_DAILY_DAYS` dias).
- **Backups:** gerados pelo agendador e gerenciáveis em *Central de Backup* (`/backups`).

## 4. Checklist de go-live

- [ ] `APP_ENV=production` e `APP_DEBUG=false`
- [ ] `APP_KEY` gerada e **guardada em local seguro**
- [ ] `BACKUP_ENCRYPTION_KEY` definida e guardada em local seguro (os backups são criptografados com ela; sem ela são irrecuperáveis)
- [ ] HTTPS ativo e `SESSION_SECURE_COOKIE=true`
- [ ] `TRUSTED_PROXIES` configurado (se houver proxy/balanceador)
- [ ] `SUPER_ADMIN_PASSWORD` forte e senha alterada após o primeiro acesso
- [ ] SMTP testado (fluxo "Esqueci minha senha")
- [ ] Cron do agendador ativo e fila processando
- [ ] `php artisan optimize` executado após cada deploy
- [ ] Permissões de `storage` e `bootstrap/cache` aplicadas

## 5. Atualizações e rollback

```bash
# Atualização
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize

# Rollback do código
git checkout <commit-anterior>
php artisan optimize
```

> Migrações são incrementais e não destrutivas. Antes de restaurar um backup pela
> interface, o sistema cria automaticamente um snapshot do estado atual.
