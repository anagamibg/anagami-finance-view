<?php

namespace App\Listeners;

use App\Events\NewProposalEvent;
use App\Models\User;
use App\Notifications\NewProposal;
use App\Notifications\ProposalSigned;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Notification;

class NewProposalListener
{

    /**
     * @param NewProposalEvent $event
     */

    public function handle(NewProposalEvent $event)
    {
        if ($event->type == 'signed' && $event->proposal->status != 'waiting') {
            if (empty($event->proposal->company_id)) {
                return;
            }

            // Public signers are often guests, or logged into another company.
            // CompanyScope would hide this proposal's admins in that request.
            $allAdmins = User::withoutGlobalScope(CompanyScope::class)
                ->without('clientDetails')
                ->withRole('admin')
                ->where('users.company_id', $event->proposal->company_id)
                ->get();

            Notification::send($allAdmins, new ProposalSigned($event->proposal));
        }
        else {
            // Notify client
            Notification::send($event->proposal->lead, new NewProposal($event->proposal));
        }
    }

}
