<?php

declare(strict_types=1);

namespace App\Http\Controllers\Goals;

use App\Actions\Goals\DeleteGoal;
use App\Actions\Goals\ListGoals;
use App\Actions\Goals\SaveAnnualEstimate;
use App\Actions\Goals\SaveMonthlyEstimate;
use App\Actions\Goals\StoreGoal;
use App\Actions\Goals\UpdateGoal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Goals\SaveAnnualEstimateRequest;
use App\Http\Requests\Goals\SaveMonthlyEstimateRequest;
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
        $year = (int) $request->query('year', (string) now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $listGoals->handle($request->user(), $year);

        return Inertia::render('goals/Index', [
            'year' => $year,
            'goals' => GoalResource::collection($listGoals->getGoals())->resolve(),
        ]);
    }

    public function store(StoreGoalRequest $request, StoreGoal $storeGoal): RedirectResponse
    {
        $storeGoal->handle($request->user(), $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'goals.toast.created',
        ]);
    }

    public function update(
        UpdateGoalRequest $request,
        Goal $goal,
        UpdateGoal $updateGoal,
    ): RedirectResponse {
        $updateGoal->handle($goal, $request->validated());

        return back()->with('toast', [
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

    public function saveAnnualEstimate(
        SaveAnnualEstimateRequest $request,
        Goal $goal,
        SaveAnnualEstimate $saveAnnualEstimate,
    ): RedirectResponse {
        $this->authorize('update', $goal);
        $saveAnnualEstimate->handle($goal, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'goals.toast.estimate_saved',
        ]);
    }

    public function saveMonthlyEstimate(
        SaveMonthlyEstimateRequest $request,
        Goal $goal,
        SaveMonthlyEstimate $saveMonthlyEstimate,
    ): RedirectResponse {
        $this->authorize('update', $goal);
        $saveMonthlyEstimate->handle($goal, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'goals.toast.estimate_saved',
        ]);
    }
}
