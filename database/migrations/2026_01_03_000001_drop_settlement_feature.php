<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tela "Acerto do casal" foi retirada do app: remove a tabela e a divisão que só ela usava.
 * (As colunas "joint" dos lançamentos ficam, sem uso, para não mexer em dados.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('settlements');

        Schema::table('couples', function (Blueprint $table) {
            $table->dropColumn('split_first_percent');
        });
    }

    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->unsignedTinyInteger('split_first_percent')->default(50);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at');
            $table->timestamps();
        });
    }
};
