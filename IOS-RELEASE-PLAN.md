# Cbox ID Authenticator — analyse og plan for iOS-release

Dato: 5. november 2026. **Implementeringen er klar til fysisk iPhone-test. App Store-release er endnu ikke valideret.**

Appen bruger NativePHP 4 / SuperNative med native SwiftUI-rendering. Kodearbejdet ligger på `sd/ios-release`; serverkontrakten ligger på `sd/authenticator-ios-api` i et separat worktree. De oprindelige lokale backendændringer er bevaret.

## Implementeringsstatus efter analysen

| Område | Implementeret | Resterende accept |
| --- | --- | --- |
| A01, A02 | Korreleret completion-event, native app-lås/generation, DPoP/enrolment-guard og fælles revocation med refresh-tilbagekaldelse. Test med rigtigt udstedt token består. | Face ID, kodefallback, låsning og app-lifecycle på fysisk iPhone. |
| A03, A04 | Push v2 mapper installation til lokal konto; notification-routing er begrænset i Swift. Tilmelding sker før push-tilladelse; tokenrotation genforsøges. | APNs/FCM i forgrund, baggrund og kold start, samt permission afvist/senere tilladt. |
| A05–A09 | Verificeret identitet, genlogin/deduplikering, rapporter/audit, API-statusser, trust/discovery, atomisk credential-lagring og beskyttet oprydning. | Samlet brugerforløb, netværksskift og opgradering/geninstallation. |
| TOTP | RFC 6238-vektorer for SHA1/256/512, 6/8 cifre, QR/manuelt input, Keychain-vault, søgning, kopiering med udløb og sletning. Argon2id/XChaCha20-Poly1305-backup med native Filer, importoversigt og atomisk dubletfri sammenfletning. | Face ID, QR, kopiering, eksport/import og opgradering på fysisk telefon. |
| Native UI | Kontodetaljer, alle 10 ID-sprog, native permission/applås, emneopdelt hjælp, sprogindstilling og mørk/lys. Screenshots og store skrifter er kontrolleret. | Fysisk VoiceOver, kameratilladelse og telefonaccept. |
| A10 | Dependencies opdateret uden kendte Composer-advisories; PHP 8.4-platform låst; CI, Larastan, pakningscheck og versioneret produktionsinventar/licenscheck. Swift-plugin og transformerede SDK-kilder kontrolleres. | Ren signeret archive og marketplace-distributionsrettigheder. Native SDK-inventar er nu genereret og kontrolleret. |
| A11 | Offentlig policy-side på backendbranchen, native hjælp, metadataudkast og konkret telefontestvejledning. | Firebase/APNs, distribution/TestFlight, reviewer-konto, App Store Connect og export compliance. Policy er deployed og verificeret. |

Verifikation efter implementeringen: **230 app-tests / 9746 assertions på PHP 8.4.27**, Larastan uden fejl, Composer-audit uden advisories, valideret plugin/privacy-plist og native iOS-kompilering. Server-PR108 er merged; main-CI, SQLite/PostgreSQL/MySQL-tests, browser og begge image-arkitekturer er grønne. Revision `d1ffbaaa40cf0aae791e063495b0b47d1877f457` blev automatisk udrullet 2026-11-05 kl. 14:45 UTC. `cbox.cboxid.com` annoncerer nu API v2, og privacy-siden svarer HTTP200. iOS-runtime er PHP8.4.27. Native TabView/NavigationStack og glasmenu er visuelt kontrolleret i iOS27-simulatoren, herunder tilslutning. Appens Swift-CI er grøn; PHP-CI mangler `COMPOSER_AUTH` til NativePHP-marketplace (HTTP401), så lokale kontroller er endnu ikke reproduceret på GitHub. Brugeren har bekræftet, at Cbox-tilslutning virker. Build 10 er installeret og åbnet over USB. Den retter klippet sletterække med native bekræftelse, input der mistede tegn, formularafstande og backup-navigation fra Indstillinger. Første verificerede login afsluttes uden ekstra oplåsning eller konto-kvittering; eksisterende beskyttede data forbliver låst. 11 Swift-livscyklustests og input-echo-tests består. To XCUITest-forløb består med simulator-fixtures: kontofjernelse samt formularer/tastatur/backup-navigation; disse verificerer ikke fysisk biometri eller filimport. Dansk tilslutning er tidligere visuelt kontrolleret i lys/mørk og med større tekst. Google/Face ID-forløbet på telefonen samt TOTP og krypteret backup afventer fysisk accept. Firebaseprojekt er endnu ikke oprettet; første test sker efter aftale uden push.

Build 11 er installeret over USB og har en samlet designrunde på ni native skærme: fælles kort, ikoner, farver, typografi og vedvarende feltlabels. Kontooversigten kan åbnes direkte fra forsiden; TOTP viser nedtælling og lokal kopieringsfeedback. UI-forløb består i lys/mørk og med stor tilgængelighedstekst. Eksempelkonti og anmodninger er visuelt kontrolleret separat med simulator-fixtures, som ikke indgår i buildet.

## Rettelser — build 15

Installeret via USB og verificeret med devicectl. Splash-generatoren rasteriserer nu
SVG med alpha bevaret; den hvide firkant i lys/mørk er væk. QR-parseren læser
form-encoded query-parametre (`Cbox+ID`) som Cbox ID-serveren udsender dem, uden at
ændre literal plus i labels eller acceptere reelle issuer-konflikter. 233 PHP-tests /
9762 assertions og Larastan består. Scanning på den fysiske telefon afventer brugerens test.

Separate, navngivne TOTP-tilmeldinger udvikles i serveren og ID-pakken; de kræver en
selvstændig serveropdatering og er ikke gjort tilgængelige af telefonbuildet alene.

## UI/UX-runde — build 12–14

**Status:** implementeret og installeret som 1.0.0 (14) over USB; automatisk åbning
var blokeret af telefonlåsen. Ingen App Store-upload. Det godkendte design fra build
11 er bevaret. Denne runde retter sprog, hjælp, native dialoger og tilgængelighed.

