<?php

require_once __DIR__ . '/budget_period_calculations.php';

if (!defined('WALLOS_WIDGET_SCHEMA_VERSION')) {
    define('WALLOS_WIDGET_SCHEMA_VERSION', 1);
}

if (!function_exists('wallos_widget_ids')) {
    /**
     * Stable public widget_id values (dashboard order).
     *
     * @return string[]
     */
    function wallos_widget_ids()
    {
        return [
            'overdue',
            'upcoming',
            'ai',
            'monthly_budget',
            'period_budget',
            'payment_method_budget',
            'subscriptions',
            'savings',
            'category_cost',
        ];
    }
}

if (!function_exists('wallos_widget_setting_column')) {
    function wallos_widget_setting_column($widgetId)
    {
        return 'dashboard_widget_' . $widgetId;
    }
}

if (!function_exists('wallos_is_widget_enabled')) {
    /**
     * Visibility defaults to ON when the flag is missing (pre-migration / unset).
     */
    function wallos_is_widget_enabled(array $settings, $widgetId)
    {
        $column = wallos_widget_setting_column($widgetId);
        if (!array_key_exists($column, $settings) || $settings[$column] === null) {
            return true;
        }

        return (int) $settings[$column] === 1 || $settings[$column] === true || $settings[$column] === '1';
    }
}

if (!function_exists('wallos_widget_title_key')) {
    function wallos_widget_title_key($widgetId)
    {
        $map = [
            'overdue' => 'overdue_renewals',
            'upcoming' => 'upcoming_payments',
            'ai' => 'ai_recommendations',
            'monthly_budget' => 'monthly_budget',
            'period_budget' => 'period_budget',
            'payment_method_budget' => 'payment_method_budget',
            'subscriptions' => 'your_subscriptions',
            'savings' => 'your_savings',
            'category_cost' => 'category_cost',
        ];

        return $map[$widgetId] ?? $widgetId;
    }
}

if (!function_exists('wallos_parse_payment_method_ids')) {
    /**
     * Parse optional payment_method_id filter: single id or comma-separated list.
     *
     * @return int[]|null null means no filter
     */
    function wallos_parse_payment_method_ids($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = explode(',', (string) $raw);
        }

        $ids = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $ids[] = (int) $part;
        }

        return empty($ids) ? null : array_values(array_unique($ids));
    }
}

if (!function_exists('wallos_filter_subscriptions_by_payment_method')) {
    function wallos_filter_subscriptions_by_payment_method(array $subscriptions, $paymentMethodId)
    {
        $paymentMethodId = (int) $paymentMethodId;
        return array_values(array_filter($subscriptions, function ($subscription) use ($paymentMethodId) {
            return isset($subscription['payment_method_id'])
                && (int) $subscription['payment_method_id'] === $paymentMethodId;
        }));
    }
}

if (!function_exists('wallos_build_payment_method_budget_rows')) {
    /**
     * Period-scoped budget vs amount needed per payment method.
     * Uses the same window as the household period budget (today → period end).
     *
     * @param array $paymentMethods rows with id, name, icon, enabled, budget
     * @param array $subscriptions active+inactive subscription rows
     * @param int[]|null $filterIds optional payment_method_id filter
     * @param bool $includeDisabled include disabled methods
     * @param bool $onlyWithBudget when true and no filter, only methods with budget > 0
     * @return array
     */
    function wallos_build_payment_method_budget_rows(
        array $paymentMethods,
        array $subscriptions,
        DateTime $rangeStart,
        DateTime $periodEnd,
        SQLite3 $database,
        $userId,
        $filterIds = null,
        $includeDisabled = false,
        $onlyWithBudget = true
    ) {
        $rows = [];

        foreach ($paymentMethods as $method) {
            $methodId = (int) ($method['id'] ?? $method['payment_method_id'] ?? 0);
            if ($methodId <= 0) {
                continue;
            }

            if ($filterIds !== null && !in_array($methodId, $filterIds, true)) {
                continue;
            }

            $enabled = !isset($method['enabled']) || (int) $method['enabled'] === 1;
            if (!$includeDisabled && !$enabled) {
                continue;
            }

            $budget = max(0, (float) ($method['budget'] ?? 0));
            if ($onlyWithBudget && $filterIds === null && $budget <= 0) {
                continue;
            }

            $methodSubs = wallos_filter_subscriptions_by_payment_method($subscriptions, $methodId);
            $amountNeeded = computeAmountNeededInPeriod(
                $methodSubs,
                $rangeStart,
                $periodEnd,
                $database,
                $userId
            );

            $remaining = max(0, $budget - $amountNeeded);
            $overBudget = max(0, $amountNeeded - $budget);
            $usedPercent = $budget > 0 ? min(100, ($amountNeeded / $budget) * 100) : 0;

            $rows[] = [
                'payment_method_id' => $methodId,
                'name' => $method['name'] ?? '',
                'icon' => $method['icon'] ?? '',
                'enabled' => $enabled,
                'budget' => round($budget, 2),
                'amount_needed' => round($amountNeeded, 2),
                'budget_used_percent' => round($usedPercent, 2),
                'remaining' => round($remaining, 2),
                'over_budget' => round($overBudget, 2),
            ];
        }

        return $rows;
    }
}

if (!function_exists('wallos_build_category_cost_rows')) {
    /**
     * Top-N category monthly costs from precomputed $categoryCost map.
     *
     * @param array $categoryCost [id => ['name' => ..., 'cost' => ...]]
     * @return array
     */
    function wallos_build_category_cost_rows(array $categoryCost, $limit = 5)
    {
        $limit = max(1, (int) $limit);
        $rows = [];

        foreach ($categoryCost as $categoryId => $entry) {
            $cost = (float) ($entry['cost'] ?? 0);
            if ($cost <= 0) {
                continue;
            }
            $rows[] = [
                'category_id' => (int) $categoryId,
                'name' => $entry['name'] ?? '',
                'monthly_cost' => round($cost, 2),
            ];
        }

        usort($rows, function ($a, $b) {
            return $b['monthly_cost'] <=> $a['monthly_cost'];
        });

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('wallos_list_widgets_catalog')) {
    /**
     * Catalog payload for list_widgets API / settings UI.
     *
     * @return array
     */
    function wallos_list_widgets_catalog(array $settings, array $i18n)
    {
        $widgets = [];
        foreach (wallos_widget_ids() as $widgetId) {
            $titleKey = wallos_widget_title_key($widgetId);
            $widgets[] = [
                'widget_id' => $widgetId,
                'enabled' => wallos_is_widget_enabled($settings, $widgetId),
                'title' => function_exists('translate')
                    ? translate($titleKey, $i18n)
                    : ($i18n[$titleKey] ?? $widgetId),
                'requires_params' => $widgetId === 'payment_method_budget',
            ];
        }

        return [
            'success' => true,
            'schema_version' => WALLOS_WIDGET_SCHEMA_VERSION,
            'widgets' => $widgets,
        ];
    }
}

?>
