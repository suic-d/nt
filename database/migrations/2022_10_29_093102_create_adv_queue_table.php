<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvQueueTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('adv_queue', function (Blueprint $table) {
            $table->increments('id');
            $table->string('open_id', 50)->default('');
            $table->tinyInteger('num')->default(1);
            $table->tinyInteger('status')->default(0);
            $table->integer('expire_at')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('adv_queue');
    }
}
