<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias passam a ter tipo: de saída (gastos), de entrada (ganhos) ou ambas ("Outros").
 * Os casais que já existem ganham as categorias de entrada (Salário, Renda extra, Reembolso, Rendimentos).
 */
return new class extends Migration
{
    private const INCOME = ['Salário', 'Renda extra', 'Reembolso', 'Rendimentos'];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('kind', 10)->default('saida')->after('name'); // saida | entrada | ambas
        });

        DB::table('categories')->where('name', 'Outros')->update(['kind' => 'ambas']);

        $now = now();
        foreach (DB::table('couples')->pluck('id') as $coupleId) {
            $position = (int) DB::table('categories')->where('couple_id', $coupleId)->max('position');

            foreach (self::INCOME as $i => $name) {
                if (DB::table('categories')->where('couple_id', $coupleId)->where('name', $name)->exists()) {
                    DB::table('categories')->where('couple_id', $coupleId)->where('name', $name)->update(['kind' => 'entrada']);

                    continue;
                }

                DB::table('categories')->insert([
                    'couple_id' => $coupleId, 'name' => $name, 'kind' => 'entrada', 'position' => $position + 1 + $i,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('categories')->where('kind', 'entrada')->whereNotExists(
            fn ($q) => $q->select(DB::raw(1))->from('entries')->whereColumn('entries.category_id', 'categories.id'),
        )->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
