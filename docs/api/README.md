# Wallos widgets API (schema_version 1)

These fragments mirror the style used on https://api.wallosapp.com/ and can be
merged into that site's `openapi.yaml` when publishing docs:

```yaml
  /api/widgets/list_widgets.php:
    $ref: '/api/widgets/list_widgets.yaml'
  /api/widgets/get_widget.php:
    $ref: '/api/widgets/get_widget.yaml'
```

Runtime calls always go to **your Wallos instance**, not api.wallosapp.com.

| File | Endpoint |
| --- | --- |
| [widgets/list_widgets.yaml](widgets/list_widgets.yaml) | `GET/POST /api/widgets/list_widgets.php` |
| [widgets/get_widget.yaml](widgets/get_widget.yaml) | `GET/POST /api/widgets/get_widget.php` |

See also:

- [Home Assistant REST examples](../homeassistant/rest-sensors.md)
- [HACS integration plan](../plans/hacs-wallos-integration.md)
- [smoke-widgets.sh](smoke-widgets.sh) — quick curl against a running instance
