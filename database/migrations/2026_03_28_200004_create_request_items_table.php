<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_request_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('service_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('service_variant_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->foreignId('fulfilled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_request_id']);
            $table->index(['service_id']);
            $table->index(['status']);
            $table->index(['fulfilled_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
