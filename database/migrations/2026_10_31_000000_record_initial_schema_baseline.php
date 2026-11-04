<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The complete clean-install schema is loaded from the Laravel schema dump.
    }

    public function down(): void
    {
        throw new LogicException('The initial database schema cannot be rolled back. Restore a database backup instead.');
    }
};