1. **Forside → tilslutning → faner.** XCUITest har gennemløbet flowet på alle ti ID-sprog.
   Ingen engelske fallbackoverskrifter i de kontrollerede skærme. Google-login og
   fysisk Face ID er ikke simuleret som en bestået accepttest.
2. **Indstillinger → hjælp → åbne/lukke emne.** Den tidligere lange tekstside er
   erstattet af seks emner med korte forklaringer. Hjælp til forkert tid på TOTP og
   kameratilladelse er kommet til. Native knaprækker eksponerer nu deres label,
   forklaring og knaprolle. Kodekort inkluderer tjenesten og cifre enkeltvis; dette rettede også en faktisk manglende accessibility-label
   på backup-rækken. Kontakt og licenser er fortsat tilgængelige ved scroll.
3. **Koder → tilføj → tastatur → backup → annullér → tilbage.** Tysk med mørk
   tilstand og accessibility-large består. Lange tyske/finske hero-tekster blev
   forkortet efter visuel gennemgang; brugerens skriftstørrelse begrænses ikke.
4. **Systemdialoger og genopretning.** Kamera, Face ID-begrundelse og applås bruger
   samme ti sprog som PHP-skærmene. Ved afvist kamera kan brugeren åbne Indstillinger.
   Kamera-/Settings-handoff og faktisk VoiceOver-oplæsning skal accepteres på telefonen.
5. **Ikoner, splash og releasegrundlag.** Eksisterende Cbox-appikon er verificeret
   som 1024×1024 RGB uden alpha. Lys/mørk splash og applås følger appens farver.
   97 Composer- og 9 native afhængigheder har notices; native lockfile-fingerprints og
   alle sprogresurser kontrolleres ved store-preflight.

Sproglisten matcher `cbox-id/config/cbox-id.php`: en, da, de, sv, nb, fr, fi, es, it,
nl. Alle kataloger har 289 ikke-tomme tekster og matchende interpolationsparametre.
Sprog vælges via iOS, også pr. app. Oversættelserne er implementeret og UI-testet;
modersmålsreview af alle tekster er endnu ikke udført.

**Evidens:** 60 skærmbilleder fra ti sprog i build 12; mørk/stor tekst på dansk,
tysk og finsk; fem tyske formularbilleder og tolv danske/engelske billeder i build 13.
Udvalgte screenshots er visuelt gennemgået, ikke samtlige mulige tilstande.
6,9-tommers screenshots er 1320×2868 og bruger fiktive konti/koder i simulatorens
visningslag. De er udkast; skærmen uden push skal genoptages med den endelige opsætning.
Ingen fixture-data eller ændret applås er med i telefonbuildet.

Lokale billeder: `/Users/sylvester/.codex/visualizations/2026/11/05/01a1ab2a-d676-78c2-998e-72dc010d27f3/build13/`.
Testresultater: `/tmp/cbox-native-ui-tests/polish-languages12-final.xcresult`,
`polish-forms13.xcresult`, `polish-dark-large13.xcresult`, `a11y-codes14.xcresult` og `store13.xcresult`. Simulatorbilleder dokumenterer
præsentation/navigation, ikke krypterings-, kamera-, push- eller biometriaccept.

Accepterede visuelle eksempler fra denne runde:

- Trin 2, hjælp: korte emner med genkendelige ikoner; resten kan nås ved scroll.
  [Screenshot](/Users/sylvester/.codex/visualizations/2026/11/05/01a1ab2a-d676-78c2-998e-72dc010d27f3/build13/store/da-06-help.png).
- Trin 3, tysk formular: felter og begge knapper kan nås med stor skrift; teksten brydes
  uden at begrænse Dynamic Type.
  [Screenshot](/Users/sylvester/.codex/visualizations/2026/11/05/01a1ab2a-d676-78c2-998e-72dc010d27f3/build13/de-large-backup-bottom.png).
- Trin 3, TOTP: tydelige koder, kopieringshandling og udløb pr. konto.
  [Screenshot](/Users/sylvester/.codex/visualizations/2026/11/05/01a1ab2a-d676-78c2-998e-72dc010d27f3/build13/store/da-03-codes.png).

**Før submission:** Firebaseprojekt + APNs/FCM; fysisk Google/Face ID/TOTP/Files-test;
signeret Release-archive og intern TestFlight med produktionspush; reviewer-konto,
App Store-privacy/export compliance og publiceringsbeslutning. Strict store-preflight
rapporterer i øjeblikket den manglende `GoogleService-Info.plist`. PHP-CI mangler
fortsat marketplace-COMPOSER_AUTH; lokale QA-kontroller er grønne.

Se [IPHONE-TEST.md](IPHONE-TEST.md) for telefonaccept og [STORE-LISTING.md](STORE-LISTING.md)
for metadata på ti sprog. Analysen nedenfor bevarer de oprindelige fund og estimater;
den beskriver udgangspunktet før rettelserne, ikke resterende uimplementerede fejl.

## 1. Afgrænsning og evidens

V1 omfatter Cbox ID-godkendelser samt lokale TOTP-koder, krypteret backup og import/eksport. Sylvester udvidede udtrykkeligt omfanget den 5. november 2026. Synkronisering venter. Alle skærme er native; både Cbox-konti og TOTP-koder kan administreres enkeltvis. Import understøtter standard-TOTP-QR/manuelle nøgler og appens eget krypterede backupformat; andre apps’ samlede migrationsformater er ikke implementeret.

