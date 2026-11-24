<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ClientCommentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientHomeController;
use App\Http\Controllers\ClientPostController;
use App\Http\Controllers\ClientPostVoteController;
use App\Http\Controllers\ClientReleaseNoteController;
use App\Http\Controllers\ClientRoadmapController;
use App\Http\Controllers\ClientTestimonialController;
use App\Http\Controllers\ClientVoteController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImpersonationController;
use App\Http\Controllers\ContactNoteController;
use App\Http\Controllers\ContactSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\OnboardingClientController;
use App\Http\Controllers\PostArchiveController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostMergeController;
use App\Http\Controllers\PostNoteController;
use App\Http\Controllers\PostPublicationController;
use App\Http\Controllers\PostStageController;
use App\Http\Controllers\PostStatusController;
use App\Http\Controllers\PostVoteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReleaseNoteController;
use App\Http\Controllers\ReleaseNotePublicationController;
use App\Http\Controllers\SendingDomainController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TestimonialApprovalController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\TestimonialImportController;
use App\Http\Controllers\UpdateClientController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WidgetSettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::domain(config('app.host'))->middleware('setupRedirect')->group(function () {
    // The first-run page. It creates the owner and closes when a user exists.
    Route::middleware(['guest', 'throttle:10,1'])->group(function () {
        Route::get('setup', [SetupController::class, 'show'])->name('setup');
        Route::post('setup', [SetupController::class, 'store']);
    });

    require __DIR__.'/auth.php';

    Route::get('/', function () {
        return Auth::check()
            ? redirect('dashboard')
            : view('welcome');
    });

    Route::get('documentation', [DocumentationController::class, 'index'])->name('documentation');
    Route::get('documentation/reference', [DocumentationController::class, 'reference'])->name('documentation.reference');
    Route::get('documentation/openapi.json', [DocumentationController::class, 'openApi'])->name('documentation.openapi');
    Route::get('documentation/openapi/download', [DocumentationController::class, 'downloadOpenApi'])->name('documentation.openapi.download');
    Route::get('documentation/postman/download', [DocumentationController::class, 'downloadPostman'])->name('documentation.postman.download');
    Route::get('documentation/guides/{slug}', [DocumentationController::class, 'guide'])->name('documentation.guide');

    Route::middleware(['auth', 'clientId'])->group(function () {
        Route::post('update-client', [UpdateClientController::class, 'update']);

        Route::get('onboarding/project', [OnboardingClientController::class, 'create']);
        Route::post('onboarding/project', [OnboardingClientController::class, 'store']);

        Route::middleware('hasAccess')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::patch('/client', [ClientController::class, 'update'])->name('client.update');

            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            Route::get('posts', [PostController::class, 'index'])->name('posts.index');
            Route::post('posts', [PostController::class, 'store'])->name('posts.store');
            Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
            Route::get('posts/{postId}', [PostController::class, 'show'])->name('posts.show');
            Route::patch('posts/{postId}', [PostController::class, 'update'])->name('posts.update');
            Route::get('posts/{postId}/edit', [PostController::class, 'edit'])->name('posts.edit');
            Route::get('posts/{postId}/notes', [PostNoteController::class, 'edit'])->name('posts-note.edit');
            Route::get('posts/{postId}/votes', [PostVoteController::class, 'index'])->name('posts-vote.index');
            Route::post('posts/{postId}/votes', [PostVoteController::class, 'store'])->name('posts-vote.store');
            Route::post('posts/{postId}/merge', [PostMergeController::class, 'store'])->name('posts.merge');
            Route::get('posts/{postId}/comments', [PostCommentController::class, 'index'])->name('posts-comment.index');
            Route::post('posts/{postId}/comments', [PostCommentController::class, 'store'])->name('posts-comment.store');
            Route::patch('posts/{postId}/notes', [PostNoteController::class, 'update'])->name('posts-note.update');
            Route::post('posts/{postId}/publication', [PostPublicationController::class, 'store'])->name('posts-publication.store');
            Route::delete('posts/{postId}/publication', [PostPublicationController::class, 'destroy'])->name('posts-publication.destroy');
            Route::post('posts/{postId}/archive', [PostArchiveController::class, 'store'])->name('posts-archive.store');
            Route::delete('posts/{postId}/archive', [PostArchiveController::class, 'destroy'])->name('posts-archive.destroy');
            Route::patch('posts/{postId}/status', [PostStatusController::class, 'update'])->name('posts-status.update');
            Route::patch('posts/{postId}/stage', [PostStageController::class, 'move'])->name('posts-stage.move');

            Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
            Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
            Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
            Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');

            Route::get('settings/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
            Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
            Route::patch('webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
            Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

            Route::get('board', [StageController::class, 'index'])->name('board.index');
            Route::post('stages', [StageController::class, 'store'])->name('stages.store');
            Route::patch('stages/reorder', [StageController::class, 'reorder'])->name('stages.reorder');
            Route::patch('stages/{stage}', [StageController::class, 'update'])->name('stages.update');
            Route::delete('stages/{stage}', [StageController::class, 'destroy'])->name('stages.destroy');

            Route::get('tags', [TagController::class, 'index'])->name('tags.index');
            Route::post('tags', [TagController::class, 'store'])->name('tags.store');
            Route::get('tags/create', [TagController::class, 'create'])->name('tags.create');
            Route::get('tags/{tagId}', [TagController::class, 'show'])->name('tags.show');
            Route::patch('tags/{tagId}', [TagController::class, 'update'])->name('tags.update');
            Route::delete('tags/{tagId}', [TagController::class, 'destroy'])->name('tags.destroy');

            Route::get('release-notes', [ReleaseNoteController::class, 'index'])->name('release-notes.index');
            Route::post('release-notes', [ReleaseNoteController::class, 'store'])->name('release-notes.store');
            Route::get('release-notes/create', [ReleaseNoteController::class, 'create'])->name('release-notes.create');
            Route::get('release-notes/{releaseNoteId}', [ReleaseNoteController::class, 'show'])->name('release-notes.show');
            Route::get('release-notes/{releaseNoteId}/edit', [ReleaseNoteController::class, 'edit'])->name('release-notes.edit');
            Route::patch('release-notes/{releaseNoteId}', [ReleaseNoteController::class, 'update'])->name('release-notes.update');
            Route::post('release-notes/{releaseNoteId}/publication', [ReleaseNotePublicationController::class, 'store'])->name('release-notes-publication.store');
            Route::delete('release-notes/{releaseNoteId}/publication', [ReleaseNotePublicationController::class, 'destroy'])->name('release-notes-publication.destroy');

            Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
            Route::get('contacts/{contactId}', [ContactController::class, 'show'])->name('contacts.show');
            Route::get('contacts/{contactId}/impersonate', [ContactImpersonationController::class, 'show'])->name('contacts.impersonate');
            Route::patch('contacts/{contactId}', [ContactController::class, 'update'])->name('contacts.update');
            Route::get('contacts/{contactId}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
            Route::get('contacts/{contactId}/notes', [ContactNoteController::class, 'edit'])->name('contacts-note.edit');
            Route::patch('contacts/{contactId}/notes', [ContactNoteController::class, 'update'])->name('contacts-note.update');

            Route::get('testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
            Route::post('testimonials', [TestimonialController::class, 'store'])->name('testimonials.store');
            Route::post('testimonials/import', [TestimonialImportController::class, 'store'])->middleware('throttle:10,1')->name('testimonials.import');
            Route::get('testimonials/create', [TestimonialController::class, 'create'])->name('testimonials.create');
            Route::get('testimonials/{testimonialId}', [TestimonialController::class, 'show'])->name('testimonials.show');
            Route::patch('testimonials/{testimonialId}', [TestimonialController::class, 'update'])->name('testimonials.update');
            Route::get('testimonials/{testimonialId}/edit', [TestimonialController::class, 'edit'])->name('testimonials.edit');
            Route::post('testimonials/{testimonialId}/approve', [TestimonialApprovalController::class, 'store'])->name('testimonials.approve');
            Route::delete('testimonials/{testimonialId}/approve', [TestimonialApprovalController::class, 'destroy'])->name('testimonials.decline');

            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::get('settings/project', [SettingsController::class, 'project'])->name('settings.project');
            Route::get('settings/api-tokens', [SettingsController::class, 'apiTokens'])->name('settings.api-tokens');
            Route::get('settings/testimonials', [SettingsController::class, 'testimonials'])->name('settings.testimonials');
            Route::get('settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
            Route::patch('settings/integrations', [SettingsController::class, 'updateIntegrations'])->name('settings.integrations.update');
            Route::get('settings/email', [SendingDomainController::class, 'show'])->name('settings.email');
            Route::post('settings/email', [SendingDomainController::class, 'store'])->middleware('throttle:sending-domains')->name('settings.email.store');
            Route::post('settings/email/check', [SendingDomainController::class, 'check'])->middleware('throttle:sending-domains')->name('settings.email.check');
            Route::post('settings/email/retry', [SendingDomainController::class, 'retry'])->middleware('throttle:sending-domains')->name('settings.email.retry');
            Route::delete('settings/email', [SendingDomainController::class, 'destroy'])->middleware('throttle:sending-domains')->name('settings.email.destroy');
            Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::get('settings/widget', [WidgetSettingsController::class, 'index'])->name('settings.widget');
            Route::patch('settings/widget', [WidgetSettingsController::class, 'update'])->name('settings.widget.update');
            Route::post('settings/widget/identity-secret', [WidgetSettingsController::class, 'regenerateIdentitySecret'])->name('settings.widget.identity-secret');
            Route::post('settings/api-keys', [ApiKeyController::class, 'store'])->name('settings.api-keys.store');
            Route::delete('settings/api-keys/{token}', [ApiKeyController::class, 'destroy'])->name('settings.api-keys.destroy');
        });
    });

    // The admin area of the instance. Only the owner gets in. Everyone else,
    // guests included, gets a 404 from the `owner` middleware.
    Route::middleware(['owner', 'clientId', 'hasAccess'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/deactivation', [Admin\UserDeactivationController::class, 'store'])->name('users.deactivate');
        Route::delete('users/{user}/deactivation', [Admin\UserDeactivationController::class, 'destroy'])->name('users.reactivate');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
});

Route::middleware(['identifyClient'])->group(function () {
    Route::get('/authenticate/{contactUuid}', [ContactSessionController::class, 'store'])->name('authenticate-contact');

    Route::middleware(['auth:contact'])->group(function () {
        Route::post('/posts', [ClientPostController::class, 'store'])->middleware('throttle:public-content');
        Route::get('/posts/create', [ClientPostController::class, 'create']);
        Route::post('/comments', [ClientCommentController::class, 'store'])->middleware('throttle:public-content');
        Route::post('/posts/{postId}/votes', [ClientPostVoteController::class, 'store'])->middleware('throttle:public-votes');
        Route::delete('/posts/{postId}/votes/{voteId}', [ClientPostVoteController::class, 'destroy'])->middleware('throttle:public-votes');

        Route::post('/testimonials', [ClientTestimonialController::class, 'store'])->middleware('throttle:public-content');
        Route::get('/testimonials/create', [ClientTestimonialController::class, 'create']);
        Route::get('/testimonials/{testimonialId}/edit', [ClientTestimonialController::class, 'edit']);
        Route::patch('/testimonials/{testimonialId}', [ClientTestimonialController::class, 'update'])->middleware('throttle:public-content');

        Route::post('/votes', [ClientVoteController::class, 'store'])->middleware('throttle:public-votes');
        Route::delete('/votes', [ClientVoteController::class, 'destroy'])->middleware('throttle:public-votes');
    });

    Route::get('/release-notes', [ClientReleaseNoteController::class, 'index']);
    Route::get('/release-notes/{releaseNoteSlug}', [ClientReleaseNoteController::class, 'show']);

    Route::middleware(['clientId', 'adminContactLogin'])->group(function () {
        Route::get('/', [ClientHomeController::class, 'index']);
        Route::get('/roadmap', [ClientRoadmapController::class, 'index'])->name('client-roadmap.index');
        Route::get('/posts', [ClientPostController::class, 'index']);
        Route::get('/posts/{postId}', [ClientPostController::class, 'show']);
        Route::get('/testimonials', [ClientTestimonialController::class, 'index']);
        Route::get('/testimonials/{testimonialId}', [ClientTestimonialController::class, 'show']);
    });

});
