# App Store-udkast

Metadata er gemt i den private App Store-kladde **6831374770**, iOS **1.0.0**, 6. november 2026.
Ti sprog, Utilities, manuel frigivelse. Det reproducerbare tekstgrundlag er
`resources/store/listings.json`. Ingen reviewindsendelse eller offentlig frigivelse.

| Felt | Dansk | English |
| --- | --- | --- |
| Navn | Cbox ID Authenticator | Cbox ID Authenticator |
| Undertitel | Koder og sikre godkendelser | Codes and secure approvals |
| Primær kategori | Hjælpeprogrammer | Utilities |
| Nøgleord | cbox,id,login,godkendelse,konto,sikkerhed,agent,totp,backup | cbox,id,login,approval,account,security,agent,totp,backup |
| Support | https://cbox.dk/contact | https://cbox.dk/contact |

## Dansk beskrivelse

Godkend login og anmodninger fra apps og agenter med din Cbox ID-konto.

Se, hvilken app der beder om adgang, hvilken konto anmodningen gælder, og hvilken
handling du godkender. Sammenlign beskrivelsen med det, du blev vist på din anden enhed.
Bekræft med Face ID eller din iPhones adgangskode. Afvis anmodninger, du ikke ønsker,
eller vælg “Det var ikke mig” for at afvise og rapportere en uventet anmodning.

Tilslut flere Cbox ID-konti, og se deres anmodninger samlet. Åbn appen for at hente
aktuelle anmodninger fra din Cbox ID-server.

Gem også TOTP-bekræftelseskoder til andre tjenester. Scan en opsætningskode eller
indtast en nøgle, og brug koderne offline uden en Cbox-konto. Gem en krypteret backup
med din egen adgangskode, og importér den på en ny telefon. Der er ingen automatisk
synkronisering. Cbox kan ikke gendanne en glemt adgangskode til backup.

Cbox-godkendelser kræver en eksisterende konto hos en understøttet Cbox ID-host.
Tilslut telefonen via Betroede enheder på din Cbox ID-side med QR eller et link.

## English description

Approve sign-ins and requests from apps and agents with your Cbox ID account.

See which app is asking, which account the request concerns, and the action you are
approving. Compare the description with the one shown on your other device, then
confirm with Face ID or your iPhone passcode. Deny unwanted requests or choose
“This wasn't me” to deny and report an unexpected request.

Connect several Cbox ID accounts and see their pending requests together.
Open the app to retrieve current requests from your Cbox ID host.

Keep TOTP verification codes for other services, too. Scan a setup QR code or enter
a setup key, then use your codes offline without a Cbox account. Save an encrypted
backup with your own password and import it on a new phone. There is no automatic
sync. Cbox cannot recover a forgotten backup password.

Cbox approvals require an existing account on a supported Cbox ID host. Connect
through Trusted devices on your Cbox ID website using its QR code or a link.

## Reviewer-forløb og felter, der kræver det rigtige build

Opret en dedikeret reviewer-testkonto på den opdaterede host og angiv dens adgang i
App Store Connect, aldrig i dette dokument. Beskriv: login på host → Min konto →
Betroede enheder → tilslutningslink i app → udsted en testanmodning → sammenlign og
godkend eller afvis. Der skal være en konkret fungerende måde at oprette requesten på;
en tom testkonto er ikke et tilstrækkeligt review-forløb.

Policy-URL: https://cbox.cboxid.com/authenticator/privacy. Verificeret offentligt
tilgængelig uden login 2026-11-05; omfatter også TOTP og krypteret backup. Angiv juridisk rettighedshaver, kontaktperson og aldersrating
ud fra App Store Connects aktuelle spørgeskema.

Privacy-grundlag: ingen annoncer eller tracking; kontokoblet bruger-ID, enheds-ID og
produktinteraktioner bruges til appens funktion. Device metadata og beslutninger
behandles af Cbox ID. Firebase er deaktiveret, og denne version leverer ikke push. Ingen adgang til Face ID-data eller
lagring af adgangskoder i appen. Kontrollér den endelige SDK-sammensætning og hostens
faktiske indsamling, før privacy-svarene indsendes.

