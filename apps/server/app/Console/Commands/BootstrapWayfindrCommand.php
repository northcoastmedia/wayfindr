<?php

namespace App\Console\Commands;

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapWayfindrCommand extends Command
{
    protected $signature = 'wayfindr:bootstrap
        {--account=Wayfindr Support : Name for the first support account}
        {--slug= : Slug for the first support account}
        {--name=Support Agent : Name for the first agent}
        {--email= : Email address for the first agent}
        {--password= : Password for the first agent; generated when omitted}
        {--site=Default Site : Name for the first install site}
        {--domain= : Optional domain for the first install site}
        {--site-public-key= : Public widget key for the first site; generated when omitted}
        {--force : Allow bootstrap to run when records already exist}';

    protected $description = 'Create the first Wayfindr account, agent, and site.';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Pass --email to create the first agent.');

            return self::FAILURE;
        }

        if ($this->hasBootstrapData() && ! $this->option('force')) {
            $this->error('Wayfindr already has bootstrap data. Re-run with --force if you intentionally want to create or update bootstrap records.');

            return self::FAILURE;
        }

        $accountName = trim((string) $this->option('account')) ?: 'Wayfindr Support';
        $accountSlug = trim((string) $this->option('slug')) ?: Str::slug($accountName);
        $accountSlug = $accountSlug !== '' ? $accountSlug : 'wayfindr-support';
        $agentName = trim((string) $this->option('name')) ?: 'Support Agent';
        $siteName = trim((string) $this->option('site')) ?: 'Default Site';
        $domain = trim((string) $this->option('domain')) ?: null;
        $password = (string) $this->option('password');
        $passwordWasGenerated = $password === '';

        if ($passwordWasGenerated) {
            $password = Str::password(24);
        }

        $sitePublicKey = trim((string) $this->option('site-public-key')) ?: $this->generateSitePublicKey();

        $account = Account::query()->updateOrCreate(
            ['slug' => $accountSlug],
            ['name' => $accountName],
        );

        $agent = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'account_id' => $account->id,
                'account_role' => AccountRole::Owner,
                'name' => $agentName,
                'password' => Hash::make($password),
            ],
        );

        $site = Site::query()->updateOrCreate(
            ['public_key' => $sitePublicKey],
            [
                'account_id' => $account->id,
                'name' => $siteName,
                'domain' => $domain,
                'settings' => [
                    'mask_selectors' => ['input[type="password"]', '[data-wayfindr-mask]'],
                ],
            ],
        );

        $site->supportAgents()->syncWithoutDetaching($agent->id);

        $this->info('Wayfindr is ready.');
        $this->line("Account: {$accountName}");
        $this->line("Agent email: {$email}");

        if ($passwordWasGenerated) {
            $this->line("Agent password: {$password}");
        } else {
            $this->line('Agent password: [provided]');
        }

        $this->line("Site: {$siteName}");
        $this->line("Site public key: {$sitePublicKey}");

        return self::SUCCESS;
    }

    private function hasBootstrapData(): bool
    {
        return Account::query()->exists()
            || User::query()->whereNotNull('account_id')->exists()
            || Site::query()->exists();
    }

    private function generateSitePublicKey(): string
    {
        do {
            $key = 'site_'.Str::lower(Str::random(32));
        } while (Site::query()->where('public_key', $key)->exists());

        return $key;
    }
}
