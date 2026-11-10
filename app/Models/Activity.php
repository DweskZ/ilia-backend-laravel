<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'activity';

    public $timestamps = false;

    protected $fillable = ['course_id', 'title', 'max_attempts'];

    protected function casts(): array
    {
        return [
            'max_attempts' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ActivityFactory
    {
        return ActivityFactory::new();
    }
}