| Område | Grundlag og resultat |
| --- | --- |
| App | Lokal `feat/multi-account`, commit `a999483`. Rent checkout før analysen. |
| Integration til main | Branchen indeholder 13 commits efter main. Remote har kun main på `5d8a3e7`; forbedringerne ligger derfor kun lokalt. GitHub-søgning fandt ingen PR eller issues til appen. |
| Server | `cbox-id` lokalt på `2af61b3f` med eksisterende ændringer og nyere origin/main. Push-format, approval-ruter og device DELETE blev også læst fra GitHubs aktuelle main og har samme relevante mangler. |
| Identity engine | Lokal `laravel-id` og den version, serveren har installeret. Der er eksisterende ændringer i begge backend-checkouts. Analysen har ikke ændret deres produktkode. |
| App-tests | `php artisan test --compact`: **100 bestået, 232 assertions**. Native bridge og HTTP er simuleret i disse tests. |
| Server-tests | `DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL='' vendor/bin/pest tests/Feature/Devices --compact`: **119 bestået, 443 assertions**. Ikke hele serverens test suite. |
| Composer | Manifestet validerer. `composer audit --no-dev --format=json`: **19 advisories i 8 pakker**, exit 1. Ingen abandoned packages rapporteret. |
| Plugins | Alle 6 krævede plugins registreret: UI, browser, biometrics, secure storage, scanner og Firebase. |
| iOS | Genereret Xcode-projekt og tidligere Debug-build findes lokalt. Xcode 27.0 installeret; projektets deployment target er iOS 18.2. Ingen simulator var startet. |
| Verifikationsgrænse | Ingen ny native build, fysisk iPhone-test, visuel skærmgennemgang, live login, push-levering eller App Store Connect-validering udført i denne analyse. UI-vurderingen nedenfor er baseret på kode og templates. |

To målrettede reproduktioner blev kørt uden ændring af produktkoden:

```text
Biometric.Prompt med iOS-broens svar {status: success, data: {}}
→ PHP prompt() returnerer true uden noget completion-event.

NativeRouter::resolve('/approvals/01REQUEST')
→ ingen komponent.

NativeRouter::resolve('/approvals/01ACCOUNT/01REQUEST')
→ ApprovalDetail.
```

Tests og kodegennemgang kan fastslå disse kontraktfejl. De kan ikke dokumentere kvaliteten af en distribueret iOS-app.

## 2. Hvad der allerede findes

Appen har seks native komponenter: Launch, Connect, OAuthCallback, Approvals, ApprovalDetail og Settings. De registreres med `Route::native()` og bruger EDGE-komponenter. Requests og Settings deler en native navigation- og tabbar. Login åbner `ASWebAuthenticationSession` via browser-pluginet; det er en passende systemstyret autentificeringsflade ved siden af appens native UI.

Konti har separate lokale ID'er, DPoP-nøgler, tokens og installation-ID'er. Den fælles liste henter anmodninger fra alle konti og sorterer efter udløb. Delvise netværksfejl markeres pr. konto. PKCE-forsøg gemmes separat efter state, og der er oprydning af opgivne forsøg samt migration fra den tidligere enkeltkonto-model.

Serverens devices-modul har bootstrap/discovery, tilmeldingskoder, device-registrering, approval-API, audit, FCM-transport, kø, retries, circuit breaker, oprydning og en webflade til brugerens telefoner. Både CIBA-login og konkrete action approvals kan oprette push. Action approvals bruger også CIBA-tabellen, så de er ikke automatisk udelukket fra mobil-API'et; deres formål vises dog ikke tydeligt i API-svaret eller appen.

Det er derfor relevant at genbruge både appen og servermodulet. En omskrivning af hele produktet til Swift er ikke nødvendig for at opfylde kravet om native UI.

## 3. Releaseblokeringer

Prioritet **P0** betyder sikkerhedsstop før reel brug. **P1** betyder, at problemet skal være løst før ekstern beta eller submission. Fund baseret på kode er angivet som sådanne; driftsforhold er ikke antaget ud fra lokale filer.

### A01 — P0: Godkendelse venter ikke på Face ID

`ApprovalDetail::decide()` bruger returværdien fra `Biometrics::prompt()->prompt()` som en autentificeringsbeslutning. Den installerede iOS-plugin starter LocalAuthentication asynkront og returnerer straks et tomt resultat. Den tidligere genererede Swift-bro indpakkede dette i `status: success`, som PHP læste. Den aktuelt låste template returnerer et fladt tomt objekt; med den variant returnerer den gamle PHP-wrapper false. Begge kontrakter var forkerte som autentificeringsbeslutning. Den rigtige beslutning kommer senere som et event, som skærmen ikke lytter til.

**Konsekvens:** Med den indpakkede bro kan approve blive sendt før Face ID eller enhedens kode; med den aktuelle flade bro bliver approve i stedet blokeret. Ingen af varianterne afventede den reelle beslutning. Den nye implementering accepterer kun et matchende, vellykket completion-event. Den præcise gamle adfærd på en distribueret telefon er ikke målt.

**Løsning og accept:** En eksplicit tilstand for afventende autentificering. Et unikt attempt-ID bindes til konto, request og skærm. Kun et matchende, succesfuldt completion-event må udløse approve. Annullering, fejl, låsning, navigation væk, udløb og gamle/duplikerede events skal sende nul godkendelser. Deny skal fortsat kunne bruges uden biometrisk kontrol. UI skal beskrive, at iOS også kan bruge enhedens kode.

Kilder: [ApprovalDetail](/Volumes/DockDisk/projects/cbox-id-authenticator/app/NativeComponents/ApprovalDetail.php:136), [PHP-kaldet](/Volumes/DockDisk/projects/cbox-id-authenticator/vendor/nativephp/mobile/src/PendingBiometric.php:85), [iOS-plugin](/Volumes/DockDisk/projects/cbox-id-authenticator/vendor/nativephp/mobile-biometrics/resources/ios/BiometricFunctions.swift:30), [broens svar](/Volumes/DockDisk/projects/cbox-id-authenticator/nativephp/ios/NativePHP/Bridge/BridgeRouter.swift:220).

### A02 — P0: En fjernet telefon beholder API-adgang

Både mobil-API'et og brugerens remove-action sletter device-rækken og skriver audit. Approval-ruterne kræver scopes og validerer en eksisterende DPoP-binding, men kontrollerer ikke, at nøglen stadig tilhører en aktivt tilmeldt enhed. Ingen tilsvarende token-tilbagekaldelse ses i den gennemgåede fjernelsesvej.

