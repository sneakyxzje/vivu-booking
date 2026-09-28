<?php

namespace App\Services;

use App\Enums\BookingAuditAction;
use App\Enums\BookingStatus;
use App\Enums\ProposalStatus;
use App\Models\Booking;
use App\Models\BookingChangeProposal;
use App\Models\TourSchedule;

class BookingProposalService
{
    public function __construct(
        private BookingTransferService $transferService,
        private BookingPaymentService $payments,
        private BookingHoldService $holdService,
        private BookingAuditLogger $auditLogger
    ) {
    }


}
