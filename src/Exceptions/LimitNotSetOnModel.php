<?php

namespace Tolery\AiCad\Exceptions;

use Exception;

class LimitNotSetOnModel extends Exception
{
    public function __construct(string $name)
    {
        parent::__construct("$name limit is not set for the model.");
    }
}
