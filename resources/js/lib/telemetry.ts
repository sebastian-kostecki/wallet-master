import { apiFetch } from '@/lib/apiFetch';
import { route } from 'ziggy-js';

type TelemetryPayload = Record<string, string | number | boolean | null>;

export function track(event: string, payload: TelemetryPayload = {}): void {
    void apiFetch(route('telemetry.store'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ event, payload }),
    }).catch(() => {
        // Best-effort — never block UX
    });
}
