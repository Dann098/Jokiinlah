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
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('two_factor_recovery_codes_viewed_at')->nullable()->after('two_factor_confirmed_at');
        });

        foreach (['consultations', 'projects', 'project_files', 'revisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('purge_status', 30)->default('eligible')->after('retention_until');
                $table->dateTime('purge_pending_at')->nullable()->after('purge_status');
                $table->dateTime('physical_deleted_at')->nullable()->after('purge_pending_at');
                $table->dateTime('purged_at')->nullable()->after('physical_deleted_at');
                $table->string('purge_failure_code', 80)->nullable()->after('purged_at');
                $table->index(
                    ['deleted_at', 'retention_until', 'purge_status'],
                    $tableName.'_retention_purge_index',
                );
            });
        }

        Schema::table('consultations', function (Blueprint $table): void {
            $table->string('attachment_scan_status', 20)->default('clean')->after('attachment_checksum');
            $table->dateTime('attachment_scanned_at')->nullable()->after('attachment_scan_status');
        });

        Schema::table('project_files', function (Blueprint $table): void {
            $table->string('scan_status', 20)->default('clean')->after('checksum')->index();
            $table->dateTime('scanned_at')->nullable()->after('scan_status');
        });

        Schema::table('revisions', function (Blueprint $table): void {
            $table->string('attachment_scan_status', 20)->default('clean')->after('attachment_checksum');
            $table->dateTime('attachment_scanned_at')->nullable()->after('attachment_scan_status');
        });

        DB::table('consultations')
            ->whereNotNull('attachment_path')
            ->update(['attachment_scanned_at' => DB::raw('updated_at')]);
        DB::table('project_files')
            ->update(['scanned_at' => DB::raw('updated_at')]);
        DB::table('revisions')
            ->whereNotNull('attachment_path')
            ->update(['attachment_scanned_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table): void {
            $table->dropColumn(['attachment_scan_status', 'attachment_scanned_at']);
        });

        Schema::table('project_files', function (Blueprint $table): void {
            $table->dropIndex(['scan_status']);
            $table->dropColumn(['scan_status', 'scanned_at']);
        });

        Schema::table('consultations', function (Blueprint $table): void {
            $table->dropColumn(['attachment_scan_status', 'attachment_scanned_at']);
        });

        foreach (['consultations', 'projects', 'project_files', 'revisions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_retention_purge_index');
                $table->dropColumn([
                    'purge_status',
                    'purge_pending_at',
                    'physical_deleted_at',
                    'purged_at',
                    'purge_failure_code',
                ]);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes_viewed_at',
            ]);
        });
    }
};
