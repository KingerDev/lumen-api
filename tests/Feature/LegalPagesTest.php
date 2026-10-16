<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The pages App Store Connect links to must stay up, in both languages. */
class LegalPagesTest extends TestCase
{
    public function test_privacy_and_support_render_in_both_languages(): void
    {
        config(['services.lumen.contact_email' => 'help@example.com']);

        $this->get('/privacy?lang=sk')->assertOk()->assertSee('Zásady ochrany súkromia')->assertSee('help@example.com');
        $this->get('/privacy?lang=en')->assertOk()->assertSee('Privacy Policy')->assertSee('Settings → Delete Account');
        $this->get('/support?lang=sk')->assertOk()->assertSee('Podpora');
        $this->get('/support', ['Accept-Language' => 'en-US'])->assertOk()->assertSee('Support');
    }

    public function test_pages_work_without_a_contact_email(): void
    {
        config(['services.lumen.contact_email' => null]);

        $this->get('/privacy?lang=en')->assertOk();
        $this->get('/support?lang=sk')->assertOk()->assertSee('e-mail uvedený v App Store');
    }
}
