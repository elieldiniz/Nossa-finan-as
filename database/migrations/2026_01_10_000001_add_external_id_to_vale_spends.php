<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vale_spends', function (Blueprint $table) {
            // identificador do lançamento no extrato importado: impede trazer o mesmo gasto duas vezes
            $table->string('external_id', 80)->nullable()->after('spent_on');
            $table->index(['couple_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vale_spends', function (Blueprint $table) {
            $table->dropIndex(['couple_id', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
