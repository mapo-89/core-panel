<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ManagedFile extends Model implements HasMedia
{
    use HasUuids;
    use InteractsWithMedia;

    protected $table = 'managed_files';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folder_id',
        'name',
        'collection',
        'disk',
        'mime_type',
        'size',
        'extension',
        'meta_json',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'size' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'folder_id');
    }
}
