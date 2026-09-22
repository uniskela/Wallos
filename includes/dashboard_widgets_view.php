<?php
/**
 * Renders the sortable dashboard widget list.
 * Expects variables prepared by index.php (subscriptions data, budgets, settings, i18n, …).
 */

$dashboardWidgetLayout = wallos_get_dashboard_widget_layout($settings);

$widgetHasContent = [
    'overdue' => $hasOverdueSubscriptions,
    'upcoming' => true, // always has a section (empty state message ok)
    'ai' => !empty($aiRecommendations),
    'monthly_budget' => isset($totalCostPerMonth),
    'period_budget' => isset($periodBudget) && $periodBudget > 0,
    'payment_method_budget' => !empty($paymentMethodBudgetRows),
    'subscriptions' => isset($activeSubscriptions) && $activeSubscriptions > 0,
    'savings' => isset($inactiveSubscriptions) && $inactiveSubscriptions > 0,
    'category_cost' => !empty($categoryCostRows),
];

?>
<div class="dashboard-toolbar">
    <button type="button" id="editDashboardWidgets" class="button thin" title="<?= translate('edit_widgets', $i18n) ?>">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
        <span><?= translate('edit_widgets', $i18n) ?></span>
    </button>
    <button type="button" id="doneDashboardWidgets" class="button thin" hidden title="<?= translate('done_editing_widgets', $i18n) ?>">
        <i class="fa-solid fa-check" aria-hidden="true"></i>
        <span><?= translate('done_editing_widgets', $i18n) ?></span>
    </button>
    <p class="dashboard-edit-hint" hidden><?= translate('edit_widgets_hint', $i18n) ?></p>
</div>

