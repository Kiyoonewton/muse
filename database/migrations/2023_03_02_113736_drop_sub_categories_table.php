<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropSubCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('sub_categories');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('category_id');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });
    }
}
