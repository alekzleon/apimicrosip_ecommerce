<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_run_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sync_run_items', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('ecommerce_response');
                $table->index(['status', 'resolved_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sync_run_items', function (Blueprint $table) {
            if (Schema::hasColumn('sync_run_items', 'resolved_at')) {
                $table->dropIndex(['status', 'resolved_at']);
                $table->dropColumn('resolved_at');
            }
        });
    }
};
