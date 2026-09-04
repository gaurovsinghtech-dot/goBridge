<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Modules\Shared\Models\Contact;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CrmAnalyticsService
{
    /**
     * Get CRM overview KPIs and metrics.
     */
    public function getDashboardMetrics(int $workspaceId, int $days = 30): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();

        $totalLeads = Contact::where('workspace_id', $workspaceId)->count();
        $newLeads = Contact::where('workspace_id', $workspaceId)->where('created_at', '>=', $since)->count();
        $qualifiedLeads = Contact::where('workspace_id', $workspaceId)
            ->where(function ($q) {
                $q->where('lead_score', '>=', 70)
                  ->orWhereHas('stage', fn ($sq) => $sq->where('name', 'like', '%Qualified%'));
            })->count();

        $wonContacts = Contact::where('workspace_id', $workspaceId)
            ->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->get();

        $wonCount = $wonContacts->count();

        $lostCount = Contact::where('workspace_id', $workspaceId)
            ->whereHas('stage', fn ($q) => $q->where('is_lost', true))
            ->count();

        $wonDeals = CrmDeal::where('workspace_id', $workspaceId)
            ->where(function ($q) {
                $q->where('status', 'won')
                    ->orWhereHas('stage', fn ($sq) => $sq->where('is_won', true));
            })
            ->get();

        $wonRevenue = (float) $wonDeals->sum('value');
        if ($wonRevenue <= 0) {
            $wonRevenue = (float) $wonContacts->sum('deal_value');
        }

        $wonCountTotal = max($wonCount, $wonDeals->count());
        $avgDealValue = $wonCountTotal > 0 ? round($wonRevenue / $wonCountTotal, 2) : 0.0;

        // Sales cycle duration (days from lead creation to won)
        $cycleDays = [];
        foreach ($wonContacts as $wc) {
            if ($wc->created_at && $wc->updated_at) {
                $created = \Carbon\Carbon::parse($wc->created_at);
                $updated = \Carbon\Carbon::parse($wc->updated_at);
                $cycleDays[] = (float) $created->diffInDays($updated);
            }
        }
        $avgSalesCycleDays = ! empty($cycleDays) ? round(array_sum($cycleDays) / count($cycleDays), 1) : 0.0;

        $totalPipelineValue = (float) Contact::where('workspace_id', $workspaceId)->sum('deal_value');
        $dealsPipelineValue = (float) CrmDeal::where('workspace_id', $workspaceId)->sum('value');
        if ($dealsPipelineValue > 0) {
            $totalPipelineValue = $dealsPipelineValue;
        }

        // Weighted value calculation
        $contactsWithStages = Contact::where('workspace_id', $workspaceId)
            ->with('stage')
            ->get();

        $weightedValue = $contactsWithStages->sum(function ($c) {
            $prob = $c->stage?->probability ?? 10;
            return ($c->deal_value * ($prob / 100));
        });

        $conversionRate = $totalLeads > 0 ? round(($wonCount / $totalLeads) * 100, 1) : 0.0;

        $followUpsToday = CrmTask::where('workspace_id', $workspaceId)
            ->where('status', '!=', 'completed')
            ->whereDate('due_at', '<=', Carbon::today())
            ->count();

        return [
            'total_leads' => $totalLeads,
            'new_leads' => $newLeads,
            'qualified_leads' => $qualifiedLeads,
            'won_deals' => $wonCountTotal,
            'lost_deals' => $lostCount,
            'pipeline_value' => round($totalPipelineValue, 2),
            'weighted_value' => round($weightedValue, 2),
            'won_revenue' => round($wonRevenue, 2),
            'average_deal_value' => $avgDealValue,
            'sales_cycle_days' => $avgSalesCycleDays,
            'conversion_rate' => $conversionRate,
            'follow_ups_due' => $followUpsToday,
        ];
    }

    /**
     * Get Channel Source Attribution.
     */
    public function getChannelAttribution(int $workspaceId, int $days = 30): array
    {
        $sources = ['whatsapp', 'instagram', 'messenger', 'email', 'website', 'google_ads', 'facebook_ads', 'voice', 'api', 'manual'];
        $results = [];

        $contacts = Contact::where('workspace_id', $workspaceId)
            ->with('stage')
            ->get();

        foreach ($sources as $source) {
            $matching = $contacts->filter(fn ($c) => ($c->source ?? 'manual') === $source);
            $total = $matching->count();
            $won = $matching->filter(fn ($c) => $c->stage && $c->stage->is_won)->count();
            $val = $matching->sum('deal_value');

            if ($total > 0) {
                $results[] = [
                    'source' => $source,
                    'label' => ucwords(str_replace('_', ' ', $source)),
                    'leads' => $total,
                    'won' => $won,
                    'total_value' => round($val, 2),
                    'conversion_pct' => round(($won / $total) * 100, 1),
                ];
            }
        }

        // Add fallback default if empty
        if (empty($results)) {
            $results[] = [
                'source' => 'manual',
                'label' => 'Direct / Organic',
                'leads' => $contacts->count(),
                'won' => 0,
                'total_value' => 0.0,
                'conversion_pct' => 0.0,
            ];
        }

        return $results;
    }

    /**
     * Get Stage Conversion Funnel.
     */
    public function getConversionFunnel(int $workspaceId, ?int $pipelineId = null): array
    {
        $stagesQuery = CrmPipelineStage::where('workspace_id', $workspaceId)->orderBy('position');
        if ($pipelineId) {
            $stagesQuery->where('pipeline_id', $pipelineId);
        }
        $stages = $stagesQuery->get();

        $funnel = [];
        $firstCount = null;

        foreach ($stages as $stage) {
            $count = Contact::where('workspace_id', $workspaceId)
                ->where('stage_id', $stage->id)
                ->count();

            if ($firstCount === null) {
                $firstCount = max(1, $count);
            }

            $funnel[] = [
                'stage_id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'count' => $count,
                'drop_off_pct' => round(($count / $firstCount) * 100, 1),
            ];
        }

        return $funnel;
    }
}
