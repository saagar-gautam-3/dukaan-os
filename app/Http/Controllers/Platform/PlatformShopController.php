<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ReactivateShop;
use App\Actions\Platform\SuspendShop;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SuspendShopRequest;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\Services\Platform\PlatformShopInsights;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PlatformShopController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('platform.manage');

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        $shops = Shop::query()
            ->with(['ownerUser.user:id,email'])
            ->withCount('users')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%')
                        ->orWhereHas('ownerUser.user', function ($userQuery) use ($q): void {
                            $userQuery->where('email', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'suspended', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('platform.shops.index', compact('shops', 'q', 'status'));
    }

    public function suspendForm(Shop $shop): View
    {
        $this->authorize('platform.manage');

        $shop->load(['ownerUser.user:id,email']);

        return view('platform.shops.suspend', compact('shop'));
    }

    public function suspend(SuspendShopRequest $request, Shop $shop, SuspendShop $suspendShop): RedirectResponse
    {
        $this->authorize('platform.manage');

        $suspendShop->execute($shop, $request->user(), (string) $request->validated('reason'));

        return redirect()->route('platform.shops.index')
            ->with('status', __('platform.shop_suspended'));
    }

    public function reactivate(Shop $shop, ReactivateShop $reactivateShop): RedirectResponse
    {
        $this->authorize('platform.manage');

        $reactivateShop->execute($shop, request()->user());

        return redirect()->route('platform.shops.index')
            ->with('status', __('platform.shop_reactivated'));
    }

    public function show(Shop $shop, PlatformShopInsights $insights): View
    {
        $this->authorize('platform.manage');

        $shop->load(['ownerUser.user:id,name,email']);

        $this->recordViewAudit($shop, request()->user());

        $platformAudits = AuditLog::query()
            ->where('auditable_type', $shop->getMorphClass())
            ->where('auditable_id', $shop->id)
            ->whereIn('event', PlatformAuditEvents::SHOP_HISTORY)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('platform.shops.show', [
            'shop' => $shop,
            'insights' => $insights->for($shop),
            'platformAudits' => $platformAudits,
        ]);
    }

    private function recordViewAudit(Shop $shop, ?User $admin): void
    {
        if ($admin === null) {
            return;
        }

        $recent = AuditLog::query()
            ->where('event', PlatformAuditEvents::SHOP_VIEWED)
            ->where('user_id', $admin->id)
            ->where('auditable_type', $shop->getMorphClass())
            ->where('auditable_id', $shop->id)
            ->where('created_at', '>=', Carbon::now()->subMinutes(60))
            ->exists();

        if ($recent) {
            return;
        }

        AuditTrail::record($shop, $admin->id, PlatformAuditEvents::SHOP_VIEWED, null, [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
        ]);
    }
}
