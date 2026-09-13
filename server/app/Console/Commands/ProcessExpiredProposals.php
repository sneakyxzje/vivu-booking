<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\BookingChangeProposal;
use App\Services\BookingProposalService;
use Illuminate\Console\Command;

class ProcessExpiredProposals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proposals:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quét và xử lý tự động (fallback_action) các đề xuất thay đổi đã quá hạn phản hồi';

    /**
     * Execute the console command.
     */
    public function handle(BookingProposalService $proposalService)
    {
        $expiredProposals = BookingChangeProposal::where('status', ProposalStatus::Pending->value)
            ->where('response_deadline', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredProposals as $proposal) {
            $proposal->update(['status' => ProposalStatus::Expired->value]);

            if ($proposal->fallback_action) {
                try {
                    $proposalService->executeSystemAction($proposal, $proposal->fallback_action);
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Lỗi khi xử lý fallback cho proposal {$proposal->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Đã xử lý xong {$count} đề xuất quá hạn.");
    }
}
