<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revisions', function (Blueprint $table): void {
            $table->string('attachment_checksum', 64)->nullable()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table): void {
            $table->dropColumn('attachment_checksum');
        });
    }
};
