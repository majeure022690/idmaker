<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->string('unit', 4)->default('mm');
            $table->boolean('has_back')->default(false);
            $table->json('front_design');
            $table->json('back_design')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
