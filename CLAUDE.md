# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

VGP EDD Stats Dashboard is a WordPress plugin that provides a modern React-based analytics dashboard for Easy Digital Downloads. It replaces an older AppSmith-based dashboard with a native WordPress solution featuring advanced filtering, caching, and modern charting.

## Build Commands

```bash
# Install dependencies
npm install

# Development mode (Vite dev server on port 3000)
npm run dev

# Production build (outputs to build/ directory)
npm run build

# Preview production build
npm run preview
```

**Critical**: After any React code changes, you MUST run `npm run build` before testing in WordPress. The plugin enqueues scripts from `build/dashboard.js` and `build/dashboard.css`.

## Architecture Overview

### Three-Layer Architecture

**PHP Backend (WordPress)**
- `vgp-edd-stats.php` - Main plugin singleton, handles initialization and asset enqueuing
- `includes/class-admin-page.php` - Admin menu structure, renders React mount point
- `includes/class-stats-query.php` - Database queries with transient caching
- `includes/class-stats-api.php` - REST API endpoints with permission checks

**REST API Layer**
- Namespace: `/wp-json/vgp-edd-stats/v1/`
- All queries use `VGP_EDD_Stats_Query::get_cached()` for automatic caching
- Cache duration controlled via plugin settings (default: 3600 seconds)
- Cache keys are MD5 hashes of the full SQL query string

**React Frontend**
- Entry point: `src/index.jsx` renders into `#vgp-edd-stats-root`
- `src/App.jsx` - Main component, manages global date range state and page routing
- `src/utils/api.js` - API client with React Query integration
- Pages are lazy-loaded based on `data-section` attribute from PHP

### Data Flow

1. PHP renders admin page with React mount point: `<div id="vgp-edd-stats-root" data-section="customers-revenue">`
2. React reads `window.vgpEddStats` (localized from PHP) for API URL and nonce
3. React Query fetches data from REST endpoints with automatic caching
4. Charts render using ECharts via `echarts-for-react`
5. Date range changes trigger new API calls with `?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` params

### Caching Strategy

**Transient-Based Caching**:
- Cache key format: `vgp_edd_stats_{md5($query)}`
- Duration: Configurable via settings (default: 1 hour)
- Manual clear: Settings page or REST endpoint `POST /cache/clear`
- Cache bypass: Set duration to 0 in settings

**Query Pattern**:
All `VGP_EDD_Stats_Query` methods follow this pattern:
```php
public static function get_something( $start_date = null, $end_date = null ) {
    $query = "SELECT ...";
    return self::get_cached( 'cache_key', $query );
}
```

## Key Architectural Patterns

### Page Routing System

Each WordPress submenu page passes a `section` slug to React via `data-section` attribute:
- `vgp-edd-stats` → defaults to `customers-revenue`
- `vgp-edd-stats-mrr-growth` → `mrr-growth`
- `vgp-edd-stats-renewals` → `renewals`

`App.jsx` uses a switch statement to render the appropriate page component with shared `dateRange` prop.

### API Client Pattern

`src/utils/api.js` exports:
- `apiRequest(endpoint, params)` - Raw fetch wrapper with nonce handling
- `formatDateRange(dateRange)` - Converts Date objects to `YYYY-MM-DD` strings
- `API` object - Pre-configured endpoint functions for React Query

**Important**: The API URL comes from `rest_url()` which returns a FULL URL including domain. Do NOT prepend `window.location.origin` or you'll get malformed URLs like `https://dev.localhttps//dev.local/...`.

### Query Customization

To add a new stat:

1. Add method to `VGP_EDD_Stats_Query` with `get_cached()` pattern
2. Register REST endpoint in `VGP_EDD_Stats_API::register_routes()`
3. Add API function to `src/utils/api.js` → `API` object
4. Use `useQuery()` in React component with the API function

## WordPress Integration Points

### Asset Enqueuing

`vgp-edd-stats.php:enqueue_admin_assets()` only enqueues on plugin pages (checks for `vgp-edd-stats` in hook name).

**Localized Script Data**:
```php
window.vgpEddStats = {
    apiUrl: rest_url('vgp-edd-stats/v1'),  // Full URL with domain
    nonce: wp_create_nonce('wp_rest'),
    dateFormat: get_option('date_format'),
    currencyCode: edd_get_currency(),
    version: '1.0.0'
};
```

