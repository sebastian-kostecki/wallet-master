# BNP Paribas Subject Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Map BNP Paribas `subject` from `Nadawca` (amount > 0) or `Odbiorca` (amount < 0), strip digit runs ≥6, and keep `subject_raw` consistent on failed rows.

**Architecture:** `SubjectSanitizer` (pure string cleanup) + `SignBasedSubjectColumnResolver` (pick column header from mapping + parsed amount) used by `BnpParibasImportAdapter` and `ImportRowRawSnapshot`. Polish `defaultMapping` stores `subject_positive` / `subject_negative` instead of a single `subject` key.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 4, existing `AmountParser` / `DateParser`.

**Spec:** `docs/superpowers/specs/2026-06-02-bnp-paribas-subject-import-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Support/Imports/SubjectSanitizer.php` | Strip `\d{6,}`, collapse whitespace |
| `app/Support/Imports/SignBasedSubjectColumnResolver.php` | Given mapping + amount, return column header key or null |
| `app/Imports/BankAdapters/BnpParibasImportAdapter.php` | PL defaultMapping + subject resolve/sanitize in normalizeRow |
| `app/Support/Imports/ImportRowRawSnapshot.php` | Raw subject from sign-based columns (no sanitization) |
| `tests/Unit/Support/Imports/SubjectSanitizerTest.php` | Sanitizer unit tests |
| `tests/Unit/Support/Imports/SignBasedSubjectColumnResolverTest.php` | Column pick unit tests |
| `tests/Unit/Imports/BnpParibasImportAdapterTest.php` | Adapter integration tests |
| `tests/Unit/Imports/BankAdapterDefaultMappingTest.php` | defaultMapping expectations |
| `tests/Unit/Support/Imports/ImportRowRawSnapshotTest.php` | Snapshot subject_raw for BNP mapping |

---

### Task 1: `SubjectSanitizer`

**Files:**
- Create: `app/Support/Imports/SubjectSanitizer.php`
- Create: `tests/Unit/Support/Imports/SubjectSanitizerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Support\Imports\SubjectSanitizer;

it('strips digit runs of six or more', function () {
    expect(SubjectSanitizer::sanitize('123456789012 JAN KOWALSKI'))
        ->toBe('JAN KOWALSKI');
});

it('keeps short digit sequences in names', function () {
    expect(SubjectSanitizer::sanitize('Firma 3M'))
        ->toBe('Firma 3M');
});

it('collapses whitespace after stripping', function () {
    expect(SubjectSanitizer::sanitize('  123456   SKLEP   '))
        ->toBe('SKLEP');
});

it('returns empty string when only digits remain', function () {
    expect(SubjectSanitizer::sanitize('123456789012'))->toBe('');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Unit/Support/Imports/SubjectSanitizerTest.php
```

Expected: FAIL — class `SubjectSanitizer` not found.

- [ ] **Step 3: Implement sanitizer**

```php
<?php

declare(strict_types=1);

namespace App\Support\Imports;

final class SubjectSanitizer
{
    public static function sanitize(string $text, int $minDigitRunLength = 6): string
    {
        $pattern = '/\d{'.$minDigitRunLength.',}/u';
        $withoutLongDigits = preg_replace($pattern, '', $text) ?? $text;

        $collapsed = preg_replace('/\s+/u', ' ', $withoutLongDigits) ?? $withoutLongDigits;

        return trim($collapsed);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --compact tests/Unit/Support/Imports/SubjectSanitizerTest.php
```

Expected: PASS (4 tests).

- [ ] **Step 5: Commit** (only if user requested commits)

```bash
git add app/Support/Imports/SubjectSanitizer.php tests/Unit/Support/Imports/SubjectSanitizerTest.php
git commit -m "feat(imports): add SubjectSanitizer for long digit runs"
```

---

### Task 2: `SignBasedSubjectColumnResolver`

