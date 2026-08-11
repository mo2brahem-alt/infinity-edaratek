<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppMetaVerificationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $configuredToken = (string) config('services.whatsapp.meta_verify_token', '');
        $mode = (string) ($request->query('hub.mode') ?? $request->query('hub_mode', ''));
        $providedToken = (string) ($request->query('hub.verify_token') ?? $request->query('hub_verify_token', ''));
        $challenge = $request->query('hub.challenge') ?? $request->query('hub_challenge');

        if (
            $configuredToken === ''
            || $mode !== 'subscribe'
            || $providedToken === ''
            || $challenge === null
            || ! hash_equals($configuredToken, $providedToken)
        ) {
            return response('', 403);
        }

        return response((string) $challenge, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
