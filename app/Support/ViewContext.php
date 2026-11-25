<?php

namespace App\Support;

use App\Models\User;

/**
 * Qual recorte o usuário está vendo (RF-26): "nos" (casal), "eu" (os dados dele) ou "par" (os do outro, só leitura).
 */
final class ViewContext
{
    public const NOS = 'nos';

    public const EU = 'eu';

    public const PAR = 'par';

    public function __construct(public readonly string $view, public readonly ?int $ownerId) {}

    public static function for(User $viewer, string $view = self::NOS): self
    {
        return match ($view) {
            self::EU => new self(self::EU, $viewer->id),
            self::PAR => new self(self::PAR, $viewer->partner()?->id),
            default => new self(self::NOS, null),
        };
    }

    public function isReadOnly(): bool
    {
        return $this->view === self::PAR;
    }

    public function applies(int $ownerId): bool
    {
        return $this->ownerId === null || $this->ownerId === $ownerId;
    }
}
