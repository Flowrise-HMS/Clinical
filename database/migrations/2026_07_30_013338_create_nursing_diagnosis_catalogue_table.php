<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_diagnosis_catalogue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->string('label');
            $table->text('definition');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_diagnosis_catalogue');
    }
};
