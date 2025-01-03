<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropOpenGraphsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('open_graphs');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('open_graphs', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('type')->nullable();
            $table->text('description');
            $table->string('site_name');
            $table->string('publisher');
            $table->foreignId('image_id')->nullable();
            $table->timestamps();
        });
    }
}
