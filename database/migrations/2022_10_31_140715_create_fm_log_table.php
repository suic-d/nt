<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFmLogTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fm_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('raid_log_id')->default(0)->comment('关联raid_log.id');
            $table->string('open_id', 50)->default('');
            $table->smallInteger('level')->default(0)->comment('附魔等级');
            $table->tinyInteger('status')->default(0)->comment('0-待处理，1-完成');
            $table->timestamps();
            $table->index('raid_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fm_log');
    }
}
