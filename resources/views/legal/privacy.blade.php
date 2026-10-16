@extends('legal.layout')

@php
    $mail = $contact ? '<a href="mailto:'.e($contact).'">'.e($contact).'</a>' : null;
@endphp

@if ($lang === 'sk')
@section('title', 'Zásady ochrany súkromia')
@section('content')
    <h1>Zásady ochrany súkromia</h1>
    <p class="meta">Platné od 16. októbra 2026</p>

    <div class="card">
        <p>Lumen je súkromný denník. Tvoje zápisky slúžia len tebe: žiadna reklama, žiadne sledovanie, žiadna analytika, žiadny predaj údajov a nič sa nepoužíva na trénovanie umelej inteligencie.</p>
    </div>

    <h2>Kto za appkou stojí</h2>
    <p>Lumen vyvíja a prevádzkuje Martin Baláž (ďalej „ja“), ktorý je prevádzkovateľom tvojich osobných údajov.@if ($mail) Kontakt: {!! $mail !!}.@endif</p>

    <h2>Aké údaje Lumen spracúva</h2>
    <ul>
        <li><strong>Účet</strong> — e-mail a meno. Pri prihlásení cez Apple aj anonymný identifikátor od Apple; ak si zvolíš „Skryť môj e-mail“, dostanem len náhodnú adresu od Apple.</li>
        <li><strong>Obsah denníka</strong> — texty zápiskov, ich dátum a čas, šablóny a označenie obľúbených.</li>
        <li><strong>Fotky a videá</strong>, ktoré do zápiskov sám pridáš. Lumen nevidí zvyšok tvojej knižnice.</li>
        <li><strong>Poloha zápisku</strong> — súradnice a názov miesta, len ak polohu povolíš. Lumen polohu nesleduje na pozadí; zisťuje ju iba pri novom zápisku.</li>
    </ul>

    <h2>Čo zostáva len v tvojom telefóne</h2>
    <p>PIN (uložený ako hash v Keychaine), Face ID / Touch ID (overuje ich iOS, Lumen k biometrickým údajom nemá prístup) a nastavenia appky. Na server sa nikdy neposielajú.</p>

    <h2>Na čo údaje používam</h2>
    <p>Výhradne na to, aby appka fungovala: aby sa zápisky uložili, zálohovali a synchronizovali medzi tvojimi zariadeniami. Právnym základom je plnenie zmluvy (čl. 6 ods. 1 písm. b) GDPR) — bez týchto údajov by denník nemohol fungovať.</p>

    <h2>Kde sú údaje uložené</h2>
    <p>Zápisky a účet sú v databáze na serveri Lumen API, fotky a videá v úložisku Cloudflare R2. Všetko sa prenáša šifrovane (HTTPS) a fotky sú dostupné len cez dočasné podpísané odkazy, ktoré po krátkom čase prestanú platiť.</p>

    <h2>S kým údaje zdieľam</h2>
    <p>S nikým, okrem služieb nevyhnutných na prevádzku:</p>
    <ul>
        <li><strong>Apple</strong> — prihlásenie cez Apple, mapy a názvy miest (prevod súradníc na názov robí iOS).</li>
        <li><strong>Cloudflare</strong> — úložisko fotiek a videí.</li>
        <li><strong>Hosting servera</strong> — databáza Lumen API.</li>
    </ul>
    <p>Títo poskytovatelia spracúvajú údaje len podľa mojich pokynov a na nič iné ich nepoužívajú.</p>

    <h2>Ako dlho údaje uchovávam a ako ich zmažeš</h2>
    <p>Kým máš účet. V appke ho zmažeš cez <strong>Nastavenia → Zmazať účet</strong>: okamžite sa zmažú všetky zápisky, fotky, videá, šablóny aj účet a zruší sa prepojenie s Apple. Ak existujú záložné kópie servera, údaje z nich zmiznú pri ich bežnom prepísaní.</p>
    <p>Odhlásenie zmaže údaje len z telefónu; na serveri zostanú, kým účet nezmažeš.</p>

    <h2>Tvoje práva</h2>
    <p>Máš právo na prístup k svojim údajom, ich opravu, vymazanie, obmedzenie spracúvania, prenosnosť a právo namietať. Stačí mi napísať{!! $mail ? ' na '.$mail : '' !!}. Ak si myslíš, že s údajmi zaobchádzam nesprávne, môžeš podať sťažnosť na Úrad na ochranu osobných údajov Slovenskej republiky.</p>

    <h2>Deti</h2>
    <p>Lumen nie je určený deťom mladším ako 13 rokov a vedome od nich údaje nezbieram.</p>

    <h2>Zmeny</h2>
    <p>Ak sa tieto zásady zmenia, nová verzia bude na tejto stránke s novým dátumom platnosti.</p>
