# Respostas do desenvolvedor (resolve) — assistente-ia-chat

## Decididas pelo desenvolvedor
- Q-01 (limite diário): NÃO haver teto diário fixo por pessoa. Em vez disso, detectar insistência/perguntas repetidas (mesma pergunta ou quase igual, várias vezes seguidas, sem avanço); nesse caso o assistente responde que não conseguiu resolver e orienta a contatar o atendente por WhatsApp (link wa.me com número configurável por env, ex.: ASSISTANT_SUPPORT_WHATSAPP). Fora isso, sem teto. O provedor será trocado depois: encapsular o cliente do modelo atrás de uma interface (provedor substituível; OpenRouter é a primeira implementação). Quando o provedor responder 429/limite do serviço gratuito, mostrar mensagem específica + o mesmo link do WhatsApp.
- Q-04 (histórico): a conversa SEMPRE começa vazia ao abrir o painel (não recarrega a anterior). As mensagens continuam sendo gravadas por pessoa apenas para auditoria e medir tokens, visíveis só ao dono; sem botão de recarregar. Sem tela de histórico. Retenção para auditoria: 90 dias com limpeza agendada (padrão).
- Q-06 (escrita): as 4 ações entram (lançar, marcar pago, mudar data, criar meta). Proposta com valores formatados em R$ gerados pelo código, expira em 10 min, só o dono confirma por clique em botão Confirmar (UI, não passa pelo modelo); digitar "sim" não confirma. Ação em nome do outro cônjuge: não permitida (só em nome de si).
- Q-08 (privacidade): nunca enviar descrição bruta de lançamentos ao modelo; só categoria, total, mês e rótulo da pessoa. RF-06 vira defesa em profundidade (qualquer texto livre devolvido por ferramenta é marcado como dado). Padrões bloqueados do RF-05 em pt-BR ficam em config. Limite de tamanho da mensagem de entrada: 500 caracteres.

## Padrões sugeridos adotados (desenvolvedor não objetou)
- Q-02: laço de ferramentas no máximo 3 iterações (configurável); ao atingir, resposta amigável com o que já foi obtido, sem nova chamada.
- Q-03: falhas (429, 5xx, timeout) não consomem nenhuma cota local (não há cota local); sem retry automático.
- Q-05: envs OPENROUTER_API_KEY, OPENROUTER_MODEL, OPENROUTER_TIMEOUT, ASSISTANT_MAX_TOOL_ITERATIONS, ASSISTANT_SUPPORT_WHATSAPP; arquivo config/assistant.php e entradas no .env.example. Modelo padrão google/gemma-4-31b-it:free (suporta tools; confirmado no catálogo), configurável. Chave ausente: o chat mostra "assistente indisponível" sem chamada HTTP.
- Q-07: timeout 25 s por chamada e teto total de 60 s por mensagem; mensagem amigável ao estourar; sem retry.
- Marcador de arquitetura ausente: aceito e registrado como resolvido por inferência do código existente.
- CT-03: download em /assistente/relatorios/{id}, autenticado, expira em 7 dias; relatório pode cobrir o casal.
- RNF-03: Http::preventStrayRequests() e Http::assertNothingSent() são compatíveis.
