<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryIdFieldToPostsTable extends Migration
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
            $table->foreignId('category_id')
            ->after('uuid')
            ->nullable();
        });

        foreach ($posts as $post) {
            $categories = $post->categories;
            $post->category_id = count($categories) > 0 ? $categories[0]->id : null;
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
            $table->dropColumn('category_id');
        });
    }
}
