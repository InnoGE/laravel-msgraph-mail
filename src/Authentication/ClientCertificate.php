<?php

namespace InnoGE\LaravelMsGraphMail\Authentication;

use Illuminate\Support\Str;
use InnoGE\LaravelMsGraphMail\Contracts\ClientAuthentication;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationInvalid;

/**
 * Authenticates the client with a certificate (Entra ID "certificate" credential)
 * by signing a JWT client assertion with the certificate's private key.
 *
 * The certificate and private key values may be either PEM content or a path
 * to a PEM file.
 */
class ClientCertificate implements ClientAuthentication
{
    protected const ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    public function __construct(
        protected readonly string $certificate,
        protected readonly string $privateKey,
        protected readonly ?string $passphrase = null,
    ) {}

    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array
    {
        return [
            'client_assertion_type' => self::ASSERTION_TYPE,
            'client_assertion' => $this->buildAssertion($clientId, $tokenEndpoint),
        ];
    }

    protected function buildAssertion(string $clientId, string $tokenEndpoint): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'x5t' => $this->certificateThumbprint(),
        ];

        $now = time();
        $claims = [
            'aud' => $tokenEndpoint,
            'iss' => $clientId,
            'sub' => $clientId,
            'jti' => (string) Str::uuid(),
            'nbf' => $now - 60,
            'iat' => $now - 60,
            'exp' => $now + 600,
        ];

        $signingInput = $this->base64UrlEncode((string) json_encode($header))
            .'.'
            .$this->base64UrlEncode((string) json_encode($claims));

        $privateKey = openssl_pkey_get_private(
            $this->loadPem($this->privateKey, 'client_certificate.private_key'),
            $this->passphrase ?? ''
        );

        if ($privateKey === false) {
            throw new ConfigurationInvalid('client_certificate.private_key', 'not a readable private key (wrong passphrase or malformed PEM)');
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256) || ! is_string($signature)) {
            throw new ConfigurationInvalid('client_certificate.private_key', 'signing the client assertion failed');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * Base64url-encoded SHA-1 thumbprint of the DER certificate, as expected
     * by Entra ID in the JWT x5t header.
     */
    protected function certificateThumbprint(): string
    {
        $pem = $this->loadPem($this->certificate, 'client_certificate.certificate');

        if (! preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $pem, $matches)) {
            throw new ConfigurationInvalid('client_certificate.certificate', 'not a PEM encoded certificate');
        }

        $der = base64_decode((string) preg_replace('/\s+/', '', $matches[1]), true);

        if ($der === false) {
            throw new ConfigurationInvalid('client_certificate.certificate', 'not a PEM encoded certificate');
        }

        return $this->base64UrlEncode(sha1($der, true));
    }

    /**
     * Accepts PEM content directly or a path to a PEM file.
     */
    protected function loadPem(string $value, string $configKey): string
    {
        if (str_starts_with(trim($value), '-----BEGIN')) {
            return $value;
        }

        $contents = @file_get_contents($value);

        if ($contents === false) {
            throw new ConfigurationInvalid($configKey, "unreadable file at path: {$value}");
        }

        return $contents;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
