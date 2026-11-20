<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CreateOwnerPasswordResetLink;
use App\Actions\Platform\ReactivateShop;
use App\Actions\Platform\SuspendShop;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateOwnerPasswordResetLinkRequest;
use App\Http\Requests\Platform\SuspendShopRequest;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\Services\Platform\PlatformShopInsights;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

    public function ownerResetForm(Shop $shop): View
    {
        $this->authorize('platform.manage');

        $shop->load(['ownerUser.user:id,email']);

        if (! $this->hasResettableOwner($shop)) {
            abort(404);
        }

        $mailConfigured = ! in_array((string) config('mail.default'), ['log', 'array', '', null], true);

        return view('platform.shops.owner-reset', [
            'shop' => $shop,
            'mailConfigured' => $mailConfigured,
            'expiryMinutes' => (int) config('auth.passwords.users.expire', 60),
        ]);
    }

    public function ownerReset(CreateOwnerPasswordResetLinkRequest $request, Shop $shop, CreateOwnerPasswordResetLink $createOwnerPasswordResetLink): RedirectResponse
    {
        $this->authorize('platform.manage');

        $shop->load(['ownerUser.user:id,email']);

        if (! $this->hasResettableOwner($shop)) {
            abort(404);
        }

        if (! Hash::check((string) $request->input('current_password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('platform.current_password_invalid'),
            ]);
        }

        $result = $createOwnerPasswordResetLink->execute(
            $shop,
            $request->user(),
            (string) $request->validated('reason'),
            (bool) $request->boolean('email_owner')
        );

        session()->flash('owner_reset_result', [
            'shop_id' => $shop->id,
            'url' => $result['url'],
            'expires_at' => $result['expires_at']->toIso8601String(),
            'owner_email' => $result['owner']->email,
        ]);

        return redirect()->route('platform.shops.owner-reset.result', $shop);
    }

    public function ownerResetResult(Shop $shop): Response
    {
        $this->authorize('platform.manage');

        $result = session()->pull('owner_reset_result');

        if (! is_array($result) || (int) ($result['shop_id'] ?? 0) !== (int) $shop->id) {
            $result = null;
        }

        $mailConfigured = ! in_array((string) config('mail.default'), ['log', 'array', '', null], true);

        return response()
            ->view('platform.shops.owner-reset-result', [
                'shop' => $shop,
                'result' => $result,
                'mailConfigured' => $mailConfigured,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function hasResettableOwner(Shop $shop): bool
    {
        $owner = $shop->ownerUser?->user;

        return $owner !== null && ! (bool) $owner->is_platform_admin;
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
