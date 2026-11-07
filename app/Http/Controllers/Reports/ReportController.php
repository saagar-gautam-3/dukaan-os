<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\QueryDailySummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\DailySummaryRequest;
use App\Http\Requests\Reports\ProfitReportRequest;
use App\Models\Shop;
use App\Services\CashReportService;
use App\Services\ProfitReportService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

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

    public function dailySummary(DailySummaryRequest $request, QueryDailySummary $queryDailySummary): View
    {
        abort_unless(
            auth()->user()?->hasPermissionInCurrentShop('reports.view'),
            403
        );

        $shop = TenantContext::shop();
        $timezone = $shop->timezone ?? 'Asia/Kathmandu';

        try {
            $summary = $queryDailySummary->handle($shop, $request->validated('date'));
        } catch (Throwable $e) {
            report($e);

            return view('reports.daily-summary', [
                'summary' => null,
                'date' => Carbon::now($timezone)->toDateString(),
                'prevDate' => null,
                'nextDate' => null,
                'shop' => $shop,
                'strings' => [
                    'sales' => can_route('sales.view'),
                    'expenses' => can_route('expenses.view'),
                    'udhar' => can_route('udhar.view'),
                    'cash' => can_route('cash.view'),
                    'stock' => can_route('inventory.view'),
                ],
                'whatsappUrl' => null,
                'error' => __('common.error_generic'),
            ]);
        }

        $date = $request->validated('date') ?? Carbon::now($timezone)->toDateString();
        $parsed = Carbon::parse($date);

        $strings = [
            'sales' => can_route('sales.view'),
            'expenses' => can_route('expenses.view'),
            'udhar' => can_route('udhar.view'),
            'cash' => can_route('cash.view'),
            'stock' => can_route('inventory.view'),
        ];

        return view('reports.daily-summary', [
            'summary' => $summary,
            'date' => $date,
            'prevDate' => $parsed->copy()->subDay()->toDateString(),
            'nextDate' => $parsed->copy()->addDay()->toDateString(),
            'shop' => $shop,
            'strings' => $strings,
            'whatsappUrl' => $this->whatsappUrl($shop, $summary, $date, $strings),
            'error' => null,
        ]);
    }

    private function whatsappUrl(Shop $shop, array $summary, string $date, array $strings): string
    {
        $message = str_replace(
            [
                ':shop',
                ':date',
                ':bills',
                ':net_sales',
                ':cash',
                ':udhar_given',
                ':udhar_collected',
                ':expenses',
                ':low_stock',
            ],
            [
                $shop->name,
                $date,
                (string) ($strings['sales'] ? $summary['sales']['bill_count'] : 0),
                format_npr($summary['sales']['net_sales']),
                format_npr($summary['cash']['net']),
                format_npr($summary['udhar']['given']),
                format_npr($summary['udhar']['collected']),
                format_npr($strings['expenses'] ? $summary['expenses']['total'] : '0.00'),
                (string) ($strings['stock'] ? $summary['stock']['low_stock_count'] : 0),
            ],
            __('daily.whatsapp_template')
        );

        return 'https://wa.me/?text='.urlencode($message);
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
