# iPhone-test af Cbox ID Authenticator

Koden ligger på `sd/ios-release`. Backendændringerne ligger separat på
`sd/authenticator-ios-api` i `/Users/sylvester/.codex/worktrees/authenticator-ios-server/cbox-id`.
De eksisterende ændringer i de oprindelige `cbox-id`- og `laravel-id`-checkouts er bevaret.
Der er endnu ikke lavet en ny native installation, signeret build eller deployment.

## 1. Server før telefon

Deploy backendbranchen til testmiljøet med den normale Cbox-procedure, og kør den nye
migration for `id_approval_reports`. Brug testkonti, som kan modtage CIBA/action requests.

Kontrollér:

- `CBOX_ID_DEVICES_ENABLED=true`.
- Den offentlige authenticator-klient er provisioneret i det rigtige environment.
  Kommando: `php artisan cbox-id:devices:client --environment=<id> --host=<host>`.
- `GET /.well-known/cbox-authenticator` på denne host svarer med korrekt issuer,
  client-ID og `api_version: 2`. Appen afviser ældre kontrakter før login.
- Til push: `CBOX_ID_DEVICES_TRANSPORT=fcm`, korrekt project-ID og en servermonteret
  service-account-fil. Serverens private Firebase-nøgle skal aldrig ind i apprepoet.
- Køworker og scheduler kører. Tidsfølsomme retries bliver samlet op af sweepet;
  en kort `next_retry_at` er ikke et løfte om levering på samme sekund.
- Den nye offentlige `/authenticator/privacy` kan åbnes uden login. Den er først en
  brugbar App Store-policy-URL, når denne deployment er gennemført.

Fjernede telefoner beholdes som tilbagekaldte nøglebindinger. Rul ikke revocation-
kontrollen tilbage som en løsning på en klientfejl. Ved problemer kan device-featuret
slås fra, mens en fejl undersøges. Den nye rapporteringstabel kan blive liggende ved
rollback; slet ikke rapporterne for at rulle appkode tilbage.

## 2. Lokale forudsætninger

- Xcode med iOS SDK, Apple signing team og en tilsluttet, oplåst iPhone i Developer Mode.
- PHP 8.4 på PATH for appen. Mac'ens øvrige PHP-version behøver ikke ændres:
  `export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"` i appens terminal.
- `composer install` med de eksisterende NativePHP-marketplace-rettigheder.
- `GoogleService-Info.plist` fra den rigtige iOS-app i projektroden. Bundle-ID skal være
  `com.cboxid.authenticator`. Firebaseprojektet skal have Apple APNs-konfiguration.
- `.env` med `NATIVEPHP_APP_ID=com.cboxid.authenticator`, samme deeplink-scheme,
  `NATIVEPHP_APP_VERSION=1.0.0`, positivt buildnummer og korrekt Apple development team.
  Versionsfeltet skal være numerisk. Brug `APP_DEBUG=false`, `LOG_CHANNEL=silent` og
  `NATIVEPHP_FPS_OVERLAY=false`, også når der testes med rigtige konti.

Firebase-plisten og signeringsmaterialet er lokale inputs og er ikke committed.
Push kan testes særskilt efter polling-forløbet; permission afvist må aldrig forhindre
at telefonen bliver tilmeldt.

## 3. Byg og installér manuelt

Kør fra `/Volumes/DockDisk/projects/cbox-id-authenticator`, efter backend er klar:

```sh
composer install
composer qa
php artisan authenticator:check
php artisan native:install ios
php artisan authenticator:check --generated=nativephp/ios
php artisan native:run ios
```

Vælg den fysiske iPhone. `native:install` gendanner den afledte iOS-shell; gem eventuelle
private signeringsfiler først. Kør ikke watch/Jump som del af denne accepttest.
Hvis sikkerheds-hooken fejler, skal kildekoden rettes og projektet genereres igen.
Fjern aldrig Swift-buildstoppet manuelt, og sæt aldrig `CBOX_AUTHENTICATOR_SOURCE_CHECK`
på appens target.

## 4. Første accepttest

Registrér iPhone-model, iOS-version, appversion/build, backendcommit, host og resultat.
Gem screenshots uden rigtige personoplysninger eller QR-/tilslutningskoder.

| Forløb | Forventet resultat |
| --- | --- |
| Frisk installation → QR → systemlogin | Verificeret konto vises; ingen webview-skærme. |
| Kun telefonen | Indtast host → Betroede enheder → tilslutningslink → samme loginforløb. |
| Afvis kamera eller annullér scanner | Linkvejen er stadig tilgængelig. |
| Afvis notifikationer / forsinket push-token | Kontoen tilmeldes; anmodninger vises ved polling. |
| Aktivér notifikationer i iOS Settings → retur → synkronisér | Den rigtige konto bliver push-tilsluttet. |
| Godkend med Face ID eller enhedskode | Præcis én beslutning, først efter lokal bekræftelse. |
| Annullér Face ID / lås telefonen / baggrund under dialog | Ingen godkendelse sendes; gammelt resultat kan ikke bruges efter retur. |
| Afvis / Det var ikke mig | Afvist; “ikke mig” registrerer desuden rapport og audit på serveren. |
| Udløb / besvaret på web / offline / POST-timeout | Korrekt status eller mulighed for opdatering, ingen automatisk gentaget beslutning. |
| To brugere på samme host + en anden host | Kontonavne og push-links åbner altid den rigtige konto. |
| Push i forgrund, baggrund og kold start | Rigtig anmodning/konto; ingen følsomme detaljer på låseskærmen. |
| Fjern telefonen via web, mens appen stadig har tokens | Appen får afslag på både læsning og godkendelse; ny nøgle/tilmelding kræves. |
| Fjern én konto lokalt | Andre konti fungerer fortsat. |
| Genlogin med samme konto / forkert konto | Samme identitet erstattes uden dublet; forkert identitet accepteres ikke som genlogin. |
| Baggrund og appskifter | Konto- og requestoplysninger dækkes; retur kræver oplåsning. |
| Dansk/engelsk, lys/mørk, stor tekst, VoiceOver | Labels, knapper, scroll, safe areas og tilbage-navigation fungerer. |
| Opgradering / geninstallation | Gemte Keychain-konti håndteres uden utilsigtet nulstilling; antag ikke, at afinstallation sletter Keychain. |

## 5. Efter godkendt telefontest

Byg et **Release**-archive med produktionsmiljø, APNs og stigende buildnummer.
Kør `php artisan authenticator:check --store --generated=nativephp/ios` før archivering.
Inspicér også det færdige archive: ingen credentials, udviklerserver, OTA-adgang eller
følsomme logs; korrekt privacy manifest, SDK-notices, entitlements og signering.
PHP-inventaret i `resources/third-party.json` skal suppleres med de native SDK-versioner
og licenser fra archive/Package.resolved/Podfile.lock.

Verificér produktionspush gennem intern TestFlight. Lav faktiske iPhone-screenshots og
et reviewer-forløb med en fungerende testkonto. Kontrollér konto-oprettelse og sletning
på den host, revieweren møder: appen tilslutter eksisterende konti; hvis hostens login
åbner for selvoprettelse, skal en passende sletteanmodning kunne startes derfra.
Afklar export compliance for OpenSSL/DPoP og de faktiske lande, samt App Store-privacy,
rettigheder, kontaktoplysninger og aftaler. Ingen af disse er bekræftet af PHP-tests.

Publicering følger Cbox-releaseprocessen efter test og en konkret releasebeslutning.
