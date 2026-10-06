<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {--email=} {--name=}';

    protected $description = 'Create an administrator account.';

    public function handle(): int
    {
        $email = $this->option('email') ?: text(__('app.admin_create.email_prompt'));

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error(__('app.admin_create.invalid_email'));

            return self::FAILURE;
        }

        if (Admin::query()->where('email', $email)->exists()) {
            $this->error(__('app.admin_create.duplicate_email', ['email' => $email]));

            return self::FAILURE;
        }

        $password = Str::password(24);
        $name = $this->option('name') ?: strstr($email, '@', true);

        Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info(__('app.admin_create.created'));
        $this->line(__('app.admin_create.email_output', ['email' => $email]));
        $this->output->writeln(__('app.admin_create.password_output').' <fg=yellow;options=bold>'.OutputFormatter::escape($password).'</>');

        return self::SUCCESS;
    }
}
