<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRaidLogTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('raid_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('game_type', 20)->default('')->comment('资料片版本');
            $table->string('open_id', 50)->default('');
            $table->string('raid_id', 20)->default('')->comment('raidId');
            $table->string('raid_name', 50)->default('')->comment('副本名称');
            $table->string('boss_id', 20)->default('')->comment('bossId');
            $table->string('boss_name', 50)->default('')->comment('boss名称');
            $table->tinyInteger('status')->default(0)->comment('0-待处理，1-处理中，2-完成');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('raid_log');
    }
}
