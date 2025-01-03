<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('feature_image')->nullable()->constrained('images');
            $table->foreignId('thumbnail')->nullable()->constrained('images');
            $table->foreignId('open_graph_id')->nullable();
            $table->foreignId('twitter_seo_id')->nullable();
            $table->text('content');
            $table->smallInteger('status')->default(0);
            $table->string('slug');
            $table->string('title');
            $table->uuid('author_id');
            $table->string('canonical');
            $table->string('focus_keyword');
            $table->string('language_attr')->default('en-GB');
            $table->timestamp('posted_time_ago')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
