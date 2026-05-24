<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAgentCommand extends Command
{
    protected $signature = 'wayfindr:agent
        {--account= : Account slug or ID; optional when only one account exists}
        {--name=Support Agent : Name for the agent}
        {--email= : Email address for the agent}
        {--password= : Password for the agent; generated when omitted}';

    protected $description = 'Create or update a Wayfindr agent for an existing account.';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Pass --email to create an agent.');

            return self::FAILURE;
        }

        $account = $this->resolveAccount();

        if (! $account) {
            return self::FAILURE;
        }

        $existingAgent = User::query()->where('email', $email)->first();

        if ($existingAgent && (int) $existingAgent->account_id !== (int) $account->id) {
            $this->error('That email already belongs to another account.');

            return self::FAILURE;
        }

        $agentName = trim((string) $this->option('name')) ?: 'Support Agent';
        $password = (string) $this->option('password');
        $passwordWasGenerated = $password === '';

        if ($passwordWasGenerated) {
            $password = Str::password(24);
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'account_id' => $account->id,
                'name' => $agentName,
                'password' => Hash::make($password),
            ],
        );

        $this->info('Agent ready.');
        $this->line("Account: {$account->name}");
        $this->line("Agent email: {$email}");

        if ($passwordWasGenerated) {
            $this->line("Agent password: {$password}");
        } else {
            $this->line('Agent password: [provided]');
        }

        return self::SUCCESS;
    }

    private function resolveAccount(): ?Account
    {
        $account = trim((string) $this->option('account'));

        if ($account !== '') {
            $query = Account::query();

            if (ctype_digit($account)) {
                $query->where('id', (int) $account);
            } else {
                $query->where('slug', $account);
            }

            $matchedAccount = $query->first();

            if (! $matchedAccount) {
                $this->error('No matching account was found.');
            }

            return $matchedAccount;
        }

        if (Account::query()->count() === 1) {
            return Account::query()->sole();
        }

        $this->error('Pass --account with an account slug or ID.');

        return null;
    }
}
