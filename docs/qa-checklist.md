# Checklist de QA manual — Nossa Finanças

Itens que os testes automáticos não conseguem conferir sozinhos. Marque no celular **e** no PC.

## 1. Contraste (WCAG 2.1 AA)
- [ ] Textos normais com contraste mínimo de 4,5:1 (use a extensão "WAVE" ou o DevTools > Accessibility).
- [ ] Textos pequenos em cinza (`text-muted`, #5A6B64 sobre #F3F6F4) continuam legíveis.
- [ ] Texto branco sobre o verde do topo (Início, Vale) legível.
- [ ] O saldo negativo mostra **sinal "−" e rótulo**, não só a cor.

## 2. Largura de 360 px sem rolagem horizontal
- [ ] Início, Extrato, Lançar, Cartões, Pessoas, Vale, Recorrentes, Acerto, Metas, Gráficos, Relatório, Categorias, Importar e Mais em 360 px: sem barra de rolagem lateral.
- [ ] Barra de baixo sempre visível e sem cobrir o último item da lista.
- [ ] No PC (1280 px) aparece o menu lateral e a barra de baixo some.

## 3. App instalável (PWA)
- [ ] Lighthouse > Progressive Web App: marca "instalável".
- [ ] No Android/Chrome aparece "Instalar app"; no iPhone, "Adicionar à Tela de Início".
- [ ] Sem internet: o último mês aberto aparece; o aviso "é necessário internet para lançar" aparece e os botões de gravar ficam desativados.
- [ ] Depois de **Sair**, abrir sem internet **não** mostra os dados do casal.

## 4. Quantidade de toques para lançar
- [ ] Saída simples pela frase rápida ("50 mercado"): digitar + tocar em **+** = 2 interações.
- [ ] Pelo botão **+**: valor, categoria, nome e **Salvar**; confira que dá para salvar sem abrir telas extras.

## 5. Dois logins
- [ ] Eliel cria a conta, convida a Ana por e-mail; o link só funciona uma vez e expira em 7 dias.
- [ ] Cada um vê o que o outro lançou (só leitura) e só altera o que é seu.
- [ ] O seletor **Nós · Eliel · Ana** troca o recorte em Início, Extrato, Cartões, Pessoas, Vale e Gráficos.

## 6. Importar a planilha real
- [ ] Planejamento_Ana_Claudia.xlsx (aba "Orç - Mês") → prévia com Bradesco R$ 600,00, Madrinha +R$ 115,00, Vale R$ 2.250,00 e fechamento −R$ 485,00.
- [ ] Importar de novo no mesmo mês é recusado.
