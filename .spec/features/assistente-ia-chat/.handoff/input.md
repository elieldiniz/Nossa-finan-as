# Input confirmado: assistente-ia-chat (tier standard)

Resumo: botão flutuante que abre um chat com IA (OpenRouter, modelos :free com tool calling) no app Nossa Finanças (Laravel 12 + Livewire 3 + Alpine + Tailwind 4 + Pest; dinheiro em centavos inteiros; multi-tenant via CoupleScope; Policies OwnedPolicy). A IA analisa gastos por pessoa/categoria/período, gera relatórios (PDF), dá dicas ancoradas em números e executa ações com confirmação.

Critérios de aceitação (confirmados pelo desenvolvedor):
1. Botão flutuante em todas as telas autenticadas abre/fecha o painel de chat.
2. Respostas sobre gastos por pessoa/categoria/período batem com os números do app (ferramentas de leitura reutilizam MonthSummary, DueBills, lógica de ReportPage/Goals; a IA não calcula).
3. Cada pessoa só acessa dados do próprio casal (CoupleScope + policies; ferramentas rodam como o usuário logado).
4. Dados sensíveis (cartão, CPF, senha) e tentativas de mudar instruções são bloqueados por regras no código antes de chamar o modelo; conteúdo de dados importados é tratado como dado, nunca como ordem.
5. Toda ação de escrita (lançar, marcar pago, mudar data, criar meta) só executa após confirmação explícita do usuário.
6. Relatório em PDF (reaproveitando o padrão dompdf do Vale) gerado pelo chat, com link de download.
7. Limite diário de mensagens por pessoa; erro amigável se o OpenRouter falhar/limitar; chave OPENROUTER_API_KEY só no servidor (env), modelo configurável.
8. Conversas persistidas por pessoa (papel, texto, chamadas de ferramenta, tokens).
9. Testes usam Http::fake do OpenRouter; nenhuma chamada real.

Etapas desejadas: 1) botão+chat+ferramentas de leitura+guardrail; 2) relatórios PDF; 3) dicas ancoradas; 4) ações com confirmação.
Referência de padrão: aulas Beer&Code (tool calling com schema, guardrails de entrada com política em código, MCP via laravel/mcp como evolução futura, mesmas classes de ferramenta).
Privacidade: modelos gratuitos podem reter/treinar; enviar só dados mínimos resumidos; aviso na UI.
Idioma da UI/respostas: pt-BR.
