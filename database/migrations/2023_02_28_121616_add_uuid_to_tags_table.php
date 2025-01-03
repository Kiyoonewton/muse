<?php

use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddUuidToTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tags = Tag::all();

        Schema::table('tags', function (Blueprint $table) {
            $table->uuid('uuid')
            ->nullable()
            ->after('id');
        });

        foreach ($tags as $tag) {
            $tag->uuid = (string) Str::uuid();
            $tag->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}
