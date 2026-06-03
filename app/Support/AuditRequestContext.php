<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class AuditRequestContext
{
    /**
     * Capture request metadata for operational audit (nullable outside HTTP / console).
     *
     * @return array{user_id: int|null, ip_address: ?string, user_agent: ?string, session_id: ?string}
     */
    public static function capture(): array
    {
        $req = function_exists('request') ? request() : null;

        $userAgent = $req?->userAgent();
        if ($userAgent !== null && strlen($userAgent) > 2000) {
            $userAgent = substr($userAgent, 0, 2000);
        }

        return [
            'user_id' => Auth::id(),
            'ip_address' => $req?->ip(),
            'user_agent' => $userAgent,
            'session_id' => $req && $req->hasSession() ? $req->session()->getId() : null,
        ];
    }
}
