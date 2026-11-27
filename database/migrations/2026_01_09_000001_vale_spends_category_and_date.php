<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vale_spends', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('categories')->nullOnDelete();
            $table->date('spent_on')->nullable()->after('month'); // dia do gasto (aparece no extrato exportado)
        });

        // a categoria "Vale" passa a se chamar "Vale alimentação" (onde não houver uma com esse nome)
        $ids = DB::table('categories')->where('name', 'Vale')->pluck('id', 'couple_id');
        foreach ($ids as $coupleId => $id) {
            if (! DB::table('categories')->where('couple_id', $coupleId)->where('name', 'Vale alimentação')->exists()) {
                DB::table('categories')->where('id', $id)->update(['name' => 'Vale alimentação']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('vale_spends', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('spent_on');
        });
    }
};
