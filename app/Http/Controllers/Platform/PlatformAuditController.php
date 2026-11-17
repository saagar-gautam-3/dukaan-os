<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\Support\PlatformAuditEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class PlatformAuditController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('platform.manage');

        $adminId = $request->query('admin');
        $shopId = $request->query('shop');
        $event = $request->query('event');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = AuditLog::query()
            ->where('event', 'like', 'platform.%')
            ->with(['user:id,name,email', 'auditable']);

        if (filled($adminId)) {
            $query->where('user_id', (int) $adminId);
        }

        if (filled($shopId)) {
            $query->where('auditable_type', (new Shop)->getMorphClass())
                ->where('auditable_id', (int) $shopId);
        }

        if (filled($event) && in_array($event, PlatformAuditEvents::ALL, true)) {
            $query->where('event', $event);
        }

        $fromDate = $this->parseDate($from);
        if ($fromDate !== null) {
            $query->where('created_at', '>=', $fromDate->copy()->startOfDay()->utc());
        }

        $toDate = $this->parseDate($to);
        if ($toDate !== null) {
            $query->where('created_at', '<=', $toDate->copy()->endOfDay()->utc());
        }

        $logs = $query->latest('created_at')->paginate(50)->withQueryString();

        $admins = User::query()
            ->where('is_platform_admin', true)
            ->orderBy('email')
            ->get(['id', 'email']);

        $shops = Shop::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('platform.audit.index', [
            'logs' => $logs,
            'admins' => $admins,
            'shops' => $shops,
            'events' => PlatformAuditEvents::ALL,
            'adminId' => $adminId,
            'shopId' => $shopId,
            'event' => $event,
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Kathmandu');
        } catch (Throwable) {
            return null;
        }

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
