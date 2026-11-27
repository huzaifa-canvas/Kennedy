<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pay frequency options become: one_time, weekly, biweekly, monthly.
 * "semimonthly" and "varies" are dropped. users.pay_frequency mirrors the
 * primary income source, so both columns change together.
 */
return new class extends Migration
{
    private const OLD = ['weekly', 'biweekly', 'semimonthly', 'monthly', 'varies'];

    private const NEW = ['one_time', 'weekly', 'biweekly', 'monthly'];

    /** Existing rows on a removed value move to the closest new one. */
    private const UP_MAP = ['semimonthly' => 'biweekly', 'varies' => 'one_time'];

    private const DOWN_MAP = ['one_time' => 'varies'];

    public function up(): void
    {
        $this->migrate(self::NEW, self::UP_MAP);
    }

    public function down(): void
    {
        $this->migrate(self::OLD, self::DOWN_MAP);
    }

    private function migrate(array $target, array $map): void
    {
        $all = array_values(array_unique([...self::OLD, ...self::NEW]));

        foreach (['income_sources' => false, 'users' => true] as $table => $nullable) {
            // Widen first so the old and new values can both be stored while rows are moved.
            Schema::table($table, fn ($t) => $this->column($t, $all, $nullable));

            foreach ($map as $from => $to) {
                DB::table($table)->where('pay_frequency', $from)->update(['pay_frequency' => $to]);
            }

            Schema::table($table, fn ($t) => $this->column($t, $target, $nullable));
        }
    }

    private function column($table, array $values, bool $nullable): void
    {
        $column = $table->enum('pay_frequency', $values);

        if ($nullable) {
            $column->nullable();
        } else {
            $column->default('biweekly');
        }

        $column->change();
    }
};
