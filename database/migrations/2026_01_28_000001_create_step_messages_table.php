<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_messages', function (Blueprint $table) {
            $table->id();
            $table->string('step_key')->unique(); // analysis, parameters, generation_code, export, complete
            $table->string('label'); // Display label for admin
            $table->json('messages'); // Array of messages
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_messages');
    }
};
