<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\BookingChangeProposal;
use App\Services\DemoClock;
use Illuminate\Console\Command;

class ExpireBookingProposals extends Command
{
    protected $signature = 'bookings:expire-proposals';
    protected $description = 'Hết hạn đề xuất chưa phản hồi, giữ nguyên chuyến của khách';

    public function handle(): int
    {
        BookingChangeProposal::query()->pending()->with('booking.schedule')->chunkById(100, function ($proposals) {
            foreach ($proposals as $proposal) {
                if ($proposal->booking && DemoClock::booking($proposal->booking)->gte($proposal->response_deadline)) {
                    BookingChangeProposal::query()->whereKey($proposal->id)->pending()
                        ->update(['status' => ProposalStatus::Expired->value]);
                }
            }
        });
        return self::SUCCESS;
    }
}
