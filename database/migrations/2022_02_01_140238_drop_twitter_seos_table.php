<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropTwitterSeosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('twitter_seos');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('twitter_seos', function (Blueprint $table) {
            $table->id();
            $table->string('creator');
            $table->string('title');
            $table->text('description');
            $table->foreignId('image_id')->nullable();
            $table->timestamps();
        });
    }
}
