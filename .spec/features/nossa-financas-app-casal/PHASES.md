# Phases: nossa-financas-app-casal

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/nossa-financas-app-casal/PHASES.md`.

## Phase 1: Fundação

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Instalar Laravel, Livewire Starter Kit, Pest e MySQL
      Arquivos: `composer.json`, `.env.example`, `config/database.php`, `phpunit.xml`, `tests/Pest.php`, `.gitignore`
      Mudança: criar projeto Laravel com Livewire Starter Kit; MySQL (banco de testes dedicado); Pest e Pint; `.env` não versionado.
      Cobre: UI-01, RNF-03
      Acceptance criteria: `php artisan test` roda com Pest e MySQL; `.env` está no `.gitignore`; `/login` responde 200.
      Testes: `tests/Feature/SmokeTest.php` — `/login` responde 200
- [ ] T02 — Localização pt-BR, fuso e layout base com Tailwind e Alpine
      Arquivos: `config/app.php`, `lang/pt_BR/*`, `resources/css/app.css`, `resources/js/app.js`, `resources/views/components/layouts/app.blade.php`
      Mudança: locale `pt_BR`, timezone `America/Sao_Paulo`; layout responsivo sem rolagem horizontal; cores com contraste AA.
      Cobre: UI-01, RNF-06
      Acceptance criteria: `config('app.locale')` é `pt_BR` e timezone `America/Sao_Paulo`; layout renderiza sem classe que force largura fixa maior que 360 px.
      Testes: `tests/Feature/LocaleTest.php` — datas e textos em pt-BR
- [ ] T03 — CI
      Arquivos: `.github/workflows/ci.yml`
      Mudança: pipeline com serviço MySQL: composer install, Pint, Pest, npm ci e build.
      Cobre: RNF-03
      Acceptance criteria: workflow define serviço MySQL e executa Pint, Pest e build do front-end.
      Testes: workflow verde no primeiro PR
- [ ] T04 — Money (centavos)
      Arquivos: `app/Support/Money.php`, `app/Providers/AppServiceProvider.php`, `tests/Unit/MoneyTest.php`
      Mudança: `Money::parse`, `Money::format`, `Money::percent` (2 casas), rejeita float; diretiva Blade `@brl`.
      Cobre: RNF-05, UI-06
      Acceptance criteria: `Money::format(225000)` retorna "R$ 2.250,00"; `Money::parse("1.234,56")` retorna 123456; negativo formata com "−"; nenhuma soma usa float.
      Testes: `tests/Unit/MoneyTest.php` — 225000 -> "R$ 2.250,00"; "1.234,56" -> 123456; 10+20 = 30 centavos

## Phase 2: Domínio puro (app/Domain)

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T05 — Fechamento do mês
      Arquivos: `app/Domain/MonthClosing.php`, `tests/Unit/Domain/MonthClosingTest.php`
      Mudança: calcula entradas, saídas e fechamento com cartão como saída única, pessoa como entrada/saída líquida, vale fora e pago irrelevante; devolve rótulo e sinal.
      Cobre: RF-09, UI-05, RNF-01
      Acceptance criteria: Madrinha +11500 e Bradesco 2x30000 resultam em fechamento −48500; alternar pago não muda o resultado; 200 linhas calculam em menos de 50 ms.
      Testes: `tests/Unit/Domain/MonthClosingTest.php` — caso planilha −48500
- [ ] T06 — Frase rápida
      Arquivos: `app/Domain/QuickPhraseParser.php`, `tests/Unit/Domain/QuickPhraseParserTest.php`
      Mudança: "50 mercado" vira saída 5000, nome "mercado", categoria "Outros"; formato inválido lança exceção tipada.
      Cobre: RF-05
      Acceptance criteria: "50 mercado" e "12,50 pão" são aceitas (5000 e 1250); "mercado" e "0 x" são rejeitadas.
      Testes: `tests/Unit/Domain/QuickPhraseParserTest.php` — casos válidos e inválidos
- [ ] T07 — Agenda de recorrência
      Arquivos: `app/Domain/RecurrenceSchedule.php`, `tests/Unit/Domain/RecurrenceScheduleTest.php`
      Mudança: informa ocorrência no mês, data (dia limitado ao fim do mês) e "parcela k/n" para mensal, anual e parcelado, com `ends_on` e `installments_total`.
      Cobre: RF-10, RF-11, RF-18
      Acceptance criteria: parcelado 3x iniciado em jan ocorre em jan, fev e mar e não em abr; término 23/12/2027 não gera em jan/2028; "Nunca" gera em 2040; anual só no mês de cobrança; dia 31 em fevereiro vira último dia.
      Testes: `tests/Unit/Domain/RecurrenceScheduleTest.php` — casos acima
- [ ] T08 — Cartões e faturas
      Arquivos: `app/Domain/CardInvoice.php`, `tests/Unit/Domain/CardInvoiceTest.php`
      Mudança: total do cartão no mês, saída só se total > 0, fatura do mês e prevista, estado pago.
      Cobre: RF-14, RF-15
      Acceptance criteria: 2 fixos de 30000 resultam em 60000; total 0 não gera saída; fatura futura prevista é a soma dos fixos e parcelas do mês.
      Testes: `tests/Unit/Domain/CardInvoiceTest.php` — casos acima
- [ ] T09 — Saldo de pessoas
      Arquivos: `app/Domain/PersonBalance.php`, `tests/Unit/Domain/PersonBalanceTest.php`
      Mudança: saldo = receber − pagar de pendências não quitadas, acumulado entre meses; quitação de parcelada zera a partir do mês seguinte; sem parcelas permanece até quitar.
      Cobre: RF-16, RF-31
      Acceptance criteria: pagar 30000 e receber 41500 dá +11500; Glória e Mãe dão 0; 3x100 com 2 pagas dá 10000; 3ª paga no mês M zera em M+1.
      Testes: `tests/Unit/Domain/PersonBalanceTest.php` — casos acima
- [ ] T10 — Vale
      Arquivos: `app/Domain/ValeBalance.php`, `tests/Unit/Domain/ValeBalanceTest.php`
      Mudança: saldo = vale − gastos do mês; sobra não transita; um vale por dono.
      Cobre: RF-17
      Acceptance criteria: 225000 − 25000 = 200000; sem gastos 225000; sobra de jan não soma em fev.
      Testes: `tests/Unit/Domain/ValeBalanceTest.php` — casos acima
- [ ] T11 — Acerto do casal
      Arquivos: `app/Domain/Settlement.php`, `tests/Unit/Domain/SettlementTest.php`
      Mudança: calcula "X deve Y a Z" com divisão configurável (padrão 50/50) e resto de centavo determinístico; acerto zera a dívida.
      Cobre: RF-19
      Acceptance criteria: 20000 pagos só pelo Eliel em 50/50 dá "Ana deve 10000 a Eliel"; 10001 dá 5001/5000; após acerto a dívida é 0.
      Testes: `tests/Unit/Domain/SettlementTest.php` — casos acima
- [ ] T12 — Metas e avisos
      Arquivos: `app/Domain/GoalAlert.php`, `tests/Unit/Domain/GoalAlertTest.php`
      Mudança: avisos nenhum, 80% e 100% por meta geral e por categoria, em inteiros.
      Cobre: RF-20, RNF-05
      Acceptance criteria: meta 100000 com 79999 não avisa; 80000 avisa 80%; 100000 avisa 100%.
      Testes: `tests/Unit/Domain/GoalAlertTest.php` — casos acima
- [ ] T13 — Janela de vencimentos
      Arquivos: `app/Domain/DueWindow.php`, `tests/Unit/Domain/DueWindowTest.php`
      Mudança: lista não pagos com vencimento entre hoje e hoje+7 usando data local.
      Cobre: RF-21
      Acceptance criteria: vencimento em 7 dias entra; em 8 dias não; item pago não.
      Testes: `tests/Unit/Domain/DueWindowTest.php` — casos acima
- [ ] T14 — Relatórios e séries
      Arquivos: `app/Domain/ReportCalculator.php`, `tests/Unit/Domain/ReportCalculatorTest.php`
      Mudança: total por categoria, variação vs. mês anterior (valor e %), séries 3m/6m/12m para linha, barras, rosca e ranking.
      Cobre: RF-23, RF-24
      Acceptance criteria: 30000 contra 20000 resulta em +50,00%; anterior 0 não divide por zero; trocar período recalcula as 4 séries.
      Testes: `tests/Unit/Domain/ReportCalculatorTest.php` — casos acima

## Phase 3: Dados, acesso, autenticação e convite

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T15 — Migrations: casal, usuários, convites
      Arquivos: `database/migrations/*_create_couples_table.php`, `database/migrations/*_add_couple_to_users_table.php`, `database/migrations/*_create_invitations_table.php`
      Mudança: tabela `couples`; `users.couple_id`; `invitations` com e-mail, token em hash, expiração e aceite.
      Cobre: RF-28, RF-29, RF-30
      Acceptance criteria: `php artisan migrate` cria as tabelas e colunas sem erro.
      Testes: `tests/Feature/Schema/CoreSchemaTest.php` — tabelas e colunas existem
- [ ] T16 — Migrations: categorias, cartões, lançamentos, recorrências
      Arquivos: `database/migrations/*_create_categories_table.php`, `database/migrations/*_create_cards_table.php`, `database/migrations/*_create_recurrences_table.php`, `database/migrations/*_create_entries_table.php`
      Mudança: `couple_id` em todas; `entries` com `amount_cents` BIGINT > 0, `month`, `category_id` NOT NULL, `owner_id`, `paid`, `joint`, `paid_by`, `recurrence_id` e índice único `recurrence_id + month`; `recurrences` com `ends_on` e `installments_total`; `cards` com `limit_cents`.
      Cobre: RF-03, RF-04, RF-11, RF-12, RF-13, RF-15
      Acceptance criteria: inserir lançamento sem categoria, com valor ≤ 0 ou ocorrência duplicada falha no banco.
      Testes: `tests/Feature/Schema/EntriesSchemaTest.php` — as três rejeições
- [ ] T17 — Migrations: pessoas, parcelas, vale, metas, acerto, faturas
      Arquivos: `database/migrations/*_create_people_table.php`, `database/migrations/*_create_person_debts_table.php`, `database/migrations/*_create_person_debt_installments_table.php`, `database/migrations/*_create_vales_table.php`, `database/migrations/*_create_vale_spends_table.php`, `database/migrations/*_create_goals_table.php`, `database/migrations/*_create_settlements_table.php`, `database/migrations/*_create_card_invoices_table.php`
      Mudança: pessoas por casal; pendências com direção, valor, parcelas e mês de quitação; parcelas com pago; vale por dono; gastos; metas; acerto; fatura paga por cartão e mês.
      Cobre: RF-15, RF-16, RF-17, RF-19, RF-20, RF-31
      Acceptance criteria: migrations aplicam e as constraints de integridade rejeitam valores negativos e donos inexistentes.
      Testes: `tests/Feature/Schema/OtherSchemaTest.php` — constraints
- [ ] T18 — Modelos Eloquent, factories e CoupleScope
      Arquivos: `app/Models/*.php`, `app/Models/Scopes/CoupleScope.php`, `database/factories/*.php`
      Mudança: modelos e relações; escopo global por casal do usuário autenticado; `$fillable` explícito sem `couple_id` vindo do cliente; factories.
      Cobre: RF-03, RF-30, RNF-03
      Acceptance criteria: usuário de outro casal não enxerga nenhum registro; criação define `couple_id` no servidor.
      Testes: `tests/Feature/CoupleScopeTest.php` — isolamento entre casais
- [ ] T19 — Policies (leitura de ambos, escrita do dono)
      Arquivos: `app/Policies/EntryPolicy.php`, `app/Policies/CardPolicy.php`, `app/Policies/ValePolicy.php`, `app/Policies/PersonDebtPolicy.php`, `app/Policies/RecurrencePolicy.php`, `app/Providers/AuthServiceProvider.php`
      Mudança: view = mesmo casal; create = membro com owner do casal; update e delete = owner igual ao usuário; cadastros compartilhados editáveis por ambos.
      Cobre: RF-02, RF-03, UI-07
      Acceptance criteria: update e delete em item do par são negados; create com dono igual ao par é permitido; todas as Policies registradas.
      Testes: coberto por T20
- [ ] T20 — Testes de isolamento e Policies
      Arquivos: `tests/Feature/Policies/EntryPolicyTest.php`, `tests/Feature/Policies/IsolationTest.php`
      Mudança: usuários A e B no casal e C fora; verifica leitura cruzada, escrita bloqueada, criação com dono trocado e visitante.
      Cobre: RF-02, RF-03, RF-28, RF-30, RNF-03
      Acceptance criteria: C não lê nada; B não edita nem exclui item de A e o item permanece idêntico; A cria item com dono B; visitante é redirecionado ao login.
      Testes: `tests/Feature/Policies/EntryPolicyTest.php`, `tests/Feature/Policies/IsolationTest.php` — cenários acima
- [ ] T21 — Cadastro cria casal e categorias padrão
      Arquivos: `app/Actions/Fortify/CreateNewUser.php`, `database/seeders/DefaultCategories.php`, `app/Services/CoupleBootstrap.php`
      Mudança: registro sem convite cria casal e as 10 categorias iniciais; login por e-mail e senha; Sair do starter kit.
      Cobre: RF-28, RF-12
      Acceptance criteria: após registrar existem 1 casal e 10 categorias; Sair encerra a sessão.
      Testes: `tests/Feature/Auth/RegistrationTest.php` — casal, categorias e Sair
- [ ] T22 — Convite do par
      Arquivos: `app/Models/Invitation.php`, `app/Services/InvitationService.php`, `app/Mail/PartnerInvitation.php`, `app/Livewire/Invite/InvitePartner.php`, `resources/views/livewire/invite/invite-partner.blade.php`, `routes/web.php`
      Mudança: "Convidar par" envia e-mail com link assinado de uso único; aceite cria o segundo usuário no mesmo casal; casal com 2 membros recusa novo convite e aceite.
      Cobre: RF-29
      Acceptance criteria: após aceite ambos veem os mesmos dados; convite não aceito ou expirado não concede acesso; 3º usuário e reutilização do link são recusados.
      Testes: `tests/Feature/Invite/InvitePartnerTest.php` — `Mail::fake`, aceite, 3º usuário, reuso
- [ ] T23 — Testes de autenticação protegida
      Arquivos: `tests/Feature/Auth/ProtectedRoutesTest.php`
      Mudança: garantir que todas as rotas de dados exigem sessão.
      Cobre: RF-28
      Acceptance criteria: cada rota de dados redireciona ao login sem sessão.
      Testes: `tests/Feature/Auth/ProtectedRoutesTest.php` — cada rota redireciona sem sessão

## Phase 4: Serviços de aplicação

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T24 — OpenMonthService (mês pronto)
      Arquivos: `app/Services/OpenMonthService.php`, `tests/Feature/Services/OpenMonthServiceTest.php`
      Mudança: materializa ocorrências das recorrências para os dois donos ao abrir o mês pela primeira vez, de forma idempotente, usando `RecurrenceSchedule`.
      Cobre: RF-10, RF-11, RNF-07
      Acceptance criteria: fixo de jan aparece em fev; parcelado 3x aparece em jan, fev e mar e não em abr; abrir duas vezes não duplica; 200 itens herdados em até 2 s.
      Testes: `tests/Feature/Services/OpenMonthServiceTest.php` — casos acima
- [ ] T25 — RecurrenceScopeService (3 escopos)
      Arquivos: `app/Services/RecurrenceScopeService.php`, `tests/Feature/Services/RecurrenceScopeServiceTest.php`
      Mudança: "só este mês", "este e próximos" e "todos, inclusive anteriores" para edição e exclusão, em transação, autorizando pela Policy.
      Cobre: RF-07, RF-08
      Acceptance criteria: 100 para 120 em "todos" altera passados pagos para 12000 mantendo-os pagos; "este e próximos" preserva meses anteriores; "só este mês" altera uma ocorrência.
      Testes: `tests/Feature/Services/RecurrenceScopeServiceTest.php` — 3 escopos
- [ ] T26 — MonthSummary (modelo do mês por visão)
      Arquivos: `app/Services/MonthSummary.php`, `app/Services/ViewScope.php`, `tests/Feature/Services/MonthSummaryTest.php`
      Mudança: carrega lançamentos do mês pela visão (Nós, usuário, par), totais de cartões e saldos de pessoas e chama `MonthClosing`.
      Cobre: RF-01, RF-03, RF-09, RF-14, RF-16
      Acceptance criteria: caso planilha em Nós retorna −48500; visão Eliel traz só itens do Eliel; 200 lançamentos usam no máximo 15 consultas.
      Testes: `tests/Feature/Services/MonthSummaryTest.php` — casos acima
- [ ] T27 — CategoryService
      Arquivos: `app/Services/CategoryService.php`, `tests/Feature/Services/CategoryServiceTest.php`
      Mudança: adicionar, renomear e remover categorias; remoção em uso exige destino e migra lançamentos.
      Cobre: RF-12
      Acceptance criteria: remover em uso sem destino é recusado; com destino migra e a categoria some; renomear reflete nos lançamentos.
      Testes: `tests/Feature/Services/CategoryServiceTest.php` — casos acima
- [ ] T28 — PersonDebtService
      Arquivos: `app/Services/PersonDebtService.php`, `tests/Feature/Services/PersonDebtServiceTest.php`
      Mudança: cria pendência com ou sem parcelas, marca parcela paga, quita e registra o mês de quitação; calcula saldo via `PersonBalance`.
      Cobre: RF-16, RF-31
      Acceptance criteria: 300 em 3x100 com 2 pagas dá 100,00; 3ª paga no mês M zera em M+1; sem parcelas permanece até quitar.
      Testes: `tests/Feature/Services/PersonDebtServiceTest.php` — casos acima

## Phase 5: Telas base (Livewire)

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T29 — Navegação celular e PC
      Arquivos: `resources/views/components/layouts/app.blade.php`, `resources/views/components/nav/bottom-bar.blade.php`, `resources/views/components/nav/sidebar.blade.php`, `resources/views/components/nav/more.blade.php`, `routes/web.php`
      Mudança: barra inferior (Início, Extrato, +, Cartões, Mais) e "Mais" com 9 itens; menu lateral a partir de 1024 px; rotas das telas.
      Cobre: UI-01, UI-02, UI-03
      Acceptance criteria: os 5 itens da barra e os 9 itens de "Mais" existem e abrem a tela; o menu lateral alcança todas as áreas.
      Testes: `tests/Feature/Ui/NavigationTest.php` — 5 e 9 itens, links do menu lateral
- [ ] T30 — Seletor de visão e navegação de mês
      Arquivos: `app/Livewire/Shell/ViewSwitcher.php`, `app/Livewire/Shell/MonthNav.php`, `app/Support/ViewContext.php`, `resources/views/livewire/shell/*.blade.php`, `tests/Feature/Livewire/ViewSwitcherTest.php`
      Mudança: seletor Nós, usuário e par; navegação anterior, seguinte e mês/ano via `#[Url]`; selo "somente leitura" na visão do par.
      Cobre: RF-01, RF-26, UI-07
      Acceptance criteria: as 3 opções aparecem e trocam o recorte; meses passados e futuros abrem identificados por mês e ano; visão do par exibe "somente leitura".
      Testes: `tests/Feature/Livewire/ViewSwitcherTest.php` — casos acima
- [ ] T31 — Tela Lançar (+) com Repetir
      Arquivos: `app/Livewire/Entry/EntryForm.php`, `resources/views/livewire/entry/entry-form.blade.php`, `resources/js/money-input.js`, `app/Services/EntryService.php`, `tests/Feature/Livewire/EntryFormTest.php`
      Mudança: formulário com valor, Saída/Entrada, categoria obrigatória, nome, "Pago com", "De quem", "Já está pago", gasto conjunto e Repetir completo; `authorize('create')`; lançamento simples em até 3 toques.
      Cobre: RF-04, RF-03, RF-11, RF-12, UI-04
      Acceptance criteria: salvar persiste os campos; "Já está pago" desmarcado salva não pago; sem categoria é recusado; em Nós o dono padrão é quem lança e pode ser trocado; recorrente com término gera regra correta.
      Testes: `tests/Feature/Livewire/EntryFormTest.php` — casos acima
- [ ] T32 — Extrato, frase rápida, filtros e pago
      Arquivos: `app/Livewire/Statement/StatementPage.php`, `resources/views/livewire/statement/statement-page.blade.php`, `tests/Feature/Livewire/StatementPageTest.php`
      Mudança: lista do mês, frase rápida, filtros Todos/Pendentes/Pagos, busca, círculo de pago só para o dono com `authorize('update')`.
      Cobre: RF-05, RF-06, RF-25, UI-07
      Acceptance criteria: "50 mercado" cria saída 50,00 em "Outros" não paga; "mercado" é rejeitada sem gravar; Pendentes lista só não pagos; busca filtra por nome; pago persiste; item do par sem controles e ação direta recusada.
      Testes: `tests/Feature/Livewire/StatementPageTest.php` — casos acima
- [ ] T33 — Tela do mês e fechamento
      Arquivos: `app/Livewire/Month/MonthPage.php`, `resources/views/livewire/month/month-page.blade.php`, `resources/views/components/closing-banner.blade.php`, `tests/Feature/Livewire/MonthPageTest.php`
      Mudança: abre o mês via `OpenMonthService`, mostra entradas, saídas e fechamento em destaque com sinal e rótulo, atualizado após cada ação sem recarregar.
      Cobre: RF-09, RF-10, UI-05
      Acceptance criteria: caso planilha exibe "−R$ 485,00" com rótulo de negativo; mês seguinte já vem preenchido; alterar lançamento atualiza o fechamento.
      Testes: `tests/Feature/Livewire/MonthPageTest.php` — casos acima
- [ ] T34 — Editar e excluir com escopo
      Arquivos: `app/Livewire/Entry/EditEntry.php`, `resources/views/livewire/entry/edit-entry.blade.php`, `resources/views/components/scope-modal.blade.php`, `tests/Feature/Livewire/EditEntryTest.php`
      Mudança: edição e exclusão de não recorrentes; modal de 3 escopos para recorrentes via `RecurrenceScopeService`; confirmação explícita em "todos, inclusive anteriores"; recusa item do par.
      Cobre: RF-07, RF-08, RF-02, UI-07
      Acceptance criteria: cada escopo altera só o previsto; fechamento é recalculado; edição de item do par retorna 403 e o item permanece inalterado.
      Testes: `tests/Feature/Livewire/EditEntryTest.php` — casos acima
- [ ] T35 — Tela de Categorias
      Arquivos: `app/Livewire/Categories/CategoriesPage.php`, `resources/views/livewire/categories/categories-page.blade.php`, `tests/Feature/Livewire/CategoriesPageTest.php`
      Mudança: adicionar, renomear e remover com escolha de destino quando em uso.
      Cobre: RF-12
      Acceptance criteria: remoção de categoria em uso exige destino; renomeação reflete nos lançamentos.
      Testes: `tests/Feature/Livewire/CategoriesPageTest.php` — casos acima

## Phase 6: Cartões, pessoas, vale e recorrentes

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T36 — Tela de Cartões e faturas
      Arquivos: `app/Livewire/Cards/CardsPage.php`, `app/Livewire/Cards/InvoiceView.php`, `resources/views/livewire/cards/*.blade.php`, `tests/Feature/Livewire/CardsPageTest.php`
      Mudança: CRUD de cartões sem limite de quantidade; lançamentos por cartão; fatura do mês e previstas; marcar fatura paga.
      Cobre: RF-13, RF-14, RF-15
      Acceptance criteria: 10 cartões com nomes distintos; 2 fixos de 300 geram saída 600,00; fatura paga persiste; fatura futura mostra previsto.
      Testes: `tests/Feature/Livewire/CardsPageTest.php` — casos acima
- [ ] T37 — Tela de Pessoas e pendências
      Arquivos: `app/Livewire/People/PeoplePage.php`, `resources/views/livewire/people/people-page.blade.php`, `tests/Feature/Livewire/PeoplePageTest.php`
      Mudança: cadastro de pessoas, pendências a pagar e receber com ou sem parcelas, parcela paga e quitação; saldo refletido no fluxo.
      Cobre: RF-16, RF-31
      Acceptance criteria: Madrinha mostra +115,00; 3 parcelas com 2 pagas mostram 100,00; pendência quitada zera.
      Testes: `tests/Feature/Livewire/PeoplePageTest.php` — casos acima
- [ ] T38 — Tela do Vale
      Arquivos: `app/Livewire/Vale/ValePage.php`, `resources/views/livewire/vale/vale-page.blade.php`, `tests/Feature/Livewire/ValePageTest.php`
      Mudança: valor do Vale por parceiro, gastos e saldo restante; sobra não passa ao mês seguinte; Vale do par somente leitura.
      Cobre: RF-17
      Acceptance criteria: 2.250,00 com gastos 250,00 mostra 2.000,00; fevereiro inicia com 2.250,00; escrita no Vale do par é recusada.
      Testes: `tests/Feature/Livewire/ValePageTest.php` — casos acima
- [ ] T39 — Tela de Recorrentes e assinaturas
      Arquivos: `app/Livewire/Recurring/RecurringPage.php`, `resources/views/livewire/recurring/recurring-page.blade.php`, `tests/Feature/Livewire/RecurringPageTest.php`
      Mudança: total mensal, lista e formulário (nome, valor, dia, cartão, categoria, todo mês ou todo ano).
      Cobre: RF-18, RF-10
      Acceptance criteria: total soma os recorrentes mensais ativos; anual entra só no mês de cobrança; item criado aparece na lista e no mês.
      Testes: `tests/Feature/Livewire/RecurringPageTest.php` — casos acima

## Phase 7: Acerto, metas, vencimentos, painel e relatórios

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T40 — Tela Acerto do casal
      Arquivos: `app/Livewire/Settlement/SettlementPage.php`, `resources/views/livewire/settlement/settlement-page.blade.php`, `tests/Feature/Livewire/SettlementPageTest.php`
      Mudança: configurar divisão, exibir "X deve R$ Y a Z" e marcar acerto realizado.
      Cobre: RF-19
      Acceptance criteria: gastos conjuntos de 200 pagos só pelo Eliel exibem "Ana deve R$ 100,00 a Eliel"; após acertar exibe 0,00.
      Testes: `tests/Feature/Livewire/SettlementPageTest.php` — casos acima
- [ ] T41 — Metas e avisos
      Arquivos: `app/Livewire/Goals/GoalsPage.php`, `resources/views/components/goal-banner.blade.php`, `resources/views/livewire/goals/goals-page.blade.php`, `tests/Feature/Livewire/GoalsPageTest.php`
      Mudança: meta geral e limite por categoria; avisos de 80% e 100% em Metas e no Início, sem e-mail e sem push.
      Cobre: RF-20
      Acceptance criteria: meta 1.000 com 799,99 não avisa; 800,00 avisa 80%; 1.000,00 avisa 100%.
      Testes: `tests/Feature/Livewire/GoalsPageTest.php` — casos acima
- [ ] T42 — Vencimentos
      Arquivos: `app/Livewire/Due/DuePanel.php`, `resources/views/livewire/due/due-panel.blade.php`, `tests/Feature/Livewire/DuePanelTest.php`
      Mudança: lista não pagos que vencem nos próximos 7 dias.
      Cobre: RF-21
      Acceptance criteria: item vencendo em 7 dias aparece; em 8 dias ou pago não aparece.
      Testes: `tests/Feature/Livewire/DuePanelTest.php` — casos acima
- [ ] T43 — Início (painel)
      Arquivos: `app/Livewire/Home/HomePage.php`, `resources/views/livewire/home/home-page.blade.php`, `tests/Feature/Livewire/HomePageTest.php`
      Mudança: saldo do mês, entradas, saídas, gastos do mês, cartões e pessoas em lista, vale e últimas movimentações; banners de metas e vencimentos.
      Cobre: RF-22
      Acceptance criteria: os 7 itens estão presentes e coerentes com o mês; cartões e pessoas aparecem em lista.
      Testes: `tests/Feature/Livewire/HomePageTest.php` — 7 itens presentes
- [ ] T44 — Painel de gráficos
      Arquivos: `app/Livewire/Charts/ChartsPage.php`, `resources/js/charts.js`, `resources/views/livewire/charts/charts-page.blade.php`, `package.json`, `tests/Feature/Livewire/ChartsPageTest.php`
      Mudança: Chart.js via Alpine com linha, barras, rosca e ranking; períodos 3m, 6m e ano; tabela alternativa acessível.
      Cobre: RF-23, RNF-06
      Acceptance criteria: os 4 conjuntos de dados são entregues à página; trocar o período recalcula todos.
      Testes: `tests/Feature/Livewire/ChartsPageTest.php` — 4 conjuntos e troca de período
- [ ] T45 — Relatório por categoria
      Arquivos: `app/Livewire/Reports/ReportPage.php`, `resources/views/livewire/reports/report-page.blade.php`, `tests/Feature/Livewire/ReportPageTest.php`
      Mudança: total por categoria e comparação com o mês anterior (valor e %).
      Cobre: RF-24
      Acceptance criteria: categoria com 300 no mês e 200 no anterior mostra +50%.
      Testes: `tests/Feature/Livewire/ReportPageTest.php` — 300 vs 200

## Phase 8: Importação de planilha

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T46 — Parser da planilha "Orç - Mês" (CT-01)
      Arquivos: `app/Import/OrcMesImport.php`, `app/Import/ImportPayload.php`, `tests/Fixtures/OrcMesFixture.php`, `tests/Unit/Import/OrcMesImportTest.php`, `composer.json`
      Mudança: instalar `maatwebsite/excel`; ler aba "Orç - Mês"; extrair entradas e saídas (Pago), cartões e linhas, pessoas e Vale em centavos; arquivo inválido lança exceção sem payload.
      Cobre: RF-27, CT-01, RNF-04
      Acceptance criteria: fixture de Setembro resulta em Bradesco 60000, Madrinha +11500, Vale 225000 e fechamento −48500; aba errada é rejeitada; 500 linhas processam em até 10 s.
      Testes: `tests/Unit/Import/OrcMesImportTest.php` — casos acima
- [ ] T47 — ImportService (atômico)
      Arquivos: `app/Services/ImportService.php`, `tests/Feature/Services/ImportServiceTest.php`
      Mudança: grava o payload em transação; recusa mês com dados; categoria "Outros" por padrão; dono padrão é quem importa, trocável por item entre membros do casal.
      Cobre: RF-27, RF-03
      Acceptance criteria: sucesso grava as contagens esperadas; mês com dados é recusado e permanece idêntico; falha no meio reverte tudo; dono fora do casal é recusado.
      Testes: `tests/Feature/Services/ImportServiceTest.php` — casos acima
- [ ] T48 — Tela Importar com prévia
      Arquivos: `app/Livewire/Import/ImportPage.php`, `resources/views/livewire/import/import-page.blade.php`, `tests/Feature/Livewire/ImportPageTest.php`
      Mudança: upload .xlsx com validação de tipo e tamanho, escolha do mês, prévia com totais e troca de dono por item; grava só após confirmação.
      Cobre: RF-27, CT-01
      Acceptance criteria: prévia mostra Bradesco 600,00, Madrinha +115,00, Vale 2.250,00 e fechamento −485,00; sem confirmar nada é gravado; arquivo inválido é rejeitado com mensagem; mês com dados é recusado.
      Testes: `tests/Feature/Livewire/ImportPageTest.php` — casos acima

## Phase 9: PWA, qualidade e entrega

Antes de implementar, leia:
1. `.spec/features/nossa-financas-app-casal/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/nossa-financas-app-casal/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T49 — PWA: manifesto, service worker e leitura offline
      Arquivos: `public/manifest.webmanifest`, `public/sw.js`, `public/icons/*`, `resources/js/pwa.js`, `resources/views/components/layouts/app.blade.php`, `tests/Feature/PwaTest.php`
      Mudança: manifesto instalável; `sw.js` com pré-cache de assets e rede primeiro com cache de reserva para o último mês; offline `wire:offline` desativa escrita e avisa "é necessário internet"; Sair apaga o cache autenticado.
      Cobre: UI-01, RNF-08, RF-28
      Acceptance criteria: manifesto e `sw.js` são servidos e referenciados no layout; offline exibe o último mês sem controles de escrita ativos; Sair limpa o cache de páginas.
      Testes: `tests/Feature/PwaTest.php` — manifesto, `sw.js` e referência no layout
- [ ] T50 — Testes de aceitação críticos
      Arquivos: `tests/Feature/Acceptance/PlanilhaSetembroTest.php`, `tests/Feature/Acceptance/RolesTest.php`, `tests/Feature/Acceptance/QuickEntryTest.php`
      Mudança: caso planilha ponta a ponta; dois usuários e um de fora com leitura cruzada e escrita recusada; lançamento simples em até 3 interações.
      Cobre: RF-02, RF-09, RF-16, RF-30, UI-04
      Acceptance criteria: Madrinha +115, Bradesco 600, Cresol 0, Vale 2.250 e fechamento −485,00; escrita no item do par recusada e item idêntico; lançamento do parceiro A visível ao B após recarregar.
      Testes: `tests/Feature/Acceptance/PlanilhaSetembroTest.php`, `tests/Feature/Acceptance/RolesTest.php`, `tests/Feature/Acceptance/QuickEntryTest.php`
- [ ] T51 — Desempenho e acessibilidade
      Arquivos: `tests/Feature/Perf/MonthPerfTest.php`, `database/seeders/Perf200Seeder.php`, `docs/qa-checklist.md`
      Mudança: seed de 200 lançamentos; asserções de tempo e de consultas; checklist manual de contraste AA, 360 px sem rolagem horizontal, Lighthouse PWA e contagem de toques.
      Cobre: RNF-01, RNF-02, RNF-04, RNF-06, RNF-07, UI-01, UI-04
      Acceptance criteria: recálculo em até 1 s, mês em até 2 s, abertura do mês seguinte em até 2 s e importação de 500 linhas em até 10 s; checklist manual existe e cobre os 4 pontos.
      Testes: `tests/Feature/Perf/MonthPerfTest.php` — limites acima
- [ ] T52 — Deploy e runbook
      Arquivos: `docs/deploy.md`, `.github/workflows/deploy.yml`
      Mudança: documentar hospedagem com PHP e MySQL, HTTPS obrigatório, `php artisan migrate --force`, fila e SMTP para convites, backup do MySQL, rotação de `APP_KEY` e checklist de segredos.
      Cobre: RNF-03, RF-29
      Acceptance criteria: runbook cobre HTTPS, migração, SMTP, backup e segredos fora do repositório; workflow de deploy existe e não contém segredos em texto claro.
      Testes: deploy em staging e `tests/Feature/SmokeTest.php`
