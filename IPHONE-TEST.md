# iPhone-test af Cbox ID Authenticator

Koden ligger på `sd/ios-release`. Backendændringerne fra `sd/authenticator-ios-api` er merged og udrullet via PR108.
De eksisterende ændringer i de oprindelige `cbox-id`- og `laravel-id`-checkouts er bevaret.
Mål for den aktuelle telefontest: 1.0.0 (17) på Sylvesters iPhone 16 Pro med iOS27.0.1 via USB. Installeret og versionskontrolleret. Åbn appen på telefonen. Indeholder TOTP, krypteret backup, native glasnavigation og ni redesignede skærme med tydeligere handlinger, formularer, kodekort og mørk tilstand.
Serverrettelserne følges i https://github.com/cboxdk/cbox-id/pull/108.
Separate TOTP-tilmeldinger fra PR109 er også udrullet: serverrevision
`6788b6473f32f246382e7c6339b849d60f7d32d5`, verificeret 2026-11-05 kl. 21:50 UTC
med web 2/2, worker 1/1, laravel-id v1.28.0 og sunde readiness-tjek.
Firebaseprojekt findes endnu ikke; første test er udtrykkeligt uden push.

Build 17 er installeret og verificeret via devicectl; den pakkede rettelse og PHP
8.4.27 er kontrolleret. Face ID kunne afslutte, mens iOS stadig meldte appen inaktiv,
og dermed fejlagtigt udløse beskeden om udløbet autentifikation. Appen afventer nu
forgrunden før afsendelse og afviser fortsat ved faktisk låsning, navigation eller
udløb. Godkendelse på den fysiske telefon afventer en ny testanmodning.
Buildet bevarer rettelsen af hvid firkant i splash og læser Cbox ID’s TOTP-QR med mellemrum korrekt. Det har alle ti ID-sprog,
lokaliseret kamera/applås, korte hjælperubrikker, sprogindstilling og opdateret splash.

238 PHP-tests / 9794 assertions og Larastan består på PHP 8.4.27, inklusive fem
regressionsscenarier for Face ID før forgrund, låsning, navigation og udløb.
De tidligere 11 Swift-livscyklusscenarier,
input-echo-tests og native kilde-/pakningskontrol består. Simulatorforløb gennem
forside → tilslutning → Indstillinger → hjælp → Koder består på alle ti sprog.
Tyske formularer med accessibility-large består, inklusive indtastning, Færdig,
backup, annullering og tilbage til Indstillinger. Dansk/engelsk præsentation af
anmodning, flere konti, kodekort og backup består på en 6,9-tommers simulator.

Simulatoren bruger opfundne visningsdata og tomme formularer uden lokal oplåsning.
Disse fixtures indgår aldrig i telefonbuildet og gendannes efter billederne. Testene
verificerer UI og navigation, ikke fysisk Face ID, Google-login, kamera, push eller Filer.

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
- Den nye offentlige `/authenticator/privacy` kan åbnes uden login. HTTP200 og policyindhold er verificeret efter deployment.

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
| Første konto → QR → systemlogin | Direkte til Cbox ID med kontonavn; ingen ekstra applås eller kvittering, hvis ingen beskyttet konto/kode fandtes før login. Eksisterende låst indhold kræver fortsat oplåsning. |
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
| Fjern én konto lokalt | Rød sletterække flugter med øvrige rækker. Native dialog: Behold kontoen ændrer intet; Fjern konto vender tilbage til Indstillinger. Andre konti fungerer fortsat. |
| Backup / tilføj konto fra Indstillinger | Bliver på Indstillinger-fanen; tilbage fører til den foregående skærm. |
| Hurtig indtastning og rettelser | Alle tegn bevares i kontonavne, tjeneste og adgangskoder; Færdig lukker tastaturet. |
| Genlogin med samme konto / forkert konto | Samme identitet erstattes uden dublet; forkert identitet accepteres ikke som genlogin. |
| Baggrund og appskifter | Konto- og requestoplysninger dækkes; retur kræver oplåsning. |
| Alle ti ID-sprog, lys/mørk, stor tekst, VoiceOver | Labels, knapper, scroll, safe areas og tilbage-navigation fungerer. Dansk, engelsk, tysk, svensk, norsk bokmål, fransk, finsk, spansk, italiensk og nederlandsk. |
| Indstillinger → Sprog | Åbner appens iPhone-indstillinger. Skift sprog og åbn appen igen; native dialoger og skærme følger samme sprog. |
| Kamera afvist | Forklaring og Åbn indstillinger er oversat; link/manual input er fortsat muligt. |
| Hjælp | Emner åbner og lukker; kontakt/licenser kan nås ved scroll, også med stor tekst. |
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

### Separate TOTP-apps til Cbox ID

Serverudrulningen af PR110 og PR109 er verificeret. Denne fysiske accepttest mangler:

1. Åbn **Min konto → Godkendelsesapps** i Cbox ID. Den eksisterende tilmelding skal
   stadig være der og kunne omdøbes til navnet på den app, der har nøglen.
2. Vælg **Tilføj godkendelsesapp**, navngiv den, og vis QR-koden. Scan den fra
   **Koder → Tilføj kode** på iPhone. Tjenesten skal hedde Cbox ID, kontoen skal
   stemme, og der må ikke komme en fejl om forskellige tjenestenavne.
3. Bekræft med koden fra iPhone. Gentag med en ny tilmelding og ny QR-kode til hver
   anden app, eksempelvis 1Password. Hver app skal få sin egen række på serveren.
4. Kontrollér login med hver app; brug en ny kode til hvert forsøg. Fjern derefter
   én tilmelding på serveren. De øvrige apps skal stadig virke, mens den fjernedes
   koder afvises. Gendannelseskoderne må ikke være blevet udskiftet.
5. Start telefonappen i lys og mørk tilstand. Splash-skjoldet skal stå på den fælles
   baggrund uden en hvid firkant.

## 5. Efter godkendt telefontest

Byg et **Release**-archive med produktionsmiljø, APNs og stigende buildnummer.
Kør `php artisan authenticator:check --store --generated=nativephp/ios` før archivering.
Inspicér også det færdige archive: ingen credentials, udviklerserver, OTA-adgang eller
følsomme logs; korrekt privacy manifest, SDK-notices, entitlements og signering.
PHP-inventaret i `resources/third-party.json` og native inventar i
`resources/ios-third-party.json` skal matche det færdige archive. Kør
`php artisan authenticator:ios-notices --check` efter native dependency resolution.

Verificér produktionspush gennem intern TestFlight. Lav faktiske iPhone-screenshots og
et reviewer-forløb med en fungerende testkonto. Kontrollér konto-oprettelse og sletning
på den host, revieweren møder: appen tilslutter eksisterende konti; hvis hostens login
åbner for selvoprettelse, skal en passende sletteanmodning kunne startes derfra.
Afklar export compliance for OpenSSL/DPoP og de faktiske lande, samt App Store-privacy,
rettigheder, kontaktoplysninger og aftaler. Ingen af disse er bekræftet af PHP-tests.

Publicering følger Cbox-releaseprocessen efter test og en konkret releasebeslutning.
