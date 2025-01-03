<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePostsTableOpenGraphTwitterSeos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('open_graph_id');
            $table->dropColumn('twitter_seo_id');
            $table->dropColumn('posted_time_ago');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('open_graph_id')->after('featured_image')->nullable();
            $table->foreignId('twitter_seo_id')->after('open_graph_id')->nullable();
            $table->timestamp('posted_time_ago')->after('language_attr')->nullable();
        });
    }
}
