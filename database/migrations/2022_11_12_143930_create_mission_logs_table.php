<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMissionLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('mission_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('open_id', 50)->default('');
            $table->integer('mission_id')->default(0)->comment('任务id');
            $table->string('name', 30)->default('')->comment('任务名称');
            $table->tinyInteger('status')->default(0)->comment('0-待处理，1-处理中，2-完成');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('mission_logs');
    }
}
