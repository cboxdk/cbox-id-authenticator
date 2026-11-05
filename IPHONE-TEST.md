# iPhone-test af Cbox ID Authenticator

Koden ligger på `sd/ios-release`. Backendændringerne fra `sd/authenticator-ios-api` er merged og udrullet via PR108.
De eksisterende ændringer i de oprindelige `cbox-id`- og `laravel-id`-checkouts er bevaret.
Mål for den aktuelle telefontest: 1.0.0 (9) på Sylvesters iPhone 16 Pro med iOS27.0.1 via USB. Installeret og verificeret. Indeholder TOTP, krypteret backup, native glasnavigation og rettet login/låseforløb.
Serverrettelserne følges i https://github.com/cboxdk/cbox-id/pull/108.
Firebaseprojekt findes endnu ikke; første test er udtrykkeligt uden push.

## 1. Server før telefon

Serveren `cbox.cboxid.com` kører nu API v2, verificeret 2026-11-05 kl. 14:46 UTC. Den normale release-controller har kørt migrationen og udrullet web2/2 samt worker1/1. Brug en frisk QR-kode fra Betroede enheder; tidligere enrollment-koder er udløbet. Brug testkonti, som kan modtage CIBA/action requests.

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
- Kun til push: `GoogleService-Info.plist` fra den rigtige iOS-app i projektroden. Bundle-ID skal være
  `com.cboxid.authenticator`. Firebaseprojektet skal have Apple APNs-konfiguration.
- `.env` med `NATIVEPHP_APP_ID=com.cboxid.authenticator`, samme deeplink-scheme,
  `NATIVEPHP_APP_VERSION=1.0.0`, positivt buildnummer og korrekt Apple development team.
  Versionsfeltet skal være numerisk. Brug `APP_DEBUG=false`, `LOG_CHANNEL=silent` og
  `NATIVEPHP_FPS_OVERLAY=false`, også når der testes med rigtige konti.

Firebase-plisten og signeringsmaterialet er lokale inputs og er ikke committed.
Push kan testes særskilt efter polling-forløbet; permission afvist må aldrig forhindre
at telefonen bliver tilmeldt.

## 3. Byg og installér manuelt

Kør fra `/Volumes/DockDisk/projects/cbox-id-authenticator`. TOTP og native UI kan
testes uden backend/Firebase; Cbox-forbindelser kræver API v2 på hosten fra QR-koden:

```sh
composer install
composer qa
php artisan authenticator:check
php artisan native:install ios
php artisan authenticator:check --generated=nativephp/ios
php artisan native:run ios
```

Forøg `NATIVEPHP_APP_VERSION_CODE` ved hvert installeret testbuild. NativePHP
genbruger ellers den gamle udpakkede appkode, selv om native shell installeres igen.
Det blev bekræftet på telefonen med build 1; build 2 løste det.

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
| Frisk installation | Starter på Cbox ID med tydelig tilslutning. Faner: Cbox ID, Koder, Indstillinger. Koder kan bruges uden Cbox-login. |
| Kun gemte TOTP-koder, ingen Cbox-konto | Starter på Koder. Med Cbox-konti starter appen på Cbox ID. |
| QR → Google-login/password manager | Kameraet lukkes først. Ingen gentagne Face ID-prompts eller skift mellem applås og login. |
| Annullér systemlogin, scan igen | Tilslutningsskærmen bliver klar igen; eksisterende konti og koder bevares. |
| Baggrund under browserlogin → retur | Login kan afsluttes. Eventuel applås åbnes med ét bevidst tryk på Lås op efter browseren. |
| Annullér Face ID ved appstart | Låseskærmen bliver stående uden automatisk nyt prompt; Lås op kan forsøge igen. |
| Frisk installation → QR → systemlogin | Kontoen er tilsluttet-bekræftelse og Cbox ID-fanen vises; ingen webview-skærme. |
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

## TOTP og krypteret backup på telefonen

Brug kun opfundne testkonti og testnøgler til første gennemløb.

1. Koder → Tilføj kode. Scan en standard `otpauth://totp`-QR eller indtast
   `JBSWY3DPEHPK3PXP` som offentlig testnøgle. Gennemse tjeneste/konto, og gem.
2. Kontrollér skift efter 30 sekunder, kopiering til en anden app og offline brug.
   TOTP-uret skal følge automatisk iPhone-tid, også hvis en Cbox-host har tidsafvigelse.
3. Tilføj en anden nøgle, søg, omdøb og fjern kun én. Annullering af sletning bevarer den.
4. Baggrund, appskifter og telefonlås skal skjule koderne. Retur kræver Face ID/kode.
5. Backup → ny adgangskode på mindst 12 tegn → gentag → gem i Filer. Annullering
   må ikke påstå at filen er gemt. Filen må ikke indeholde læsbare kontonavne eller nøgler.
6. Vælg filen igen: forkert adgangskode ændrer intet. Korrekt adgangskode viser
   antal nye/dubletter før import. Genimport bevarer eksisterende navne uden dubletter.
7. Fjern en testkode, importér backup, og bekræft at den bliver gendannet. Eksisterende
   Cbox-konti bevares. Fjernelse af sidste Cbox-konto må ikke slette TOTP eller slå låsen fra.
8. Importér en ødelagt fil, en anden filtype og en for stor fil: tydelig fejl, ingen ændring.
   Afbryd importoversigten med baggrund/lås; en gammel bekræftelse må ikke importere.
9. Installér næste build oveni, og kontrollér at Keychain-koderne stadig findes.
   Test gendannelse på en anden enhed inden App Store-release.

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
