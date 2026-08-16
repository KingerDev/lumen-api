<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Models\Media;
use App\Models\Template;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a Day One CSV export.
 *
 * Run this on the server, not from the phone: the export is ~1.9 GB across 751
 * files, and pushing that over a mobile connection one presigned URL at a time
 * would take hours and break on the first dropped connection.
 *
 *   php artisan lumen:import-dayone /srv/import --email=ja@example.sk
 *
 * where /srv/import holds Journal.csv, photos/ and videos/ from the unzipped
 * export. Safe to re-run: entries are keyed on the Day One uuid and media on
 * its md5, so a resumed run picks up where it stopped.
 */
class ImportDayOne extends Command
{
    protected $signature = 'lumen:import-dayone
                            {path : Priečinok s Journal.csv, photos/ a videos/}
                            {--email= : E-mail účtu, do ktorého sa importuje}
                            {--template=Daily Self : Šablóna, podľa ktorej sa rozdelí text}
                            {--skip-media : Naimportuje len texty, bez nahrávania do R2}
                            {--dry-run : Nič nezapíše, len vypíše čo by spravil}';

    protected $description = 'Naimportuje export z Day One (Journal.csv + photos/ + videos/)';

    private const VIDEO_EXTENSIONS = ['mov', 'mp4', 'm4v'];

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');
        $csvPath = $path.'/Journal.csv';

        if (! is_readable($csvPath)) {
            $this->error("Nenašiel som {$csvPath}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $labels = $this->promptLabels($user);

        if ($labels === []) {
            $this->warn('Nenašiel som žiadne šablóny — texty sa uložia nerozdelené.');
        }

        $handle = fopen($csvPath, 'rb');
        $header = fgetcsv($handle, escape: '');
        $rows = [];
        $ragged = 0;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            // Ragged rows would silently misalign every later column.
            if (count($row) !== count($header)) {
                $ragged++;

                continue;
            }

            // Belt and braces: anything that is not valid UTF-8 by this point
            // cannot be stored in a JSON column, so drop the bad bytes rather
            // than fail the whole import on one damaged character.
            $rows[] = array_map(
                fn ($cell) => mb_check_encoding((string) $cell, 'UTF-8')
                    ? $cell
                    : mb_convert_encoding((string) $cell, 'UTF-8', 'UTF-8'),
                array_combine($header, $row)
            );
        }
        fclose($handle);

        if ($ragged > 0) {
            $this->warn("Preskočených {$ragged} poškodených riadkov CSV.");
        }

        $this->info(sprintf('Načítaných %d zápiskov z exportu.', count($rows)));

        $stats = ['entries' => 0, 'skipped' => 0, 'media' => 0, 'missing' => 0];
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $uuid = trim($row['uuid'] ?? '');

            // Idempotency: the Day One uuid is the natural key across re-runs.
            $existing = $uuid
                ? Entry::withTrashed()->where('user_id', $user->id)
                    ->where('meta->dayOneUuid', $uuid)->first()
                : null;

            if ($existing) {
                $stats['skipped']++;
                $bar->advance();

                continue;
            }

            $attributes = $this->mapRow($row, $labels, $user);

            if ($dryRun) {
                $stats['entries']++;
                // Still check the files exist. Reporting "0 missing" without
                // looking would be worse than not reporting at all — it reads
                // as reassurance the run never actually earned.
                $stats['missing'] += $this->countMissingMedia($row, $path);
                $bar->advance();

                continue;
            }

            $entry = $user->entries()->create($attributes);
            $stats['entries']++;

            if (! $this->option('skip-media')) {
                [$imported, $missing] = $this->importMedia($entry, $row, $path, $user);
                $stats['media'] += $imported;
                $stats['missing'] += $missing;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'počet'], [
            ['Naimportované zápisky', $stats['entries']],
            ['Preskočené (už existujú)', $stats['skipped']],
            ['Nahrané médiá', $stats['media']],
            ['Chýbajúce súbory', $stats['missing']],
        ]);

