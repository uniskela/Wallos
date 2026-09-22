<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/logo_theme_variant.php';
require_once 'includes/upcoming_payments.php';
require_once 'includes/upcoming_cancellations.php';

function formatPrice($price, $currencyCode, $currencies)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);
    if (strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;

        foreach ($currencies as $currency) {

            if ($currency['code'] === $currencyCode) {
                if ($currency['symbol'] != "") {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }
        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    }

    return $formattedPrice;
}

function formatDate($date, $lang = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));

    // Determine the date format based on whether the year matches the current year
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';

    // Try to create an IntlDateFormatter; if it fails, fallback to 'en'
    try {
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );

        if (!$formatter) {
            throw new Exception('Failed to create IntlDateFormatter with language: ' . $lang);
        }
    } catch (Throwable $e) {
        $lang = 'en'; // Fallback to English on error
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );
    }

    // Format the date
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

// Get the first name of the user
$stmt = $db->prepare("SELECT username, firstname FROM user WHERE id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$first_name = $user['firstname'] ?? $user['username'] ?? '';

// Fetch the enabled subscriptions up for payment using the user's dashboard setting.
$upcomingSubscriptions = get_upcoming_payments(
    $db,
    $userId,
    $settings['upcoming_payments_limit'] ?? 3
);

// Fetch enabled subscriptions with manual renewal that are overdue
$stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive, auto_renew FROM subscriptions WHERE user_id = :userId AND next_payment < date('now') AND auto_renew = 0 AND inactive = 0 AND cycle != 5 ORDER BY next_payment ASC");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$overdueSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $overdueSubscriptions[] = $row;
}
$hasOverdueSubscriptions = !empty($overdueSubscriptions);

// Fetch the subscriptions whose cancellation reminder is still ahead (shared with
// the statistics page, so both stay in step).
$upcomingCancellations = get_upcoming_cancellations($db, $userId);
$hasUpcomingCancellations = !empty($upcomingCancellations);

require_once 'includes/stats_calculations.php';
require_once 'includes/widgets.php';

// Get AI Recommendations for user
$stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$aiRecommendations = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $aiRecommendations[] = $row;
}

// Payment-method budgets: period-scoped amount needed (same window as period budget)
$paymentMethodBudgetRows = [];
if (wallos_is_widget_enabled($settings, 'payment_method_budget')) {
    $pmStmt = $db->prepare('SELECT id, name, icon, enabled, budget FROM payment_methods WHERE user_id = :userId ORDER BY `order` ASC');
    $pmStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $pmResult = $pmStmt->execute();
    $pmRows = [];
    while ($pmResult && ($pmRow = $pmResult->fetchArray(SQLITE3_ASSOC))) {
        $pmRows[] = $pmRow;
    }
    $paymentMethodBudgetRows = wallos_build_payment_method_budget_rows(
        $pmRows,
        $subscriptions ?? [],
        $today ?? new DateTime('now'),
        $budgetPeriodEnd ?? new DateTime('now'),
        $db,
        $userId,
        null,
        false,
        true
    );
}

$categoryCostRows = [];
if (wallos_is_widget_enabled($settings, 'category_cost') && !empty($categoryCost)) {
    $categoryCostRows = wallos_build_category_cost_rows($categoryCost, 5);
}

$showWidgetOverdue = wallos_is_widget_enabled($settings, 'overdue');
$showWidgetUpcoming = wallos_is_widget_enabled($settings, 'upcoming');
$showWidgetAi = wallos_is_widget_enabled($settings, 'ai');
$showWidgetMonthlyBudget = wallos_is_widget_enabled($settings, 'monthly_budget');
$showWidgetPeriodBudget = wallos_is_widget_enabled($settings, 'period_budget');
$showWidgetPaymentMethodBudget = wallos_is_widget_enabled($settings, 'payment_method_budget');
$showWidgetSubscriptions = wallos_is_widget_enabled($settings, 'subscriptions');
$showWidgetSavings = wallos_is_widget_enabled($settings, 'savings');
$showWidgetCategoryCost = wallos_is_widget_enabled($settings, 'category_cost');

?>

