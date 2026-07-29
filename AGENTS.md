<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== nativephp/mobile rules ===

## NativePHP Mobile

- NativePHP Mobile is a Laravel package for building **fully native** iOS and Android apps with PHP. Screens are
rendered as real SwiftUI (iOS) and Jetpack Compose (Android) UI — driven entirely by PHP via SuperNative components
and EDGE Blade elements. A full PHP runtime runs directly on the device with SQLite — no web server required.
- Documentation: `https://nativephp.com/docs/mobile/4/**`
- IMPORTANT: Always activate the `nativephp-mobile` skill every time you work on any NativePHP functionality.

### Native UI First — Always

**Always build screens with native UI: `NativeComponent` classes registered via `Route::native()`, rendering EDGE
elements (`native:column`, `native:text`, `native:button`, …).** This is the way to build NativePHP apps.

- Never scaffold new screens as web views, Blade-over-WebView pages, Livewire components, or Inertia pages.
- The web view (the `native:web-view` element) is a legacy/edge-case escape hatch for embedding web content — never the
  foundation of a screen. If the user asks for a webview-based screen, build it natively with EDGE instead and
  explain why; only fall back to the web view if they explicitly insist.
- If the app contains legacy webview screens, proactively suggest converting them to native UI (see the
  `nativephp-webview-to-native` skill).
- Style EDGE elements with Tailwind utility classes via `class="..."` / `:class="..."` only — never inline
  CSS `style="..."` attributes or ad-hoc styling props.
- Use `native:icon` (SF Symbols on iOS, Material Icons on Android) for iconography — never emoji characters in
  UI text, labels, or buttons, unless the user explicitly asks for emojis. Prefer the typed icon enums
  (`App\Icons\Ios`, `App\Icons\Android`, `App\Icons\AndroidOutlined`) bound via the `:ios` / `:android`
  attributes, e.g. `:ios="Ios::Gearshape" :android="Android::Settings"`, importing each enum into the view with
  Blade's use directive first. The enums are generated, not shipped — if `app/Icons/` doesn't exist yet, run
  `php artisan native-ui:generate-icons` first (safe to run yourself).

### When a Capability Is Missing

If the app needs native functionality or a UI component that core and `native-ui` don't provide:

1. **Look for an existing plugin first.** Check the plugin marketplace (`https://plugins.nativephp.com`) and the
   official core plugins. (If a marketplace-lookup MCP tool is available in your session, use it.)
2. **If no plugin exists, build a custom plugin** with `php artisan native:plugin:create` — plugins bundle
   Swift/Kotlin bridge functions, events, permissions, and can even ship their own native EDGE components.
3. **Never fall back to the web view to fill a native gap.** A missing capability is a reason to write a plugin,
   not a reason to build a webview screen.

### Installing Plugins — Always Register and Verify

Requiring a plugin with Composer is NOT enough — an installed-but-unregistered plugin does nothing. Every plugin
install must follow all three steps:

1. `composer require vendor/plugin-name`
2. `php artisan vendor:publish --tag=nativephp-plugins-provider` — publishes the app's `NativeServiceProvider`
   (needed once, before the first plugin registration; harmless to re-run)
3. `php artisan native:plugin:register vendor/plugin-name` — adds it to the `NativeServiceProvider`
4. `php artisan native:plugin:list` — verify it shows as registered

Then tell the user to rebuild with `php artisan native:run` (native code only compiles in at build time — do not
run this yourself). If `native:run` warns "The following plugins are installed but not registered", go back to
step 3.

### Database Seeding — Always via Migrations

On-device there is no `db:seed` — NativePHP runs **migrations** on app start (once each, tracked, versioned).
Whenever asked to seed the database, use the migration trick: create a dedicated migration
(`php artisan make:migration seed_app_settings`) and put the inserts in `up()`. If a Seeder class helps organize
the data, still create it — but invoke it **from the migration's `up()`** (e.g. `(new CategorySeeder)->run()`),
never rely on `db:seed` being run. Seed migrations must be safe for both fresh installs and updates of existing
user databases.

### Build Commands — Tell the User, Never Run

**CRITICAL: Never execute any of these commands yourself. Always instruct the user to run them manually in their
terminal.**

| Command | Purpose |
|---|---|
| `php artisan native:run ios` | Compile and run on iOS simulator/device |
| `php artisan native:run android` | Compile and run on Android emulator/device |
| `php artisan native:run ios --watch` | Build, deploy, then start hot reload — all in one |
| `php artisan native:watch` | Hot reload (watch for file changes) |
| `php artisan native:open` | Open project in Xcode or Android Studio |
| `php artisan native:install` | Install/upgrade the native shell |

