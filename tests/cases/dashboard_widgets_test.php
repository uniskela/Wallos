<?php

require_once WALLOS_ROOT . '/includes/widgets.php';
require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';

wallos_test('widget visibility defaults to enabled when unset', function () {
    assert_true(wallos_is_widget_enabled([], 'upcoming'), 'missing settings still enable upcoming');
    assert_true(wallos_is_widget_enabled(['dashboard_widget_upcoming' => null], 'ai'), 'null flag still enables');
    assert_true(wallos_is_widget_enabled(['dashboard_widget_upcoming' => 1], 'upcoming'), 'explicit 1 enables');
    assert_true(!wallos_is_widget_enabled(['dashboard_widget_upcoming' => 0], 'upcoming'), 'explicit 0 disables');
});

wallos_test('widget catalog includes schema_version and all widget ids', function () {
    $catalog = wallos_list_widgets_catalog([], [
        'overdue_renewals' => 'Overdue',
        'upcoming_payments' => 'Upcoming',
        'ai_recommendations' => 'AI',
        'monthly_budget' => 'Monthly',
        'period_budget' => 'Period',
        'payment_method_budget' => 'Method',
        'your_subscriptions' => 'Subs',
        'your_savings' => 'Savings',
        'category_cost' => 'Categories',
    ]);

    assert_true($catalog['success'] === true, 'catalog success');
    assert_same(1, $catalog['schema_version'], 'schema_version is 1');
    assert_same(count(wallos_widget_ids()), count($catalog['widgets']), 'one entry per widget');

    $ids = array_column($catalog['widgets'], 'widget_id');
    assert_same(wallos_widget_ids(), $ids, 'stable dashboard order');

    foreach ($catalog['widgets'] as $widget) {
        assert_true($widget['enabled'] === true, $widget['widget_id'] . ' defaults enabled');
        assert_true($widget['requires_params'] === false, $widget['widget_id'] . ' has no required params');
    }
});

wallos_test('payment method id filter parsing', function () {
    assert_same(null, wallos_parse_payment_method_ids(null), 'null filter');
    assert_same(null, wallos_parse_payment_method_ids(''), 'empty filter');
    assert_same([3], wallos_parse_payment_method_ids('3'), 'single id');
    assert_same([3, 7], wallos_parse_payment_method_ids('3,7'), 'comma list');
    assert_same([3, 7], wallos_parse_payment_method_ids(['3', '7', '7']), 'array with duplicates');
});

wallos_test('category cost rows are top-N by monthly cost', function () {
    $rows = wallos_build_category_cost_rows([
        1 => ['name' => 'A', 'cost' => 10],
        2 => ['name' => 'B', 'cost' => 50],
        3 => ['name' => 'C', 'cost' => 0],
        4 => ['name' => 'D', 'cost' => 25],
    ], 2);

    assert_same(2, count($rows), 'respects limit');
    assert_same('B', $rows[0]['name'], 'highest first');
    assert_same('D', $rows[1]['name'], 'second highest');
});

wallos_test('migration adds payment method budget and widget flags', function () {
    $db = wallos_test_open_database();

    $budgetCol = $db->query("SELECT * FROM pragma_table_info('payment_methods') WHERE name='budget'");
    assert_true($budgetCol->fetchArray(SQLITE3_ASSOC) !== false, 'payment_methods.budget exists');

    foreach (wallos_widget_ids() as $widgetId) {
        $column = wallos_widget_setting_column($widgetId);
        $col = $db->query("SELECT * FROM pragma_table_info('settings') WHERE name='" . $column . "'");
        assert_true($col->fetchArray(SQLITE3_ASSOC) !== false, $column . ' exists');
    }

    $db->close();
});

