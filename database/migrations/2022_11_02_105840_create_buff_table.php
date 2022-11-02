<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuffTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('buff', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('buff_id')->default(0)->comment('buff id');
            $table->string('name', 20)->default('')->comment('buff名称');
            $table->string('buff_detail', 200)->default('')->comment('buff详情');
            $table->string('story', 200)->default('')->comment('buff描述');
            $table->smallInteger('level')->default(0);
            $table->smallInteger('price')->default(0);
            $table->smallInteger('paizi')->default(0);
            $table->timestamps();
            $table->unique('buff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('buff');
    }
}
