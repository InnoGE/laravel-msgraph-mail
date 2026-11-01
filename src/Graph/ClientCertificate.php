<?php

namespace InnoGE\LaravelMsGraphMail\Graph;

use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;
use SensitiveParameter;

final readonly class ClientCertificate implements ClientAuthentication
{
    private const string ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    private string $thumbprint;

    private OpenSSLAsymmetricKey $privateKey;

    public function __construct(
        string $certificate,
        #[SensitiveParameter] string $privateKey,
        #[SensitiveParameter] ?string $passphrase = null,
    ) {
        $this->thumbprint = $this->thumbprint($this->loadPem($certificate, 'certificate'));

        $key = openssl_pkey_get_private($this->loadPem($privateKey, 'private key'), $passphrase ?? '');
        if ($key === false) {
            throw new InvalidCredentialsException('The client certificate private key is not readable (wrong passphrase or malformed PEM).');
        }

        $this->privateKey = $key;
    }

    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array
    {
        $now = time();

        $signingInput = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'x5t' => $this->thumbprint]))
            .'.'
            .$this->base64UrlEncode((string) json_encode([
                'aud' => $tokenEndpoint,
                'iss' => $clientId,
                'sub' => $clientId,
                'jti' => (string) Str::uuid(),
                'nbf' => $now - 60,
                'iat' => $now - 60,
                'exp' => $now + 600,
            ]));

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256) || ! is_string($signature)) {
            throw new InvalidCredentialsException('Signing the client assertion failed.');
        }

        return [
            'client_assertion_type' => self::ASSERTION_TYPE,
            'client_assertion' => $signingInput.'.'.$this->base64UrlEncode($signature),
        ];
    }

    private function thumbprint(string $pem): string
    {
        $body = Str::of($pem)
            ->match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s')
            ->replaceMatches('/\s+/', '');

        $der = base64_decode($body->value(), true);
        if ($body->isEmpty() || $der === false) {
            throw new InvalidCredentialsException('The client certificate is not a PEM encoded certificate.');
        }

        return $this->base64UrlEncode(sha1($der, true));
    }

    private function loadPem(#[SensitiveParameter] string $value, string $name): string
    {
        $isInlinePem = Str::contains($value, ['-----BEGIN', "\n"]);
        if ($isInlinePem) {
            return $value;
        }

        $isLocalFile = ! Str::contains($value, '://') && is_file($value);
        if (! $isLocalFile) {
            throw new InvalidCredentialsException("The client {$name} is neither PEM content nor a local file path.");
        }

        $contents = @file_get_contents($value);
        if ($contents === false) {
            throw new InvalidCredentialsException("The client {$name} file is not readable.");
        }

        return $contents;
    }

    private function base64UrlEncode(string $value): string
    {
        return Str::of(base64_encode($value))->replace(['+', '/'], ['-', '_'])->rtrim('=')->value();
    }
}