**Konsekvens udledt af kode:** Fjernelse stopper push til en tabt telefon, men en telefon med sine tokens og sin nøgle kan stadig hente og besvare anmodninger. Det skal reproduceres med rigtige tokens i en regressionstest; de eksisterende API-tests bruger overvejende simuleret introspektion.

**Løsning og accept:** Knyt mobilautorisation til `(environment, subject, client, dpop_jkt, installation)`. En tilbagekaldt tilmelding må ikke læse eller beslutte approvals, heller ikke med et gammelt access token. Tilbagekald tilhørende refresh-grants efter den valgte tokenmodel. Brug samme revocation-service fra alle remove-veje. Genaktivering kræver ny, gyldig tilmelding. Gør DPoP obligatorisk for denne mobilkontrakt, frem for alene at kontrollere det, når et token har `cnf`.

Registrering skal fungere uden push-tilladelse: **en tilmeldt enhed og en leverbar push-token er to forskellige tilstande**. Ellers vil den nødvendige autorisationskontrol bryde polling for brugere, der siger nej til notifikationer.

Kilder: [DeviceController](/Volumes/DockDisk/projects/cbox-id/modules/devices/src/Http/Controllers/DeviceController.php:172), [RemoveMyDevice](/Volumes/DockDisk/projects/cbox-id/modules/devices/src/Actions/RemoveMyDevice.php:54), [RequireScope](/Volumes/DockDisk/projects/cbox-id/app/Http/Middleware/RequireScope.php:38).

### A03 — P1: Push åbner en rute, der ikke findes

Serveren sender `com.cboxid.authenticator://approvals/{request_id}`. Appen forventer `/approvals/{local_account_id}/{request_id}`. Det lokale konto-ID kender serveren ikke; payloaden mangler også en entydig kobling til tilmeldingen. Problemet findes i både CIBA- og action-push.

**Løsning og accept:** En versioneret push-kontrakt med request-ID og en opak identifikator for den registrerede enhed/konto samt et valideret issuer-hint, hvor nødvendigt. Appen mapper til en allerede tilmeldt lokal konto, henter detaljerne med dens token og viser først derefter anmodningen. En ukendt eller tvetydig reference åbner en forklarende indbakke, aldrig et automatisk godkendelsesforløb. Push må ikke vælge en ny netværkshost eller indeholde tokens, binding message eller andre følsomme detaljer. Test med to brugere på samme host og to hosts med samme request-ID.

Kilder: [serverens payload](/Volumes/DockDisk/projects/cbox-id/modules/devices/src/Decorators/PushNotifyingBackchannelAuthentication.php:143), [mobilruter](/Volumes/DockDisk/projects/cbox-id-authenticator/routes/mobile.php), [routingtests](/Volumes/DockDisk/projects/cbox-id-authenticator/tests/Feature/DeepLinkTest.php).

### A04 — P1: Førstegangstilmelding mister koden, når push-token kommer senere

Enrolment spørger om push-tilladelse og læser straks den cachede token. Hvis den endnu ikke findes, afsluttes login uden device-registrering. Det efterfølgende `TokenGenerated`-event kalder `register()` uden den oprindelige tilmeldingskode, mens serveren kræver den ved første registrering. Fejlen sluges. Settings' re-sync har samme begrænsning.

**Løsning og accept:** Tilmeld enheden med koden uafhængigt af push. Gem en vedvarende status for tilmelding og leveringskanal; opdatér push-token separat og idempotent. Håndtér kodeudløb, afbrudt netværk, genstart og senere tilladelse med en tydelig genoptagelsesvej. Ingen bruger må vises som push-klar, før registrering og tokenopdatering er bekræftet.

Kilder: [DeviceEnrolment](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/DeviceEnrolment.php:49), [callback](/Volumes/DockDisk/projects/cbox-id-authenticator/app/NativeComponents/OAuthCallback.php:106), [token-event](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Providers/PushTokenServiceProvider.php:26).

### A05 — P1: Flere konti mangler verificeret brugeridentitet og genlogin

Account har et subject-felt, men callback udfylder det ikke. Konti på samme issuer får derfor samme host-label. Et nyt login opretter altid et nyt lokalt konto-ID; der er ingen færdig reauthentication-vej for en udløbet eller tilbagekaldt session. OAuthClient sender nonce, men gemmer eller validerer ikke et ID-token.

**Løsning og accept:** Hent verificeret brugeridentitet fra et korrekt beskyttet userinfo-endpoint, eller implementér fuld ID-token-validering med signatur, issuer, audience, expiry og gemt nonce. Et dekodet JWT alene er ikke tilstrækkeligt. Vis menneskeligt kontonavn plus host. Identificér kontoen ved issuer + subject, og genautentificér den eksisterende konto uden at skabe dubletter. Skeln mellem offline, session udløbet og enhed tilbagekaldt.

Kilder: [Account](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Accounts/Account.php), [OAuthCallback](/Volumes/DockDisk/projects/cbox-id-authenticator/app/NativeComponents/OAuthCallback.php:108), [OAuthClient](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/OAuthClient.php:81).

### A06 — P1: “This wasn't me” rapporterer ingen hændelse

Appen sender samme deny-request for begge knapper. `reason = not_me` ændrer kun det lokale succesbudskab til “Reported and denied”. Serveren læser ingen reason og registrerer almindelig denial.

**Løsning og accept:** En defineret rapporteringshandling, som både afviser og registrerer et sikkert, idempotent risikosignal/audit-event. Vis kun “reported”, når serveren har bekræftet det. Beskriv relevante næste handlinger uden automatisk at tilbagekalde alle brugerens andre sessioner.

Kilder: [wasNotMe](/Volumes/DockDisk/projects/cbox-id-authenticator/app/NativeComponents/ApprovalDetail.php:131), [API-klient](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/CboxApi.php:115), [serverbeslutning](/Volumes/DockDisk/projects/cbox-id/modules/devices/src/Http/Controllers/ApprovalController.php:82).

