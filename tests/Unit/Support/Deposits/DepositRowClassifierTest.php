<?php

declare(strict_types=1);

use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\BankAdapters\MBankImportAdapter;
use App\Support\Deposits\DepositClassification;
use App\Support\Deposits\DepositClassificationConfidence;
use App\Support\Deposits\DepositClassificationIntent;

it('classifies BNP OTWARCIE LOKATY as auto open', function () {
    $adapter = new BnpParibasImportAdapter;
    $row = [
        'Data transakcji' => '01-08-2026',
        'Kwota' => '-5000.00',
        'Opis' => '',
        'Typ transakcji' => 'OTWARCIE LOKATY',
    ];
    $mapping = ['date' => 'Data transakcji', 'amount' => 'Kwota', 'description' => 'Opis'];
    $parsed = $adapter->normalizeRow($row, $mapping);

    $result = $adapter->classifyDepositRow($row, $mapping, $parsed);

    expect($result->intent)->toBe(DepositClassificationIntent::Open);
    expect($result->confidence)->toBe(DepositClassificationConfidence::Auto);
});

it('classifies BNP SPŁATA DEPOZYTU with positive amount as auto close', function () {
    $adapter = new BnpParibasImportAdapter;
    $row = [
        'Data transakcji' => '01-08-2026',
        'Kwota' => '5000.00',
        'Opis' => '',
        'Typ transakcji' => 'SPŁATA DEPOZYTU',
    ];
    $mapping = ['date' => 'Data transakcji', 'amount' => 'Kwota', 'description' => 'Opis'];
    $parsed = $adapter->normalizeRow($row, $mapping);

    $result = $adapter->classifyDepositRow($row, $mapping, $parsed);

    expect($result->intent)->toBe(DepositClassificationIntent::ClosePrincipal);
    expect($result->confidence)->toBe(DepositClassificationConfidence::Auto);
});

it('classifies mBank Lokaty category with negative amount as auto open', function () {
    $adapter = new MBankImportAdapter;
    $row = [
        'Data operacji' => '2026-08-01',
        'Opis operacji' => 'Zakładanie lokaty',
        'Kategoria' => 'Lokaty i konto oszcz.',
        'Kwota' => '-3000,00 PLN',
    ];
    $mapping = ['date' => 'Data operacji', 'amount' => 'Kwota', 'description' => 'Opis operacji'];
    $parsed = $adapter->normalizeRow($row, $mapping);

    $result = $adapter->classifyDepositRow($row, $mapping, $parsed);

    expect($result->intent)->toBe(DepositClassificationIntent::Open);
    expect($result->confidence)->toBe(DepositClassificationConfidence::Auto);
});

it('classifies mBank Lokaty category with positive amount as auto close', function () {
    $adapter = new MBankImportAdapter;
    $row = [
        'Data operacji' => '2026-08-01',
        'Opis operacji' => 'Wypłata z lokaty',
        'Kategoria' => 'Lokaty i konto oszcz.',
        'Kwota' => '3000,00 PLN',
    ];
    $mapping = ['date' => 'Data operacji', 'amount' => 'Kwota', 'description' => 'Opis operacji'];
    $parsed = $adapter->normalizeRow($row, $mapping);

    $result = $adapter->classifyDepositRow($row, $mapping, $parsed);

    expect($result->intent)->toBe(DepositClassificationIntent::ClosePrincipal);
    expect($result->confidence)->toBe(DepositClassificationConfidence::Auto);
});

it('returns none for unsupported bank adapter default', function () {
    expect(DepositClassification::none()->intent)->toBe(DepositClassificationIntent::None);
});
