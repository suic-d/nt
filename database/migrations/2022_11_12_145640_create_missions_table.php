<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMissionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('open_id', 50)->default('');
            $table->integer('mission_id')->default(0)->comment('任务id');
            $table->string('name', 30)->default('')->comment('任务名称');
            $table->string('sw', 30)->default('')->comment('声望名称');
            $table->integer('sw_val')->default(0)->comment('声望值');
            $table->integer('level')->default(0)->comment('任务等级');
            $table->integer('time')->default(0)->comment('任务时长');
            $table->tinyInteger('status')->default(0)->comment('0-未完成，1-已完成');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('missions');
    }
}
