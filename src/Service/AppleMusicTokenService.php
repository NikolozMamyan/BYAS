<?php

namespace App\Service;

class AppleMusicTokenService
{
    private const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private readonly string $appleMusicTeamId,
        private readonly string $appleMusicKeyId,
        private readonly string $appleMusicPrivateKey,
        private readonly string $appleMusicAppName,
    ) {
    }

    public function getAppName(): string
    {
        return $this->appleMusicAppName !== '' ? $this->appleMusicAppName : 'BYAS';
    }

    public function isConfigured(): bool
    {
        return trim($this->appleMusicTeamId) !== ''
            && trim($this->appleMusicKeyId) !== ''
            && $this->normalizePrivateKey($this->appleMusicPrivateKey) !== '';
    }

    public function createDeveloperToken(): string
    {
        $teamId = trim($this->appleMusicTeamId);
        $keyId = trim($this->appleMusicKeyId);
        $privateKey = $this->normalizePrivateKey($this->appleMusicPrivateKey);

        if ($teamId === '' || $keyId === '' || $privateKey === '') {
            throw new \RuntimeException('Apple Music credentials are missing. Set APPLE_MUSIC_TEAM_ID, APPLE_MUSIC_KEY_ID and APPLE_MUSIC_PRIVATE_KEY.');
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + self::TOKEN_TTL_SECONDS;

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $keyId,
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        $signatureInput = $header . '.' . $payload;
        $signature = '';

        $result = openssl_sign($signatureInput, $signature, $privateKey, 'sha256');

        if ($result !== true || $signature === '') {
            throw new \RuntimeException('Unable to sign Apple Music developer token.');
        }

        return $signatureInput . '.' . $this->base64UrlEncode($this->convertDerSignatureToJose($signature, 64));
    }

    private function normalizePrivateKey(string $privateKey): string
    {
        $privateKey = trim($privateKey);

        if ($privateKey === '') {
            return '';
        }

        if (str_contains($privateKey, '-----BEGIN PRIVATE KEY-----')) {
            return str_replace('\n', "\n", $privateKey);
        }

        $decoded = base64_decode($privateKey, true);

        if (is_string($decoded) && str_contains($decoded, '-----BEGIN PRIVATE KEY-----')) {
            return $decoded;
        }

        return str_replace('\n', "\n", $privateKey);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function convertDerSignatureToJose(string $derSignature, int $partLength): string
    {
        $offset = 3;
        $rLength = ord($derSignature[$offset]);
        $offset += 1;
        $r = substr($derSignature, $offset, $rLength);
        $offset += $rLength + 1;
        $sLength = ord($derSignature[$offset]);
        $offset += 1;
        $s = substr($derSignature, $offset, $sLength);

        return str_pad(ltrim($r, "\x00"), $partLength / 2, "\x00", STR_PAD_LEFT)
            . str_pad(ltrim($s, "\x00"), $partLength / 2, "\x00", STR_PAD_LEFT);
    }
}
