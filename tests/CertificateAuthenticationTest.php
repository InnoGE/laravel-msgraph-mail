<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMail;

function configureCertificateMailer(string $certificate, string $privateKey, ?string $passphrase = null): void
{
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'tenant_id' => 'foo_tenant_id',
        'client_certificate' => array_filter([
            'certificate' => $certificate,
            'private_key' => $privateKey,
            'passphrase' => $passphrase,
        ]),
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Http::fake([
        'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token' => Http::response(['access_token' => 'foo_access_token', 'expires_in' => 3599]),
        'https://graph.microsoft.com/v1.0*' => Http::response(['value' => []]),
    ]);
}

function assertValidClientAssertion(): void
{
    $tokenRequestSeen = false;

    Http::assertSent(function (Request $request) use (&$tokenRequestSeen) {
        if (! str_starts_with($request->url(), 'https://login.microsoftonline.com')) {
            return true;
        }

        $tokenRequestSeen = true;

        parse_str($request->body(), $body);

        expect($body['grant_type'])->toBe('client_credentials')
            ->and($body['client_id'])->toBe('foo_client_id')
            ->and($body)->not->toHaveKey('client_secret')
            ->and($body['client_assertion_type'])->toBe('urn:ietf:params:oauth:client-assertion-type:jwt-bearer');

        [$header, $claims, $signature] = explode('.', (string) $body['client_assertion']);

        $decode = fn (string $part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true);

        $expectedThumbprint = rtrim(strtr(base64_encode(hex2bin(
            str_replace(':', '', '9D:76:A4:16:78:1F:1A:AD:E0:0C:04:AC:E4:5F:83:3E:54:F3:32:18')
        )), '+/', '-_'), '=');

        expect($decode($header))->toMatchArray(['alg' => 'RS256', 'typ' => 'JWT', 'x5t' => $expectedThumbprint])
            ->and($decode($claims))->toMatchArray([
                'aud' => 'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token',
                'iss' => 'foo_client_id',
                'sub' => 'foo_client_id',
            ]);

        $publicKey = openssl_pkey_get_public((string) file_get_contents(__DIR__.'/Resources/certs/test-certificate.pem'));
        $verified = openssl_verify(
            "{$header}.{$claims}",
            (string) base64_decode(strtr($signature, '-_', '+/')),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );
        expect($verified)->toBe(1);

        return true;
    });

    expect($tokenRequestSeen)->toBeTrue();
}

it('authenticates with a certificate from file paths', function () {
    configureCertificateMailer(
        __DIR__.'/Resources/certs/test-certificate.pem',
        __DIR__.'/Resources/certs/test-private-key.pem',
    );

    Mail::to('caleb@livewire.com')->send(new TestMail(false));

    assertValidClientAssertion();
});

it('authenticates with inline PEM certificate content', function () {
    configureCertificateMailer(
        (string) file_get_contents(__DIR__.'/Resources/certs/test-certificate.pem'),
        (string) file_get_contents(__DIR__.'/Resources/certs/test-private-key.pem'),
    );

    Mail::to('caleb@livewire.com')->send(new TestMail(false));

    assertValidClientAssertion();
});

it('authenticates with an encrypted private key and passphrase', function () {
    configureCertificateMailer(
        __DIR__.'/Resources/certs/test-certificate-encrypted.pem',
        __DIR__.'/Resources/certs/test-private-key-encrypted.pem',
        'fixture-passphrase',
    );

    Mail::to('caleb@livewire.com')->send(new TestMail(false));

    Http::assertSent(function (Request $request) {
        if (str_starts_with($request->url(), 'https://login.microsoftonline.com')) {
            parse_str($request->body(), $body);
            expect($body)->toHaveKey('client_assertion');
        }

        return true;
    });
});

it('requires certificate and private key when client_certificate is configured', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'tenant_id' => 'foo_tenant_id',
        'client_certificate' => [
            'certificate' => __DIR__.'/Resources/certs/test-certificate.pem',
        ],
        'from' => ['address' => 'taylor@laravel.com'],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(ConfigurationMissing::class, 'Configuration key client_certificate.private_key for microsoft-graph mailer is missing.');
});

it('requires a client secret when no certificate is configured', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'tenant_id' => 'foo_tenant_id',
        'from' => ['address' => 'taylor@laravel.com'],
    ]);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(ConfigurationMissing::class, 'Configuration key client_secret for microsoft-graph mailer is missing.');
});
