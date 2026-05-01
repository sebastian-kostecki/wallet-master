<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('typesense:setup is a no-op when typesense is disabled', function () {
    config()->set('services.typesense.enabled', false);

    Http::fake();

    $this->artisan('typesense:setup')
        ->expectsOutputToContain('Typesense is disabled')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

test('typesense:setup fails when api key is missing', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.api_key', '');

    Http::fake();

    $this->artisan('typesense:setup')
        ->expectsOutputToContain('Typesense API key is missing')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

test('typesense:setup succeeds when collection already exists', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.protocol', 'http');
    config()->set('services.typesense.host', 'typesense');
    config()->set('services.typesense.port', 8108);
    config()->set('services.typesense.api_key', 'test-key');
    config()->set('services.typesense.timeout_ms', 800);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET' && $request->url() === 'http://typesense:8108/health') {
            return Http::response(['ok' => true], 200);
        }

        if ($request->method() === 'GET' && $request->url() === 'http://typesense:8108/collections/import_description_memory') {
            return Http::response(['name' => 'import_description_memory'], 200);
        }

        return Http::response(['message' => 'unexpected request'], 500);
    });

    $this->artisan('typesense:setup', ['collection' => 'import_description_memory'])
        ->expectsOutputToContain('Typesense collection exists: import_description_memory')
        ->assertExitCode(0);
});

test('typesense:setup creates collection when it does not exist', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.protocol', 'http');
    config()->set('services.typesense.host', 'typesense');
    config()->set('services.typesense.port', 8108);
    config()->set('services.typesense.api_key', 'test-key');
    config()->set('services.typesense.timeout_ms', 800);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET' && $request->url() === 'http://typesense:8108/health') {
            return Http::response(['ok' => true], 200);
        }

        if ($request->method() === 'GET' && $request->url() === 'http://typesense:8108/collections/import_description_memory') {
            return Http::response(['message' => 'Not found'], 404);
        }

        if ($request->method() === 'POST' && $request->url() === 'http://typesense:8108/collections') {
            $data = $request->data();

            expect($data['name'])->toBe('import_description_memory');
            expect($data['default_sorting_field'])->toBe('updated_at');

            $fields = collect($data['fields'] ?? [])->keyBy('name');

            expect($fields['user_id']['type'])->toBe('int32');
            expect($fields['user_id']['facet'])->toBeTrue();

            expect($fields['bank']['type'])->toBe('string');
            expect($fields['bank']['facet'])->toBeTrue();

            expect($fields['raw_key']['type'])->toBe('string');

            expect($fields['learned_subject']['type'])->toBe('string');
            expect($fields['learned_subject']['optional'])->toBeTrue();
            expect($fields['learned_subject']['index'])->toBeFalse();

            expect($fields['learned_description']['type'])->toBe('string');
            expect($fields['learned_description']['index'])->toBeFalse();

            expect($fields['updated_at']['type'])->toBe('int64');

            return Http::response(['name' => 'import_description_memory'], 201);
        }

        return Http::response(['message' => 'unexpected request'], 500);
    });

    $this->artisan('typesense:setup', ['collection' => 'import_description_memory'])
        ->expectsOutputToContain('Typesense collection created: import_description_memory')
        ->assertExitCode(0);
});
