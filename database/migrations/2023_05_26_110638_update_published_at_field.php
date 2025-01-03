<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePublishedAtField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $posts = Post::all();

        if (Schema::hasColumn('posts', 'published_at')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('published_at');
            });
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('published_at')
            ->after('language_attr')
            ->nullable();
        });

        foreach ($posts as $post) {
            $post->published_at = $post->created_at;
            $post->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
}
