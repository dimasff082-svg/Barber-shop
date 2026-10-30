<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $image_path
 * @property string $alt
 * @property string|null $category
 * @property int $sort_order
 * @property bool $is_active
 */
class GalleryImage extends Model
{
    protected $fillable = ['title', 'image_path', 'alt', 'category', 'sort_order', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
