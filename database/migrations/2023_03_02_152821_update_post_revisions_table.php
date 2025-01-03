<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePostRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $posts = Post::all();

        Schema::table('post_revisions', function (Blueprint $table) {
            $table->renameColumn('revision', 'content');
            $table->string('name')->nullable()->after('post_id');
            $table->string('title')->nullable()->after('name');
            $table->string('slug')->nullable()->after('title');
        });

        foreach ($posts as $post) {
            $post->revisions()->update([
                'title' => $post->title,
                'slug' => $post->slug,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_revisions', function (Blueprint $table) {
            $table->renameColumn('content', 'revision');
            $table->dropColumn('name');
            $table->dropColumn('title');
            $table->dropColumn('slug');
        });
    }
}
