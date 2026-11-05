# App Store-udkast

Dette er tekstgrundlaget. Ingen metadata er uploadet, og App Store Connect er ikke
ændret. Screenshots skal tages fra den endelige, signerede app.

| Felt | Dansk | English |
| --- | --- | --- |
| Navn | Cbox ID Authenticator | Cbox ID Authenticator |
| Undertitel | Godkend login og handlinger | Approve sign-ins and actions |
| Primær kategori | Hjælpeprogrammer | Utilities |
| Nøgleord | cbox,id,login,godkendelse,konto,sikkerhed,agent | cbox,id,login,approval,account,security,agent |
| Support | https://cbox.dk/contact | https://cbox.dk/contact |

## Dansk beskrivelse

Godkend login og anmodninger fra apps og agenter med din Cbox ID-konto.

Se, hvilken app der beder om adgang, hvilken konto anmodningen gælder, og hvilken
handling du godkender. Sammenlign beskrivelsen med det, du blev vist på din anden enhed.
Bekræft med Face ID eller din iPhones adgangskode. Afvis anmodninger, du ikke ønsker,
eller vælg “Det var ikke mig” for at afvise og rapportere en uventet anmodning.

Tilslut flere Cbox ID-konti, og se deres anmodninger samlet. Notifikationer er valgfrie;
du kan altid åbne appen for at hente aktuelle anmodninger fra din Cbox ID-server.

Appen kræver en eksisterende konto hos en understøttet Cbox ID-host. Den genererer ikke
engangskoder til andre tjenester. Tilslut telefonen via Betroede enheder på din Cbox
ID-side, enten med QR-koden eller et link på samme telefon.

## English description

Approve sign-ins and requests from apps and agents with your Cbox ID account.

See which app is asking, which account the request concerns, and the action you are
approving. Compare the description with the one shown on your other device, then
confirm with Face ID or your iPhone passcode. Deny unwanted requests or choose
“This wasn't me” to deny and report an unexpected request.

Connect several Cbox ID accounts and see their pending requests together.
Notifications are optional; open the app to retrieve current requests from your
Cbox ID host.

An existing account on a supported Cbox ID host is required. This app does not generate
one-time codes for other services. Connect through Trusted devices on your Cbox ID
website using its QR code or a link on the same phone.

## Reviewer-forløb og felter, der kræver det rigtige build

Opret en dedikeret reviewer-testkonto på den opdaterede host og angiv dens adgang i
App Store Connect, aldrig i dette dokument. Beskriv: login på host → Min konto →
Betroede enheder → tilslutningslink i app → udsted en testanmodning → sammenlign og
godkend eller afvis. Der skal være en konkret fungerende måde at oprette requesten på;
en tom testkonto er ikke et tilstrækkeligt review-forløb.

Policy-URL: den deployede hosts offentlige `/authenticator/privacy`; kontrollér den
uden login før submission. Den nye side er med i backendbranchen, ikke allerede
bekræftet publiceret. Angiv juridisk rettighedshaver, kontaktperson og aldersrating
ud fra App Store Connects aktuelle spørgeskema.

Privacy-grundlag: ingen annoncer eller tracking; kontokoblet bruger-ID, enheds-ID og
produktinteraktioner bruges til appens funktion. Device metadata og beslutninger
behandles af Cbox ID; Apple/Firebase leverer push. Ingen adgang til Face ID-data eller
lagring af adgangskoder i appen. Kontrollér den endelige SDK-sammensætning og hostens
faktiske indsamling, før privacy-svarene indsendes.

Sæt ikke en eksport-undtagelse uden at gennemgå OpenSSL/DPoP og distributionen.
Der må ikke annonceres Secure Enclave-nøgler, TOTP, Android-understøttelse eller
færdig App Store-godkendelse for denne iOS-version.