### A07 — P1: Fejl og afsluttede anmodninger vises forkert

Alle ikke-succesfulde beslutningssvar bliver til “Already handled”, også 403, 429 og 500. Detail-fetch behandler RuntimeException som en manglende anmodning. Et timeout efter POST kan betyde, at serveren allerede har godkendt, men skærmen har ingen afklaringsvej. Detailtimeren bruger telefonens ur, mens DPoP bruger korrigeret servertid. `tick()` genhenter ikke status, og API'ets detaljesvar indeholder ikke status.

**Løsning og accept:** En typed fejlmodel for uautoriseret, tilbagekaldt, ikke tilgængelig, allerede afsluttet, throttled, offline og serverfejl. Efter usikkert POST-resultat genhentes status før et nyt forsøg. API returnerer formål, status og serverbaseret udløb. Opdatér detaljen ved resume og med en passende pollingfrekvens; brug samme tidsgrundlag som resten af appen.

Kilder: [CboxApi](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/CboxApi.php:88), [detailtimer](/Volumes/DockDisk/projects/cbox-id-authenticator/app/NativeComponents/ApprovalDetail.php:91), [API-præsentation](/Volumes/DockDisk/projects/cbox-id/modules/devices/src/Http/Controllers/ApprovalController.php:139).

### A08 — P1: Discovery kan flytte forbindelsen uden ny validering

Connect kontrollerer den scannede host mod `cboxid.com`, men HostDiscovery accepterer svarenes issuer som en vilkårlig streng. HTTP-redirects og den endelige issuer er ikke bundet til den godkendte host i appkoden. Callback læser heller ikke issuer-parametret som en forventet identitet. Loopback-undtagelser er ikke begrænset til development-builds.

**Løsning og accept:** Fastlæg tilladte origin-regler for discovery, redirects og issuer; kræv HTTPS, godkendt host og matchende issuer. Valider callback-issuer, når protokollen leverer det. Anvend streng skema-/typekontrol på QR og callback, og fjern development-undtagelser fra release. Behold PKCE og single-use state. Dette er et hul i tillidsgrænsen; analysen har ikke påvist en kompromitteret Cbox-host.

Kilder: [HostDiscovery](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/HostDiscovery.php:29), [SupportedHost](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/SupportedHost.php), [DeepLinkQuery](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Support/DeepLinkQuery.php).

### A09 — P1: Token- og Keychain-livscyklus er ikke fejlsikker

DPoP-nøglen og kontoindekset kontrollerer write-fejl. TokenStore skriver derimod access-token, expiry og refresh-token separat uden kontrol af resultaterne. Flere delete-kald ignorerer også resultatet. Ved afbrudt refresh-rotation kan den nye refresh-token gå tabt. Et tabt netværkssvar efter en rotation skal have en defineret genlogin-vej.

**Løsning og accept:** Samlet, versioneret credential-record pr. konto; kontroller writes og deletes; koordiner refresh pr. konto, så samtidige callbacks ikke roterer samme token konkurrerende. Håndtér Keychain locked som midlertidigt utilgængelig, ikke som en ny installation. Test genstart midt i login, rotation, migration og logout. Sign-out skal angive ærligt, hvis servertilbagekaldelse ikke er bekræftet, samtidig med at lokal adgang lukkes.

Kilder: [TokenStore](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Services/TokenStore.php:62), [PendingSignIns](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Accounts/PendingSignIns.php), [AccountServices](/Volumes/DockDisk/projects/cbox-id-authenticator/app/Accounts/AccountServices.php).

### A10 — P1: Aktuel releasekæde og afhængigheder er ikke klar

Audit rapporterer Laravel 13.24.0, Livewire 4.3.5, CommonMark 2.9.0, Flysystem 3.35.2 og fire Symfony-pakker. Der er blandt andet en advisory med critical severity. Det er et fund i dependency-grafen, ikke dokumentation for, at samtlige advisories kan udnyttes i mobilappen.

NativePHP er låst til `dev-main` commit `7b3882647020`, UI til 0.2.0; `composer.lock` giver en konkret revision, men framework-opgraderinger skal kvalificeres. Den indlejrede PHP-version er 8.4.24, mens lokale tests kørte på PHP 8.5.7. Der findes ingen tracked CI-workflow, PHPStan-konfiguration, SBOM, changelog eller LICENSE. Composer siger MIT, mens README siger, at licensen ikke er valgt.

**Løsning og accept:** Opdatér kompatible afhængigheder og kvalificér en fast NativePHP/plugin/runtime-kombination. Test på den indlejrede runtime og en ren buildmaskine. Opret appens CI, statisk analyse, dependency-audit og licens/SBOM-kontrol. Afklar repoets egen licens og plugin-distributionsrettigheder. Generer iOS-shell fra versionerede inputs; undgå nødvendige håndrettelser alene i ignorerede buildfiler eller vendor.

### A11 — P1: Push-opsætning og butiksgrundlag mangler dokumentation

`GoogleService-Info.plist` findes ikke i projektroden, den genererede appkilde eller den eksisterende Debug-app. Den lokale entitlement er `aps-environment = development`; det er normalt for udvikling, men dokumenterer ingen produktionslevering. Der er SDK-privacy manifests i Pods, men ingen app-ejet manifest fundet i app-targetet. Settings har ingen privacy-, support-, konto-sletnings- eller versionsflade.

**Løsning og accept:** Match bundle-ID, Firebase iOS-app, APNs-nøgle og serverens FCM-projekt. Kontrollér den eksporterede distributionsapps faktiske signering, entitlements, manifests og indhold. Verificér push i TestFlight. Færdiggør App Store-metadata og privatlivsoplysninger efter afsnit 7. Apple-konto, agreements, app-record og produktionsnøgler er ikke verificeret i analysen.

## 4. Sikkerhedsmodel og produktgrænser

V1 bør beskrives som en **native godkendelsesapp til Cbox ID**. Den er ikke en generel kodegenerator, en passkey-provider eller dokumenteret phishing-resistent MFA. En binding message, der skal sammenlignes manuelt, er nyttig kontekst; den er ikke samme garanti som WebAuthn. CIBA tillader i dag også en manglende binding message, så produktets sikkerhed kan ikke baseres på, at alle requests har den.

