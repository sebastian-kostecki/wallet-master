<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('typesense:reset-memory is a no-op when typesense is disabled', function () {
    config()->set('services.typesense.enabled', false);

    Http::fake();

    $this->artisan('typesense:reset-memory', ['--force' => true])
        ->expectsOutputToContain('Typesense is disabled')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

test('typesense:reset-memory fails when api key is missing', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.api_key', '');

    Http::fake();

    $this->artisan('typesense:reset-memory', ['--force' => true])
        ->expectsOutputToContain('Typesense API key is missing')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

test('typesense:reset-memory deletes and recreates the collection', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.protocol', 'http');
    config()->set('services.typesense.host', 'typesense');
    config()->set('services.typesense.port', 8108);
    config()->set('services.typesense.api_key', 'test-key');
    config()->set('services.typesense.timeout_ms', 800);

    $collection = 'import_description_memory';

    Http::fake(function (Request $request) use ($collection) {
        if ($request->method() === 'GET' && $request->url() === 'http://typesense:8108/health') {
            return Http::response(['ok' => true], 200);
        }

        if ($request->method() === 'DELETE' && $request->url() === "http://typesense:8108/collections/{$collection}") {
            return Http::response(['ok' => true], 200);
        }

        if ($request->method() === 'POST' && $request->url() === 'http://typesense:8108/collections') {
            $data = $request->data();

            expect($data['name'])->toBe($collection);
            expect($data['default_sorting_field'])->toBe('updated_at');

            return Http::response(['name' => $collection], 201);
        }

        return Http::response(['message' => 'unexpected request'], 500);
    });

    $this->artisan('typesense:reset-memory', [
        'collection' => $collection,
        '--force' => true,
    ])
        ->expectsOutputToContain('Typesense collection deleted')
        ->expectsOutputToContain('Typesense collection created')
        ->assertExitCode(0);
});

test('typesense:reset-memory recreates the collection when it does not exist', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.protocol', 'http');
    config()->set('services.typesense.host', 'typesense');
    config()->set('services.typesense.port', 8108);
    config()->set('services.typesense.api_key', 'test-key');
    config()->set('services.typesense.timeout_ms', 800);

    $collection = 'import_description_memory';

    Http::fake(function (Request $request) use ($collection) {
        if ($request->method() === 'GET' && $request->url() === 'http://typesense:8108/health') {
            return Http::response(['ok' => true], 200);
        }

        if ($request->method() === 'DELETE' && $request->url() === "http://typesense:8108/collections/{$collection}") {
            return Http::response(['message' => 'Not found'], 404);
        }

        if ($request->method() === 'POST' && $request->url() === 'http://typesense:8108/collections') {
            $data = $request->data();
            expect($data['name'])->toBe($collection);

            return Http::response(['name' => $collection], 201);
        }

        return Http::response(['message' => 'unexpected request'], 500);
    });

    $this->artisan('typesense:reset-memory', [
        'collection' => $collection,
        '--force' => true,
    ])
        ->expectsOutputToContain('Typesense collection not found')
        ->expectsOutputToContain('Typesense collection created')
        ->assertExitCode(0);
});
