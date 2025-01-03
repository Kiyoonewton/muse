<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $posts = Post::all();

        Schema::create('post_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id');
            $table->text('permalink')->nullable();
            $table->boolean('is_comments_enabled')->default(false);
            $table->boolean('is_in_sitemap')->default(true);
            $table->json('schema')->nullable();
            $table->json('social_preview_config')->nullable();
            $table->boolean('has_related_posts')->default(false);
            $table->json('related_posts_config')->nullable();
            $table->timestamps();
        });

        foreach ($posts as $post) {
            $post->postConfig()->create(['post_id' => $post->id]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_configs');
    }
}
