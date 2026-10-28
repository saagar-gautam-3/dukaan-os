<?php

declare(strict_types=1);

namespace App\Http\Controllers\Udhar;

use App\Actions\Udhar\CollectPayment;
use App\Actions\Udhar\SendReminder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Udhar\CollectPaymentRequest;
use App\Jobs\SendReminderJob;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\LedgerService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

final class UdharController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly CollectPayment $collectPayment,
        private readonly SendReminder $sendReminder,
    ) {}

    /**
     * Outstanding customers listing.
     */
    public function outstanding(Request $request): View
    {
        $this->authorize('viewAny', CustomerLedgerEntry::class);

        $shop = TenantContext::shop();
        $search = $request->string('search')->toString() ?: null;

        $customers = $this->ledgerService->outstandingList($shop->id, $search, 20);

        // Calculate total outstanding balance across all customers
        $totalOutstanding = (string) CustomerLedgerEntry::where('shop_id', $shop->id)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')
            ->value('balance');

        return view('udhar.outstanding', [
            'customers' => $customers,
            'totalOutstanding' => $totalOutstanding,
            'search' => $search,
        ]);
    }

    /**
     * Ageing report (current, 30, 60, 90, 90+ days overdue buckets).
     */
    public function ageing(): View
    {
        $this->authorize('viewAny', CustomerLedgerEntry::class);

        $shop = TenantContext::shop();
        $report = $this->ledgerService->ageingReport($shop->id);

        return view('udhar.ageing', [
            'customers' => $report['customers'],
            'totals' => $report['totals'],
        ]);
    }

    /**
     * Collection form for a customer.
     */
    public function collectForm(Customer $customer): View
    {
        $this->authorize('collect', $customer);

        $balance = $customer->ledgerBalance();
        $openSales = $customer->openSales()->get();

        return view('udhar.collect', [
            'customer' => $customer,
            'balance' => $balance,
            'openSales' => $openSales,
        ]);
    }

    /**
     * Process collection payment.
     */
    public function collect(CollectPaymentRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('collect', $customer);

        try {
            $amount = (string) $request->input('amount');
            $method = (string) $request->input('payment_method');
            $notes = $request->string('notes')->toString() ?: null;
            $allocationsInput = $request->input('allocations');
            $allocationsMap = [];
            if (is_array($allocationsInput)) {
                foreach ($allocationsInput as $item) {
                    if (isset($item['sale_id']) && isset($item['amount'])) {
                        $allocationsMap[(int) $item['sale_id']] = (string) $item['amount'];
                    }
                }
            }

            $result = $this->collectPayment->handle(
                customer: $customer,
                userId: (int) auth()->id(),
                data: [
                    'amount' => $amount,
                    'method' => $method,
                    'notes' => $notes,
                    'allocations' => ! empty($allocationsMap) ? $allocationsMap : null,
                ],
            );

            return redirect()->route('customers.show', $customer)
                ->with('status', __('udhar.payment_collected', ['amount' => $amount]));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.unexpected_error'));
        }
    }

    /**
     * Dispatch payment reminder to customer.
     */
    public function remind(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('remind', $customer);

        try {
            // Queue the reminder job
            SendReminderJob::dispatch(
                shopId: (int) $customer->shop_id,
                customerId: (int) $customer->id,
                sentBy: (int) auth()->id(),
                customMessage: $request->string('message')->toString() ?: null,
            );

            return back()->with('status', __('udhar.reminder_sent'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.unexpected_error'));
        }
    }
}
