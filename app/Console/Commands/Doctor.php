<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Models\Media;
use App\Models\Template;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Post-deploy self-check.
 *
 * R2 misconfiguration is the failure mode that hurts most here, because it does
 * not surface until someone tries to attach a photo — and then it looks like an
 * app bug. This runs the whole round trip (write, read, presign, delete) against
 * the real bucket so a broken deploy is obvious in one command.
 */
class Doctor extends Command
{
    protected $signature = 'lumen:doctor {--keep : Nechá testovací objekt v R2}';

    protected $description = 'Overí, že databáza aj R2 sú správne nastavené';

    private array $failures = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=yellow>Lumen — kontrola nasadenia</>');
        $this->newLine();

        $this->checkDatabase();
        $this->checkSchema();
        $this->checkAccount();

        // Without credentials the round trip just prints the same failure five
        // times over, burying the one line that says what to fix.
        if ($this->checkR2Config()) {
            $this->checkR2RoundTrip();
        } else {
            $this->line('  <fg=gray>›</> R2 testy preskočené — najprv doplň premenné');
        }

        $this->newLine();

        if ($this->failures !== []) {
            $this->error(sprintf('  %d kontrol zlyhalo:', count($this->failures)));
            foreach ($this->failures as $failure) {
                $this->line("    <fg=red>•</> {$failure}");
            }
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  Všetko v poriadku — appka sa môže pripojiť.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        $this->attempt('Pripojenie k databáze', function () {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();

            // The nastiest failure this project can have. Laravel 11+ defaults
            // to sqlite, so a missing DB_CONNECTION looks perfectly healthy —
            // right up until the next deploy replaces the container and takes
            // years of journal entries with it.
            if ($driver === 'sqlite' && app()->environment('production')) {
                throw new \RuntimeException(
                    'v produkcii beží SQLite vnútri kontajnera — pri redeployi sa '.
                    'zmaže celý denník. Nastav DB_CONNECTION=pgsql a DB_HOST'
                );
            }

            return $driver;
        });
    }

    private function checkSchema(): void
    {
        $this->attempt('Migrácie prebehli', function () {
            foreach (['entries', 'media', 'templates', 'personal_access_tokens'] as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    throw new \RuntimeException("chýba tabuľka {$table} — spusti php artisan migrate");
                }
            }

            return sprintf(
                '%d zápiskov, %d médií, %d šablón',
                Entry::count(),
                Media::count(),
                Template::count()
            );
        });
    }

    private function checkAccount(): void
    {
        $this->attempt('Používateľský účet', function () {
            $count = User::count();

            if ($count === 0) {
                throw new \RuntimeException('žiadny účet — spusti php artisan lumen:user');
            }

            if (User::doesntHave('templates')->exists()) {
                throw new \RuntimeException('účet bez šablón — spusti php artisan db:seed');
            }

            return "{$count} účet(y)";
        });
    }

    /**
     * The two settings that silently break R2 while looking perfectly healthy.
     */
    private function checkR2Config(): bool
    {
        return $this->attempt('Nastavenie R2', function () {
            $disk = config('filesystems.disks.r2');

            // Config keys and env names differ ('key' ← R2_ACCESS_KEY_ID), and
            // the whole point of this check is to name the variable the user
            // has to go and fill in.
            $envNames = [
                'key' => 'R2_ACCESS_KEY_ID (alebo AWS_ACCESS_KEY_ID)',
                'secret' => 'R2_SECRET_ACCESS_KEY (alebo AWS_SECRET_ACCESS_KEY)',
                'bucket' => 'R2_BUCKET (alebo AWS_BUCKET)',
                'endpoint' => 'R2_ENDPOINT (alebo AWS_ENDPOINT)',
            ];

            $missing = collect($envNames)
                ->filter(fn ($env, $field) => blank($disk[$field] ?? null))
                ->values()
                ->all();

            if ($missing !== []) {
                throw new \RuntimeException('chýbajú premenné: '.implode(', ', $missing));
            }

            if (($disk['region'] ?? null) !== 'auto') {
                throw new \RuntimeException("region je '{$disk['region']}', R2 vyžaduje 'auto'");
            }

            if (! ($disk['use_path_style_endpoint'] ?? false)) {
                throw new \RuntimeException('use_path_style_endpoint musí byť true');
            }

            if (! str_contains((string) $disk['endpoint'], 'r2.cloudflarestorage.com')) {
                $this->warn('      endpoint nevyzerá ako R2 — skontroluj R2_ENDPOINT');
            }

            return $disk['bucket'];
        });
    }

    private function checkR2RoundTrip(): void
    {
        $key = 'health/'.Str::uuid().'.txt';
        $payload = 'lumen-doctor '.now()->toIso8601String();

        $this->attempt('Zápis do R2', function () use ($key, $payload) {
            Storage::disk('r2')->put($key, $payload);

            return $key;
        });

        $this->attempt('Čítanie z R2', function () use ($key, $payload) {
            $read = Storage::disk('r2')->get($key);

            if ($read !== $payload) {
                throw new \RuntimeException('prečítaný obsah nesedí');
            }

            return strlen($read).' B';
        });

        // This is what the app actually depends on — a signed URL that a phone
        // can PUT to without ever seeing the bucket credentials.
        $this->attempt('Podpísaná upload URL', function () {
            $signed = Storage::disk('r2')->temporaryUploadUrl(
                'health/presign-'.Str::uuid().'.jpg',
                now()->addMinutes(5)
            );

            if (blank($signed['url'] ?? null)) {
                throw new \RuntimeException('URL sa nevygenerovala');
            }

            return Str::before($signed['url'], '?');
        });

        $this->attempt('Podpísaná download URL', fn () => Str::before(
            Storage::disk('r2')->temporaryUrl($key, now()->addMinutes(5)),
            '?'
        ));

        if (! $this->option('keep')) {
            $this->attempt('Upratanie testovacieho objektu', function () use ($key) {
                Storage::disk('r2')->delete($key);

                return 'zmazané';
            });
        }
    }

    /**
     * Runs one check and prints a single aligned pass/fail line.
     *
     * @return bool whether the check passed, so callers can skip dependent ones
     */
    private function attempt(string $label, callable $check): bool
    {
        $padded = str_pad($label, 32, '.');

        try {
            $detail = $check();
            $this->line("  <fg=green>✓</> {$padded} <fg=gray>{$detail}</>");

            return true;
        } catch (Throwable $e) {
            $this->line("  <fg=red>✗</> {$padded} <fg=red>{$e->getMessage()}</>");
            $this->failures[] = "{$label}: {$e->getMessage()}";

            return false;
        }
    }
}