wallos_test('payment method budget uses period amount needed window', function () {
    $db = wallos_test_open_database();

    $db->exec("INSERT INTO user (id, username, email, password, main_currency, api_key, budget_period_type, budget_period_anchor_date, period_budget)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'test-api-key', 'fortnightly', '2026-09-11', 260)");
    $db->exec("INSERT INTO household (id, name, user_id) VALUES (1, 'Me', 1)");

    // Reuse seeded payment methods for user 1; set budgets on two of them.
    $db->exec("UPDATE payment_methods SET name = 'Personal card', budget = 200, enabled = 1 WHERE id = 1 AND user_id = 1");
    $db->exec("UPDATE payment_methods SET name = 'Business bank', budget = 60, enabled = 1 WHERE id = 2 AND user_id = 1");
    $db->exec("UPDATE payment_methods SET budget = 0 WHERE id = 3 AND user_id = 1");

    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id
    ) VALUES (
        1, 'Apple TV+', 45.20, 1, '2026-09-23', 3, 1,
        1, 1, 1, 0, 1, 1
    )");
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id
    ) VALUES (
        2, 'Biz tool', 25.49, 1, '2026-09-20', 3, 1,
        2, 1, 1, 0, 1, 1
    )");

    $subs = [];
    $result = $db->query('SELECT * FROM subscriptions WHERE user_id = 1');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subs[] = $row;
    }

    $methods = [];
    $result = $db->query('SELECT * FROM payment_methods WHERE user_id = 1 ORDER BY `order` ASC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $methods[] = $row;
    }

    $reference = new DateTime('2026-09-11');
    $period = getActiveBudgetPeriod($reference, 'fortnightly', '2026-09-11');

    $rows = wallos_build_payment_method_budget_rows(
        $methods,
        $subs,
        $reference,
        $period['end'],
        $db,
        1,
        null,
        false,
        true
    );

    assert_same(2, count($rows), 'only methods with budget > 0');
    assert_same(1, $rows[0]['payment_method_id'], 'personal first by order');
    assert_same('Personal card', $rows[0]['name'], 'includes name metadata');
    assert_true(isset($rows[0]['icon']) && $rows[0]['icon'] !== '', 'includes icon metadata');
    assert_true($rows[0]['enabled'] === true, 'includes enabled metadata');
    assert_equals(200.0, $rows[0]['budget'], 'personal budget');
    assert_equals(45.2, $rows[0]['amount_needed'], 'personal amount needed');
    assert_equals(60.0, $rows[1]['budget'], 'business budget');
    assert_equals(25.49, $rows[1]['amount_needed'], 'business amount needed');

    $filtered = wallos_build_payment_method_budget_rows(
        $methods,
        $subs,
        $reference,
        $period['end'],
        $db,
        1,
        [2],
        false,
        true
    );
    assert_same(1, count($filtered), 'filter to business only');
    assert_same(2, $filtered[0]['payment_method_id'], 'filtered id');

    $db->close();
});

wallos_test('savings monthly cost nets out replacement subscriptions', function () {
    $db = wallos_test_open_database();
    $db->exec("INSERT INTO user (id, username, email, password, main_currency, api_key)
               VALUES (1, 'tester', 't@example.com', 'x', 1, 'test-api-key')");

    assert_equals(
        10.0,
        wallos_subscription_monthly_cost([
            'price' => 10,
            'currency_id' => 1,
            'cycle' => 3,
            'frequency' => 1,
        ], $db, 1),
        'monthly cycle price is unchanged when rates are 1'
    );

    // Inactive Netflix replaced by cheaper active Disney+
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id, replacement_subscription_id
    ) VALUES (
        10, 'Netflix', 20, 1, '2026-09-01', 3, 1,
        1, 1, 1, 1, 1, 1, 11
    )");
    $db->exec("INSERT INTO subscriptions (
        id, name, price, currency_id, next_payment, cycle, frequency,
        payment_method_id, payer_user_id, category_id, inactive, auto_renew, user_id, replacement_subscription_id
    ) VALUES (
        11, 'Disney+', 8, 1, '2026-09-15', 3, 1,
        1, 1, 1, 0, 1, 1, NULL
    )");

    $subs = [];
    $result = $db->query('SELECT * FROM subscriptions WHERE user_id = 1');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subs[] = $row;
    }

    $inactiveCount = 0;
    $totalSavings = 0.0;
    $totalCostsInReplacements = 0.0;
    $countedReplacements = [];
    foreach ($subs as $sub) {
        if ((int) ($sub['inactive'] ?? 0) !== 1) {
            continue;
        }
        $inactiveCount++;
        $totalSavings += wallos_subscription_monthly_cost($sub, $db, 1);
        $replacementId = $sub['replacement_subscription_id'] ?? null;
        if ($replacementId && !in_array($replacementId, $countedReplacements, true)) {
            foreach ($subs as $candidate) {
                if ((int) $candidate['id'] === (int) $replacementId) {
                    $totalCostsInReplacements += wallos_subscription_monthly_cost($candidate, $db, 1);
                    break;
                }
            }
            $countedReplacements[] = $replacementId;
        }
    }
    $totalSavings -= $totalCostsInReplacements;

    assert_same(1, $inactiveCount, 'one inactive');
    assert_equals(12.0, $totalSavings, '20 inactive minus 8 replacement = 12 net savings');

    $db->close();
});