Notes:
- The `./native` shortcut wraps the `native:` namespace (`./native run`, `./native watch`).
- The Vite dev server is **opt-in** in v4: add `--vite` to `native:run`/`native:watch` only when the app actually
  uses JS/CSS HMR. Native UI screens hot-reload without Vite.
- `npm run build -- --mode=ios|android` is only needed for apps with web-view assets — not for native UI screens.

**Always ask which platform before giving any build or run command.** If the user hasn't specified iOS or Android,
ask: "Which platform do you want to build/test on — iOS or Android?" Never assume a platform.

When the platform is confirmed, give the relevant command(s) above and tell the user to run it in their terminal.
Do not run it yourself.

=== nativephp/mobile-biometrics rules ===

## nativephp/biometrics

Biometric authentication plugin for NativePHP Mobile (Face ID, Touch ID, Fingerprint).

### PHP Usage (Livewire/Blade)

Use the `Biometrics` facade:

<code-snippet name="Using Biometrics Facade" lang="php">
use Native\Mobile\Facades\Biometrics;

// Prompt for biometric authentication
Biometrics::prompt();
</code-snippet>

### JavaScript Usage (Vue/React/Inertia)

<code-snippet name="Using Biometrics in JavaScript" lang="javascript">
import { biometric } from '#nativephp';

// Basic usage
await biometric.prompt();

// With identifier for tracking
await biometric.prompt().id('secure-action-auth');
</code-snippet>

### Handling Biometric Events

#### PHP

<code-snippet name="Listening for Biometric Events in PHP" lang="php">
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Biometric\Completed;

#[OnNative(Completed::class)]
public function handleBiometric(bool $success)
{
    if ($success) {
        $this->unlockSecureFeature();
    } else {
        $this->showErrorMessage();
    }
}
</code-snippet>

#### Vue

<code-snippet name="Listening for Biometric Events in Vue" lang="javascript">
import { biometric, on, off, Events } from '#nativephp';
import { ref, onMounted, onUnmounted } from 'vue';

const handleBiometricComplete = (payload) => {
    if (payload.success) {
        isAuthenticated.value = true;
    } else {
        showErrorMessage();
    }
};

onMounted(() => {
    on(Events.Biometric.Completed, handleBiometricComplete);
});

onUnmounted(() => {
    off(Events.Biometric.Completed, handleBiometricComplete);
});
</code-snippet>

### Platform Support

- **iOS**: Face ID, Touch ID
- **Android**: Fingerprint, face unlock, other biometric methods
- **Fallback**: System authentication (PIN, password, pattern)

=== nativephp/mobile-firebase rules ===

## nativephp/firebase

Push notifications via Firebase Cloud Messaging (FCM) for NativePHP Mobile. Handles device registration, token management, permission flow, deep linking from notifications, data-only messages with background PHP processing, and server-side sending.

---

### Configuration

**Android:** Place `google-services.json` from Firebase Console in your project root.

**iOS:** Place `GoogleService-Info.plist` from Firebase Console in your project root. Enable Push Notifications capability in Apple Developer account and upload your APNs key to Firebase Console.

**Server-side:** Set `FIREBASE_CREDENTIALS=/path/to/service-account.json` in `.env` for sending from your server.

---

### Permission Flow

Always check before prompting. Show a pre-prompt UI explaining value of notifications when status is `not_determined`.

<code-snippet name="Check Permission (PHP)" lang="php">
use Native\Mobile\Facades\PushNotifications;

$status = PushNotifications::checkPermission();
// Returns: "granted", "denied", "not_determined", "provisional", or "ephemeral"
</code-snippet>

<code-snippet name="Enroll for Push (PHP)" lang="php">
use Native\Mobile\Facades\PushNotifications;

// Triggers system dialog if not_determined, fetches token if already granted
PushNotifications::enroll();
</code-snippet>

<code-snippet name="Get Cached Token (PHP)" lang="php">
use Native\Mobile\Facades\PushNotifications;

$token = PushNotifications::getToken();
// FCM token on Android, APNs/FCM token on iOS, null if not enrolled
</code-snippet>

<code-snippet name="Recommended Permission Flow (PHP)" lang="php">
use Native\Mobile\Facades\PushNotifications;
use Native\Mobile\Facades\Dialog;

public function requestPush()
{
    $status = PushNotifications::checkPermission();

    if ($status === 'denied') {
        Dialog::toast('Enable notifications in Settings');
        return;
    }

    PushNotifications::enroll();
}
</code-snippet>

<code-snippet name="Permission Flow (JavaScript)" lang="js">
import { PushNotifications, On, Events } from '#nativephp';

const { status } = await PushNotifications.checkPermission();

