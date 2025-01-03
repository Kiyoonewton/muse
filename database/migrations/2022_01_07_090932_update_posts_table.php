<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('uuid')->after('id');
            $table->renameColumn('author_id', 'author_uuid');
            $table->dropColumn('page_uuid');
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
            $table->renameColumn('author_uuid', 'author_id');
            $table->uuid('page_uuid');
            $table->dropColumn('uuid');
        });
    }
}