**Files:**
- Create: `app/Support/Imports/SignBasedSubjectColumnResolver.php`
- Create: `tests/Unit/Support/Imports/SignBasedSubjectColumnResolverTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Support\Imports\SignBasedSubjectColumnResolver;

$mapping = [
    'subject_positive' => 'Nadawca',
    'subject_negative' => 'Odbiorca',
];

it('returns positive column header for positive amounts', function () use ($mapping) {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey($mapping, '100.00'))
        ->toBe('Nadawca');
});

it('returns negative column header for negative amounts', function () use ($mapping) {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey($mapping, '-12.34'))
        ->toBe('Odbiorca');
});

it('returns null for zero amount', function () use ($mapping) {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey($mapping, '0.00'))
        ->toBeNull();
});

it('returns null when mapping uses single subject column only', function () {
    expect(SignBasedSubjectColumnResolver::resolveColumnKey(['subject' => 'subject'], '10.00'))
        ->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Unit/Support/Imports/SignBasedSubjectColumnResolverTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement resolver**

```php
<?php

declare(strict_types=1);

namespace App\Support\Imports;

final class SignBasedSubjectColumnResolver
{
    /**
     * @param  array{subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    public static function resolveColumnKey(array $mapping, string $parsedAmount): ?string
    {
        if (isset($mapping['subject'])) {
            return null;
        }

        $positive = $mapping['subject_positive'] ?? null;
        $negative = $mapping['subject_negative'] ?? null;

        if ($positive === null && $negative === null) {
            return null;
        }

        $numericAmount = (float) $parsedAmount;

        if ($numericAmount > 0) {
            return $positive;
        }

        if ($numericAmount < 0) {
            return $negative;
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --compact tests/Unit/Support/Imports/SignBasedSubjectColumnResolverTest.php
```

Expected: PASS (4 tests).

---

### Task 3: Fix `BnpParibasImportAdapter::defaultMapping`

**Files:**
- Modify: `app/Imports/BankAdapters/BnpParibasImportAdapter.php`
- Modify: `tests/Unit/Imports/BankAdapterDefaultMappingTest.php`

- [ ] **Step 1: Add failing test for Polish headers**

Append to `tests/Unit/Imports/BankAdapterDefaultMappingTest.php`:

```php
it('maps BNP Paribas Nadawca and Odbiorca as sign-based subject columns', function () {
    $adapter = new BnpParibasImportAdapter;

    $mapping = $adapter->defaultMapping([
        'Data transakcji',
        'Kwota',
        'Opis',
        'Nadawca',
        'Odbiorca',
    ]);

    expect($mapping)->toBe([
        'date' => 'Data transakcji',
        'amount' => 'Kwota',
        'description' => 'Opis',
        'subject_positive' => 'Nadawca',
        'subject_negative' => 'Odbiorca',
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="maps BNP Paribas Nadawca"
```

Expected: FAIL — receives `subject` key or wrong structure.

- [ ] **Step 3: Replace broken defaultMapping block**

In `BnpParibasImportAdapter::defaultMapping()`, remove lines 40–54 (`if ($amount > 0)` block) and replace with:

```php
        $data = [
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
        ];

        $nadawca = $this->findHeader($headers, 'Nadawca');
        if ($nadawca !== null) {
            $data['subject_positive'] = $nadawca;
        }

        $odbiorca = $this->findHeader($headers, 'Odbiorca');
        if ($odbiorca !== null) {
            $data['subject_negative'] = $odbiorca;
        }

        return $data;
```

- [ ] **Step 4: Run mapping tests**

```bash
php artisan test --compact tests/Unit/Imports/BankAdapterDefaultMappingTest.php
```

Expected: PASS (all tests in file).

---

### Task 4: `BnpParibasImportAdapter::normalizeRow` subject logic

**Files:**
- Modify: `app/Imports/BankAdapters/BnpParibasImportAdapter.php`
- Modify: `tests/Unit/Imports/BnpParibasImportAdapterTest.php`

- [ ] **Step 1: Add failing adapter tests**

Append to `tests/Unit/Imports/BnpParibasImportAdapterTest.php`:

```php
$plMapping = [
    'date' => 'Data transakcji',
    'amount' => 'Kwota',
    'description' => 'Opis',
    'subject_positive' => 'Nadawca',
    'subject_negative' => 'Odbiorca',
];

it('uses Nadawca as subject for positive amounts and strips long digits', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '24-04-2026',
            'Kwota' => '100,00',
            'Opis' => 'Przelew',
            'Nadawca' => '123456789012 JAN KOWALSKI',
            'Odbiorca' => '',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBe('JAN KOWALSKI');
});

it('uses Odbiorca as subject for negative amounts', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '25-04-2026',
            'Kwota' => '-12,34',
            'Opis' => 'Zakup',
            'Nadawca' => 'SHOULD NOT USE',
            'Odbiorca' => '987654321098 SKLEP',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBe('SKLEP');
});

it('returns null subject when chosen column is empty without fallback', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '26-04-2026',
            'Kwota' => '50,00',
            'Opis' => 'Opłata',
            'Nadawca' => '',
            'Odbiorca' => 'ONLY ODBIORCA',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBeNull();
});

it('returns null subject for zero amount', function () use ($plMapping) {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '27-04-2026',
            'Kwota' => '0,00',
            'Opis' => 'Korekta',
            'Nadawca' => 'A',
            'Odbiorca' => 'B',
        ],
        $plMapping,
    );

    expect($parsed->subject)->toBeNull();
});

it('sanitizes manual mapping.subject column', function () {
    $adapter = new BnpParibasImportAdapter;

    $parsed = $adapter->normalizeRow(
        [
            'Data transakcji' => '28-04-2026',
            'Kwota' => '-5,00',
            'Opis' => 'Test',
            'subject' => '111111 Jan',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
            'subject' => 'subject',
        ],
    );

    expect($parsed->subject)->toBe('Jan');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact tests/Unit/Imports/BnpParibasImportAdapterTest.php
```

Expected: new tests FAIL.

- [ ] **Step 3: Update normalizeRow**

Add imports:

```php
use App\Support\Imports\SignBasedSubjectColumnResolver;
use App\Support\Imports\SubjectSanitizer;
```

Replace subject handling in `normalizeRow`:

```php
        $date = DateParser::parse($dateRaw);
        $amount = AmountParser::parse($amountRaw);
        $subject = $this->resolveSubject($row, $mapping, $amount);

        return new ParsedImportRow(
            date: $date,
            amount: $amount,
            description: Str::limit($descriptionRaw, 2000, ''),
            subject: $subject,
            rawStatementDescription: Str::limit($descriptionRaw, 2000, ''),
        );
```

Add private methods:

```php
    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    private function resolveSubject(array $row, array $mapping, string $parsedAmount): ?string
    {
        $raw = $this->resolveSubjectRaw($row, $mapping, $parsedAmount);

        if ($raw === '') {
            return null;
        }

        $sanitized = SubjectSanitizer::sanitize($raw);

        if ($sanitized === '') {
            return null;
        }

        return Str::limit($sanitized, 255, '');
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    private function resolveSubjectRaw(array $row, array $mapping, string $parsedAmount): string
    {
        if (isset($mapping['subject'])) {
            return trim((string) Arr::get($row, $mapping['subject'], ''));
        }

        $columnKey = SignBasedSubjectColumnResolver::resolveColumnKey($mapping, $parsedAmount);

        if ($columnKey === null) {
            return '';
        }

        return trim((string) Arr::get($row, $columnKey, ''));
    }
```

Remove old `$subjectRaw` variable and inline subject in return.

- [ ] **Step 4: Run adapter tests**

```bash
php artisan test --compact tests/Unit/Imports/BnpParibasImportAdapterTest.php
```

Expected: PASS (all tests).

---

### Task 5: `ImportRowRawSnapshot` sign-based `subject_raw`

**Files:**
- Modify: `app/Support/Imports/ImportRowRawSnapshot.php`
- Create: `tests/Unit/Support/Imports/ImportRowRawSnapshotTest.php`

- [ ] **Step 1: Write failing snapshot test**

```php
<?php

declare(strict_types=1);

use App\Support\Imports\ImportRowRawSnapshot;

it('stores raw subject from Nadawca for positive BNP mapping without sanitizing digits', function () {
    $snapshot = ImportRowRawSnapshot::fromMappedRow(
        [
            'Kwota' => '100,00',
            'Nadawca' => '123456789012 JAN',
            'Odbiorca' => 'IGNORED',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
            'subject_positive' => 'Nadawca',
            'subject_negative' => 'Odbiorca',
        ],
    );

    expect($snapshot['subject_raw'])->toBe('123456789012 JAN');
});

it('stores raw subject from Odbiorca for negative amounts', function () {
    $snapshot = ImportRowRawSnapshot::fromMappedRow(
        [
            'Kwota' => '-10,00',
            'Nadawca' => 'IGNORED',
            'Odbiorca' => 'RAW SHOP',
        ],
        [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
            'subject_positive' => 'Nadawca',
            'subject_negative' => 'Odbiorca',
        ],
    );

    expect($snapshot['subject_raw'])->toBe('RAW SHOP');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Unit/Support/Imports/ImportRowRawSnapshotTest.php
```

Expected: FAIL — `subject_raw` null or wrong column.

- [ ] **Step 3: Update snapshot**

Add imports:

```php
use App\Support\Imports\AmountParser;
use App\Support\Imports\SignBasedSubjectColumnResolver;
```

Replace `$subjectRaw` block in `fromMappedRow`:

```php
        $subjectRaw = self::resolveSubjectRaw($row, $mapping);
```

Add private static method:

```php
    /**
     * @param  array<string, string>  $row
     * @param  array{date: string, amount: string, description: string, subject?: string, subject_positive?: string, subject_negative?: string}  $mapping
     */
    private static function resolveSubjectRaw(array $row, array $mapping): ?string
    {
        if (isset($mapping['subject'])) {
            $raw = Str::limit(trim((string) Arr::get($row, $mapping['subject'], '')), 255, '');

            return $raw !== '' ? $raw : null;
        }

        $amountRaw = trim((string) Arr::get($row, $mapping['amount'], ''));

        if ($amountRaw === '') {
            return null;
        }

        $parsedAmount = AmountParser::parse($amountRaw);
        $columnKey = SignBasedSubjectColumnResolver::resolveColumnKey($mapping, $parsedAmount);

        if ($columnKey === null) {
            return null;
        }

        $raw = Str::limit(trim((string) Arr::get($row, $columnKey, '')), 255, '');

        return $raw !== '' ? $raw : null;
    }
```

Update PHPDoc on `$mapping` param to include optional `subject_positive` / `subject_negative`.

- [ ] **Step 4: Run snapshot tests**

```bash
php artisan test --compact tests/Unit/Support/Imports/ImportRowRawSnapshotTest.php
```

Expected: PASS.

---

### Task 6: Final verification

- [ ] **Step 1: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Run all related tests**

```bash
php artisan test --compact \
  tests/Unit/Support/Imports/SubjectSanitizerTest.php \
  tests/Unit/Support/Imports/SignBasedSubjectColumnResolverTest.php \
  tests/Unit/Support/Imports/ImportRowRawSnapshotTest.php \
  tests/Unit/Imports/BnpParibasImportAdapterTest.php \
  tests/Unit/Imports/BankAdapterDefaultMappingTest.php
```

Expected: all PASS.

- [ ] **Step 3: Optional feature smoke** (if PL fixture exists or add minimal row to job test later)

```bash
php artisan test --compact tests/Feature/Imports/CommitImportJobTest.php
```

Expected: PASS — English-header fixtures unchanged.

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| amount > 0 → Nadawca | Task 2, 4 |
| amount < 0 → Odbiorca | Task 2, 4 |
| amount = 0 → null | Task 2, 4 |
| No fallback | Task 4 test |
| Strip ≥6 digits | Task 1, 4 |
| Manual mapping.subject | Task 4 |
| subject_raw unsanitized | Task 5 |
| defaultMapping PL keys | Task 3 |
| Remove broken `$amount > 0` on column index | Task 3 |
| English parent fallback | Existing tests Task 6 |
| No UI/API changes | N/A |

No placeholders. Types consistent across resolver, adapter, snapshot.
