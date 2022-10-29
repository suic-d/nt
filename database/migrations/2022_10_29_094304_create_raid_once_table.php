<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRaidOnceTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('raid_once', function (Blueprint $table) {
            $table->increments('id');
            $table->string('open_id', 50)->default('');
            $table->string('raid_id', 20)->default('')->comment('raidId');
            $table->string('boss_id', 20)->default('')->comment('bossId');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('raid_once');
    }
}
