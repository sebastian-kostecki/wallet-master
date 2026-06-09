<?php

declare(strict_types=1);

namespace App\Http\Requests\Telemetry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTelemetryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKeys = (int) config('telemetry.max_payload_keys', 20);

        return [
            'event' => ['required', 'string', Rule::in(config('telemetry.client_events', []))],
            'payload' => ['nullable', 'array', "max:{$maxKeys}"],
            'payload.*' => ['nullable'],
        ];
    }
}
