<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pockets;

use App\Actions\Pockets\DeletePocket;
use App\Actions\Pockets\ListPockets;
use App\Actions\Pockets\ReorderPockets;
use App\Actions\Pockets\StorePocket;
use App\Actions\Pockets\UpdatePocket;
use App\Data\Pockets\PocketFormOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pockets\ReorderPocketsRequest;
use App\Http\Requests\Pockets\StorePocketRequest;
use App\Http\Requests\Pockets\UpdatePocketRequest;
use App\Http\Resources\Pockets\PocketResource;
use App\Models\Pocket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PocketController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Pocket::class, 'pocket');
    }

    public function index(Request $request, ListPockets $listPockets): Response
    {
        $filter = $request->query('filter', 'active');
        if (! in_array($filter, ['active', 'archived', 'all'], true)) {
            $filter = 'active';
        }

        $listPockets->handle($request->user(), $filter);

        return Inertia::render('goals/Index', [
            'filter' => $filter,
            'pockets' => PocketResource::collection($listPockets->getPockets())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('goals/Create', [
            ...(new PocketFormOptions)->toArray(),
        ]);
    }

    public function store(StorePocketRequest $request, StorePocket $storePocket): RedirectResponse
    {
        $storePocket->handle($request->user(), $request->validated());

        return to_route('pockets.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'pockets.toast.created',
        ]);
    }

    public function edit(Pocket $pocket): Response
    {
        $pocket->load('currency');

        return Inertia::render('goals/Edit', [
            'pocket' => (new PocketResource($pocket))->resolve(request()),
            ...(new PocketFormOptions)->toArray(),
        ]);
    }

    public function update(
        UpdatePocketRequest $request,
        Pocket $pocket,
        UpdatePocket $updatePocket,
    ): RedirectResponse {
        $validated = $request->validated();
        $updatePocket->handle($pocket, $validated);

        $onlyArchiveToggle = count($validated) === 1 && array_key_exists('is_archived', $validated);

        if ($onlyArchiveToggle) {
            return back()->with('toast', [
                'type' => 'success',
                'message_key' => 'pockets.toast.updated',
            ]);
        }

        return to_route('pockets.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'pockets.toast.updated',
        ]);
    }

    public function destroy(Pocket $pocket, DeletePocket $deletePocket): RedirectResponse
    {
        $deletePocket->handle($pocket);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'pockets.toast.deleted',
        ]);
    }

    public function reorder(ReorderPocketsRequest $request, ReorderPockets $reorderPockets): RedirectResponse
    {
        $reorderPockets->handle($request->user(), $request->validated('ids'));

        return back();
    }
}
