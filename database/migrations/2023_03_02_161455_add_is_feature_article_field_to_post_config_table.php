<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsFeatureArticleFieldToPostConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_configs', function (Blueprint $table) {
            $table->integer('is_feature_article')
            ->default(0)
            ->after('is_in_sitemap');
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
            $table->dropColumn('is_feature_article');
        });
    }
}
