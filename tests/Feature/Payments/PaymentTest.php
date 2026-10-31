<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Actions\Sales\AllocatePayment;
use App\Actions\Sales\FailPayment;
use App\Actions\Sales\RecordPayment;
use App\Actions\Sales\VerifyPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\CashReportService;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($this->shop);

        $this->user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->user);
        TenantContext::setShop($this->shop);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_record_payment_assigns_payment_no(): void
    {
        $payment = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Cash->value,
            'amount' => '100.00',
        ]);

        $this->assertNotNull($payment->payment_no);
        $this->assertSame(PaymentStatus::Verified, $payment->status);
    }

    public function test_duplicate_idempotency_key_returns_same_payment(): void
    {
        $first = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Cash->value,
            'amount' => '50.00',
            'idempotency_key' => 'dup-key',
        ]);

        $second = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Cash->value,
            'amount' => '50.00',
            'idempotency_key' => 'dup-key',
        ]);

        $this->assertSame($first->id, $second->id);
    }

    public function test_qr_payment_is_pending_and_can_be_verified(): void
    {
        $payment = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '75.00',
        ]);

        $this->assertSame(PaymentStatus::Pending, $payment->status);

        $verified = app(VerifyPayment::class)->handle($payment, $this->user->id);
        $this->assertSame(PaymentStatus::Verified, $verified->status);

        $this->assertAuditTransition($payment, 'pending', 'verified');
    }

    public function test_qr_payment_can_be_failed(): void
    {
        $payment = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '75.00',
        ]);

        $failed = app(FailPayment::class)->handle($payment, $this->user->id);
        $this->assertSame(PaymentStatus::Failed, $failed->status);

        $this->assertAuditTransition($payment, 'pending', 'failed');
    }

    public function test_payment_can_be_allocated_to_a_posted_sale(): void
    {
        $payment = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Cash->value,
            'amount' => '100.00',
        ]);
        $sale = Sale::factory()->posted()->create([
            'shop_id' => $this->shop->id,
            'grand_total' => '80.00',
            'paid_total' => '0.00',
        ]);

        $allocation = app(AllocatePayment::class)->handle($payment, $sale->id, '50.00');

        $this->assertSame('50.00', $allocation->amount);
        $this->assertSame('50.00', $sale->fresh()->paid_total);
        $this->assertSame('50.00', $payment->fresh()->unallocatedAmount());
    }

    public function test_failed_qr_payment_is_excluded_from_cash_report(): void
    {
        $payment = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '75.00',
        ]);
        app(FailPayment::class)->handle($payment, $this->user->id);

        $breakdown = app(CashReportService::class)->cashBreakdown($this->shop->id, [
            'from' => Carbon::now()->startOfDay(),
            'to' => Carbon::now()->addDay()->startOfDay(),
        ]);

        $this->assertSame('0.00', $breakdown['qr_verified']);
        $this->assertSame('0.00', $breakdown['total_received']);
    }

    public function test_payment_status_routes_allow_an_authorized_owner(): void
    {
        $toVerify = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '25.00',
        ]);
        $this->actingAs($this->user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post(route('payments.verify', $toVerify))
            ->assertRedirect();
        $this->assertSame(PaymentStatus::Verified, $toVerify->fresh()->status);

        $toFail = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '30.00',
        ]);
        $this->actingAs($this->user)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post(route('payments.fail', $toFail))
            ->assertRedirect();
        $this->assertSame(PaymentStatus::Failed, $toFail->fresh()->status);
    }

    private function assertAuditTransition(Payment $payment, string $old, string $new): void
    {
        $entry = AuditLog::query()
            ->where('auditable_type', $payment->getMorphClass())
            ->where('auditable_id', $payment->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->shop->id, $entry->shop_id);
        $this->assertSame($this->user->id, $entry->user_id);
        $this->assertSame(['status' => $old], $entry->old_values);
        $this->assertSame(['status' => $new], $entry->new_values);
    }
}