<section class="contain dashboard">
    <?php
        if ($isAdmin && $settings['update_notification']) {
            if (!is_null($settings['latest_version'])) {
                $latestVersion = $settings['latest_version'];
                if (version_compare($version, $latestVersion) == -1) {
                    ?>
                    <div class="update-banner">
                        <div class="update-banner-icon">
                            <i class="fa-solid fa-arrow-up"></i>
                        </div>
                        <div class="update-banner-text">
                            <strong><?= translate('new_version_available', $i18n) ?></strong>
                            <span>
                                <?= translate('current_version', $i18n) ?>: <?= htmlspecialchars($version) ?>
                                <i class="fa-solid fa-arrow-right"></i>
                                <b><?= htmlspecialchars($latestVersion) ?></b>
                            </span>
                        </div>
                        <a class="update-banner-link"
                            href="https://github.com/ellite/Wallos/releases/tag/<?= htmlspecialchars($latestVersion) ?>"
                            target="_blank" title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                            <?= translate('release_notes', $i18n) ?>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>
                    <?php
                }
            }
        }
        if ($demoMode) {
            ?>
            <div class="demo-banner">
            Running in <b>Demo Mode</b>, certain actions and settings are disabled.<br>
            The database will be reset every 120 minutes.
            </div>
            <?php
        }
    ?>
    <h1><?= translate('hello', $i18n) ?> <?= htmlspecialchars($first_name) ?></h1>

    <?php
    // If there are overdue subscriptions, display them
    if ($showWidgetOverdue && $hasOverdueSubscriptions) {
        ?>
        <div class="overdue-subscriptions">
            <h2><?= translate('overdue_renewals', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <?php

                    foreach ($overdueSubscriptions as $subscription) {
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);

                        ?>
                        <div class="subscription-item" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                            <?php
                            if (empty($subscription['logo'])) {
                                ?>
                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                <?php
                            } else {
                                $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                            }
                            ?>
                            <div class="subscription-item-info">
                                <p class="subscription-item-date"> <?= $subscriptionDisplayNextPayment ?>
                                </p>
                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <?php if ($showWidgetUpcoming) { ?>
    <div class="upcoming-subscriptions">
        <h2><?= translate('upcoming_payments', $i18n) ?></h2>
        <div class="dashboard-subscriptions-container">
            <div class="dashboard-subscriptions-list">
                <?php
                if (empty($upcomingSubscriptions)) {
                    ?>
                    <p><?= translate('no_upcoming_payments', $i18n) ?></p>
                    <?php
                } else {
                    foreach ($upcomingSubscriptions as $subscription) {
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);

                        ?>
                        <div class="subscription-item" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                            <?php
                            if (empty($subscription['logo'])) {
                                ?>
                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                <?php
                            } else {
                                $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                            }
                            ?>
                            <div class="subscription-item-info">
                                <p class="subscription-item-date"> <?= $subscriptionDisplayNextPayment ?></p>
                                <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php } ?>

        <?php if ($hasUpcomingCancellations) { ?>
            <div class="cancellation-subscriptions">
                <h2><?= translate('upcoming_cancellations', $i18n) ?></h2>
                <div class="dashboard-subscriptions-container">
                    <div class="dashboard-subscriptions-list">
                        <?php
                        foreach ($upcomingCancellations as $subscription) {
                            $subscriptionName = htmlspecialchars($subscription['name']);
                            $subscriptionPrice = $subscription['price'];
                            $subscriptionCurrency = $subscription['currency_id'];
                            $subscriptionDisplayCancellationDate = formatDate($subscription['cancellation_date'], $lang);
                            $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);

                            ?>
                            <div class="subscription-item" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                <?php
                                if (empty($subscription['logo'])) {
                                    ?>
                                    <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                    <?php
                                } else {
                                    $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                    $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                    echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                                }
                                ?>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-date"> <?= $subscriptionDisplayCancellationDate ?></p>
                                    <p class="subscription-item-price"> <?= $subscriptionDisplayPrice ?></p>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($showWidgetAi && !empty($aiRecommendations)) { ?>
            <div class="ai-recommendations">
                <h2><?= translate('ai_recommendations', $i18n) ?></h2>
                <div class="ai-recommendations-container">
                    <ul class="ai-recommendations-list">
                        <?php

                        foreach ($aiRecommendations as $key => $recommendation) { ?>
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

        <?php } ?>

        <?php if ($showWidgetMonthlyBudget && isset($totalCostPerMonth)) { ?>
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
        <?php } ?>

        <?php if ($showWidgetPeriodBudget && isset($periodBudget) && $periodBudget > 0) { ?>
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
        <?php } ?>

        <?php if ($showWidgetPaymentMethodBudget && !empty($paymentMethodBudgetRows)) { ?>
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
        <?php } ?>

        <?php if ($showWidgetCategoryCost && !empty($categoryCostRows)) { ?>
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
        <?php } ?>

    <?php if ($showWidgetSubscriptions && isset($activeSubscriptions) && $activeSubscriptions > 0) { ?>
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
    <?php } ?>

    <?php if ($showWidgetSavings && isset($inactiveSubscriptions) && $inactiveSubscriptions > 0) { ?>
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
    <?php } ?>

</section>

<?php
// Get all subscriptions for user details lookup
$query = 'SELECT * FROM subscriptions WHERE user_id = :userId';
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
}
require_once 'includes/subscription_details_popup.php';
?>

<script src="scripts/dashboard.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>
