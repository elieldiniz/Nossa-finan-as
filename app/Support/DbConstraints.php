<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Garante no BANCO (MySQL e SQLite) que um valor em centavos seja positivo (ou não negativo).
 * MySQL usa CHECK; SQLite não permite ALTER, então usa gatilhos que abortam a gravação.
 */
final class DbConstraints
{
    public static function positive(string $table, string $column): void
    {
        self::apply($table, $column, '>', 0, 'maior que zero');
    }

    public static function nonNegative(string $table, string $column): void
    {
        self::apply($table, $column, '>=', 0, 'zero ou mais');
    }

    private static function apply(string $table, string $column, string $op, int $limit, string $text): void
    {
        $name = "chk_{$table}_{$column}";

        if (DB::getDriverName() === 'sqlite') {
            $message = "{$table}.{$column} deve ser {$text}";
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER {$name}_".strtolower($event)." BEFORE {$event} ON {$table} FOR EACH ROW WHEN NOT (NEW.{$column} {$op} {$limit}) BEGIN SELECT RAISE(ABORT, '{$message}'); END");
            }

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$column} {$op} {$limit})");
    }
}
