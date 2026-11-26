<?php

namespace App\Livewire\More;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mais · Nossa Finanças')]
class MorePage extends Component
{
    /** Os 8 itens de "Mais" (UI-03). */
    public const ITEMS = [
        ['people', 'Pessoas', 'people'],
        ['vale', 'Vale', 'vale'],
        ['recurring', 'Recorrentes e assinaturas', 'repeat'],
        ['goals', 'Metas e investimentos', 'target'],
        ['report', 'Relatório', 'pie'],
        ['import', 'Importar planilha', 'upload'],
        ['import.ofx', 'Importar fatura do cartão', 'card'],
        ['categories', 'Categorias', 'tag'],
        ['invite', 'Convidar meu par', 'invite'],
    ];

    public function render()
    {
        return view('livewire.more.more-page', ['items' => self::ITEMS]);
    }
}
