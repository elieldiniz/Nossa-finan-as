<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            // Identificador da transação no extrato do banco (FITID do OFX): impede importar a mesma compra duas vezes
            $table->string('external_id', 80)->nullable()->after('installment_label');
            $table->index(['couple_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex(['couple_id', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
