<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('system');
            $table->string('status', 30)->default('running');
            $table->unsignedInteger('pending_selected')->default(0);
            $table->unsignedInteger('items_synced')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->unsignedInteger('rows_marked_as_synced')->default(0);
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source', 'created_at']);
        });

        Schema::create('sync_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->constrained('sync_runs')->cascadeOnDelete();
            $table->string('tipo', 80)->nullable();
            $table->string('entity_id', 120)->nullable();
            $table->string('endpoint', 180)->nullable();
            $table->string('payload_key', 80)->nullable();
            $table->string('db_key', 255)->nullable();
            $table->string('status', 30)->default('running');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error')->nullable();
            $table->json('ecommerce_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_run_items');
        Schema::dropIfExists('sync_runs');
    }
};
