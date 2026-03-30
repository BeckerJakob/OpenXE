<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Auth;

use DateTimeImmutable;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\ApiAccountRepository;
use Xentral\Modules\ApiV3\Repository\ApiV3TokenRepository;

final class TokenService
{
    /** @var ApiAccountRepository */
    private $apiAccountRepository;

    /** @var ApiV3TokenRepository */
    private $tokenRepository;

    public function __construct(ApiAccountRepository $apiAccountRepository, ApiV3TokenRepository $tokenRepository)
    {
        $this->apiAccountRepository = $apiAccountRepository;
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTokens(int $apiAccountId): array
    {
        return $this->tokenRepository->listTokensByAccountId($apiAccountId);
    }

    /**
     * @param string[] $scopes
     *
     * @return array{token:string,token_meta:array<string, mixed>}
     */
    public function createToken(int $apiAccountId, string $label, array $scopes, ?string $expiresAt = null): array
    {
        $account = $this->apiAccountRepository->findById($apiAccountId);
        if ($account === null) {
            throw new ApiV3Exception(404, 'api_account_not_found', 'The API account was not found.');
        }

        $scopes = ScopeRegistry::normalize($scopes);
        if (empty($scopes)) {
            throw new ApiV3Exception(422, 'missing_scopes', 'At least one v3 scope must be selected.');
        }

        $label = trim($label);
        if ($label === '') {
            $label = 'Connector Token';
        }

        $normalizedExpiry = null;
        if ($expiresAt !== null && trim($expiresAt) !== '') {
            $normalizedExpiry = (new DateTimeImmutable($expiresAt))->format('Y-m-d H:i:s');
        }

        $tokenPlain = 'oxev3_' . bin2hex(random_bytes(24));
        $tokenPrefix = substr($tokenPlain, 0, 12);
        $tokenMeta = $this->tokenRepository->createToken(
            $apiAccountId,
            $label,
            $tokenPrefix,
            hash('sha256', $tokenPlain),
            $scopes,
            $normalizedExpiry
        );

        return [
            'token'      => $tokenPlain,
            'token_meta' => $tokenMeta,
        ];
    }

    public function revokeToken(int $tokenId): void
    {
        $this->tokenRepository->revokeToken($tokenId);
    }
}
