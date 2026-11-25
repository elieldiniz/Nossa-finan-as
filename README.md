# Nossa Finanças

App de finanças para casal, feito a partir da planilha "Orç - Mês": fechamento do mês, cartões (fixos e parcelados), pessoas a pagar/receber, vale, recorrentes, acerto do casal, metas, gráficos e importação da planilha. Cada um lança o que é seu e vê o do outro em leitura; "Nós" mostra tudo junto.

**Stack:** Laravel 12 · Livewire 3 (starter kit com Flux) · Alpine.js · Tailwind CSS 4 · MySQL · Pest · Chart.js. App instalável (PWA).

## Rodar local
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# .env: configure o MySQL (DB_*). Para só testar, use DB_CONNECTION=sqlite e crie database/database.sqlite
php artisan migrate
npm run build        # ou npm run dev
php artisan serve
```

## Testes
```bash
php artisan test              # Pest (usa SQLite em memória por padrão)
./vendor/bin/pint --test      # estilo de código
```
A CI roda os testes contra MySQL 8 (`.github/workflows/tests.yml`).

## Documentação
- Especificação e plano: `.spec/features/nossa-financas-app-casal/` (SPEC, PLAN, PHASES)
- Publicação: `docs/deploy.md` · QA manual: `docs/qa-checklist.md`

## Regras importantes
- Dinheiro é sempre guardado em **centavos inteiros** (`App\Support\Money`), nunca em float.
- As contas ficam em `app/Domain` (código puro, sem banco), cobertas por testes.
- Isolamento do casal em `CoupleScope`; leitura dos dois e escrita só do dono em `app/Policies`.
