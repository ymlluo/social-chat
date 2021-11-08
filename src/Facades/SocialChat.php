<?php

namespace Ymlluo\SocialChat\Facades;

use Illuminate\Support\Facades\Facade;

class SocialChat extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'social-chat';
    }
}
