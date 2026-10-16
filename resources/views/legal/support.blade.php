@extends('legal.layout')

@if ($lang === 'sk')
@section('title', 'Podpora')
@section('content')
    <h1>Podpora</h1>
    <p class="meta">Lumen — súkromný denník s fotkami, miestami a sériou.</p>

    <div class="card">
        <p>Máš otázku, našiel si chybu alebo ti niečo chýba? Napíš na
        @if ($contact)<a href="mailto:{{ $contact }}">{{ $contact }}</a>@else e-mail uvedený v App Store @endif
        — odpovedám zvyčajne do pár dní.</p>
    </div>

    <h2>Časté otázky</h2>
    <p><strong>Ako sa prihlásim?</strong><br>Cez „Pokračovať s Apple“ alebo e-mailom a heslom. Účet zálohuje zápisky aj fotky, takže o ne neprídeš ani pri výmene telefónu.</p>
    <p><strong>Funguje Lumen bez internetu?</strong><br>Áno. Zápisky sa uložia v telefóne a odošlú sa, keď budeš znova online.</p>
    <p><strong>Zabudol som PIN.</strong><br>PIN je uložený len v tvojom telefóne a nedá sa obnoviť. Odinštaluj appku, nainštaluj ju znova a prihlás sa — zápisky sa stiahnu zo servera.</p>
    <p><strong>Ako zmažem účet?</strong><br>V appke: Nastavenia → Zmazať účet. Okamžite sa zmažú všetky zápisky, fotky a šablóny. Podrobnosti sú v <a href="{{ route('privacy', ['lang' => 'sk']) }}">zásadách ochrany súkromia</a>.</p>
@endsection
@else
@section('title', 'Support')
@section('content')
    <h1>Support</h1>
    <p class="meta">Lumen — a private journal with photos, places and streaks.</p>

    <div class="card">
        <p>Have a question, found a bug or missing something? Write to
        @if ($contact)<a href="mailto:{{ $contact }}">{{ $contact }}</a>@else the email listed on the App Store @endif
        — I usually reply within a few days.</p>
    </div>

    <h2>Common questions</h2>
    <p><strong>How do I sign in?</strong><br>With “Continue with Apple”, or with email and password. Your account backs up entries and photos, so they’re safe even if you change phones.</p>
    <p><strong>Does Lumen work offline?</strong><br>Yes. Entries are saved on your phone and sent when you’re back online.</p>
    <p><strong>I forgot my passcode.</strong><br>The passcode lives only on your phone and can’t be recovered. Delete the app, install it again and sign in — your entries download from the server.</p>
    <p><strong>How do I delete my account?</strong><br>In the app: Settings → Delete Account. All entries, photos and templates are deleted right away. See the <a href="{{ route('privacy', ['lang' => 'en']) }}">privacy policy</a> for details.</p>
@endsection
@endif
