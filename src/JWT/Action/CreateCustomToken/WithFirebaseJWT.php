<?php

declare(strict_types=1);

namespace Kreait\Firebase\JWT\Action\CreateCustomToken;

use Firebase\JWT\JWT;
use Kreait\Clock;
use Kreait\Firebase\JWT\Action;
use Kreait\Firebase\JWT\Action\CreateCustomToken\Error\CustomTokenCreationFailed;
use Kreait\Firebase\JWT\Contract\Token;
use Kreait\Firebase\JWT\Token as TokenInstance;
use Throwable;

final class WithFirebaseJWT implements Handler
{
    /** @var string */
    private $clientEmail;

    /** @var string */
    private $privateKey;

    /** @var Clock */
    private $clock;

    public function __construct(string $clientEmail, string $privateKey, Clock $clock)
    {
        $this->clientEmail = $clientEmail;
        $this->privateKey = $privateKey;
        $this->clock = $clock;
    }

    public function handle(Action\CreateCustomToken $action): Token
    {
        $now = $this->clock->now();

        $payload = [
            'iss' => $this->clientEmail,
            'sub' => $this->clientEmail,
            'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
            'iat' => $now->getTimestamp(),
            'exp' => $now->modify('+'.$action->expirationTimeInSeconds().' seconds')->getTimestamp(),
            'uid' => $action->uid(),
        ];

        if (!empty($customClaims = $action->customClaims())) {
            $payload['claims'] = $customClaims;
        }

        try {
            $encodedValue = JWT::encode($payload, $this->privateKey, 'RS256');
        } catch (Throwable $e) {
            throw CustomTokenCreationFailed::because($e->getMessage(), $e->getCode(), $e);
        }

        // We replicate what's done in JWT::encode()
        $headers = [
            'typ' => 'JWT',
            'alg' => 'RS256',
        ];

        return TokenInstance::withValues($encodedValue, $headers, $payload);
    }
}