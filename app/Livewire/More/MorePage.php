<?php

namespace App\Livewire\More;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mais · Nossa Finanças')]
class MorePage extends Component
{
    /** Os 9 itens de "Mais" (UI-03). */
    public const ITEMS = [
        ['people', 'Pessoas', 'people'],
        ['vale', 'Vale', 'vale'],
        ['recurring', 'Recorrentes e assinaturas', 'repeat'],
        ['settlement', 'Acerto do casal', 'swap'],
        ['goals', 'Metas e vencimentos', 'target'],
        ['report', 'Relatório', 'pie'],
        ['import', 'Importar planilha', 'upload'],
        ['categories', 'Categorias', 'tag'],
        ['invite', 'Convidar meu par', 'invite'],
    ];

    public function render()
    {
        return view('livewire.more.more-page', ['items' => self::ITEMS]);
    }
}
