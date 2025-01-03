<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('revisions');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')
            ->constrained('posts')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();
            $table->foreignId('category_id')
            ->constrained('categories')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();
            $table->text('content');
            $table->smallInteger('status')->default(0);
            $table->string('slug');
            $table->string('title');
            $table->uuid('author_id');
            $table->string('language_attr')->default('en-GB');
            $table->timestamps();
        });
    }
}
