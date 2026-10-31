<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'blog_posts';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'status',
        'show_on_site',
        'is_featured',
        'views',
        'author_id',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'show_on_site' => 'integer',
            'is_featured' => 'integer',
            'views' => 'integer',
            'author_id' => 'string',
            'published_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'blp';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function readingTime(): int
    {
        $words = str_word_count(strip_tags($this->content ?: ''));

        return max(1, (int) ceil($words / 200));
    }

    public function scopeVisibleOnSite($query)
    {
        return $query->where('show_on_site', 1);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }
}
