<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\FailPayment;
use App\Actions\Sales\VerifyPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly VerifyPayment $verifyPayment,
        private readonly FailPayment $failPayment,
    ) {}

    public function verify(Payment $payment): RedirectResponse
    {
        $this->authorize('payments.verify');

        try {
            $this->verifyPayment->handle($payment, (int) auth()->id());

            return back()->with('status', __('sales.payment_verified'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }

    public function fail(Payment $payment): RedirectResponse
    {
        $this->authorize('payments.verify');

        try {
            $this->failPayment->handle($payment, (int) auth()->id());

            return back()->with('status', __('sales.payment_failed'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }
}
