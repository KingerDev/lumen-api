<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the single account. There is no signup endpoint, so this is the only
 * way in — run it once after the first deploy.
 */
class CreateUser extends Command
{
    protected $signature = 'lumen:user
                            {--email= : E-mail účtu}
                            {--name= : Meno}
                            {--password= : Heslo (radšej nechaj prázdne a zadaj interaktívne)}';

    protected $description = 'Vytvorí alebo aktualizuje používateľa denníka';

    public function handle(): int
    {
        $email = $this->option('email') ?: text('E-mail', required: true);
        $name = $this->option('name') ?: text('Meno', default: 'Martin');

        // Prefer the prompt: a password given as a flag lands in shell history.
        $plain = $this->option('password') ?: password('Heslo', required: true);

        if (mb_strlen($plain) < 12) {
            $this->error('Heslo musí mať aspoň 12 znakov — toto API je verejne dostupné.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($plain)]
        );

        // A fresh account with no templates could not write a templated entry,
        // and the importer needs the prompt labels to split the export's text.
        if ($user->templates()->doesntExist()) {
            $this->call('db:seed', ['--class' => TemplateSeeder::class, '--force' => true]);
        }

        // Any device still holding a token used the old password.
        $revoked = $user->tokens()->delete();

        $this->info("Používateľ {$user->email} pripravený (id {$user->id}).");

        if ($revoked) {
            $this->warn("Zrušených tokenov: {$revoked} — na telefóne sa treba prihlásiť znova.");
        }

        return self::SUCCESS;
    }
}
