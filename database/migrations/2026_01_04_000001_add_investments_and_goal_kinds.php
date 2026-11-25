<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Investimento (lançamento do tipo "aporte"): novas categorias, metas por tipo (gasto | investimento)
 * e correção de dados: entrada ou aporte nunca ficam ligados a cartão (eles sumiam dos totais).
 */
return new class extends Migration
{
    private const INVESTMENT_CATEGORIES = ['Reserva de emergência', 'Renda fixa', 'Ações e FIIs', 'Previdência', 'Outros investimentos'];

    public function up(): void
    {
        // metas: gasto (padrão) ou investimento
        Schema::table('goals', function (Blueprint $table) {
            $table->string('kind', 12)->default('gasto')->after('category_id');
            $table->unique(['couple_id', 'kind', 'category_id'], 'goals_couple_kind_category_unique');
        });
        Schema::table('goals', function (Blueprint $table) {
            $table->dropUnique(['couple_id', 'category_id']);
        });

        // categorias de investimento para os casais que já existem
        $now = now();
        foreach (DB::table('couples')->pluck('id') as $coupleId) {
            $position = (int) DB::table('categories')->where('couple_id', $coupleId)->max('position');

            foreach (self::INVESTMENT_CATEGORIES as $i => $name) {
                $exists = DB::table('categories')->where('couple_id', $coupleId)->where('name', $name)->exists();
                if ($exists) {
                    DB::table('categories')->where('couple_id', $coupleId)->where('name', $name)->update(['kind' => 'aporte']);

                    continue;
                }

                DB::table('categories')->insert([
                    'couple_id' => $coupleId, 'name' => $name, 'kind' => 'aporte', 'position' => $position + 1 + $i,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // entradas (e aportes) ligadas a cartão voltam a aparecer nos totais e no Extrato
        DB::table('entries')->where('kind', '!=', 'saida')->whereNotNull('card_id')->update(['card_id' => null]);
        DB::table('recurrences')->where('kind', '!=', 'saida')->whereNotNull('card_id')->update(['card_id' => null]);
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->unique(['couple_id', 'category_id']);
        });
        Schema::table('goals', function (Blueprint $table) {
            $table->dropUnique('goals_couple_kind_category_unique');
            $table->dropColumn('kind');
        });
    }
};
