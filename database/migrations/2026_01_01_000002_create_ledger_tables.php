<?php

use App\Support\DbConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['couple_id', 'name']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('closing_day')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->unsignedBigInteger('limit_cents')->nullable();
            $table->timestamps();
        });

        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->string('kind', 10); // entrada | saida
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->string('frequency', 15); // monthly | yearly | installments
            $table->date('starts_on');
            $table->unsignedTinyInteger('charge_day')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('installments_total')->nullable();
            $table->boolean('joint')->default(false);
            $table->timestamps();
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 10); // entrada | saida
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->date('month'); // sempre o dia 1
            $table->date('due_on')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->boolean('paid')->default(false);
            $table->boolean('joint')->default(false);
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('recurrences')->cascadeOnDelete();
            $table->string('installment_label', 10)->nullable();
            $table->timestamps();
            $table->softDeletes(); // "só este mês": a ocorrência apagada não volta a ser criada

            $table->unique(['recurrence_id', 'month']);
            $table->index(['couple_id', 'month']);
            $table->index(['owner_id', 'month']);
        });

        DbConstraints::positive('entries', 'amount_cents');
        DbConstraints::positive('recurrences', 'amount_cents');
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
        Schema::dropIfExists('recurrences');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('categories');
    }
};
