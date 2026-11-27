<?php

namespace App\Livewire\Settings;

use App\Support\Theme;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

/** Configurações > Perfil: a pessoa escolhe a cor do app. Vale só para a conta dela (o parceiro tem a própria). */
class ThemeColor extends Component
{
    public string $color = Theme::DEFAULT;

    public function mount(): void
    {
        $this->color = Theme::key(auth()->user());
    }

    public function choose(string $key): mixed
    {
        if (! Theme::valid($key)) {
            $this->addError('color', 'Cor inválida.');

            return null;
        }

        auth()->user()->forceFill(['theme_color' => $key])->save();
        // lembra no navegador para a tela de login também sair com a cor escolhida
        Cookie::queue(Cookie::make('nf_theme', $key, 60 * 24 * 365));

        // recarrega a página inteira para todas as telas, gráficos e a barra do celular já virem na cor nova
        return $this->redirectRoute('settings.profile');
    }

    public function render()
    {
        return view('livewire.settings.theme-color', ['options' => Theme::PALETTES]);
    }
}