TOTP-nøgler forlader kun telefonen i en brugerinitieret krypteret backup.
Gennemgå OpenSSL/DPoP, libsodium/Argon2id/XChaCha20-Poly1305 og distributionen ved
App Store Connects krypteringserklæring; antag ikke en undtagelse.
Der må ikke annonceres Secure Enclave-nøgler, automatisk synkronisering, Android-understøttelse eller
færdig App Store-godkendelse for denne iOS-version.

## Lokalisering til v1

Appen understøtter samme sæt som Cbox ID: `en, da, de, sv, nb, fr, fi, es, it, nl`.
Det gælder skærme, fejl, app-lås, Face ID-tekster, kamera og tilladelsesdialoger.
iOS vælger sproget, og Indstillinger → Sprog åbner appens systemindstillinger.
Skærmteksterne og disse butiksudkast er skrevet til produktet; de er ikke
attesteret af professionelle oversættere. Tjek fagudtryk med modersmålstalere
før den endelige marketinggodkendelse.

| Sprog | Undertitel (maks. 30 tegn) |
| --- | --- |
| Deutsch | Codes und sichere Freigaben |
| Svenska | Koder och säkra godkännanden |
| Norsk bokmål | Koder og sikre godkjenninger |
| Français | Codes et validations sûres |
| Suomi | Koodit ja hyväksynnät |
| Español | Códigos y aprobaciones seguras |
| Italiano | Codici e approvazioni sicure |
| Nederlands | Codes en veilige goedkeuringen |

### Deutsch

Bestätige Anmeldungen und Anfragen mit deinen Cbox ID-Konten. Prüfe die App,
das Konto und die gewünschte Aktion, bevor du mit Face ID oder deinem iPhone-Code
bestätigst. Verbinde mehrere Konten über den QR-Code oder Link unter
Vertrauenswürdige Geräte auf deinem Cbox ID-Server.

Speichere auch TOTP-Bestätigungscodes für andere Dienste. Sie funktionieren offline
und ohne Cbox-Konto. Scanne den Einrichtungs-QR-Code oder gib den Schlüssel ein.
Erstelle ein verschlüsseltes Backup mit deinem eigenen Passwort und importiere es
auf einem neuen Telefon. Es gibt keine automatische Synchronisierung. Cbox kann
ein vergessenes Backup-Passwort nicht wiederherstellen.

Cbox-Anfragen benötigen ein bestehendes Konto auf einem unterstützten Cbox ID-Server.
Öffne die App, um aktuelle Anfragen abzurufen.

### Svenska

Godkänn inloggningar och förfrågningar med dina Cbox ID-konton. Granska appen,
kontot och åtgärden innan du bekräftar med Face ID eller din iPhone-lösenkod.
Anslut flera konton via QR-koden eller länken under Betrodda enheter på din Cbox ID-server.

Spara även TOTP-verifieringskoder för andra tjänster. De fungerar offline och utan
Cbox-konto. Skanna tjänstens QR-kod eller ange konfigurationsnyckeln. Skapa en
krypterad säkerhetskopia med ett eget lösenord och importera den på en ny telefon.
Det finns ingen automatisk synkronisering. Cbox kan inte återställa ett glömt
lösenord till säkerhetskopian.

Cbox-förfrågningar kräver ett befintligt konto på en Cbox ID-server som stöds.
Öppna appen för att hämta aktuella förfrågningar.

### Norsk bokmål

Godkjenn innlogginger og forespørsler med Cbox ID-kontoene dine. Sjekk appen,
kontoen og handlingen før du bekrefter med Face ID eller iPhone-koden din.
Koble til flere kontoer via QR-koden eller lenken under Klarerte enheter på Cbox ID-serveren.

