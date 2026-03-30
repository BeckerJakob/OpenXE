<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Auth;

use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Repository\ApiV3TokenRepository;

final class OpaqueTokenAuthenticator
{
    /** @var ApiV3TokenRepository */
    private $tokenRepository;

    public function __construct(ApiV3TokenRepository $tokenRepository)
    {
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function authenticate(ApiV3Request $request): array
    {
        $token = $request->getBearerToken();
        if ($token === '') {
            throw new ApiV3Exception(
                401,
                'missing_bearer_token',
                'A valid Bearer token is required.',
                [],
                ['WWW-Authenticate' => 'Bearer']
            );
        }

        $tokenHash = hash('sha256', $token);
        $tokenRow = $this->tokenRepository->findActiveTokenByHash($tokenHash);
        if ($tokenRow === null) {
            throw new ApiV3Exception(
                401,
                'invalid_bearer_token',
                'The supplied Bearer token is invalid.',
                [],
                ['WWW-Authenticate' => 'Bearer error="invalid_token"']
            );
        }

        if ((int)$tokenRow['account_active'] !== 1) {
            throw new ApiV3Exception(403, 'inactive_api_account', 'The referenced API account is inactive.');
        }

        if (!empty($tokenRow['revoked_at'])) {
            throw new ApiV3Exception(401, 'revoked_bearer_token', 'The supplied Bearer token has been revoked.');
        }

        if (!empty($tokenRow['expires_at']) && strtotime((string)$tokenRow['expires_at']) < time()) {
            throw new ApiV3Exception(401, 'expired_bearer_token', 'The supplied Bearer token has expired.');
        }

        $this->tokenRepository->touchToken((int)$tokenRow['id']);

        return [
            'api_account_id' => (int)$tokenRow['api_account_id'],
            'token_id'       => (int)$tokenRow['id'],
            'label'          => (string)$tokenRow['label'],
            'token_label'    => (string)$tokenRow['label'],
            'account_label'  => (string)$tokenRow['account_label'],
            'scopes'         => (array)$tokenRow['scopes'],
            'expires_at'     => $tokenRow['expires_at'],
        ];
    }

    /**
     * @param array<string, mixed> $principal
     */
    public function assertScope(array $principal, string $scope): void
    {
        $scopes = array_map('strval', (array)($principal['scopes'] ?? []));
        if (!in_array($scope, $scopes, true)) {
            throw new ApiV3Exception(
                403,
                'missing_scope',
                'The Bearer token does not include the required scope.',
                ['required_scope' => $scope]
            );
        }
    }
}
