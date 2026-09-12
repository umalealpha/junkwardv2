<?php

namespace App\Resolvers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use AlphaDirect\ArchivedKYC;

class UserResolver implements \OwenIt\Auditing\Contracts\AgentResolver
{
    public static function resolve()
    {
        return 'Agent';
    }
}
?>
