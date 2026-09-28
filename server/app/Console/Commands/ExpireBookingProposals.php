<?php

namespace App\Console\Commands;

use App\Models\BookingChangeProposal;
use App\Services\DemoClock;
use App\Services\ScheduleMergeService;
use Illuminate\Console\Command;

class ExpireBookingProposals extends Command
{
    protected $signature = 'bookings:expire-proposals';
    protected $description = 'Hết hạn phản hồi ghép chuyến và ghi nhận hoàn tiền cho khách';

    public function handle(ScheduleMergeService $merges): int
    {
        BookingChangeProposal::query()->pending()
            ->whereHas('booking', fn ($query) => DemoClock::commandScope($query, booking: true))
            ->with('booking.schedule')->chunkById(100, function ($proposals) use ($merges) {
                foreach ($proposals as $proposal) {
                    if ($proposal->booking && DemoClock::booking($proposal->booking)->gte($proposal->response_deadline)) {
                        $merges->respond($proposal, 'expire');
                    }
                }
            });
        return self::SUCCESS;
    }
}
