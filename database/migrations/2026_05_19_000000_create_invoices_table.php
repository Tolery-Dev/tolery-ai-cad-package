<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('file_purchase_id')->nullable()->constrained('file_purchases')->nullOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('number')->nullable();
            $table->string('status')->nullable();
            $table->integer('subtotal')->default(0); // montant HT en centimes
            $table->integer('tax')->default(0);      // TVA en centimes
            $table->integer('total')->default(0);    // montant TTC en centimes
            $table->integer('amount_paid')->default(0);
            $table->string('currency', 3)->default('eur');
            $table->string('hosted_invoice_url')->nullable();
            $table->string('invoice_pdf')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('stripe_subscription_id');
            $table->index('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
