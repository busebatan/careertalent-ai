<?php

namespace App\Support;

use Illuminate\Http\Request;

final class PortalAuthSession
{
    public const DEFAULT = 'auth';

    public const COMPANY = 'company_auth';

    public static function keyFor(Request $request): string
    {
        return $request->routeIs('company.*') ? self::COMPANY : self::DEFAULT;
    }

    public static function token(Request $request): ?string
    {
        $token = session(self::keyFor($request).'.access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
