<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdvertLogTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('advert_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('raid_log_id')->default(0)->comment('关联raid_log.id');
            $table->string('open_id', 50)->default('');
            $table->tinyInteger('num')->default(1)->comment('广告次序');
            $table->tinyInteger('status')->default(0)->comment('0-未完成，1-已完成');
            $table->timestamps();
            $table->index('raid_log_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('advert_log');
    }
}
