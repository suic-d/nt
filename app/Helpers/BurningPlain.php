<?php

namespace App\Helpers;

class BurningPlain extends MiniGameAbstract
{
    public function __construct()
    {
        $this->setGameType(config('raid.burning_plain.game_type', ''));
        $this->openId = config('raid.burning_plain.open_id', '');
        $this->advance = config('raid.burning_plain.advance', []);
        $this->always = config('raid.burning_plain.always', []);
    }

    public function handle()
    {
        $this->run();
    }
}
