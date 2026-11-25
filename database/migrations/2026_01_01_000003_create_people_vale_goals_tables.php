<?php

use App\Support\DbConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('person_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('direction', 10); // receber | pagar
            $table->string('description')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->date('created_month');
            $table->date('settled_month')->nullable(); // mês em que foi quitada (sem parcelas)
            $table->timestamps();
        });

        Schema::create('person_debt_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_debt_id')->constrained('person_debts')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_month')->nullable();
            $table->timestamps();
        });

        Schema::create('vales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->timestamps();
            $table->unique('owner_id');
        });

        Schema::create('vale_spends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vale_id')->constrained('vales')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->date('month');
            $table->timestamps();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete(); // null = meta geral
            $table->unsignedBigInteger('limit_cents');
            $table->timestamps();
            $table->unique(['couple_id', 'category_id']);
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

        Schema::create('card_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->date('month');
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['card_id', 'month']);
        });

        foreach (['person_debts', 'person_debt_installments', 'vale_spends'] as $t) {
            DbConstraints::positive($t, 'amount_cents');
        }
        DbConstraints::nonNegative('vales', 'amount_cents');
        DbConstraints::positive('goals', 'limit_cents');
        DbConstraints::nonNegative('settlements', 'amount_cents');
    }

    public function down(): void
    {
        foreach (['card_invoices', 'settlements', 'goals', 'vale_spends', 'vales', 'person_debt_installments', 'person_debts', 'people'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
