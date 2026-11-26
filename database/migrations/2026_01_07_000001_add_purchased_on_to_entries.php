<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            // Dia em que a compra foi feita (vem do extrato do banco); o lançamento continua pertencendo a um mês
            $table->date('purchased_on')->nullable()->after('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn('purchased_on');
        });
    }
};
