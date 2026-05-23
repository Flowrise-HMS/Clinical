<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->index();
            $table->text('description');
            $table->string('category')->nullable();
            $table->boolean('nhis_covered')->default(false);
            $table->string('source', 20)->default('who');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_codes');
    }
};
