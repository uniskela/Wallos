# Home Assistant — Wallos widgets via REST

Wallos exposes JSON under `/api/widgets/*` on **your** instance. Use these as
`rest` sensors (or a future HACS integration — see
[hacs-wallos-integration.md](../plans/hacs-wallos-integration.md)).

## 1. Discover instances

```bash
curl -sS "http://wallos.local/api/widgets/list_widgets.php?api_key=YOUR_KEY" | jq .
```

Pick a `payment_method_budget` `instance_id` (e.g. `pmb_10bbd4a2`).  
Note: `pmb_default` with empty `payment_method_ids` only returns methods that
have a **budget > 0**. Custom instances with selected ids return those methods
even if budget is 0.

## 2. Example `configuration.yaml`

```yaml
rest:
  - resource: http://wallos.local/api/widgets/list_widgets.php
    method: GET
    scan_interval: 300
    params:
      api_key: !secret wallos_api_key
    sensor:
      - name: Wallos widget catalog
        unique_id: wallos_widget_catalog
        value_template: "{{ value_json.widgets | length }}"
        json_attributes_path: "$"
        json_attributes:
          - schema_version
          - widgets

  - resource: http://wallos.local/api/widgets/get_widget.php
    method: GET
    scan_interval: 300
    params:
      api_key: !secret wallos_api_key
      widget_id: period_budget
    sensor:
      - name: Wallos period amount needed
        unique_id: wallos_period_amount_needed
        unit_of_measurement: AUD
        device_class: monetary
        value_template: "{{ value_json.amount_needed }}"
        json_attributes:
          - period_budget
          - remaining
          - over_budget
          - budget_used_percent
          - period
          - currency_code

  - resource: http://wallos.local/api/widgets/get_widget.php
    method: GET
    scan_interval: 300
    params:
      api_key: !secret wallos_api_key
      widget_id: payment_method_budget
      instance_id: pmb_10bbd4a2   # from list_widgets
    sensor:
      - name: Wallos Paypal and DB needed
        unique_id: wallos_pmb_paypal_db_needed
        unit_of_measurement: AUD
        device_class: monetary
        # Combined mode: one totals row in methods[0]
        value_template: "{{ value_json.methods[0].amount_needed if value_json.methods else none }}"
        json_attributes_path: "$.methods[0]"
        json_attributes:
          - budget
          - remaining
          - over_budget
          - budget_used_percent
          - name
          - payment_method_ids
```

`secrets.yaml`:

```yaml
wallos_api_key: "paste-from-wallos-profile"
```

## 3. Tips to avoid entity spam

- Prefer **one REST resource per widget instance you care about**, not every catalog row.
- Use `combined` display mode in Wallos when you want a single monetary sensor.
- For per-method detail, either:
  - keep breakdown only in attributes (`methods_breakdown`), or
  - add separate REST sensors with `payment_method_id=N`.
- The planned HACS integration (see plan doc) will let you **tick which widgets to expose** in the UI instead of hand-writing YAML.