if (status === 'not_determined') {
    await PushNotifications.enroll();
}

const { token } = await PushNotifications.getToken();
</code-snippet>

---

### Handling Token Events

<code-snippet name="TokenGenerated Event (Livewire)" lang="php">
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\PushNotification\TokenGenerated;

#[OnNative(TokenGenerated::class)]
public function handleToken(string $token)
{
    // Store token on your server for this user/device
    $this->user->update(['push_token' => $token]);
}
</code-snippet>

<code-snippet name="TokenGenerated Event (Vue)" lang="js">
import { On, Off, Events } from '#nativephp';
import { onMounted, onUnmounted } from 'vue';

const handleToken = ({ token }) => {
    fetch('/api/push-token', {
        method: 'POST',
        body: JSON.stringify({ token }),
    });
};

onMounted(() => On(Events.PushNotification.TokenGenerated, handleToken));
onUnmounted(() => Off(Events.PushNotification.TokenGenerated, handleToken));
</code-snippet>

<code-snippet name="TokenGenerated Event (React)" lang="jsx">
import { On, Off, Events } from '#nativephp';
import { useEffect } from 'react';

useEffect(() => {
    const handler = On(Events.PushNotification.TokenGenerated, ({ token }) => {
        sendTokenToServer(token);
    });
    return () => Off(Events.PushNotification.TokenGenerated, handler);
}, []);
</code-snippet>

---

### Deep Linking

Include a `url` or `link` key in the push data payload. The app navigates on tap, even from cold start.

<code-snippet name="FCM Payload with Deep Link" lang="json">
{
    "message": {
        "token": "device-token",
        "notification": {
            "title": "New Message",
            "body": "You have a new message"
        },
        "data": {
            "url": "/messages/42"
        }
    }
}
</code-snippet>

---

### Data Messages (Background Processing)

Send data-only messages to trigger Laravel event processing on the device without showing a visible notification. Include an `event` key in the data payload.

<code-snippet name="Data-Only FCM Message" lang="json">
{
    "message": {
        "token": "device-token",
        "data": {
            "event": "App\\Events\\DataSyncRequested",
            "payload": "{\"sync_id\": 42}"
        },
        "apns": {
            "payload": {
                "aps": { "content-available": 1 }
            }
        }
    }
}
</code-snippet>

The plugin spins up an ephemeral PHP runtime on the device to dispatch the event. Your Laravel event listeners run natively, even when the app is in the background.

<code-snippet name="Handle Data Message Event (PHP)" lang="php">
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\PushNotification\PushNotificationReceived;

#[OnNative(PushNotificationReceived::class)]
public function handlePush($data)
{
    // Process incoming data message
}
</code-snippet>

---

### Sending from Server

<code-snippet name="Test Command" lang="bash">

# Basic notification

php artisan fcm:send {token} --title="Hello" --body="World"

# With deep link

php artisan fcm:send {token} --title="Order Ready" --url="/orders/123"

# Data-only (silent background processing)

php artisan fcm:send {token} --data-only --d event=App\\Events\\SyncNeeded

# With badge count

php artisan fcm:send {token} --title="Messages" --badge=3

# Custom data pairs

php artisan fcm:send {token} --title="Update" --d type=order --d order_id=55
</code-snippet>

<code-snippet name="Send via FCM v1 API (PHP)" lang="php">
use Illuminate\Support\Facades\Http;

$response = Http::withToken($accessToken)
    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => 'Order Shipped',
                'body' => 'Your order is on the way!',
            ],
            'data' => [
                'url' => '/orders/123',
            ],
        ],
    ]);
</code-snippet>

---

### Clear Badge

<code-snippet name="Clear Badge" lang="php">
use Native\Mobile\Facades\PushNotifications;

PushNotifications::clearBadge(); // iOS resets to 0, Android cancels displayed notifications
</code-snippet>

---

### Permission Status Reference

| Status | Description |
|---|---|
| `granted` | User allowed notifications |
| `denied` | User denied — direct to Settings |
| `not_determined` | Never asked — safe to call `enroll()` |
| `provisional` | Quiet notifications (iOS only) |
| `ephemeral` | App Clips (iOS only) |

---

### Platform Notes

| Feature | iOS | Android |
|---|---|---|
| Permission | System dialog via UNUserNotificationCenter | System dialog on API 33+ (auto below) |
| Token | APNs → FCM token (if Firebase configured) | FCM token |
| Background data messages | Ephemeral PHP runtime | Ephemeral PHP runtime |
| Deep linking | `url` or `link` in data payload | `url` or `link` in data payload |
| Badge | APNs payload | OS-managed badge dots |
| `clearBadge()` | Resets to zero | Cancels displayed notifications |
| Config file | `GoogleService-Info.plist` | `google-services.json` |

