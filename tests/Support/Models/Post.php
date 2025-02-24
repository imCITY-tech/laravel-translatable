<?php

namespace Nevadskiy\Translatable\Tests\Support\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nevadskiy\Translatable\HasTranslations;
use Nevadskiy\Uuid\Uuid;

/**
 * @property string id
 * @property string body
 * @property string slug
 * @property Carbon|null deleted_at
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Post extends Model
{
    use Uuid,
        SoftDeletes,
        HasTranslations;

    /**
     * The attributes that can be translatable.
     *
     * @var array
     */
    protected $translatable = [
        'body',
        'slug',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
