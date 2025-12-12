<?php

namespace Macoauth2canva\OAuth2Canva\Commands;

use Illuminate\Console\Command;

class OAuth2CanvaCommand extends Command
{
    public $signature = 'oauth2canva';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
