<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ProfitReportRequest;
use App\Services\CashReportService;
use App\Services\ProfitReportService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly ProfitReportService $profitService,
        private readonly CashReportService $cashService,
    ) {}

    public function profitSummary(ProfitReportRequest $request): View
    {
        abort_unless(
            auth()->user()?->hasPermissionInCurrentShop('reports.profit.view'),
            403
        );

        $shop = TenantContext::shop();
        $range = $this->profitService->resolveDateRange(
            $request->input('range', 'today'),
            $shop->timezone ?? 'Asia/Kathmandu',
            $request->input('custom_from'),
            $request->input('custom_to'),
        );

        $summary = $this->profitService->summary($shop->id, $range);

        return view('reports.profit', compact('summary', 'range'));
    }

    public function exportCsv(ProfitReportRequest $request): Response
    {
        abort_unless(
            auth()->user()?->hasPermissionInCurrentShop('reports.export'),
            403
        );

        $shop = TenantContext::shop();
        $range = $this->profitService->resolveDateRange(
            $request->input('range', 'today'),
            $shop->timezone ?? 'Asia/Kathmandu',
            $request->input('custom_from'),
            $request->input('custom_to'),
        );

        $summary = $this->profitService->summary($shop->id, $range);

        $csv = "Metric,Value\n";
        foreach ($summary as $key => $value) {
            $csv .= '"'.str_replace('"', '""', $key).'","'.str_replace('"', '""', (string) $value).'"'."\n";
        }

        $filename = 'profit-report-'.$range['from']->toDateString().'-to-'.$range['to']->toDateString().'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function stockValuation(): View
    {
        abort_unless(
            auth()->user()?->hasPermissionInCurrentShop('reports.view'),
            403
        );

        $shop = TenantContext::shop();
        $valuation = $this->profitService->stockValuation($shop->id);

        return view('reports.stock', compact('valuation'));
    }
}
