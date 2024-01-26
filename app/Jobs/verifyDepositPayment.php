<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Services\PaymentGatewayVerificationService;

class verifyDepositPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $payment;

    /**
     * Create a new job instance.
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayVerificationService $verification): void
    {
        $verification->process($this->payment);
    }
}
