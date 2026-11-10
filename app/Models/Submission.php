<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasUuids;

    protected $table = 'submission';

    public $timestamps = false;

    protected $fillable = [
        'activity_id', 'user_id', 'prompt', 'attempt', 'observation', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
