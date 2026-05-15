<?php

namespace App\Http\Controllers;

use App\Services\Certificates\CertificateVerificationService;
use Illuminate\Http\Response;

class CertificateVerificationController extends Controller
{
    public function __invoke(string $token, CertificateVerificationService $verificationService): Response
    {
        return response()->view('certificates.verify', [
            'certificate' => $verificationService->publicPayload($token),
        ]);
    }
}
