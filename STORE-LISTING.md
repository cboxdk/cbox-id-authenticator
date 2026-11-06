# Cbox ID — App Store-materiale

App Store Connect **6831374770**, iOS **1.0.0 (5)**. Forberedt 6. november 2026.
Ingen reviewindsendelse eller offentlig frigivelse. Se `IOS-RELEASE-PLAN.md` for
acceptgrundlaget. Apple viser **Ready for Review** efter validering af alle krævede felter.

## Gemte felter

Navn: **Cbox ID**. Kategori: Utilities. Primært sprog: English (U.S.).
Ti lokaliserede navne, undertitler, beskrivelser, nøgleord og promotekster kommer fra
`resources/store/listings.json`. Teksterne forklarer Cbox-godkendelser, valgfri push,
offline TOTP, krypteret backup og fraværet af automatisk synkronisering.

- Support: https://cbox.dk/contact
- Privacy: https://cbox.cboxid.com/authenticator/privacy
- Copyright: 2026 Cbox
- Alder: 4+ efter Apples spørgeskema; ingen annoncer, socialt indhold eller åben browser.
- Pris: gratis. Tilgængelighed: 175 lande ved manuel frigivelse.
- Mac og Vision Pro: ikke tilgængelig i denne første iPhone-version.
- Reviewkontakt: Sylvester Damgaard, samme erhvervskontakt som Julemosegård i App Store Connect.
- Standard Apple EULA. Indhold omfatter licenserede lokale tjenesteikoner (Simple Icons, CC0-1.0).

## Billedpakke

Seks billeder i denne rækkefølge, hver **1320 × 2868, RGB JPEG**:

1. Godkendelser samlet.
2. TOTP-koder offline.
3. Gennemgå og godkend eller afvis.
4. Krypteret backup.
5. Flere Cbox ID-konti.
6. Find den rigtige kode med søgning og liste.

Sprogene er `en, da, de, sv, nb, fr, fi, es, it, nl`. Den store iPhone-størrelse med
Dynamic Island er uploadet; Apples øvrige relevante iPhone-visninger bruger disse assets.
Header er 3840 × 1646, søgegrafik 1920 × 1280. Begge er sprogneutrale og uden alpha-kanal. Headeren er PNG; søgegrafikken er JPEG.
Apples header-upload afviste JPEG trods formatangivelsen i dokumentationen. PNG er
accepteret og genlæst på alle ti sprog.

Originalerne er ægte simulatorbilleder med fiktive visningsdata. De er skaleret som
hele billeder inde i en telefonramme, uden AI-genereret eller retoucheret UI.
`resources/store/screenshots.json` dokumenterer originale hashes og kilderevision.
`resources/store/artwork.json` dokumenterer eksporterne og deres kildefiler.
Overskrifter og billedtekster ligger i `resources/store/screenshots-copy.json`.

Gendan på macOS med AppKit:

```sh
swift resources/store/tools/render.swift SOURCE_DIR OUTPUT_DIR resources/store/screenshots-copy.json public/icon.png
swift resources/store/tools/brand-art.swift OUTPUT_DIR public/icon.png
```

Den færdige pakke ligger lokalt i
`/Users/sylvester/.codex/visualizations/2026/11/05/01a1ab2a-d676-78c2-998e-72dc010d27f3/app-store-polished`.
`index.html` er en oversigt over alle sprog; ZIP-pakken ligger ved siden af mappen som
`cbox-id-app-store-artwork.zip`. Store billedfiler og testdata er ikke indlejret i appen.

## Privacy-grundlag

Erklæringen er publiceret hos Apple efter ejerens udtrykkelige bekræftelse og genlæst i App Store Connect.
Grundlaget er både serverens behandling og PrivacyInfo.xcprivacy i den signerede IPA:

| Apple-datatype | Formål | Knyttet til bruger | Tracking |
| --- | --- | --- | --- |
| User ID | App Functionality | Ja | Nej |
| Device ID | App Functionality | Ja | Nej |
| Product Interaction | App Functionality | Ja | Nej |
| Other Diagnostic Data | Analytics, App Functionality | Nej | Nej |
| Other Data | Analytics, App Functionality | Ja | Nej |

De første tre dækker konto, installation og godkendelsesbeslutninger. Firebase Messaging,
Firebase Installations og GoogleDataTransport erklærer SDK-metadata/diagnostik. Der er
hverken Firebase Analytics SDK, Crashlytics eller annonce-SDK. Other Data omfatter også
hostens kontokoblede sikkerheds-/netværksmetadata; derfor er den samlede kategori angivet
som knyttet til bruger. Ingen indsamling af biometriske data eller TOTP-hemmeligheder.
TOTP-backup er brugerinitieret og krypteret; brugeren vælger selv destinationen i Filer.

## Reviewer-forløb

En isoleret reviewkonto er provisioneret på cbox.cboxid.com med nul medlemskaber,
rolletildelinger og operatorrettigheder. Almindeligt login og siden med en frisk
tilslutningskode er verificeret i en separat browsersession. Kun denne kontos login
er gemt i Apples reviewfelter. Ingen adgangskoder eller client secrets findes i
repository eller dette dokument.

Testforløbet er https://cbox.cboxid.com/authenticator/review, udrullet og verificeret
på serverrevision `9da3d2b70a80f3e594efaff6c3895e15ca0f5dee`. Herfra åbner revieweren Betroede enheder, tilslutter sin telefon og
sender en rigtig godkendelse til sig selv. Svaret kan genlæses på siden. Der udstedes
ingen access tokens fra testen. En udløbende QR bruges ikke som permanent adgang.

TOTP og backup kan testes uden Cbox-login:

1. Åbn Codes og vælg at tilføje en kode manuelt.
2. Service: Review demo. Konto: review@example.com. Offentlig demonstrationsnøgle:
   `JBSWY3DPEHPK3PXP`. SHA1, seks cifre, 30 sekunder. Dette er ingen rigtig konto.
3. Kontrollér kode, nedtælling, kopiering, liste/gitter, søgning og omdøbning.
4. Slå netværk fra: koden fortsætter lokalt. Slå netværk til igen.
5. Åbn Backup, eksportér med en selvvalgt adgangskode til Filer. Importér filen igen;
   eksisterende koder dubleres ikke. En forkert adgangskode skal afvises.
6. Fjern testkoden igen. TOTP-funktionerne kræver ingen abonnementer eller køb.

Cbox-forløbet er kontrolleret med almindeligt login, frisk tilslutningskode,
midlertidig PKCE/DPoP-enhed, browseroprettede testanmodninger og godkendelse/afvisning
gennem appens offentlige API. Begge svar er genlæst på review-siden. Testenheden er
tilbagekaldt, og dens token afvises med 403. Fysisk pushaccept er ejerens build 5-test
med PUSH 7926. Kontakt, isoleret login og trinvis vejledning findes under App Review Information.
