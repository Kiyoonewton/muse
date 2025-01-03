<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropPostUuidFieldFromPostRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_revisions', function (Blueprint $table) {
            $table->dropColumn('post_uuid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_revisions', function (Blueprint $table) {
            $table->uuid('post_uuid')
            ->after('post_id')
            ->nullable();
        });
    }
}
