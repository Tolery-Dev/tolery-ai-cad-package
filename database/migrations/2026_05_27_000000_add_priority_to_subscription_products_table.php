<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_products', function (Blueprint $table) {
            // Generation priority (0-100) used to prioritize CAD generation by plan.
            // Higher = higher priority. Null = no explicit priority (paid-tier floor).
            $table->unsignedTinyInteger('priority')->nullable()->after('files_allowed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_products', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
