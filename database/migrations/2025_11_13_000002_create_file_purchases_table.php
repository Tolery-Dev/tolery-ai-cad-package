<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
            $table->string('stripe_payment_intent_id')->unique();
            $table->integer('amount'); // montant en centimes
            $table->string('currency')->default('eur');
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->index(['team_id', 'chat_id']);
            $table->index('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_purchases');
    }
};