@endsection
@else
@section('title', 'Privacy Policy')
@section('content')
    <h1>Privacy Policy</h1>
    <p class="meta">Effective October 16, 2026</p>

    <div class="card">
        <p>Lumen is a private journal. Your entries are for you alone: no ads, no tracking, no analytics, no selling of data, and nothing is used to train artificial intelligence.</p>
    </div>

    <h2>Who runs the app</h2>
    <p>Lumen is developed and operated by Martin Baláž (“I”), the controller of your personal data.@if ($mail) Contact: {!! $mail !!}.@endif</p>

    <h2>What data Lumen processes</h2>
    <ul>
        <li><strong>Account</strong> — email and name. With Sign in with Apple, also an anonymous identifier from Apple; if you choose “Hide My Email”, I only receive a random address from Apple.</li>
        <li><strong>Journal content</strong> — the text of your entries, their date and time, templates and favorites.</li>
        <li><strong>Photos and videos</strong> you add to entries yourself. Lumen can’t see the rest of your library.</li>
        <li><strong>Entry location</strong> — coordinates and place name, only if you allow location. Lumen never tracks you in the background; it only checks your location for a new entry.</li>
    </ul>

    <h2>What stays on your phone only</h2>
    <p>Your passcode (stored as a hash in the Keychain), Face ID / Touch ID (handled by iOS — Lumen has no access to biometric data) and app settings. These are never sent to the server.</p>

    <h2>How the data is used</h2>
    <p>Only to make the app work: to save, back up and sync your entries across your devices. The legal basis is performance of a contract (Art. 6(1)(b) GDPR) — the journal can’t work without this data.</p>

    <h2>Where the data is stored</h2>
    <p>Entries and your account live in the Lumen API server’s database; photos and videos in Cloudflare R2 storage. Everything travels encrypted (HTTPS), and photos are only reachable through temporary signed links that expire shortly.</p>

    <h2>Who the data is shared with</h2>
    <p>No one, except the services needed to run the app:</p>
    <ul>
        <li><strong>Apple</strong> — Sign in with Apple, maps and place names (iOS turns coordinates into a place name).</li>
        <li><strong>Cloudflare</strong> — storage for photos and videos.</li>
        <li><strong>Server hosting</strong> — the Lumen API database.</li>
    </ul>
    <p>These providers process data only on my instructions and use it for nothing else.</p>

    <h2>How long data is kept and how to delete it</h2>
    <p>For as long as you have an account. Delete it in the app under <strong>Settings → Delete Account</strong>: all entries, photos, videos, templates and the account itself are deleted right away, and the link to Apple is revoked. If server backups exist, the data disappears from them when they are routinely overwritten.</p>
    <p>Signing out only removes data from the phone; it stays on the server until you delete the account.</p>

    <h2>Your rights</h2>
    <p>You have the right to access, correct, delete and port your data, to restrict its processing and to object. Just write to me{!! $mail ? ' at '.$mail : '' !!}. If you believe your data is mishandled, you can lodge a complaint with your local data protection authority (in Slovakia, the Office for Personal Data Protection).</p>

    <h2>Children</h2>
    <p>Lumen isn’t intended for children under 13, and I don’t knowingly collect their data.</p>

    <h2>Changes</h2>
    <p>If this policy changes, the new version will be on this page with a new effective date.</p>
@endsection
@endif
