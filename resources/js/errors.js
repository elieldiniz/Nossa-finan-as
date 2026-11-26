// Toda falha de uma ação (tocar em Pago, Salvar, Excluir...) vira um aviso em português dizendo o que houve.
// Sem isso o Livewire mostra uma janela com o HTML do erro ou nada acontece e a pessoa não sabe o motivo.
const MENSAGENS = {
    0: 'Sem conexão com a internet. O que você fez NÃO foi salvo. Confira a conexão e tente de novo.',
    401: 'Sua sessão terminou. Entre de novo para continuar.',
    403: 'Você não pode alterar isso: só quem lançou pode mudar. Você consegue apenas ver.',
    404: 'Esse item não existe mais (talvez tenha sido apagado). Atualize a tela.',
    408: 'A conexão demorou demais. Confira a internet e tente de novo; veja no Extrato se já foi salvo antes de repetir.',
    413: 'O arquivo é grande demais.',
    419: 'Sua sessão expirou. Recarregue a página e refaça a ação. Nada do que já estava salvo foi perdido.',
    422: 'Algum campo está inválido. Confira o que foi preenchido.',
    429: 'Muitos pedidos em pouco tempo. Espere um minuto e tente de novo.',
    500: 'Algo deu errado do nosso lado e a ação pode não ter sido salva. Confira no Extrato antes de repetir, para não duplicar.',
    503: 'O app está em manutenção. Tente de novo em alguns minutos.',
};

function mostrar(status) {
    const texto = MENSAGENS[status] ?? (status >= 500 ? MENSAGENS[500] : `Não foi possível concluir (erro ${status}). Tente de novo.`);
    window.dispatchEvent(new CustomEvent('nf-toast', { detail: { text: texto, status, reload: status === 419 || status === 401 } }));
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, content, preventDefault }) => {
            preventDefault();
            // sem resposta do servidor (internet caiu) o Livewire informa 503 sem conteúdo: não é manutenção
            mostrar(! navigator.onLine || ! content ? 0 : status);
        });
    });
});
