<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRaidTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('raid', function (Blueprint $table) {
            $table->increments('id');
            $table->string('game_type', 20)->default('')->comment('资料片版本');
            $table->string('raid_id', 20)->default('')->comment('raidId');
            $table->string('raid_name', 50)->default('')->comment('副本名称');
            $table->integer('raid_time')->default(0)->comment('副本时长');
            $table->string('boss_id', 20)->default('')->comment('bossId');
            $table->string('boss_name', 50)->default('')->comment('boss名称');
            $table->integer('boss_level')->default(0)->comment('boss等级');
            $table->string('zb_id', 30)->default('')->comment('装备id');
            $table->string('zb_name', 50)->default('')->comment('装备名称');
            $table->tinyInteger('zb_level')->default(0)->comment('装备等级');
            $table->string('zb_color', 20)->default('')->comment('装备品质');
            $table->string('drop_rate', 500)->default('')->comment('掉率');
            $table->tinyInteger('zb_got')->default(0)->comment('0-未获得，1-已获得，2-待定');
            $table->tinyInteger('gold')->default(0)->comment('掉落金币');
            $table->tinyInteger('gong_zheng')->default(0)->comment('掉落公正徽章');
            $table->tinyInteger('han_bing')->default(0)->comment('掉落寒冰纹章');
            $table->timestamps();
            $table->primary('id');
            $table->unique('zb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('raid');
    }
}
