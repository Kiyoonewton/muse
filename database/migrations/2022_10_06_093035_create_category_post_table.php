<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryPostTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $posts = Post::all();

        Schema::create('category_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('post_id');
            $table->timestamps();
        });

        foreach ($posts as $post) {
            $post->categories()->attach($post->category_id);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category_post');
    }
}
