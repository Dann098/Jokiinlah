<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'project_id', 'uploaded_by', 'document_uuid', 'version', 'stored_name', 'file_path'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'file_size' => 'integer', 'archived_at' => 'immutable_datetime', 'retention_until' => 'immutable_datetime'];
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
