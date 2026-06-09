<?php

declare(strict_types=1);

namespace App\Console\Commands\Currencies;

use App\Models\Currency;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\text;

#[Signature('currency:create
    {--code= : ISO 4217 code (3 letters)}
    {--name= : Display name}
    {--symbol= : Currency symbol}
    {--precision=2 : Decimal places}
    {--update : Update name/symbol/precision when code already exists}')]
#[Description('Create or update a currency in the reference table.')]
final class CreateCurrency extends Command
{
    public function handle(): int
    {
        $code = strtoupper($this->resolveCode());
        $name = $this->resolveName();
        $symbol = $this->resolveSymbol();
        $precision = $this->resolvePrecision();

        try {
            $validated = Validator::make(
                [
                    'code' => $code,
                    'name' => $name,
                    'symbol' => $symbol,
                    'precision' => $precision,
                ],
                [
                    'code' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
                    'name' => ['required', 'string', 'max:64'],
                    'symbol' => ['nullable', 'string', 'max:8'],
                    'precision' => ['required', 'integer', 'min:0', 'max:255'],
                ],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $existing = Currency::query()->where('code', $validated['code'])->first();

        if ($existing !== null && ! $this->option('update')) {
            $this->error("Currency with code {$validated['code']} already exists. Use --update to modify it.");

            return self::FAILURE;
        }

        if ($existing !== null) {
            $existing->update([
                'name' => $validated['name'],
                'symbol' => $validated['symbol'],
                'precision' => $validated['precision'],
            ]);

            $this->info("Currency {$validated['code']} updated.");

            return self::SUCCESS;
        }

        $currency = Currency::query()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'precision' => $validated['precision'],
        ]);

        $this->info("Currency #{$currency->id} created ({$currency->code}).");

        return self::SUCCESS;
    }

    private function resolveCode(): string
    {
        $code = $this->option('code');

        if (is_string($code) && $code !== '') {
            return $code;
        }

        if ($this->input->hasParameterOption('--code')) {
            return is_string($code) ? $code : '';
        }

        return text(
            label: 'Code',
            placeholder: 'EUR',
            required: true,
        );
    }

    private function resolveName(): string
    {
        $name = $this->option('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        if ($this->input->hasParameterOption('--name')) {
            return is_string($name) ? $name : '';
        }

        return text(
            label: 'Name',
            placeholder: 'Euro',
            required: true,
        );
    }

    private function resolveSymbol(): ?string
    {
        $symbol = $this->option('symbol');

        if (is_string($symbol) && $symbol !== '') {
            return $symbol;
        }

        if ($symbol === null && ! $this->input->hasParameterOption('--symbol')) {
            $prompted = text(
                label: 'Symbol',
                placeholder: '€',
                required: false,
            );

            return $prompted !== '' ? $prompted : null;
        }

        return null;
    }

    private function resolvePrecision(): int
    {
        $precision = $this->option('precision');

        if (is_numeric($precision)) {
            return (int) $precision;
        }

        if ($precision === null && ! $this->input->hasParameterOption('--precision')) {
            $prompted = text(
                label: 'Precision',
                placeholder: '2',
                default: '2',
                required: true,
            );

            return (int) $prompted;
        }

        return 2;
    }
}
