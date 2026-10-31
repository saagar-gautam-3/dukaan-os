<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\QueryCashSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\CloseCashSessionRequest;
use App\Http\Requests\Reports\OpenCashSessionRequest;
use App\Models\CashSession;
use App\Services\CashReportService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class CashSessionController extends Controller
{
    public function __construct(
        private readonly OpenCashSession $openCashSession,
        private readonly CloseCashSession $closeCashSession,
        private readonly CashReportService $cashReport,
    ) {}

    public function index(QueryCashSessions $queryCashSessions): View
    {
        $this->authorize('viewAny', CashSession::class);

        $sessions = $queryCashSessions->index();

        $currentSession = $this->cashReport->currentSession(
            TenantContext::shop()->id
        );

        return view('cash.index', compact('sessions', 'currentSession'));
    }

    public function show(CashSession $cashSession): View
    {
        $this->authorize('view', $cashSession);

        $detail = $this->cashReport->sessionDetail($cashSession);

        return view('cash.show', compact('cashSession', 'detail'));
    }

    public function open(OpenCashSessionRequest $request): RedirectResponse
    {
        $this->authorize('create', CashSession::class);

        try {
            $this->openCashSession->handle(auth()->id(), $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('cash.index')->with('success', __('cash.session_opened'));
    }

    public function close(CloseCashSessionRequest $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorize('close', $cashSession);

        try {
            $this->closeCashSession->handle($cashSession, auth()->id(), $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('cash.show', $cashSession)->with('success', __('cash.session_closed'));
    }
}