        if ($dryRun) {
            $this->warn('DRY RUN — nič sa nezapísalo.');
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $email = $this->option('email');
        $user = $email ? User::where('email', $email)->first() : User::first();

        if (! $user) {
            $this->error('Používateľ sa nenašiel. Vytvor ho cez: php artisan lumen:user');

            return null;
        }

        $this->info("Importujem do účtu {$user->email}.");

        return $user;
    }

    /**
     * Every prompt label the user's templates know about, mapped to its field id.
     *
     * Built across all templates, not just the chosen one, because the export
     * spans a rename: older entries use the English prompts, newer ones Slovak.
     *
     * @return array<string, array{fieldId: string, templateId: string, templateName: string}>
     */
    private function promptLabels(User $user): array
    {
        $labels = [];

        foreach ($user->templates as $template) {
            foreach ($template->fields ?? [] as $field) {
                $label = trim($field['label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $labels[$label] = [
                    'fieldId' => $field['id'] ?? Str::slug($label),
                    'templateId' => $template->id,
                    'templateName' => $template->name,
                ];
            }
        }

        return $labels;
    }

    /** One CSV row → entry column values. */
    private function mapRow(array $row, array $labels, User $user): array
    {
        [$templateName, $sections] = $this->parseText($row['text'] ?? '', $labels);

        $template = $templateName
            ? Template::where('user_id', $user->id)->where('name', $templateName)->first()
            : null;

        $latitude = $this->numeric($row['latitude'] ?? null);
        $longitude = $this->numeric($row['longitude'] ?? null);

        // Day One writes 0.0 for "no fix", which would drop every such entry
        // on Null Island in the Gulf of Guinea.
        $hasLocation = $latitude !== null && $longitude !== null
            && ! ($latitude === 0.0 && $longitude === 0.0);

        // Day One writes the offset it was captured at (…+02:00) — keeping it
        // is what makes an imported entry read at the hour it was written.
        [$instant, $offset] = Entry::splitTimestamp($row['date']);

        return [
            'entry_date' => $instant,
            'entry_utc_offset' => $offset,
            'text' => trim($row['text'] ?? ''),
            'template_id' => $template?->id,
            'template_name' => $templateName,
            'sections' => $sections ?: null,

            'latitude' => $hasLocation ? $latitude : null,
            'longitude' => $hasLocation ? $longitude : null,
            'place_name' => $this->nullableString($row['placeName'] ?? null),
            'locality_name' => $this->nullableString($row['localityName'] ?? null),
            'administrative_area' => $this->nullableString($row['administrativeArea'] ?? null),
            'country' => $this->nullableString($row['country'] ?? null),
            'altitude' => $this->numeric($row['altitude'] ?? null),
            'location_auto' => true,

            'weather' => array_filter([
                'conditionsDescription' => $this->nullableString($row['conditionsDescription'] ?? null),
                'temperatureCelsius' => $this->numeric($row['temperatureCelsius'] ?? null),
                'weatherCode' => $this->nullableString($row['weatherCode'] ?? null),
                'sunriseDate' => $this->nullableString($row['sunriseDate'] ?? null),
                'sunsetDate' => $this->nullableString($row['sunsetDate'] ?? null),
            ], fn ($value) => $value !== null) ?: null,

            'tags' => [],
            'starred' => ($row['starred'] ?? 'false') === 'true',

            // Kept verbatim so nothing from the export is lost.
            'meta' => array_filter([
                'dayOneUuid' => $this->nullableString($row['uuid'] ?? null),
                'timeZoneIdentifier' => $this->nullableString($row['timeZoneIdentifier'] ?? null),
                'creationDevice' => $this->nullableString($row['creationDevice'] ?? null),
                'editingTime' => $this->numeric($row['editingTime'] ?? null),
                'stepCount' => $this->numeric($row['stepCount'] ?? null),
                'activityName' => $this->nullableString($row['activityName'] ?? null),
                'modifiedDate' => $this->nullableString($row['modifiedDate'] ?? null),
                'importedAt' => now()->toIso8601String(),
            ], fn ($value) => $value !== null),
        ];
    }

    /**
     * Splits Day One's flat text back into template sections.
     *
     * The export writes the template name, then alternating prompt / answer
     * lines. Any line matching a known prompt starts a new section; everything
     * until the next one is that section's answer.
     *
     * @return array{0: ?string, 1: array}
     */
    private function parseText(string $text, array $labels): array
    {
        // The /u modifier is load-bearing. Without it PCRE works on bytes and
        // \R also matches 0x85 (NEL) — which is a continuation byte inside many
        // multi-byte characters. An emoji like 😅 (F0 9F 98 85) would be split
        // straight down the middle, producing text that json_encode rejects.
        $lines = preg_split('/\R/u', $text) ?: [];
        $templateName = null;
        $sections = [];
        $currentIndex = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (isset($labels[$trimmed])) {
                $sections[] = [
                    'fieldId' => $labels[$trimmed]['fieldId'],
                    'label' => $trimmed,
                    'value' => [],
                ];
                $currentIndex = count($sections) - 1;
                $templateName ??= $labels[$trimmed]['templateName'];

                continue;
            }

            if ($trimmed === '') {
                continue;
            }

            if ($currentIndex === null) {
                // First non-empty line before any prompt is the template name.
                $templateName ??= $trimmed;

                continue;
            }

            $sections[$currentIndex]['value'][] = $trimmed;
        }

        $sections = array_map(fn (array $section) => [
            'fieldId' => $section['fieldId'],
            'label' => $section['label'],
            'value' => implode("\n", $section['value']),
        ], $sections);

        return [$templateName, $sections];
    }

