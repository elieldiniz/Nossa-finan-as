<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Somar o vale alimentação nas Entradas/Saídas do saldo (cada pessoa escolhe; o vale não é dinheiro livre)
            $table->boolean('vale_in_balance')->default(true)->after('theme_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vale_in_balance');
        });
    }
};
