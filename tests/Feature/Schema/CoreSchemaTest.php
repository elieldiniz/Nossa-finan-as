<?php

use Illuminate\Support\Facades\Schema;

it('cria as tabelas e colunas do casal', function () {
    foreach (['couples', 'invitations', 'categories', 'cards', 'recurrences', 'entries', 'people',
        'person_debts', 'person_debt_installments', 'vales', 'vale_spends', 'goals', 'card_invoices'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabela {$table}");
    }

    expect(Schema::hasColumn('users', 'couple_id'))->toBeTrue()
        ->and(Schema::hasColumns('invitations', ['email', 'token_hash', 'expires_at', 'accepted_at']))->toBeTrue()
        ->and(Schema::hasColumns('entries', ['amount_cents', 'month', 'category_id', 'owner_id', 'paid', 'joint', 'paid_by', 'recurrence_id']))->toBeTrue()
        ->and(Schema::hasColumns('recurrences', ['ends_on', 'installments_total', 'frequency']))->toBeTrue();
});