Lagre også TOTP-bekreftelseskoder for andre tjenester. De fungerer uten nett og uten
Cbox-konto. Skann tjenestens QR-kode eller oppgi oppsettsnøkkelen. Opprett en kryptert
sikkerhetskopi med ditt eget passord og importer den på en ny telefon. Det finnes
ingen automatisk synkronisering. Cbox kan ikke gjenopprette et glemt passord til sikkerhetskopien.

Cbox-forespørsler krever en eksisterende konto på en støttet Cbox ID-server.
Åpne appen for å hente aktuelle forespørsler.

### Français

Approuvez les connexions et demandes avec vos comptes Cbox ID. Examinez l’app,
le compte et l’action demandée avant de confirmer avec Face ID ou le code de votre
iPhone. Connectez plusieurs comptes via le QR code ou le lien dans Appareils de
confiance sur votre serveur Cbox ID.

Conservez aussi les codes de vérification TOTP d’autres services. Ils fonctionnent
hors ligne, sans compte Cbox. Scannez le QR code du service ou saisissez sa clé de
configuration. Créez une sauvegarde chiffrée avec votre propre mot de passe et
importez-la sur un nouveau téléphone. Il n’y a pas de synchronisation automatique.
Cbox ne peut pas récupérer un mot de passe de sauvegarde oublié.

Les demandes Cbox nécessitent un compte existant sur un serveur Cbox ID pris en
charge. Ouvrez l’app pour consulter les demandes en cours.

### Suomi

Hyväksy kirjautumiset ja pyynnöt Cbox ID -tileilläsi. Tarkista sovellus, tili ja
pyydetty toiminto ennen vahvistamista Face ID:llä tai iPhonen pääsykoodilla.
Yhdistä useita tilejä Cbox ID -palvelimen Luotetut laitteet -sivun QR-koodilla tai linkillä.

Tallenna myös muiden palvelujen TOTP-vahvistuskoodit. Ne toimivat ilman verkkoyhteyttä
ja Cbox-tiliä. Skannaa palvelun QR-koodi tai anna käyttöönottoavain. Luo omalla
salasanallasi suojattu, salattu varmuuskopio ja tuo se uuteen puhelimeen.
Automaattista synkronointia ei ole. Cbox ei voi palauttaa unohtunutta varmuuskopion salasanaa.

Cbox-pyynnöt edellyttävät olemassa olevaa tiliä tuetulla Cbox ID -palvelimella.
Avaa sovellus hakeaksesi nykyiset pyynnöt.

### Español

Aprueba inicios de sesión y solicitudes con tus cuentas de Cbox ID. Revisa la app,
la cuenta y la acción solicitada antes de confirmar con Face ID o el código del
iPhone. Conecta varias cuentas mediante el QR o enlace de Dispositivos de confianza
en tu servidor de Cbox ID.

Guarda también códigos de verificación TOTP de otros servicios. Funcionan sin
conexión y sin cuenta de Cbox. Escanea el QR del servicio o introduce su clave de
configuración. Crea una copia cifrada con tu propia contraseña e impórtala en un
nuevo teléfono. No hay sincronización automática. Cbox no puede recuperar una
contraseña de copia olvidada.

Las solicitudes de Cbox requieren una cuenta existente en un servidor compatible.
Abre la app para consultar las solicitudes actuales.

### Italiano

Approva accessi e richieste con i tuoi account Cbox ID. Controlla l’app, l’account
e l’azione richiesta prima di confermare con Face ID o il codice dell’iPhone.
Collega più account tramite QR o link in Dispositivi attendibili sul tuo server Cbox ID.

Conserva anche i codici di verifica TOTP di altri servizi. Funzionano offline e senza
un account Cbox. Scansiona il QR del servizio o inserisci la chiave di configurazione.
Crea un backup cifrato con una password scelta da te e importalo su un nuovo telefono.
Non è prevista la sincronizzazione automatica. Cbox non può recuperare una password
di backup dimenticata.

Le richieste Cbox richiedono un account esistente su un server Cbox ID supportato.
Apri l’app per recuperare le richieste in sospeso.

