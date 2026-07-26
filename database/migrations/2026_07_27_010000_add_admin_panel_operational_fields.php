<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('institution')->nullable()->after('phone');
            $table->string('study_program')->nullable()->after('institution');
        });

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->text('internal_note')->nullable()->after('description');
        });

        Schema::table('revisions', function (Blueprint $table): void {
            $table->text('internal_note')->nullable()->after('admin_response');
        });

        Schema::table('reminders', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_customer_visible')->default(true)->after('is_completed');
            $table->index(['project_id', 'is_customer_visible', 'reminder_date'], 'reminders_project_visibility_date');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->text('internal_note')->nullable()->after('notes');
        });

        Schema::table('portfolios', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->after('is_published');
            $table->index(['is_published', 'is_demo'], 'portfolios_published_demo');
        });

        DB::table('projects')->where('payment_status', 'unpaid')->update(['payment_status' => 'belum_dibayar']);
        DB::table('projects')->where('payment_status', 'down_payment')->update(['payment_status' => 'dp']);
        DB::table('projects')->where('payment_status', 'paid')->update(['payment_status' => 'lunas']);

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('payment_status', 30)->default('belum_dibayar')->change();
        });
    }

    public function down(): void
    {
        DB::table('projects')->where('payment_status', 'belum_dibayar')->update(['payment_status' => 'unpaid']);
        DB::table('projects')->where('payment_status', 'dp')->update(['payment_status' => 'down_payment']);
        DB::table('projects')->where('payment_status', 'lunas')->update(['payment_status' => 'paid']);

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('payment_status', 30)->default('unpaid')->change();
        });

        Schema::table('portfolios', function (Blueprint $table): void {
            $table->dropIndex('portfolios_published_demo');
            $table->dropColumn('is_demo');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('internal_note');
        });

        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropIndex('reminders_project_visibility_date');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('is_customer_visible');
        });

        Schema::table('revisions', function (Blueprint $table): void {
            $table->dropColumn('internal_note');
        });

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->dropColumn('internal_note');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['institution', 'study_program']);
        });
    }
};
