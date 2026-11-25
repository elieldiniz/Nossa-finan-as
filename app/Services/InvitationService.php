<?php

namespace App\Services;

use App\Mail\PartnerInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Convite do par (RF-29): link assinado, de uso único, que expira. O casal tem no máximo 2 membros.
 */
class InvitationService
{
    public const MAX_MEMBERS = 2;

    public const VALID_DAYS = 7;

    public function invite(User $inviter, string $email): Invitation
    {
        $email = Str::lower(trim($email));

        if ($inviter->couple_id === null) {
            throw new InvalidArgumentException('Você ainda não faz parte de um casal.');
        }

        if (User::where('couple_id', $inviter->couple_id)->count() >= self::MAX_MEMBERS) {
            throw new InvalidArgumentException('O casal já tem os dois membros.');
        }

        if (User::where('email', $email)->exists()) {
            throw new InvalidArgumentException('Este e-mail já tem uma conta.');
        }

        $token = Str::random(40);

        $invitation = new Invitation(['email' => $email, 'expires_at' => now()->addDays(self::VALID_DAYS)]);
        $invitation->couple_id = $inviter->couple_id;
        $invitation->invited_by = $inviter->id;
        $invitation->token_hash = hash('sha256', $token);
        $invitation->save();

        $url = URL::temporarySignedRoute('invitation.accept', $invitation->expires_at, ['token' => $token]);
        Mail::to($email)->send(new PartnerInvitation($inviter->name, $url));

        return $invitation;
    }

    public function find(string $token): ?Invitation
    {
        return Invitation::where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * Aceita o convite: cria o segundo usuário no mesmo casal. Link usado, expirado ou casal cheio é recusado.
     *
     * @param  array{name: string, password: string}  $data
     */
    public function accept(string $token, array $data): User
    {
        return DB::transaction(function () use ($token, $data) {
            $invitation = $this->find($token);

            if ($invitation === null || ! $invitation->isUsable()) {
                throw new InvalidArgumentException('Este convite não é mais válido.');
            }

            if (User::where('couple_id', $invitation->couple_id)->count() >= self::MAX_MEMBERS) {
                throw new InvalidArgumentException('O casal já tem os dois membros.');
            }

            $user = new User(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
            $user->couple_id = $invitation->couple_id;
            $user->email_verified_at = now();
            $user->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
