<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table): void {
            $table->dateTime('academic_integrity_accepted_at')->nullable()->after('privacy_accepted_at');
            $table->string('attachment_checksum', 64)->nullable()->after('attachment_size');
            $table->string('submission_fingerprint', 64)->nullable()->unique()->after('request_code');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table): void {
            $table->dropUnique(['submission_fingerprint']);
            $table->dropColumn(['academic_integrity_accepted_at', 'attachment_checksum', 'submission_fingerprint']);
        });
    }
};
