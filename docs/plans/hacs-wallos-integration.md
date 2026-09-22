# Plan: HACS Wallos integration (entity picker)

## Goal

A Home Assistant custom integration installable via HACS that connects to a
Wallos instance and creates sensors **only for widgets/instances the user
selects**, so the entity list stays small.

## Non-goals (v1)

- Writing back to Wallos (create subscriptions, edit budgets)
- Replacing the Wallos UI
- Hosting on api.wallosapp.com (docs only)

## Architecture

```
HA ──(HTTPS)──> Wallos /api/widgets/list_widgets.php
             └─> /api/widgets/get_widget.php?widget_id=&instance_id=
```

- **Config entry**: base URL + API key (+ optional verify SSL).
- **Options flow**: multi-select of catalog entries from `list_widgets`.
- **Coordinator**: poll selected widgets on an interval (default 5–15 min).
- **Entities**: one sensor (or small set) per selected catalog row.

## Entity model (proposed)

| Catalog row | Default entities |
| --- | --- |
| `period_budget` | `sensor.wallos_period_amount_needed` (+ attributes: budget, remaining, over, period) |
| `monthly_budget` | `sensor.wallos_monthly_cost` |
| `payment_method_budget` (combined) | `sensor.wallos_pmb_<slug>_amount_needed` |
| `payment_method_budget` (per_method) | either one sensor with `methods` attributes, **or** optional expand-to-per-method (off by default) |
| `category_cost` | one sensor with category list in attributes (or top-1 as state) |
| `subscriptions` / `savings` / `upcoming` / `overdue` / `ai` | count-style sensors; optional |

**Slug**: derive from `instance_id` or sanitized `title` (`paypal_db`).

## Options UI (anti-overwhelm)

1. After login test succeeds, fetch `list_widgets`.
2. Show checkboxes grouped by `widget_id`.
3. Defaults: only enable `period_budget` + any **combined** PMB instances; leave the rest off.
4. Toggle: “Expand per-method PMB into separate sensors” (default **off**).
5. Poll interval number field.

Re-run options when Wallos layout changes (button: “Refresh catalog”).

## Repo layout (separate from Wallos app)

Suggested: `github.com/<you>/ha-wallos` (HACS default).

```
custom_components/wallos/
  manifest.json          # domain wallos, config_flow, iot_class cloud_polling
  __init__.py
  config_flow.py         # URL + API key; options flow for entity picker
  coordinator.py         # DataUpdateCoordinator
  sensor.py
  const.py
  strings.json
hacs.json
README.md
```

`manifest.json` iot_class: `cloud_polling` (local URL still fine).

## Auth & errors

- Query `api_key` (Wallos accepts `api_key` / `apiKey`).
- 401 / `success:false` → raise `ConfigEntryAuthFailed`.
- Timeout / connection → `UpdateFailed`; keep last good data.

## Implementation steps

1. Scaffold integration + config flow (URL/key validation via `list_widgets`).
2. Coordinator fetches only selected `(widget_id, instance_id?)` keys.
3. Sensor platform maps payloads → state/attributes (`schema_version` check ≥ 1).
4. Options flow multi-select + expand toggle.
5. README with HACS add-custom-repository instructions.
6. Optional: diagnostics redacting API key.

## Testing

- HA core `pytest` with aioresponses mocking `list_widgets` / `get_widget`.
- Manual: point at local Docker Wallos (`http://host.docker.internal:8282`).

## Relationship to this Wallos fork

- No HA code in the Wallos PHP tree required.
- Keep OpenAPI fragments in `docs/api/` as the contract the integration targets.
- When upstream publishes widgets on api.wallosapp.com, point README at that too.

## Rollout

1. Ship PHP widgets API (done on `cursor/dashboard-widgets-1ebf`).
2. Publish HA REST YAML examples (this folder’s sibling doc).
3. New repo + HACS custom repo for early users.
4. Later: HACS default repo listing once stable.
