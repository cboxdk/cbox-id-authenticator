<?php

declare(strict_types=1);

use App\NativeComponents\ApprovalDetail;
use App\NativeComponents\Approvals;
use App\NativeComponents\Connect;
use App\NativeComponents\OAuthCallback;
use App\NativeComponents\Settings;
use Illuminate\Support\Facades\Route;

/*
 * Native screens. These routes are also the DEEP-LINK surface: with
 * NATIVEPHP_DEEPLINK_SCHEME set, `dk.cbox.id.authenticator://approvals/01J…` opens the
 * app straight at `/approvals/01J…`, including from a cold start. That is how a
 * notification tap reaches the right approval.
 *
 * The push payload is untrusted routing data — the detail screen re-fetches the
 * approval over TLS and renders only what the server returns.
 */

Route::native('/', Connect::class);

// The OAuth redirect target. Registered on the client as both this scheme and an
// HTTPS App Link; Spike A decides which one the build actually uses.
Route::native('/oauth/callback', OAuthCallback::class);

Route::native('/approvals', Approvals::class);
Route::native('/approvals/{id}', ApprovalDetail::class);

Route::native('/settings', Settings::class);
