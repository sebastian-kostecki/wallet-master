<?php

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Actions\Goals\DeleteGoal;
use App\Actions\Goals\ListGoals;
use App\Actions\Goals\ReorderGoals;
use App\Actions\Goals\StoreGoal;
use App\Actions\Goals\UpdateGoal;
use App\Data\Goals\GoalFormOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Goals\ReorderGoalsRequest;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Http\Requests\Goals\UpdateGoalRequest;
use App\Http\Resources\Goals\GoalResource;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GoalController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Goal::class, 'goal');
    }

    public function index(Request $request, ListGoals $listGoals): Response
    {
        $filter = $request->query('filter', 'active');
        if (! in_array($filter, ['active', 'archived', 'all'], true)) {
            $filter = 'active';
        }

        $listGoals->handle($request->user(), $filter);

        return Inertia::render('goals/Index', [
            'filter' => $filter,
            'goals' => GoalResource::collection($listGoals->getGoals())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('goals/Create', [
            ...(new GoalFormOptions)->toArray(),
        ]);
    }

    public function store(StoreGoalRequest $request, StoreGoal $storeGoal): RedirectResponse
    {
        $storeGoal->handle($request->user(), $request->validated());

        return to_route('goals.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'goals.toast.created',
        ]);
    }

    public function edit(Goal $goal): Response
    {
        $goal->load('currency');

        return Inertia::render('goals/Edit', [
            'goal' => (new GoalResource($goal))->resolve(request()),
            ...(new GoalFormOptions)->toArray(),
        ]);
    }

    public function update(
        UpdateGoalRequest $request,
        Goal $goal,
        UpdateGoal $updateGoal,
    ): RedirectResponse {
        $validated = $request->validated();
        $updateGoal->handle($goal, $validated);

        $onlyArchiveToggle = count($validated) === 1 && array_key_exists('is_archived', $validated);

        if ($onlyArchiveToggle) {
            return back()->with('toast', [
                'type' => 'success',
                'message_key' => 'goals.toast.updated',
            ]);
        }

        return to_route('goals.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'goals.toast.updated',
        ]);
    }

    public function destroy(Goal $goal, DeleteGoal $deleteGoal): RedirectResponse
    {
        $deleteGoal->handle($goal);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'goals.toast.deleted',
        ]);
    }

    public function reorder(ReorderGoalsRequest $request, ReorderGoals $reorderGoals): RedirectResponse
    {
        $reorderGoals->handle($request->user(), $request->validated('ids'));

        return back();
    }
}