DPoP-nøglen genereres i PHP/OpenSSL og opbevares som PEM i Keychain med `WhenUnlockedThisDeviceOnly`. Det beskytter ved lagring, men gør ikke nøglen non-exportable eller hardwarebundet. En korrekt lokal biometrisk eventkontrol giver heller ikke serveren kryptografisk bevis for user verification. Behold en ærlig v1-sikkerhedsbeskrivelse. Hvis produktet skal love hardwarebundet signering eller dokumenteret user verification, kræver det en separat native SecKey/Secure Enclave-løsning og en passende serverprotokol; det er ikke noget, det eksisterende plugin leverer.

Godkendelsesskærmen skal vise verificeret konto, afsender, formål, relevante tilladelser/ændringer, udløb og sammenligningsbesked. Manglende eller uklar sikkerhedskontekst skal håndteres efter en eksplicit politik. Push og QR er ubetroet input. Der må aldrig godkendes direkte fra notifikationens payload eller fra låseskærmens knapper.

Planlæg app-lås og en privat skærm i app-switcheren. Afbryd en igangværende godkendelsesautorisation ved baggrund/låsning. Opret ikke en baggrundspollingservice: polling er et supplement i forgrunden, mens iOS-notifikationer bringer brugeren tilbage.

## 5. Native iOS-produktet

Behold systemtypografi, SF Symbols, native navigation, native sheets, native dialogs, standard swipe-back og korrekt safe-area-adfærd. Cbox-branding tilpasses native kontroller gennem farver og diskrete flader. Desktop-rails, web-combobox-regler og webdimensioner fra Cbox UI skal ikke kopieres ind på iPhone. De nuværende kølige Slate-farver i `config/native-ui.php` skal afstemmes med Cbox-designets varme neutrale palette, både lys og mørk.

| Flade | Det færdige forløb |
| --- | --- |
| Første start | Kort forklaring, tilslut konto, privatliv og hjælp. Både QR fra computer og et fungerende linkforløb fra samme telefon. Fejl ved kameraadgang har en alternativ vej. |
| Login | Systemets authentication session. Tydelig afbrydelse, kodeudløb og genoptagelse. Ingen permanent spinner, intet råt exception-output. |
| Requests | Fælles liste med tydelig konto, formål, udløb, pull-to-refresh og status for hver forbindelse. “Ingen anmodninger” må ikke skjule, at alle konti er offline. |
| Request | Sikkerhedskontekst først; godkend/afvis/rapportér. Scroll og store tekststørrelser må ikke skjule betydningen af handlingen. Ingen godkendelse efter udløb eller fejlet autentificering. |
| Kontodetalje | Kontoidentitet, host, tilmeldingsstatus, push-status, genlogin og fjern denne enhed. Handlinger på én konto påvirker ikke de andre. |
| Settings | Konti, notifikationer med åbning af iOS Settings, privatliv, support, version/build, licenser og relevant kontosletningsvej. Opdatér status efter retur fra iOS Settings. |
| Sikkerhedshændelser | V1-valg: Enten en enkel kontoafgrænset hændelsesvisning med et passende API, eller klart afgrænsede notifikationer med verificeret link til kontosikkerhed. Den eksisterende app har ingen hændelsesindbakke, så README's løfte skal afstemmes. |

Test VoiceOver-læserækkefølge og labels, Dynamic Type til tilgængelighedsstørrelser, kontrast, mindst 44 pt trykflader, Reduce Motion og lys/mørk/system. Engelske produkttekster med dansk lokalisering følger Cbox-konventionen. Den nuværende hardkodede tekst skal flyttes til oversættelser. V1 holdes iPhone-fokuseret; iPad er allerede deaktiveret og udvides først, når et selvstændigt layout er testet.

De eksisterende 15-sekunders polls henter konti sekventielt med op til 15 sekunders API-timeout pr. kald. Det kan give lang ventetid, når flere hosts fejler. Indfør begrænset samtidighed, status pr. konto, afbrydelse ved navigation/baggrund og styret backoff. Mål tid til brugbar liste, hukommelse og energiforbrug på en fysisk iPhone før finjustering.

## 6. Byggeplan i seks milepæle

Dagene er samlet aktiv udviklingstid med adgang til iOS-signering, testtelefoner og staging. Milepælene er sekventielle på den kritiske vej. Butikstekster og adgangsforberedelse kan foregå sideløbende uden at ændre sikkerhedsprioriteringen.