---

### Complete API Summary

**Facade methods:**
- `PushNotifications::checkPermission()` — check status without prompting
- `PushNotifications::enroll()` — request permission + register for push
- `PushNotifications::getToken()` — get cached device token
- `PushNotifications::clearBadge()` — reset badge count

**Events (PHP):**
- `TokenGenerated` — `token`, optional `id`
- `PushNotificationReceived` — data message payload

**Events (JavaScript):**
- `Events.PushNotification.TokenGenerated` — `{ token }`
- `Events.PushNotification.PushNotificationReceived` — `{ data }`

**Artisan commands:**
- `fcm:send {token}` — send test push notification
- `native:dispatch-push-event` — internal, dispatches data message events

=== nativephp/mobile-scanner rules ===

## nativephp/scanner

Barcode and QR code scanning plugin for NativePHP Mobile applications.

### PHP Usage (Livewire/Blade)

<code-snippet name="Basic Scanning" lang="php">
use Native\Mobile\Facades\Scanner;

Scanner::scan();
</code-snippet>

<code-snippet name="Configured Scanning" lang="php">
use Native\Mobile\Facades\Scanner;

Scanner::scan()
    ->prompt('Scan your ticket')
    ->continuous(true)
    ->formats(['qr', 'ean13'])
    ->id('ticket-scanner');
</code-snippet>

### JavaScript Usage (Vue/React/Inertia)

<code-snippet name="Scanning in JavaScript" lang="javascript">
import { scanner, on, Events } from '#nativephp';

await scanner.scan();

// With configuration
await scanner.scan()
    .prompt('Scan your ticket')
    .continuous(true)
    .formats(['qr', 'ean13'])
    .id('ticket-scanner');
</code-snippet>

### Handling Scan Events

<code-snippet name="Scan Events in PHP" lang="php">
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Scanner\CodeScanned;

#[OnNative(CodeScanned::class)]
public function handleScan($data, $format, $id = null)
{
    // Process the scanned data
}
</code-snippet>

<code-snippet name="Scan Events in JavaScript" lang="javascript">
import { on, Events } from '#nativephp';

on(Events.Scanner.CodeScanned, (payload) => {
    const { data, format, id } = payload;
    // Process the scanned data
});
</code-snippet>

### Configuration Methods

| Method | Purpose |
|--------|---------|
| `prompt(string $prompt)` | Set custom prompt text on scanner screen |
| `continuous(bool $continuous = true)` | Enable multiple scans; default is `false` |
| `formats(array $formats)` | Specify barcode types: `qr`, `ean13`, `ean8`, `code128`, `code39`, `upca`, `upce`, `all` |
| `id(string $id)` | Set unique session identifier for scan context |

### Events

- `CodeScanned` - Fired when barcode successfully scans
  - `string $data` - Decoded barcode content
  - `string $format` - Barcode type detected
  - `string|null $id` - Session identifier if configured

### Requirements

Enable `scanner` permission in `config/nativephp.php`; camera permissions handled automatically.

=== nativephp/mobile-secure-storage rules ===

## nativephp/secure-storage

Encrypted data persistence using iOS Keychain and Android Keystore.

### PHP Usage (Livewire/Blade)

<code-snippet name="Secure Storage Operations" lang="php">
use Native\Mobile\Facades\SecureStorage;

// Store encrypted data
SecureStorage::set('api_token', 'abc123xyz');

// Retrieve encrypted data
$token = SecureStorage::get('api_token');

// Delete stored data
SecureStorage::delete('api_token');
</code-snippet>

### JavaScript Usage (Vue/React/Inertia)

<code-snippet name="Secure Storage in JavaScript" lang="javascript">
import { secureStorage } from '#nativephp';

// Store encrypted data
const result = await secureStorage.set('api_token', 'abc123xyz');

// Retrieve encrypted data
const result = await secureStorage.get('api_token');
const token = result.value;

// Delete stored data
const result = await secureStorage.delete('api_token');
</code-snippet>

### Methods

- `SecureStorage::set(string $key, string|null $value)` - Store encrypted data
- `SecureStorage::get(string $key)` - Retrieve encrypted data (returns `string|null`)
- `SecureStorage::delete(string $key)` - Remove stored data

### Platform Implementation

**iOS**: Uses iOS Keychain Services API with encryption tied to app bundle ID.

**Android**: Uses Android Keystore system with hardware-backed encryption when available.

### Recommended Storage

API tokens, refresh tokens, encryption keys, authentication secrets, sensitive user preferences.

### Not Recommended

Large datasets, non-sensitive information, temporary data, cached content.

</laravel-boost-guidelines>
