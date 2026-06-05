<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_document_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('dashboard');
            $table->string('status', 30)->default('running');
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('synced')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('sales_document_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_sync_run_id')->constrained('sales_document_sync_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('ecommerce_sync_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('sync_status', 40)->nullable();
            $table->string('folio', 80)->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('clave_cliente', 120)->nullable();
            $table->string('cliente_id', 120)->nullable();
            $table->unsignedInteger('details_count')->default(0);
            $table->string('status', 30)->default('running');
            $table->string('error_stage', 40)->nullable();
            $table->text('error')->nullable();
            $table->json('validation_errors')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'created_at']);
            $table->index(['status', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_sync_items');
        Schema::dropIfExists('sales_document_sync_runs');
    }
};
