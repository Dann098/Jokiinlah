<?php

namespace App\Models;

use App\Enums\MalwareScanStatus;
use App\Enums\PurgeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id', 'project_id', 'uploaded_by', 'document_uuid', 'version',
        'original_name', 'stored_name', 'disk', 'file_path',
        'file_type', 'file_size', 'checksum',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'file_size' => 'integer',
            'archived_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime',
            'scan_status' => MalwareScanStatus::class,
            'scanned_at' => 'immutable_datetime',
            'purge_status' => PurgeStatus::class,
            'purge_pending_at' => 'immutable_datetime',
            'physical_deleted_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
