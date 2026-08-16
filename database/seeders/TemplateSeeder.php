<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the templates the journal is actually written under.
 *
 * "Daily Self" carries both the Slovak and the English prompt set, because the
 * Day One export spans a rename — the importer matches labels against these,
 * so both eras split into sections correctly.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {
            $this->seedFor($user);
        }
    }

    private function seedFor(User $user): void
    {
        $templates = [
            [
                'name' => 'Daily Self',
                'description' => 'Denný prehľad dňa — miesta, ľudia, jedlo, momenty',
                'icon' => 'sunny-outline',
                'labels' => [
                    'Kde som bol...',
                    'Čo som robil...',
                    'Koho som videl...',
                    'Čo som dosiahol...',
                    'Čo som jedol...',
                    'Príbehový moment dňa...',
                    'Jedna vec, ktorú som sa dnes naučil...',
                    'Čo ma dnes potešilo, alebo komu som ja dnes pomohol?',
                ],
            ],
            [
                // Not offered in the picker — it exists so the importer can
                // split the older, English-prompt entries into sections too.
                'name' => 'Daily Self (EN)',
                'description' => 'Staršie zápisky s anglickými otázkami',
                'icon' => 'time-outline',
                'labels' => [
                    "Where I've been...",
                    'My mood is...',
                    'People I saw...',
                    'People i saw...',
                    'Accomplishments...',
                    'Food I ate...',
                    'Food i ate...',
                ],
            ],
            [
                'name' => 'Cesta',
                'description' => 'Pre dni mimo domova',
                'icon' => 'airplane-outline',
                'labels' => [
                    'Kam som sa dostal...',
                    'Čo som videl...',
                    'Čo ma prekvapilo...',
                    'Čo si chcem zapamätať...',
                ],
            ],
            [
                'name' => 'Vďačnosť',
                'description' => 'Krátky večerný zápis',
                'icon' => 'heart-outline',
                'labels' => [
                    'Za čo som dnes vďačný...',
                    'Čo mi dnes urobilo radosť...',
                    'Čo chcem zajtra spraviť inak...',
                ],
            ],
        ];

        foreach ($templates as $definition) {
            Template::updateOrCreate(
                ['user_id' => $user->id, 'name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'built_in' => true,
                    'fields' => array_map(fn (string $label) => [
                        'id' => Str::slug($label).'-'.Str::lower(Str::random(5)),
                        'label' => $label,
                        'multiline' => true,
                    ], $definition['labels']),
                ]
            );
        }
    }
}
