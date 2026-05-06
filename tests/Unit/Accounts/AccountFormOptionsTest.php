<?php

use App\ViewModels\Accounts\AccountFormOptions;
use Tests\TestCase;

uses(TestCase::class);

it('exposes account type icons', function () {
    $options = (new AccountFormOptions)->toArray();

    $accountTypes = collect($options['accountTypes'])
        ->keyBy('value');

    expect($accountTypes->get('checking'))->not->toBeNull();
    expect($accountTypes->get('checking')['icon_name'])->toBe('creditCard');

    expect($accountTypes->get('savings'))->not->toBeNull();
    expect($accountTypes->get('savings')['icon_name'])->toBe('piggyBank');
});
