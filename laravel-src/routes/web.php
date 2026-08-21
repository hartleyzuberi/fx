<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AiOperationsController;
use App\Http\Controllers\Admin\ContentAuditController;
use App\Http\Controllers\AssessmentAttemptController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\BacktestController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CourseSearchController;
use App\Http\Controllers\CurriculumMapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoProgramController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GateAssessmentController;
use App\Http\Controllers\NotebookController;
use App\Http\Controllers\NotebookEntryController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PracticeHubController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ResourceLibraryController;
use App\Http\Controllers\ReviewQueueController;
use App\Http\Controllers\RobustnessController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SessionPositionController;
use App\Http\Controllers\StrategyController;
use App\Http\Controllers\TradeJournalController;
use App\Http\Controllers\TutorController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('review', ReviewQueueController::class)->name('review.index');
    Route::get('course', CurriculumMapController::class)->name('course.index');
    Route::get('course-search', CourseSearchController::class)->middleware('throttle:60,1')->name('course.search');
    Route::get('course/{unit:slug}', [SessionController::class, 'show'])->name('sessions.show');
    Route::put('course/{unit:slug}/position', [SessionPositionController::class, 'update'])->name('sessions.position.update');
    Route::get('course/{unit:slug}/quiz', [AssessmentController::class, 'show'])->name('assessments.show');
    Route::post('course/{unit:slug}/quiz', [AssessmentAttemptController::class, 'store'])->name('assessments.attempts.store');
    Route::get('course/{unit:slug}/gate', [GateAssessmentController::class, 'show'])->name('gates.show');
    Route::post('course/{unit:slug}/gate', [GateAssessmentController::class, 'store'])->name('gates.store');
    Route::get('course/{unit:slug}/exercises', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('course/{unit:slug}/exercises/{assignment}', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::post('notebooks', [NotebookEntryController::class, 'store'])->name('notebooks.store');
    Route::post('bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::delete('bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::get('notebooks', [NotebookController::class, 'index'])->name('notebooks.index');
    Route::get('calculators', [CalculationController::class, 'index'])->name('calculators.index');
    Route::post('calculators', [CalculationController::class, 'store'])->name('calculators.store');
    Route::get('practice', PracticeHubController::class)->name('practice.index');
    Route::get('resources', ResourceLibraryController::class)->name('resources.index');
    Route::get('exports/{dataset}', ExportController::class)->name('exports.download');
    Route::get('references/{unit:slug}', [ReferenceController::class, 'show'])->name('references.show');
    Route::post('strategies', [StrategyController::class, 'store'])->name('strategies.store');
    Route::post('strategies/{strategy}/freeze', [StrategyController::class, 'freeze'])->name('strategies.freeze');
    Route::post('backtests', [BacktestController::class, 'store'])->name('backtests.store');
    Route::post('backtests/{run}/observations', [BacktestController::class, 'addObservation'])->name('backtests.observations.store');
    Route::post('backtests/{run}/complete', [BacktestController::class, 'complete'])->name('backtests.complete');
    Route::post('demo-programs', [DemoProgramController::class, 'store'])->name('demo-programs.store');
    Route::post('robustness-runs', [RobustnessController::class, 'store'])->name('robustness-runs.store');
    Route::post('demo-programs/{program}/trades', [TradeJournalController::class, 'store'])->name('demo-programs.trades.store');
    Route::post('course/{unit:slug}/tutor', [TutorController::class, 'store'])->name('tutor.store');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::get('ai', [AiOperationsController::class, 'index'])->name('ai.index');
    Route::patch('ai/providers/{provider}', [AiOperationsController::class, 'updateProvider'])->name('ai.providers.update');
    Route::patch('ai/routing/{task}', [AiOperationsController::class, 'updateRouting'])->name('ai.routing.update');
    Route::post('ai/providers/{provider}/test', [AiOperationsController::class, 'testProvider'])->middleware('throttle:10,1')->name('ai.providers.test');
    Route::get('content-audit', [ContentAuditController::class, 'index'])->name('content-audit.index');
    Route::patch('mappings/{mapping}', [ContentAuditController::class, 'reviewMapping'])->name('mappings.review');
    Route::patch('conflicts/{conflict}', [ContentAuditController::class, 'resolveConflict'])->name('conflicts.resolve');
    Route::patch('feature-flags/{key}', [ContentAuditController::class, 'updateFlag'])->name('feature-flags.update');
    Route::patch('gate-attempts/{attempt}', [ContentAuditController::class, 'reviewGate'])->name('gate-attempts.review');
});

require __DIR__.'/settings.php';
