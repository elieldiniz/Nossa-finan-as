# Publicar o Nossa Finanças (runbook)

App Laravel 12 + Livewire + MySQL. Precisa de hospedagem com **PHP 8.3+**, **MySQL 8**, **HTTPS** e envio de e-mail (**SMTP**) para os convites.

## 1. Pré-requisitos do servidor
- PHP 8.3 com as extensões: `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `xml`, `curl`.
- MySQL 8 com um banco vazio e um usuário só para o app (sem permissão de administrador).
- Domínio com **HTTPS obrigatório** (certificado válido, por exemplo Let's Encrypt). O app só deve ser servido por `https://`.
- O servidor web aponta para a pasta `public/` (nunca para a raiz do projeto).

## 2. Variáveis de ambiente (`.env` no servidor, nunca no repositório)
Copie `.env.example` para `.env` e preencha:

| Variável | Valor |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://seu-dominio` |
| `APP_KEY` | gerada com `php artisan key:generate` |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | dados do MySQL |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | SMTP (o convite do par vai por e-mail) |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `database` (e rode o worker, ver item 5) |

## 3. Primeira publicação
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate        # só na primeira vez
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

## 4. Atualizar uma versão nova
```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```
A migração é sempre `php artisan migrate --force`. Faça o backup (item 6) **antes** de migrar.

## 5. Fila e e-mail
Rode um worker (Supervisor ou systemd): `php artisan queue:work --tries=3 --max-time=3600`. Teste o convite: Mais > Convidar meu par, com um e-mail seu.

## 6. Backup do MySQL
- Diário, com cópia fora do servidor: `mysqldump --single-transaction --routines nossa_financas | gzip > backup-$(date +%F).sql.gz`.
- Guarde pelo menos 14 dias e teste a restauração uma vez por mês em um banco de teste.

## 7. Rotação do `APP_KEY`
Trocar a `APP_KEY` encerra todas as sessões e invalida links de convite pendentes (são assinados com ela).
1. Faça backup. 2. `php artisan key:generate --force`. 3. `php artisan config:cache`. 4. Peça para todos entrarem de novo e reenvie convites pendentes.

## 8. Checklist de segredos
- [ ] `.env` fora do Git (está no `.gitignore`); nenhuma senha em arquivos do repositório.
- [ ] Segredos do deploy ficam em **GitHub > Settings > Secrets**: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY`. Nunca em texto claro no workflow.
- [ ] Usuário do MySQL com acesso só ao banco do app.
- [ ] `APP_DEBUG=false` em produção.
- [ ] HTTPS ativo e redirecionando `http://` para `https://`.
- [ ] Senha do SMTP em variável de ambiente, não no código.

## 9. Teste de fumaça depois de publicar
1. Abrir `https://seu-dominio/login` (responde 200 e é HTTPS).
2. Criar a conta, convidar o par e aceitar o convite.
3. Lançar uma saída, conferir o fechamento no Início e **Sair**.