### EDD Database Schema

The plugin queries these EDD tables:
- `wp_edd_customers` - Customer records with `date_created`
- `wp_edd_orders` - Orders with `type` (sale/renewal), `status`, `total`
- `wp_edd_subscriptions` - Subscriptions with `created`, `status`, `initial_amount`, `recurring_amount`
- `wp_edd_licenses` - Software Licensing (optional, checks table existence)
- `wp_edd_license_activations` - License activations (optional)

### Table Prefix Handling

All queries use `$wpdb->prefix` dynamically. If working with custom prefix installations, the queries auto-adapt.

## Component Architecture

### Shared Components

**DateRangeFilter** (`src/components/DateRangeFilter.jsx`):
- Manages presets (30d, 90d, 12mo, YTD, custom)
- Uses `react-datepicker` for custom range modal
- Lifts state up to `App.jsx` for global date control

**StatCard** (`src/components/StatCard.jsx`):
- Displays metric with optional change percentage
- Handles loading state with spinner
- Auto-formats based on `type` prop (number, currency, percentage)

**ChartWrapper** (`src/components/ChartWrapper.jsx`):
- Wraps `ReactECharts` with consistent styling
- Handles loading state
- Provides title/subtitle structure

### Page Component Pattern

Each page follows this structure:
```jsx
function PageName({ dateRange }) {
    const { data, isLoading } = useQuery({
        queryKey: ['query-key', dateRange],
        queryFn: () => API.getSomething(dateRange),
    });

    const chartOption = { /* ECharts config */ };

    return (
        <div className="space-y-6">
            <StatCard title="..." value={data?.value} />
            <ChartWrapper option={chartOption} loading={isLoading} />
        </div>
    );
}
```

## ECharts Configuration

Charts use Apache ECharts 5.4+ via `echarts-for-react`. Common patterns:

**Line Chart with Area Fill**:
```javascript
series: [{
    type: 'line',
    smooth: true,
    areaStyle: {
        color: {
            type: 'linear',
            colorStops: [
                { offset: 0, color: 'rgba(14, 165, 233, 0.3)' },
                { offset: 1, color: 'rgba(14, 165, 233, 0.05)' }
            ]
        }
    }
}]
```

**Stacked Bar Chart**:
```javascript
series: [
    { name: 'New', data: [...], type: 'bar', stack: 'total' },
    { name: 'Recurring', data: [...], type: 'bar', stack: 'total' }
]
```

**Dual Y-Axis**:
```javascript
yAxis: [
    { type: 'value', name: 'Revenue', position: 'left' },
    { type: 'value', name: 'Count', position: 'right' }
],
series: [
    { yAxisIndex: 0, /* ... */ },
    { yAxisIndex: 1, /* ... */ }
]
```

## Styling System

**TailwindCSS** with custom utility classes in `src/index.css`:
- `.stat-card` - White card with shadow and padding
- `.chart-container` - Container for charts
- `.btn-primary` - Blue action button
- `.btn-secondary` - Gray secondary button

**Color Palette**:
- Primary: `#0ea5e9` (sky-500)
- Success: `#10b981` (green-500)
- Error: `#ef4444` (red-500)
- Gray scale: Tailwind defaults

## Common Development Tasks

### Adding a New Dashboard Page

1. Create page component in `src/pages/NewPage.jsx`
2. Import and add route case in `App.jsx:renderPage()`
3. Add submenu in `class-admin-page.php:add_menu_pages()`
4. Add title mapping in `App.jsx:getSectionTitle()`
5. Build: `npm run build`

### Adding a New Query/Stat

1. **PHP**: Add method to `VGP_EDD_Stats_Query` following the pattern:
   ```php
   public static function get_new_stat( $start_date = null, $end_date = null ) {
       global $wpdb;
       $query = "SELECT ..."; // Use $wpdb->prefix
       return self::get_cached( 'new_stat_' . md5($query), $query );
   }
   ```

