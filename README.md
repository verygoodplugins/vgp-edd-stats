# VGP EDD Stats Dashboard

Modern analytics dashboard for Easy Digital Downloads with advanced filtering, comparisons, and charts.

## Features

- 7 dashboard pages: Customers & Revenue, MRR & Growth, Renewals & Cancellations, Refunds, Software Licensing, Sites Stats, Support
- Date range filtering with presets
- REST API endpoints with transient caching
- React + ECharts UI bundled via Vite

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Easy Digital Downloads 3.0+
- Node.js 16+ (for development)

## Installation

1. Upload `vgp-edd-stats/` to `/wp-content/plugins/`
2. Activate via WordPress admin
3. Open **EDD Stats** in the admin menu

## Development

```bash
npm install
npm run dev
npm run build
```

### Project Structure

```
vgp-edd-stats/
├── includes/                # PHP classes
│   ├── class-admin-page.php
│   ├── class-stats-api.php
│   └── class-stats-query.php
├── src/                     # React source (Vite)
│   ├── components/
│   ├── pages/
│   ├── utils/
│   ├── App.jsx
│   └── index.jsx
├── build/                   # Compiled assets (generated)
├── scripts/                 # Dev utilities
├── data/                    # Local DB dumps (gitignored)
└── vgp-edd-stats.php         # Main plugin file
```

## Development with Live Data

### SSH Tunnel (Currently Not Working)

An SSH tunnel can connect to the live database directly, but the current hosting provider has disabled SSH port forwarding.

See `TUNNEL-ISSUE.md` for details and potential solutions.

### Alternative: Dev Database Mode

If you have database access through another method (VPN, remote MySQL access, etc.):

```bash
cp dev-config-sample.php dev-config.php
# Edit dev-config.php with your database credentials
```

When `dev-config.php` exists, the plugin queries the specified database instead of the WordPress database.

## REST API Endpoints

All endpoints are available at `/wp-json/vgp-edd-stats/v1/`.

### Customers & Revenue

- `GET /customers/by-month` - New customers by month
- `GET /customers/yoy-change` - Year-over-year change
- `GET /revenue/by-month` - Revenue breakdown
- `GET /revenue/refunded` - Refunded revenue

### MRR & Growth

- `GET /mrr/by-month` - MRR over time
- `GET /mrr/current` - Current month MRR breakdown

### Renewals

- `GET /renewals/rates` - Renewal rates by month
- `GET /renewals/upcoming?days=30` - Upcoming renewals

### Refunds

- `GET /refunds/rates` - Refund rates by month

### Licensing

- `GET /licenses/top?limit=20` - Top licenses by activations

### Utility

- `POST /cache/clear` - Clear all cached data
- `GET /health` - Health check

## Settings

Settings live under **EDD Stats → Settings**:

- Cache duration (seconds)
- Default date range (including "All Time")
- Clear cached stats

## License

GPL-3.0-or-later
