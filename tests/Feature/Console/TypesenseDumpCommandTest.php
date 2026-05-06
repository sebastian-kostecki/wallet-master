<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('typesense:dump queries import_description_memory for user and prints a table', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.protocol', 'http');
    config()->set('services.typesense.host', 'typesense');
    config()->set('services.typesense.port', 8108);
    config()->set('services.typesense.api_key', 'test-key');
    config()->set('services.typesense.timeout_ms', 800);

    Http::fake(function (Request $request) {
        expect($request->method())->toBe('GET');
        expect($request->url())->toStartWith('http://typesense:8108/collections/import_description_memory/documents/search');

        $data = $request->data();

        expect($data['q'])->toBe('*');
        expect($data['query_by'])->toBe('raw_key');
        expect($data['page'])->toBe(1);
        expect($data['per_page'])->toBe(50);
        expect($data['filter_by'])->toBe('user_id:=123');

        return Http::response([
            'found' => 1,
            'hits' => [
                [
                    'document' => [
                        'id' => 'abc',
                        'user_id' => 123,
                        'bank' => 'mbank',
                        'raw_key' => 'raw key',
                        'learned_subject' => 'Subject',
                        'learned_description' => 'Description',
                        'updated_at' => 1234567890,
                    ],
                ],
            ],
        ], 200);
    });

    $this->artisan('typesense:dump', [
        'userId' => 123,
        '--page' => 1,
        '--per-page' => 50,
    ])
        ->expectsOutputToContain('Typesense import_description_memory: found=1 page=1 perPage=50')
        ->expectsTable(
            ['id', 'bank', 'raw_key', 'learned_subject', 'learned_description', 'updated_at'],
            [['abc', 'mbank', 'raw key', 'Subject', 'Description', '1234567890']],
        )
        ->assertExitCode(0);
});

test('typesense:dump adds bank filter when provided', function () {
    config()->set('services.typesense.enabled', true);
    config()->set('services.typesense.protocol', 'http');
    config()->set('services.typesense.host', 'typesense');
    config()->set('services.typesense.port', 8108);
    config()->set('services.typesense.api_key', 'test-key');
    config()->set('services.typesense.timeout_ms', 800);

    Http::fake(function (Request $request) {
        expect($request->method())->toBe('GET');

        $data = $request->data();
        expect($data['filter_by'])->toBe('user_id:=123 && bank:="mbank"');

        return Http::response([
            'found' => 0,
            'hits' => [],
        ], 200);
    });

    $this->artisan('typesense:dump', [
        'userId' => 123,
        '--bank' => 'mbank',
    ])->assertExitCode(0);
});
