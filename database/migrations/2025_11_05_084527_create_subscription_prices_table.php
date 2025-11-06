<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_product_id')
                ->constrained('subscription_products')
                ->cascadeOnDelete();
            $table->string('stripe_price_id')->unique();
            $table->integer('amount');
            $table->string('currency', 3)->default('eur');
            $table->string('interval');
            $table->boolean('active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['subscription_product_id', 'active']);
            $table->index('stripe_price_id');
        });
    }
};
