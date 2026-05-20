<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasUuids;

    protected $table = 'form_submissions';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'data_json',
        'submitted_by',
        'ip_address',
        'user_agent',
        'locale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_json' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
