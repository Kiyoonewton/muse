<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class CategoryPost extends MorphPivot
{
    protected $table = 'category_post';
}
