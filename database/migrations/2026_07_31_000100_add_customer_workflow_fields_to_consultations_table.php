<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table): void {
            $table->text('customer_response')->nullable()->after('admin_note');
            $table->text('rejection_reason')->nullable()->after('customer_response');
            $table->dateTime('responded_at')->nullable()->after('rejection_reason');
            $table->index(['source', 'status'], 'consultations_source_status');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table): void {
            $table->dropIndex('consultations_source_status');
            $table->dropColumn(['customer_response', 'rejection_reason', 'responded_at']);
        });
    }
};
