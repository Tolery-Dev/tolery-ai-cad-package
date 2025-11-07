<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_products', function (Blueprint $table) {
            $table->integer('price')->nullable()->change();
            $table->string('stripe_price_id')->nullable()->change();
            $table->string('frequency')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_products', function (Blueprint $table) {
            $table->integer('price')->nullable(false)->change();
        });
    }
};