| Milepæl | Arbejde og ejerskab | Afhænger af | Færdig når | Estimat |
| --- | --- | --- | --- | --- |
| M0 — Fast grundlag og første iPhone-forløb | App: bevar de 13 lokale commits, opret en `sd/` integrationsbranch, fastlæg versioner, opdatér kendt sårbare dependencies. App/drift: staging, Firebase, APNs og signering. Ret A01 før brug med rigtige konti. | Adgang til Apple/Firebase og testkonto | Ren iOS-build kan logge ind; en korrekt afsluttet lokal autentificering kan godkende én request; annullering sender intet. Push kan leveres til testtelefonen, også hvis routing endnu åbner indbakken. | 3–5 dage |
| M1 — Serverkontrakt og revocation | `cbox-id`: A02–A04, obligatorisk mobil-DPoP, registration uden push, versioneret push-routing, status/formål i API, rapporteringshandling. `laravel-id` kun hvor token-revocation/protokol kræver genbrugelig engine-kode. | M0 og aftalt API-kontrakt | Rigtige tokens kan bruges før revocation og nægtes bagefter; førstegangstilmelding virker med forsinket/afvist push; kontrakttests på tværs af repos består. | 5–7 dage |
| M2 — Appens sikkerhed og kontolivscyklus | App: A05–A09, korrelerede biometric-events, sikker discovery, kontogenlogin, robust tokenlagring, typed API-fejl, app-lås og privacy snapshot. | M1 | Hele tilstands- og fejlmatricen er testet. To brugere på samme host og flere hosts fungerer uden forkert routing eller identitetsvisning. | 5–8 dage |
| M3 — Native UI færdig | App/design: alle flader fra afsnit 5, tilgængelighed, dansk/engelsk, visuelle tokens, samme-telefon-onboarding og ærlig notifikationsstatus. Tag faktiske screenshots til designreview. | M2; skærmdesign kan starte tidligere | Fysisk iPhone-gennemgang er godkendt i lys/mørk og stor tekst. Alle fejl har en meningsfuld næste handling. | 4–6 dage |
| M4 — Reproducerbar release og intern TestFlight | App/drift: CI, archive/export, SBOM/licenser, secret- og logkontrol, manifests, export compliance, metadataudkast. Backend: push-kø, observability, retry og produktionskonfiguration. | M1–M3 | Samme commit kan bygges på ren runner og installeres via TestFlight. Produktions-APNs fungerer. Native bundle indeholder kun godkendt konfiguration og kode. | 3–5 dage |
| M5 — Beta, submission og drift | QA/produkt: testmatricen, upgrade/reinstall, reviewer-konto og reproducerbar demo, screenshots, privacy/support, endelige metadata, releaseberedskab. | M4 | Ingen åbne P0/P1. Alle releasekriterier nedenfor dokumenteret. Et konkret build er klar til Sylvesters releasebeslutning. | 5–7 dage |

**Total: 25–38 udviklingsdage.** Planlæg omkring 6–9 kalenderuger med én primær udvikler, fysisk test og mindst en uges stabil betabrug; Apples behandling ligger uden for vores kontrol. Reestimér efter M0. Hvis NativePHP-versionen kræver større renderer- eller bridge-reparationer, gælder estimatet ikke uændret.

M0 er også et teknologivalg med en tydelig grænse: fortsæt, hvis login, Keychain, events, push og de nødvendige native kontroller kan verificeres. En manglende funktion løses først med et vedligeholdt plugin eller en lille, versioneret Swift-plugin. En fuld SwiftUI-omskrivning skal kun vælges på baggrund af en konkret, uløselig runtime-begrænsning og have et nyt estimat. Ingen webview-skærme indføres som løsning på native problemer.

### Konkrete arbejdspakker og afhængigheder

1. **Baseline og CI:** Sikr den lokale branch, etabler reproducérbar dependency-installation og automatisér de nuværende tests. Tilføj sikkerhedsregressionen for biometric completion som første nye test.
2. **Device authorization:** Serverregistrering uden push, fast key-binding, tilbagekaldelsesstatus og fælles remove-service. Denne skal lande før appens endelige polling-only-forløb.
3. **Push contract:** Fælles fixtures for faktisk serverpayload og app-router; migration/fallback for gamle payloads; rigtige cold/warm-start-tests.
4. **Account lifecycle:** Verificeret subject, deduplikering, genlogin, kontonavne, credential-record, refresh-koordinering og migration.
5. **Approval state:** Status/formål i API, typed fejl, biometric attempt-binding, timeout-reconciliation og audit for report.
6. **Native completion:** Onboarding, kontodetalje, help/privacy, app-lås, tilgængelighed og lokalisering.
7. **Distribution:** Ren archive, signing, APNs-production, privacy/export, beta og reviewer-forløb.

API-ændringer udgives bagudkompatibelt før app-buildet, der kræver dem. Opdatér først `laravel-id`, hvis engine-kode er ændret, derefter `cbox-id`, og til sidst mobilappen. Bevar mulighed for at lukke mobiltilmelding eller den berørte approval-funktion på serveren uden at slå andre loginmetoder fra. En tilbagekaldt enhed må aldrig genåbnes som en rollback-effekt.

## 7. App Store og distributionskrav