    /**
     * Uploads the row's media to R2 and records it.
     *
     * @return array{0: int, 1: int} [imported, missing]
     */
    private function importMedia(Entry $entry, array $row, string $path, User $user): array
    {
        $md5s = array_filter(explode(';', $row['mediaMD5s'] ?? ''));
        $imported = 0;
        $missing = 0;
        $position = 0;

        foreach ($md5s as $md5) {
            $md5 = trim($md5);
            $file = $this->findMediaFile($path, $md5);

            if (! $file) {
                $missing++;

                continue;
            }

            // Unique on (user_id, md5) — a resumed run must not re-upload.
            if (Media::withTrashed()->where('user_id', $user->id)->where('md5', $md5)->exists()) {
                continue;
            }

            $extension = mb_strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $kind = in_array($extension, self::VIDEO_EXTENSIONS, true) ? 'video' : 'photo';
            $key = sprintf('media/%d/%s.%s', $user->id, Str::uuid(), $extension);

            // Streamed, so a 35 MB video never has to fit in PHP's memory limit.
            $stream = fopen($file, 'rb');
            Storage::disk('r2')->writeStream($key, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            // See MediaController::store — user_id is not fillable on purpose.
            $media = $entry->media()->make([
                'kind' => $kind,
                'r2_key' => $key,
                'mime' => mime_content_type($file) ?: null,
                'size_bytes' => filesize($file) ?: null,
                'captured_at' => $entry->entry_date,
                'md5' => $md5,
                'position' => $position++,
            ]);

            $media->user_id = $user->id;
            $media->save();

            $imported++;
        }

        return [$imported, $missing];
    }

    /** How many of the row's media files are not on disk. Used by --dry-run. */
    private function countMissingMedia(array $row, string $path): int
    {
        $missing = 0;

        foreach (array_filter(explode(';', $row['mediaMD5s'] ?? '')) as $md5) {
            if (! $this->findMediaFile($path, trim($md5))) {
                $missing++;
            }
        }

        return $missing;
    }

    /** Day One names files `<md5>.<ext>` under photos/ or videos/. */
    private function findMediaFile(string $path, string $md5): ?string
    {
        foreach (['photos', 'videos'] as $folder) {
            $matches = glob("{$path}/{$folder}/{$md5}.*");
            if ($matches) {
                return $matches[0];
            }
        }

        return null;
    }

    private function numeric(?string $value): ?float
    {
        $value = trim((string) $value);

        return $value === '' || ! is_numeric($value) ? null : (float) $value;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
