<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveImageForeignKeysFromPostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['feature_image']);
            $table->dropForeign(['thumbnail']);
            $table->dropColumn('feature_image');
            $table->dropColumn('thumbnail');
            $table->string('featured_image')->nullable()->after('content');
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
            $table->foreignId('feature_image')
            ->after('category_id')
            ->nullable()
            ->constrained('images');
            $table->foreignId('thumbnail')
            ->after('feature_image')
            ->nullable()
            ->constrained('images');
            $table->dropColumn('featured_image');
        });
    }
}