### Nederlands

Keur aanmeldingen en verzoeken goed met je Cbox ID-accounts. Controleer de app,
het account en de gevraagde actie voordat je bevestigt met Face ID of je
iPhone-toegangscode. Koppel meerdere accounts via de QR-code of link bij
Vertrouwde apparaten op je Cbox ID-server.

Bewaar ook TOTP-verificatiecodes voor andere diensten. Ze werken offline en zonder
Cbox-account. Scan de QR-code van de dienst of voer de installatiesleutel in.
Maak een versleutelde back-up met je eigen wachtwoord en importeer deze op een nieuwe
telefoon. Er is geen automatische synchronisatie. Cbox kan een vergeten
back-upwachtwoord niet herstellen.

Cbox-verzoeken vereisen een bestaand account op een ondersteunde Cbox ID-server.
Open de app om actuele verzoeken op te halen.

## Kontrolleret 5. november 2026

- Support: `https://cbox.dk/contact` svarer offentligt.
- Privacy: `https://cbox.cboxid.com/authenticator/privacy` svarer HTTP 200 uden login
  og beskriver også TOTP, lokal clipboard, krypteret backup og fjernelse.
- Ikon: 1024 × 1024 RGB uden alpha. Splash: 1290 × 2796 i lys og mørk variant.
- Appen indeholder licenstekster for 97 Composer-pakker samt de 9 låste CocoaPods/
  Swift-pakker. `authenticator:ios-notices --check` kontrollerer native notices mod
  de opløste afhængigheder; storekontrollen opdager ændrede lock-filer.
- Metadata er gemt i kladden. App Store Connects privacy- og eksportspørgsmål skal
  udfyldes for det endelige build. Se Apples [krypteringsvejledning](https://developer.apple.com/help/app-store-connect/manage-app-information/overview-of-export-compliance)
  og [screenshotkrav](https://developer.apple.com/help/app-store-connect/reference/app-information/screenshot-specifications).


## Screenshots af den aktuelle app

60 faktiske simulatorbilleder på alle ti sprog, 1320×2868 (6,9 tommer), er gemt i:
`/Users/sylvester/.codex/visualizations/2026/11/05/01a1ab2a-d676-78c2-998e-72dc010d27f3/store-ready/`.
Sættet dækker indbakke, godkendelse, kodegitter, kodeliste, flere konti og backup.
Billederne bruger den aktuelle native rendering og fiktive visningsdata i en isoleret
simulatorcontainer. Det er produktbilleder, ikke dokumentation for en ægte loginbeslutning.
De oprindelige build-13-billeder er historiske og skal ikke bruges til denne kladde.

## App Review — TOTP og backup, klar til brug

These features do not require an account or network connection:

1. Open Codes, tap Add code, and choose manual entry. Set service to “Review demo”,
   account to “review@example.com”, and key to `JBSWY3DPEHPK3PXP`. This is a public
   demonstration key, not a credential for a real account. Keep SHA-1, 6 digits,
   and 30 seconds. Save.
2. Select the service, copy its current code, and switch between grid and list.
   Turn on airplane mode: codes continue rotating offline.
3. Open Settings → Encrypted backup → Create backup. Choose a temporary password,
   save the file through the native Files dialog, then import that file. The preview
   identifies duplicate entries and import preserves the existing code.
4. Import with an incorrect password: the app rejects it without changing saved codes.
5. Open the code details to rename or remove the demonstration entry. Cancelling the
   native removal confirmation preserves it.

Cbox approvals use existing accounts on the host encoded in a connection QR/link.
The app does not create a separate app account. There are no purchases, ads, cloud
sync, or push notifications in this build. Requests are fetched on opening the app
and refreshed while the inbox is visible. Biometric confirmation is handled by iOS;
no face data is received by the app. Browser sign-in is ASWebAuthenticationSession.

**Do not submit the approval review path until an isolated review host/account and
self-service request trigger are available.** Never put review passwords in git.
