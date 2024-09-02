<?php

namespace App\Console\Commands\SuperAdmin;

use Illuminate\Console\Command;

class CancelOrphanedMollieSubscriptions extends Command
{
    protected $signature = 'mollie:cancel-orphaned-subscriptions
                            {--dry-run : List matching subscriptions without cancelling them}
                            {--company= : Limit to a single company id}
                            {--list-all : Show every uncancelled local Mollie subscription, including still-valid paid ones}';

    protected $description = 'Cancel Mollie recurring subscriptions that are still charging after the company left the paid plan.';

    public function handle(): int
    {
        $params = ['--gateway' => 'mollie'];

        if ($this->option('dry-run')) {
            $params['--dry-run'] = true;
        }

        if ($this->option('list-all')) {
            $params['--list-all'] = true;
        }

        if ($this->option('company')) {
            $params['--company'] = $this->option('company');
        }

        return $this->call('billing:cancel-orphaned-subscriptions', $params);
    }
}
