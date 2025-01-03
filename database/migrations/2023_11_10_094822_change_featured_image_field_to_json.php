<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeFeaturedImageFieldToJson extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $posts = Post::all();

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('featured_image');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('featured_image')
            ->after('category_id')
            ->nullable();
        });

        foreach ($posts as $post) {
            if ($post->featured_image) {
                $post->featured_image = json_encode(['src' => $post->featured_image]);
                $post->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $posts = Post::all();

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('featured_image');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('featured_image')->after('category_id')->nullable();
        });

        foreach ($posts as $post) {
            if ($post->featured_image) {
                $post->featured_image = json_decode($post->featured_image, true)['src'];
                $post->save();
            }
        }
    }
}
