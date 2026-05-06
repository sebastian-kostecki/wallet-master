# WebSockets (Laravel Reverb + Inertia Vue)

This project uses **Laravel Reverb** as the WebSocket server and **Laravel Echo** on the frontend.

## Runtime requirements (local / Sail)

- **Reverb server must be running** (long-running process).
- **The Reverb port must be reachable from the browser**.
  - With Sail, expose the Reverb port in `compose.yaml` (example: `8080:8080`).
- Vite must receive the Reverb env vars (restart `npm run dev` / `composer run dev` after `.env` changes).

Relevant env vars:

- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`

## Backend: emitting events

### 1) Create a broadcastable event

Create an event class that implements `ShouldBroadcast` (queued) or `ShouldBroadcastNow` (synchronous).

Typical structure:

- `broadcastOn()`: channel name(s)
- `broadcastAs()`: event name (string you will listen to on the frontend)
- `broadcastWith()`: payload array

Example skeleton:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class ExampleEvent implements ShouldBroadcastNow
{
    public function __construct(
        public int $userId,
        public string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('public-channel');
    }

    public function broadcastAs(): string
    {
        return 'example.created';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'message' => $this->message,
        ];
    }
}
```

### 2) Dispatch the event

Anywhere in backend code:

```php
event(new \App\Events\ExampleEvent(userId: $userId, message: 'Hello'));
```

Notes:

- Prefer `ShouldBroadcast` for real features (so broadcasting happens on the queue).
- Use `ShouldBroadcastNow` for debugging or very small synchronous flows.

## Frontend: receiving events (Vue)

Echo is configured globally in `resources/js/app.ts` using `@laravel/echo-vue`:

- `configureEcho({ broadcaster: 'reverb' })`
- the package reads `VITE_REVERB_*` automatically

### Public channel

Use `useEchoPublic(channelName, eventName, callback)`:

```ts
import { useEchoPublic } from '@laravel/echo-vue';

type Payload = { user_id: number; message: string };

useEchoPublic<Payload>('public-channel', 'example.created', (payload) => {
    console.log('WS payload', payload);
});
```

### Private or presence channels

Use:

- `useEcho('channel', 'event', cb)` (defaults to **private**)
- `useEchoPresence('channel', 'event', cb)` (presence)

Private/presence channels require:

- channel authorization logic in `routes/channels.php`
- an authenticated session (Inertia app must be logged in)

Example pattern:

```ts
import { useEcho } from '@laravel/echo-vue';

useEcho(`users.${userId}`, 'example.created', (payload) => {
    console.log(payload);
});
```

## Troubleshooting checklist

- **No WS connection attempts in DevTools**
  - you are not subscribing to any channel (Echo instantiates on first usage)
  - Vite did not pick up `VITE_REVERB_*` (restart dev server)
- **WS connects but events never arrive**
  - backend event does not implement `ShouldBroadcast*`
  - wrong `broadcastAs()` / wrong event name on frontend
  - wrong channel name or visibility (public vs private)
- **Connection refused**
  - Reverb not running
  - port not exposed from Sail to host (browser can’t reach it)

