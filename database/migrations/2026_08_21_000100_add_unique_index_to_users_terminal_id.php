<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->guardAgainstDuplicates();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['terminal_id']);
            $table->unique('terminal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['terminal_id']);
            $table->index('terminal_id');
        });
    }

    /**
     * Fail with a readable message instead of a raw driver error when existing
     * rows already share a terminal id.
     */
    private function guardAgainstDuplicates(): void
    {
        $duplicates = DB::table('users')
            ->select('terminal_id')
            ->whereNotNull('terminal_id')
            ->groupBy('terminal_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('terminal_id')
            ->all();

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Cannot add a unique index: these terminal ids are used by more than one user: '
                .implode(', ', $duplicates).'. Resolve the duplicates, then re-run this migration.'
            );
        }
    }
};
