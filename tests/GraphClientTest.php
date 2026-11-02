<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InnoGE\LaravelMsGraphMail\Graph\ClientCertificate;
use InnoGE\LaravelMsGraphMail\Graph\ClientSecret;
use InnoGE\LaravelMsGraphMail\Graph\GraphClient;
use InnoGE\LaravelMsGraphMail\Graph\GraphCredentials;
use InnoGE\LaravelMsGraphMail\Graph\InvalidCredentialsException;
use InnoGE\LaravelMsGraphMail\Graph\UntrustedGraphLinkException;

function graphClient(): GraphClient
{
    return new GraphClient(new GraphCredentials('t', 'c', new ClientSecret('s')));
}

beforeEach(function () {
    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3599]),
        'https://graph.microsoft.com/v1.0/*' => Http::response(['ok' => true]),
    ]);
});

it('builds an authenticated request against the graph base url', function () {
    graphClient()->request()->get('/me');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.microsoft.com/v1.0/me'
        && $request->hasHeader('Authorization', 'Bearer token'));
    Http::assertSent(fn (Request $request) => $request->url() === 'https://login.microsoftonline.com/t/oauth2/v2.0/token'
        && $request['client_id'] === 'c' && $request['client_secret'] === 's');
});

it('encodes every path segment', function () {
    expect(graphClient()->path('users', 'a@example.com', 'messages', 'id/../move?x=1#'))
        ->toBe('/users/a@example.com/messages/id%2F..%2Fmove%3Fx%3D1%23');
});

it('rejects dot and empty path segments', function (string $segment) {
    graphClient()->path('users', $segment);
})->with(['', '.', '..'])->throws(InvalidArgumentException::class);

it('never sends the token outside the graph api', function (string $url) {
    expect(fn () => graphClient()->request()->get($url))->toThrow(UntrustedGraphLinkException::class);

    Http::assertNotSent(fn (Request $request) => $request->url() === $url);
})->with([
    'foreign host' => ['https://attacker.example/v1.0/next'],
    'look-alike host' => ['https://graph.microsoft.com.attacker.example/v1.0/next'],
    'other api version' => ['https://graph.microsoft.com/beta/me'],
]);

it('follows absolute graph links', function () {
    graphClient()->request()->get('https://graph.microsoft.com/v1.0/next?$skiptoken=1');

    Http::assertSent(fn (Request $request) => Str::startsWith($request->url(), 'https://graph.microsoft.com/v1.0/next'));
});

it('sends default and per-request preferences', function () {
    $client = new GraphClient(new GraphCredentials('t', 'c', new ClientSecret('s')), ['IdType="ImmutableId"']);

    $client->request('odata.maxpagesize=10')->get('/me');
    graphClient()->request()->get('/me');

    Http::assertSent(fn (Request $request) => $request->header('Prefer') === ['IdType="ImmutableId", odata.maxpagesize=10']);
    Http::assertSent(fn (Request $request) => Str::endsWith($request->url(), '/me') && ! $request->hasHeader('Prefer'));
});

it('rejects empty credentials', function (Closure $credentials) {
    expect($credentials)->toThrow(InvalidCredentialsException::class);
})->with([
    'tenant' => [fn () => new GraphCredentials('', 'c', new ClientSecret('s'))],
    'client' => [fn () => new GraphCredentials('t', '', new ClientSecret('s'))],
    'secret' => [fn () => new ClientSecret('')],
]);

it('never puts a misconfigured key into the exception message', function (string $privateKey) {
    expect(fn () => new ClientCertificate(__DIR__.'/Resources/certs/test-certificate.pem', $privateKey))
        ->toThrow(function (InvalidCredentialsException $e) {
            expect($e->getMessage())->not->toContain('super-secret');
        });
})->with([
    'looks like a path' => ['super-secret-base64-key'],
    'stream wrapper' => ['data://text/plain,super-secret'],
    'inline without pem header' => ["super-secret\nbase64"],
]);

it('rejects an invalid certificate on construction', function () {
    new ClientCertificate("not\na certificate", __DIR__.'/Resources/certs/test-private-key.pem');
})->throws(InvalidCredentialsException::class, 'The client certificate is not a PEM encoded certificate.');

it('redacts credentials from stack traces', function () {
    expect(fn () => new ClientCertificate(__DIR__.'/Resources/certs/test-certificate.pem', 'super-secret', 'super-secret'))
        ->toThrow(function (InvalidCredentialsException $e) {
            expect($e->getTraceAsString())->not->toContain('super-secret')
                ->and(json_encode($e->getTrace()))->not->toContain('super-secret');
        });
});