<div id="dashboard-widgets-list" class="dashboard-widgets-list sortable-list">
<?php
foreach ($dashboardWidgetLayout as $entry) {
    $widgetId = $entry['widget_id'];
    $enabled = $entry['enabled'];
    $hasContent = !empty($widgetHasContent[$widgetId]);
    $titleKey = wallos_widget_title_key($widgetId);
    $title = translate($titleKey, $i18n);
    ?>
    <div class="dashboard-widget"
         data-widget-id="<?= htmlspecialchars($widgetId, ENT_QUOTES, 'UTF-8') ?>"
         data-enabled="<?= $enabled ? '1' : '0' ?>"
         data-has-content="<?= $hasContent ? '1' : '0' ?>">
        <div class="dashboard-widget-chrome">
            <div class="drag-icon" title="<?= translate('reorder_widget', $i18n) ?>">
                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
            </div>
            <button type="button"
                    class="dashboard-widget-toggle image-button medium"
                    title="<?= $enabled ? translate('hide_widget', $i18n) : translate('show_widget', $i18n) ?>"
                    aria-pressed="<?= $enabled ? 'true' : 'false' ?>">
                <i class="fa-solid <?= $enabled ? 'fa-eye' : 'fa-eye-slash' ?>" aria-hidden="true"></i>
            </button>
            <span class="dashboard-widget-chrome-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="dashboard-widget-body">
            <?php
            switch ($widgetId) {
                case 'overdue':
                    if ($hasOverdueSubscriptions) {
                        ?>
                        <div class="overdue-subscriptions">
                            <h2><?= translate('overdue_renewals', $i18n) ?></h2>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <?php foreach ($overdueSubscriptions as $subscription) {
                                        $subscriptionName = htmlspecialchars($subscription['name']);
                                        $subscriptionPrice = $subscription['price'];
                                        $subscriptionCurrency = $subscription['currency_id'];
                                        $subscriptionNextPayment = $subscription['next_payment'];
                                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                                        ?>
                                        <div class="subscription-item" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                            <?php if (empty($subscription['logo'])) { ?>
                                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                            <?php } else {
                                                $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                                $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                                echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                                            } ?>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-date"> <?= $subscriptionDisplayNextPayment ?></p>
                                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'upcoming':
                    ?>
                    <div class="upcoming-subscriptions">
                        <h2><?= translate('upcoming_payments', $i18n) ?></h2>
                        <div class="dashboard-subscriptions-container">
                            <div class="dashboard-subscriptions-list">
                                <?php if (empty($upcomingSubscriptions)) { ?>
                                    <p><?= translate('no_upcoming_payments', $i18n) ?></p>
                                <?php } else {
                                    foreach ($upcomingSubscriptions as $subscription) {
                                        $subscriptionName = htmlspecialchars($subscription['name']);
                                        $subscriptionPrice = $subscription['price'];
                                        $subscriptionCurrency = $subscription['currency_id'];
                                        $subscriptionNextPayment = $subscription['next_payment'];
                                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                                        ?>
                                        <div class="subscription-item" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                            <?php if (empty($subscription['logo'])) { ?>
                                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                            <?php } else {
                                                $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                                $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                                echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                                            } ?>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-date"> <?= $subscriptionDisplayNextPayment ?></p>
                                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                                            </div>
                                        </div>
                                    <?php }
                                } ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($hasUpcomingCancellations) { ?>
                        <div class="cancellation-subscriptions">
                            <h2><?= translate('upcoming_cancellations', $i18n) ?></h2>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <?php foreach ($upcomingCancellations as $subscription) {
                                        $subscriptionName = htmlspecialchars($subscription['name']);
                                        $subscriptionPrice = $subscription['price'];
                                        $subscriptionCurrency = $subscription['currency_id'];
                                        $subscriptionDisplayCancellationDate = formatDate($subscription['cancellation_date'], $lang);
                                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                                        ?>
                                        <div class="subscription-item" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                            <?php if (empty($subscription['logo'])) { ?>
                                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                            <?php } else {
                                                $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                                $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                                echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                                            } ?>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-date"> <?= $subscriptionDisplayCancellationDate ?></p>
                                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php }
                    break;

                case 'ai':
                    if (!empty($aiRecommendations)) {
                        ?>
                        <div class="ai-recommendations">
                            <h2><?= translate('ai_recommendations', $i18n) ?></h2>
                            <div class="ai-recommendations-container">
                                <ul class="ai-recommendations-list">
                                    <?php foreach ($aiRecommendations as $key => $recommendation) { ?>
                                        <li class="ai-recommendation-item" data-id="<?= $recommendation['id'] ?>">
                                            <div class="ai-recommendation-header">
                                                <h3>
                                                    <span><?= ($key + 1) . ". " ?></span>
                                                    <?= htmlspecialchars($recommendation['title']) ?>
                                                </h3>
                                                <span class="item-arrow-down fa fa-caret-down"></span>
                                            </div>
                                            <p class="collapsible"><?= htmlspecialchars($recommendation['description']) ?></p>
                                            <p class="ai-recommendation-savings">
                                                <?= htmlspecialchars($recommendation['savings']) ?>
                                                <span>
                                                    <a href="#" class="delete-ai-recommendation" title="<?= translate('delete', $i18n) ?>">
                                                        <i class="fa fa-trash"></i>
                                                    </a>
                                                </span>
                                            </p>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'monthly_budget':
                    if (isset($totalCostPerMonth)) {
                        ?>
                        <div class="budget-subscriptions">
                            <h2><?= translate('monthly_budget', $i18n) ?></h2>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <div class="subscription-item thin">
                                        <p class="subscription-item-title"><?= translate("monthly_cost", $i18n) ?></p>
                                        <div class="subscription-item-info">
                                            <p class="subscription-item-value">
                                                <?= CurrencyFormatter::format($totalCostPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (isset($monthlyBudget) && $monthlyBudget > 0) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("budget", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= formatPrice($monthlyBudget, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if (isset($monthlyBudgetUsed)) { ?>
                                            <div class="subscription-item thin">
                                                <p class="subscription-item-title"><?= translate("budget_used", $i18n) ?></p>
                                                <div class="subscription-item-info">
                                                    <p class="subscription-item-value">
                                                        <?= number_format($monthlyBudgetUsed, 2) ?>%
                                                    </p>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("budget_remaining", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= formatPrice($monthlyBudgetLeft, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if (isset($monthlyOverBudgetAmount) && $monthlyOverBudgetAmount > 0) { ?>
                                            <div class="subscription-item thin">
                                                <p class="subscription-item-title"><?= translate("over_budget", $i18n) ?></p>
                                                <div class="subscription-item-info">
                                                    <p class="subscription-item-value">
                                                        <?= formatPrice($monthlyOverBudgetAmount, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'period_budget':
                    if (isset($periodBudget) && $periodBudget > 0) {
                        ?>
                        <div class="budget-subscriptions">
                            <h2><?= translate('period_budget', $i18n) ?></h2>
                            <?php if (isset($budgetPeriodLabel)) { ?>
                                <p class="header-subtitle"><?= translate('current_period', $i18n) ?>: <?= htmlspecialchars($budgetPeriodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php } ?>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <div class="subscription-item thin">
                                        <p class="subscription-item-title"><?= translate("amount_needed_this_period", $i18n) ?></p>
                                        <div class="subscription-item-info">
                                            <p class="subscription-item-value">
                                                <?= CurrencyFormatter::format($amountNeededThisPeriod, $currencies[$userData['main_currency']]['code']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="subscription-item thin">
                                        <p class="subscription-item-title"><?= translate("budget", $i18n) ?></p>
                                        <div class="subscription-item-info">
                                            <p class="subscription-item-value">
                                                <?= formatPrice($periodBudget, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (isset($periodBudgetUsed)) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("budget_used", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= number_format($periodBudgetUsed, 2) ?>%
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    <div class="subscription-item thin">
                                        <p class="subscription-item-title"><?= translate("budget_remaining", $i18n) ?></p>
                                        <div class="subscription-item-info">
                                            <p class="subscription-item-value">
                                                <?= formatPrice($periodBudgetLeft, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (isset($periodOverBudgetAmount) && $periodOverBudgetAmount > 0) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("over_budget", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= formatPrice($periodOverBudgetAmount, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'payment_method_budget':
                    if (!empty($paymentMethodBudgetRows)) {
                        ?>
                        <div class="budget-subscriptions payment-method-budget">
                            <h2><?= translate('payment_method_budget', $i18n) ?></h2>
                            <?php if (isset($budgetPeriodLabel)) { ?>
                                <p class="header-subtitle"><?= translate('current_period', $i18n) ?>: <?= htmlspecialchars($budgetPeriodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php } ?>
                            <?php foreach ($paymentMethodBudgetRows as $methodBudget) { ?>
                                <h3 class="payment-method-budget-name"><?= htmlspecialchars($methodBudget['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <div class="dashboard-subscriptions-container">
                                    <div class="dashboard-subscriptions-list">
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("amount_needed_this_period", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= CurrencyFormatter::format($methodBudget['amount_needed'], $currencies[$userData['main_currency']]['code']) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("budget", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= formatPrice($methodBudget['budget'], $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("budget_used", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= number_format($methodBudget['budget_used_percent'], 2) ?>%
                                                </p>
                                            </div>
                                        </div>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate("budget_remaining", $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= formatPrice($methodBudget['remaining'], $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($methodBudget['over_budget'] > 0) { ?>
                                            <div class="subscription-item thin">
                                                <p class="subscription-item-title"><?= translate("over_budget", $i18n) ?></p>
                                                <div class="subscription-item-info">
                                                    <p class="subscription-item-value">
                                                        <?= formatPrice($methodBudget['over_budget'], $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'subscriptions':
                    if (isset($activeSubscriptions) && $activeSubscriptions > 0) {
                        ?>
                        <div class="current-subscriptions">
                            <h2><?= translate('your_subscriptions', $i18n) ?></h2>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <div class="subscription-item thin">
                                        <p class="subscription-item-title"><?= translate('active_subscriptions', $i18n) ?></p>
                                        <div class="subscription-item-info">
                                            <p class="subscription-item-value"><?= $activeSubscriptions ?></p>
                                        </div>
                                    </div>
                                    <?php if (isset($totalCostPerMonth)) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate('monthly_cost', $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= CurrencyFormatter::format($totalCostPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($totalCostPerYear)) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate('yearly_cost', $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= CurrencyFormatter::format($totalCostPerYear, $currencies[$userData['main_currency']]['code']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'savings':
                    if (isset($inactiveSubscriptions) && $inactiveSubscriptions > 0) {
                        ?>
                        <div class="savings-subscriptions">
                            <h2><?= translate('your_savings', $i18n) ?></h2>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <div class="subscription-item thin">
                                        <p class="subscription-item-title"><?= translate('inactive_subscriptions', $i18n) ?></p>
                                        <div class="subscription-item-info">
                                            <p class="subscription-item-value"><?= $inactiveSubscriptions ?></p>
                                        </div>
                                    </div>
                                    <?php if (isset($totalSavingsPerMonth) && $totalSavingsPerMonth > 0) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate('monthly_savings', $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= CurrencyFormatter::format($totalSavingsPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= translate('yearly_savings', $i18n) ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= CurrencyFormatter::format($totalSavingsPerMonth * 12, $currencies[$userData['main_currency']]['code']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;

                case 'category_cost':
                    if (!empty($categoryCostRows)) {
                        ?>
                        <div class="budget-subscriptions category-cost">
                            <h2><?= translate('category_cost', $i18n) ?></h2>
                            <div class="dashboard-subscriptions-container">
                                <div class="dashboard-subscriptions-list">
                                    <?php foreach ($categoryCostRows as $categoryRow) { ?>
                                        <div class="subscription-item thin">
                                            <p class="subscription-item-title"><?= htmlspecialchars($categoryRow['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <div class="subscription-item-info">
                                                <p class="subscription-item-value">
                                                    <?= CurrencyFormatter::format($categoryRow['monthly_cost'], $currencies[$userData['main_currency']]['code']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p class="dashboard-widget-empty">' . htmlspecialchars(translate('widget_no_data', $i18n), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
?>
</div>