2. **REST API**: Register endpoint in `VGP_EDD_Stats_API::register_routes()`:
   ```php
   register_rest_route(
       self::NAMESPACE,
       '/stats/new',
       array(
           'methods' => 'GET',
           'callback' => array( $this, 'get_new_stat' ),
           'permission_callback' => array( $this, 'check_permissions' ),
           'args' => $this->get_date_range_args(),
       )
   );
   ```

3. **API Callback**: Add method to `VGP_EDD_Stats_API`:
   ```php
   public function get_new_stat( $request ) {
       $start_date = $request->get_param('start_date');
       $end_date = $request->get_param('end_date');
       $data = VGP_EDD_Stats_Query::get_new_stat( $start_date, $end_date );
       return rest_ensure_response( array( 'success' => true, 'data' => $data ) );
   }
   ```

4. **API Client**: Add to `src/utils/api.js:API` object:
   ```javascript
   getNewStat: (dateRange) =>
       apiRequest('/stats/new', formatDateRange(dateRange)),
   ```

5. Build and test

### Modifying Chart Styling

Charts use inline ECharts options. To change colors/styles globally, update the options in page components or create a shared theme in `src/utils/chartTheme.js`.

### Debugging API Issues

1. Check browser Network tab for REST calls
2. Verify `window.vgpEddStats` is defined and has correct `apiUrl`
3. Check nonce is valid (look for 403 errors)
4. Test endpoint directly: `curl -H "X-WP-Nonce: {nonce}" {apiUrl}/endpoint`
5. Check PHP error logs for query failures
6. Verify cache is clearing if stale data appears

## Development with Live Data

### Live Data Sync System

The plugin includes an automated system to sync live EDD data to a local development database, enabling realistic development without production deployments.

**Setup (One-time):**

1. Ensure SSH key authentication is set up for the live site
2. The `dev-config.php` file is already created from the sample (gitignored)
3. Run the sync script:

```bash
cd /Users/jgarturo/Local\ Sites/dev/app/public/wp-content/plugins/vgp-edd-stats
./scripts/sync-live-data.sh
```

**What the sync does:**

- Connects to live site via SSH: `REDACTED_SSH_USER@REDACTED_IP`
- Exports only EDD tables (customers, orders, subscriptions, licenses)
- Downloads to local `data/` directory
- Creates separate database: `vgp_edd_dev`
- Anonymizes customer data for privacy:
  - Emails: `user@example.com` → `anon_md5hash@localhost.dev`
  - Names: `John Doe` → `Customer_12345`
  - Preserves: All IDs, amounts, dates, transaction patterns
- Logs sync completion with timestamp

**How it works:**

- When `dev-config.php` exists, the plugin automatically uses the `vgp_edd_dev` database
- All queries transparently route to dev database via `VGP_EDD_Stats_Query::get_db()`
- Cache keys get `_dev` suffix to prevent collisions
- On production (no `dev-config.php`), uses normal WordPress database
- Zero code changes needed between dev/production

**Refresh data anytime:**

```bash
./scripts/sync-live-data.sh
```

**File structure:**

```
vgp-edd-stats/
├── scripts/
│   ├── sync-live-data.sh       # Main sync script
│   └── anonymize-data.sql      # Privacy protection SQL
├── data/
│   ├── edd-dump-*.sql          # Timestamped dumps (gitignored)
│   └── last-sync.log           # Sync history
├── dev-config.php              # Dev mode config (gitignored, auto-created)
└── dev-config-sample.php       # Template (committed)
```

**Development workflow:**

1. Sync live data: `./scripts/sync-live-data.sh`
2. Make changes to React components or PHP code
3. Build: `npm run build`
4. Test with real data patterns locally
5. Deploy to production when ready

**Troubleshooting:**

- **SSH fails**: Ensure SSH key authentication is configured
- **MySQL connection fails**: Check Local WP MySQL is running (default credentials: root/root)
- **Tables not found**: Verify EDD is active on live site
- **Data not updating**: Re-run sync script to refresh

## Known Limitations

- **Sites Stats** and **Support** pages are placeholders (require custom table integration)
- Charts auto-scale but may need manual axis formatting for very large/small numbers
- No comparison mode yet (planned feature)
- Export functionality not yet implemented
- Large datasets (>50k rows) may benefit from query optimization
