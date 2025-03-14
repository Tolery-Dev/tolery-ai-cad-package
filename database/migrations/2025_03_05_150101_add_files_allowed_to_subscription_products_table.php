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
            //
            $table->tinyInteger('files_allowed')->nullable()->default(10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_products', function (Blueprint $table) {
            //
            $table->dropColumn('files_allowed');
        });
    }
};
