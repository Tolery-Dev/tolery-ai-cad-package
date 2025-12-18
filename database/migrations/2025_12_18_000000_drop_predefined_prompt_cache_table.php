<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('predefined_prompt_caches');
    }

    public function down(): void
    {
        // Table is no longer used, no need to recreate
    }
};
