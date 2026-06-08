<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

test('app service provider forces https url scheme in production', function () {
    $this->app['env'] = 'production';

    (new AppServiceProvider($this->app))->boot();

    expect(parse_url(url('/login'), PHP_URL_SCHEME))->toBe('https');
});
