<?php

namespace App\Helpers;

class WarSongGulch extends MiniGameAbstract
{
    public function __construct()
    {
        $this->setGameType(config('raid.war_song_gulch.game_type', ''));
        $this->openId = config('raid.war_song_gulch.open_id', '');
        $this->advance = config('raid.war_song_gulch.advance', []);
        $this->always = config('raid.war_song_gulch.always', []);
    }

    public function handle()
    {
        $this->run();
    }
}
