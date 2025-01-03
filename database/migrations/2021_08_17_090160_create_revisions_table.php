<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id');
            $table->foreignId('category_id');
            $table->foreignId('feature_image')->nullable();
            $table->foreignId('thumbnail');
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
        Schema::dropIfExists('revisions');
    }
}
