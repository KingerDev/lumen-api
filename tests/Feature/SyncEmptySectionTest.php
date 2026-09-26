<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An unanswered template section is "" in the app, but ConvertEmptyStringsToNull
 * turns it into null before validation. The push must still land.
 */
class SyncEmptySectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_accepts_empty_section_and_stores_it_as_empty_string(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $id = (string) Str::uuid();

        $this->postJson('/api/sync', [
            'entries' => [[
                'id' => $id,
                'updatedAt' => now()->toIso8601String(),
                'date' => now()->toIso8601String(),
                'text' => 'Doma',
                'sections' => [
                    ['fieldId' => 'where', 'label' => 'Kde som bol…', 'value' => 'Doma'],
                    ['fieldId' => 'what', 'label' => 'Čo som robil…', 'value' => ''],
                ],
            ]],
        ])->assertOk();

        $entry = Entry::where('user_id', $user->id)->findOrFail($id);
        $this->assertSame('', $entry->sections[1]['value']);
    }
}
