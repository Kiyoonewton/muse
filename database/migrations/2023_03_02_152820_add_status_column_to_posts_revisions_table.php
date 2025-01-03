<?php

use App\Models\PostRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusColumnToPostsRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_revisions', function (Blueprint $table) {
            $table->integer('status')->default(0);
        });

        PostRevision::where('id', '>=', 1)->update(['status' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('post_revisions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
