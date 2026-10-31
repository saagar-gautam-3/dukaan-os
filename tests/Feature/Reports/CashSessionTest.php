<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\RecordCashMovement;
use App\Enums\CashMovementType;
use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Part 1 — Cash Session Tests
 *
 * Covers:
 *  - Only one open session per shop at a time (enforced by OpenCashSession)
 *  - Close session shows expected vs counted difference
 *  - Cross-tenant isolation for cash_sessions
 */
final class CashSessionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => true],
            'timezone' => 'Asia/Kathmandu',
        ]);
        TenantContext::setShop($this->shop);

        $rbac = app(RbacService::class);
        $rbac->createDefaultRolesForShop($this->shop);

        $this->user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'role_id' => $this->shop->roles()->where('name', 'Shop Owner')->first()->id,
            'status' => 'active',
        ]);

        Warehouse::factory()->create([
            'shop_id' => $this->shop->id,
            'is_default' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ONE OPEN SESSION RULE
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Only one open session per shop is allowed at a time.
     * Attempting to open a second throws InvalidArgumentException.
     */
    public function test_only_one_open_session_per_shop_is_enforced(): void
    {
        // Open first session
        app(OpenCashSession::class)->handle($this->user->id, [
            'opening_cash' => '5000.00',
        ]);

        // Attempting to open a second must throw
        $this->expectException(InvalidArgumentException::class);

        app(OpenCashSession::class)->handle($this->user->id, [
            'opening_cash' => '1000.00',
        ]);
    }

    /**
     * After closing a session, a new one can be opened.
     */
    public function test_new_session_can_be_opened_after_previous_is_closed(): void
    {
        $session = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '2000.00']);

        app(CloseCashSession::class)->handle($session, $this->user->id, ['closing_cash' => '2000.00']);

        // Should succeed without exception
        $newSession = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '500.00']);

        $this->assertNotNull($newSession);
        $this->assertTrue($newSession->isOpen());
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPECTED vs COUNTED DIFFERENCE
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Close session shows correct expected vs counted difference.
     *
     * Scenario:
     *   Opening: 5,000
     *   CashIn movements: 2,000 (sales) + 500 (other)
     *   CashOut movements: 300 (expense)
     *   Expected = 5000 + 2000 + 500 - 300 = 7,200
     *   Counted = 7,500
     *   Difference = +300 (counted > expected = overage)
     */
    public function test_close_session_shows_expected_vs_counted_difference(): void
    {
        $session = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '5000.00']);

        // Record cash in movement: Rs 2000 (sale)
        $movement = app(RecordCashMovement::class)->handle($this->shop->id, $this->user->id, [
            'type' => CashMovementType::SaleCash->value,
            'amount' => '2000.00',
            'reference_type' => 'payment',
            'reference_id' => 1,
            'notes' => 'Sale cash',
        ]);
        $movementAudit = AuditLog::query()
            ->where('auditable_type', $movement->getMorphClass())
            ->where('auditable_id', $movement->id)
            ->where('event', 'recorded')
            ->firstOrFail();
        $this->assertSame($this->shop->id, $movementAudit->shop_id);
        $this->assertSame($this->user->id, $movementAudit->user_id);
        $this->assertNull($movementAudit->old_values);
        $this->assertSame('2000.00', $movementAudit->new_values['amount']);

        // Record cash in movement: Rs 500 (other)
        app(RecordCashMovement::class)->handle($this->shop->id, $this->user->id, [
            'type' => CashMovementType::CashIn->value,
            'amount' => '500.00',
            'notes' => 'Other cash in',
        ]);

        // Record cash out movement: Rs 300 (expense)
        app(RecordCashMovement::class)->handle($this->shop->id, $this->user->id, [
            'type' => CashMovementType::ExpenseCash->value,
            'amount' => '300.00',
            'reference_type' => 'expense',
            'reference_id' => 1,
            'notes' => 'Expense paid',
        ]);

        // Expected = 5000 + 2000 + 500 - 300 = 7200
        $expectedCash = $session->fresh()->computeExpectedCash();
        $this->assertSame('7200.00', $expectedCash, 'Expected cash must be 7200');

        // Count Rs 7500 physically
        $closed = app(CloseCashSession::class)->handle($session, $this->user->id, [
            'closing_cash' => '7500.00',
        ]);

        $this->assertSame('7200.00', (string) $closed->expected_cash, 'Expected must be saved as 7200');
        $this->assertSame('7500.00', (string) $closed->closing_cash, 'Closing must be 7500');
        $this->assertSame('300.00', (string) $closed->difference, 'Difference must be +300 (overage)');
        $this->assertFalse($closed->isOpen(), 'Session must be closed');
        $closeAudit = AuditLog::query()
            ->where('auditable_type', $closed->getMorphClass())
            ->where('auditable_id', $closed->id)
            ->where('event', 'closed')
            ->firstOrFail();
        $this->assertSame($this->shop->id, $closeAudit->shop_id);
        $this->assertSame($this->user->id, $closeAudit->user_id);
        $this->assertSame('open', $closeAudit->old_values['status']);
        $this->assertSame('closed', $closeAudit->new_values['status']);
        $this->assertSame('300.00', $closeAudit->new_values['difference']);
    }

    /**
     * Shortfall (counted < expected) is recorded as a negative difference.
     */
    public function test_close_session_shortfall_is_negative_difference(): void
    {
        $session = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '3000.00']);

        $closed = app(CloseCashSession::class)->handle($session, $this->user->id, [
            'closing_cash' => '2800.00',
        ]);

        // Expected = 3000 (opening only, no other movements)
        // Counted = 2800
        // Difference = 2800 - 3000 = -200
        $this->assertSame('-200.00', (string) $closed->difference, 'Shortfall must be -200');
    }

    /**
     * Closing an already-closed session throws InvalidArgumentException.
     */
    public function test_closing_already_closed_session_throws(): void
    {
        $session = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '1000.00']);
        app(CloseCashSession::class)->handle($session, $this->user->id, ['closing_cash' => '1000.00']);

        $this->expectException(InvalidArgumentException::class);

        app(CloseCashSession::class)->handle($session->fresh(), $this->user->id, ['closing_cash' => '1000.00']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CROSS-TENANT ISOLATION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cash sessions from another shop must not be visible in this shop's context.
     */
    public function test_cross_tenant_isolation_cash_sessions(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::setShop($otherShop);

        $rbac = app(RbacService::class);
        $rbac->createDefaultRolesForShop($otherShop);

        // Open a session in other shop
        CashSession::factory()->create([
            'shop_id' => $otherShop->id,
            'opening_cash' => '10000.00',
            'opened_by' => $this->user->id,
        ]);

        // Switch back to this shop
        TenantContext::setShop($this->shop);

        // This shop should see 0 sessions
        $this->assertCount(0, CashSession::all(), 'Other shop sessions must not be visible');
    }

    // ─────────────────────────────────────────────────────────────────────
    // OPENING CASH VALIDATION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Opening cash cannot be negative.
     */
    public function test_opening_cash_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '-100.00']);
    }

    /**
     * Opening with zero cash is allowed.
     */
    public function test_opening_with_zero_cash_is_valid(): void
    {
        $session = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '0.00']);

        $this->assertNotNull($session);
        $this->assertSame('0.00', (string) $session->opening_cash);

        $audit = AuditLog::query()
            ->where('auditable_type', $session->getMorphClass())
            ->where('auditable_id', $session->id)
            ->where('event', 'opened')
            ->firstOrFail();
        $this->assertSame($this->shop->id, $audit->shop_id);
        $this->assertSame($this->user->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertSame('0.00', $audit->new_values['opening_cash']);
    }
}
