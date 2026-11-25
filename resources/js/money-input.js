/**
 * Campo de dinheiro: ao sair do campo mostra no formato brasileiro ("3000" vira "R$ 3.000,00").
 * O servidor aceita os dois jeitos (App\Support\Money::parse), então nada muda no que é gravado.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('moneyInput', () => ({
        format(el) {
            const text = el.value.replace(/R\$|\s/g, '');
            if (text === '' || !/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+(,\d{1,2})?$/.test(text)) {
                return; // vazio ou inválido: deixa como está para o servidor avisar
            }
            const [reais, centavos = ''] = text.replace(/\./g, '').split(',');
            const value = Number(reais) + Number(centavos.padEnd(2, '0')) / 100;
            el.value = 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            el.dispatchEvent(new Event('input', { bubbles: true })); // avisa o Livewire do novo texto
        },
    }));
});
