<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveHasRelatedPostsFieldFromPostConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_configs', function (Blueprint $table) {
            $table->dropColumn('has_related_posts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_configs', function (Blueprint $table) {
            $table->integer('has_related_posts')
            ->default(0)
            ->after('social_preview_config');
        });
    }
}