Apple kræver aktuelt Xcode 26 eller nyere og iOS 26 SDK eller nyere ved upload. Det er et krav til buildværktøjerne, ikke et krav om iOS 26 som minimum for brugerne. Projektets iOS 18.2-target kan beholdes som udgangspunkt, hvis den valgte NativePHP-version og en fysisk enhed på minimumsversionen består testen. Installeret Xcode 27.0 skal stadig kvalificeres med en rigtig distribution-build. [Apples aktuelle krav](https://developer.apple.com/news/upcoming-requirements/).

Før submission skal følgende være konkret klar:

- **Apple-konto og build:** Organisation/team, app-record, entydigt bundle-ID, distributionssignering og monotont buildnummer. Bekræft det rigtige produktionsmiljø i den eksporterede apps APNs-entitlement; en lokal development-entitlement alene afgør ikke export-resultatet.
- **Metadata og reviewer-forløb:** Endeligt navn og kategori, beskrivelser, aldersspørgsmål, faktiske screenshots, supportside og kontakt. En dedikeret konto/tenant skal kunne oprette en frisk tilmelding og anmodning under review; en QR-kode, der er udløbet før review, er ikke tilstrækkelig. Reviewer skal kunne prøve succes, denial og relevante sikkerhedsforløb uden kontakt til en medarbejder. [App Review Guidelines](https://developer.apple.com/app-store/review/guidelines/).
- **Privacy:** Offentligt tilgængelig politik og link inde i appen. Kortlæg kontoidentitet, device/install-ID, FCM-token, sikkerhedsaudit og eventuelle diagnosticeringsdata samt deres lagring og modtagere. App Store-labels skal omfatte relevant SDK-behandling. “Ingen data indsamles” kan ikke vælges alene, fordi appen ikke har analytics. [App Privacy Details](https://developer.apple.com/app-store/app-privacy-details/).
- **Manifests:** Gennemgå appens required-reason APIs og alle indlejrede SDK'er; kontrollér de manifests og signaturer, som den endelige archive faktisk indeholder. Pod-manifests dokumenterer ikke alene appens egen brug. [SDK-krav](https://developer.apple.com/support/third-party-SDK-requirements/).
- **Kontosletning:** Fjernelse af denne telefon og sletning af Cbox ID-kontoen er forskellige handlinger. Hvis appens onboarding tilbyder kontooprettelse, også via et link, skal brugeren kunne starte kontosletning i appen. Afklar kontotyper og brug Cbox ID's eksisterende slettepolitik; implementér ikke automatisk sletning af alle organisationer. [Apples vejledning om kontosletning](https://developer.apple.com/help/app-review/guideline-reference/5-1-1-account-deletion).
- **Login:** Behold `ASWebAuthenticationSession`. Login via egne Cbox-konti er omfattet af en relevant undtagelse i 4.8; den præcise vurdering afhænger af de loginmuligheder, onboarding faktisk tilbyder. Social login skal derfor vurderes i det konkrete forløb, ikke antages undtaget. [Login Services, 4.8](https://developer.apple.com/app-store/review/guidelines/#login-services).
- **Kryptografi:** Gennemfør Apples export compliance-spørgsmål for den faktiske OpenSSL/DPoP/Keychain-brug og distributionslande. Sæt ikke `ITSAppUsesNonExemptEncryption=false` uden den vurdering. [Export compliance](https://developer.apple.com/help/app-store-connect/manage-app-information/overview-of-export-compliance/).

Release-buildet skal være selvstændigt og uden download af ny eksekverbar PHP-kode, Jump/hot-reload-adgang eller udviklingsserver. Gennemgå også browser-pluginets logging af callback-URL og push-pluginets logging af payloads/tokens. Produktionslogs må ikke gemme credentials, tilmeldingskoder eller følsomme requestbeskeder. Kontroller den faktiske archive; `.gitignore` er ikke en pakningspolitik. [Software Requirements, 2.5.2](https://developer.apple.com/app-store/review/guidelines/#software-requirements).

## 8. Test- og releasekriterier

| Testgruppe | Minimum før release |
| --- | --- |
| Autentificering | Frisk installation; passkey/password efter hostens politik; systembrowser annulleret; callback i varm/kold app; forkert state/issuer; gentaget callback; udløbet tilmeldingskode; forkert bruger; mistet netværk. |
| Lokal godkendelse | Face ID eller enhedskode lykkes; annulleres; fejler; ikke tilgængelig; skærm forlades; app baggrundes; request udløber under dialogen; gammelt/duplikeret event. Nul approve før matchende succes-event. |
| Autorisation | Rigtige JWT/DPoP-tokens; forkert subject/issuer/audience/scope/key; replay; fjernet device; ingen tilmelding; tilbagekaldt refresh-grant. En anden konto må aldrig blive påvirket. |
| Push | Første token kommer sent; permission afvist og senere tilladt; token roterer; payload er ugyldig; tap i foreground/background/kold start; to konti på samme host; manglende/duplikeret push. Polling virker uden push. |
| Beslutninger | Approve/deny/report; allerede besvaret på web/anden telefon; 404/409/429/5xx; timeout efter POST; tidsdrift; manglende binding message; action kontra login. |
| Lagring | Keychain-fejl; write/delete-fejl; rotation og processtop; gammel appdata; opgradering med konti; afinstallation/geninstallation; ny telefon/restore. Ingen antagelse om, at Keychain altid slettes ved afinstallation. |
| Native UI | Mindste understøttede iPhone-layout, en nyere iPhone, minimum-iOS og aktuel iOS; VoiceOver, stor tekst, mørk/lys, Reduce Motion, safe areas og navigation. |
| Distribution | Ren clone → locked install → archive → export → TestFlight. Ingen produktionshemmeligheder i bundle. Faktisk produktionspush og reviewer-forløb gennemført. IPv6-only-netværk kontrolleret. |

Testlagene skal supplere hinanden: PHP-tests for tilstande og kontrakter; servertests med ægte token/proof/revocation; iOS-tests for renderer, browser, LocalAuthentication, Keychain og lifecycle; til sidst TestFlight på fysisk hardware. De eksisterende 219 tests er et godt udgangspunkt, men deres grønne status er ikke et end-to-end-bevis.

Foreslåede releasekriterier:

- Alle A01–A11 løst eller, for afgrænsede drifts-/produktvalg, dokumenteret med en konkret godkendt løsning. Ingen åbne P0/P1.
- Ingen approve uden gyldig lokal autentificering; tilbagekaldelse af en enhed stopper dens approval-adgang straks på serveren.
- Alle kontrakt-, app- og relevante servertests grønne. Statisk analyse, dependency-audit, licenscheck og SBOM er automatiseret. Dependency-undtagelser kræver en konkret reachability-vurdering; ikke blot ignorering af hele audit.
- Mindst en uges stabil intern/betabrug på fysiske telefoner med hele testmatricen gennemført; ingen kendte tab af konti/nøgler eller fejl i konto-routing.
- Push-latens, fejlrate og køalder kan ses på serveren uden følsomme payloads. En ansvarlig kan diagnosticere “push kom ikke” og skelne permission, token, queue og APNs/FCM-fejl.
- Drift har en procedure for tilbagekaldt/kompromitteret telefon, FCM/APNs-fejl og en dårlig appversion. Store-rollback er ikke øjeblikkelig; serverens kompatibilitet og afgrænsede stopmuligheder skal derfor være på plads.
- App Store-oplysninger og det konkrete build stemmer overens. Publicering sker efter en konkret releasebeslutning fra Sylvester i henhold til Cbox-releaseprocessen.

## 9. Efter v1

Android, tilpasset iPad-layout, automatisk krypteret synkronisering, flere backup-/migrationsformater, selvhostede hosts med push-relay, widgets/Watch samt hardwarebundet signering vurderes efter v1. Lokal TOTP og krypteret filbackup er med i v1.

Næste trin er backenddeployment og den fysiske iPhone-test i [IPHONE-TEST.md](IPHONE-TEST.md). M0/M3/M4/M5 er ikke accepteret alene på baggrund af grønne PHP-tests; device-, distributions- og reviewkravene ovenfor gælder fortsat.
