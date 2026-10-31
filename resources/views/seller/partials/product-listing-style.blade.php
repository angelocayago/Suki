<style>
    .seller-products { width:100%; max-width:1200px; min-width:0; margin:0 auto; color:#24312C; font-family:'Poppins',sans-serif; }
    .seller-products [hidden] { display:none !important; }
    .seller-products a { color:#1F6F5B; text-decoration:none; }
    .seller-products .products-breadcrumb { display:flex; align-items:center; gap:10px; margin-bottom:9px; font-size:10px; color:#728078; }
    .seller-products h2 { font-size:26px; line-height:1.3; font-weight:600; letter-spacing:-.04em; }
    .seller-products h3 { font-size:12px; line-height:1.5; font-weight:600; }
    .seller-products p { margin-top:4px; }
    .seller-products .products-muted, .seller-products .products-sku { color:#7B8982; font-size:10px; line-height:1.6; }
    .seller-products .products-subtitle { margin-top:8px; color:#728078; font-size:12px; line-height:1.7; }
    .seller-products .products-header { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:end; gap:14px; margin-bottom:23px; }
    .seller-products .products-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
    .seller-products .products-summary > div { padding:16px; border:1px solid #E1E8E4; border-radius:15px; background:#fff; box-shadow:0 5px 22px #173F3506; }
    .seller-products .products-metric-main { display:flex; justify-content:space-between; gap:8px; }
    .seller-products .products-metric-main p { color:#7B8982; font-size:9px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; }
    .seller-products .products-metric-main strong { display:block; margin-top:12px; font-size:24px; line-height:1; font-weight:600; letter-spacing:-.04em; }
    .seller-products .products-metric-icon { display:grid; place-items:center; width:34px; height:34px; flex-shrink:0; border-radius:11px; background:#EEF5F1; color:#173F35; }
    .seller-products .products-metric-icon[data-tone="green"] { background:#ECFDF5; color:#059669; }
    .seller-products .products-metric-icon[data-tone="amber"] { background:#FFFBEB; color:#D97706; }
    .seller-products .products-metric-icon[data-tone="neutral"] { background:#F3F5F4; color:#7B8982; }
    .seller-products .products-metric-icon :is(i,svg) { width:16px; height:16px; }
    .seller-products .products-metric-footer { margin-top:20px; padding-top:11px; border-top:1px solid #EDF1EF; color:#7B8982; font-size:9px; }
    .seller-products .products-panel { min-width:0; overflow:hidden; border:1px solid #E1E8E4; border-radius:16px; background:#fff; box-shadow:0 8px 32px #173F3506; }
    .seller-products .products-panel-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; padding:17px 16px; border-bottom:1px solid #EDF1EF; }
    .seller-products .products-filter-form { padding:12px 14px; border-bottom:1px solid #EDF1EF; }
    .seller-products .products-primary-filters { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
    .seller-products .products-primary-filters > label:first-child { flex:1 1 220px; }
    .seller-products .products-primary-filters > label:nth-child(2) { flex:0 1 155px; }
    .seller-products label { display:block; min-width:0; font-size:10px; font-weight:600; color:#52635B; }
    .seller-products input:not([type="checkbox"]), .seller-products select { display:block; width:100%; min-width:0; padding:8px 10px; border:1px solid #DDE6E1; border-radius:9px; background:#fff; color:#24312C; font:inherit; font-size:11px; font-weight:400; }
    .seller-products input[type="checkbox"] { width:13px; height:13px; margin:0; accent-color:#1F6F5B; cursor:pointer; }
    .seller-products .products-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:32px; padding:7px 11px; border:1px solid #DDE6E1; border-radius:10px; background:#fff; color:#173F35; font-size:10px; line-height:1.5; font-weight:500; cursor:pointer; white-space:nowrap; }
    .seller-products .products-button :is(i,svg) { width:14px; height:14px; }
    .seller-products .products-button-primary { background:#173F35; border-color:#173F35; color:#fff; font-weight:600; }
    .seller-products .products-button:hover { background:#EEF5F1; }
    .seller-products .products-button-primary:hover { background:#1F6F5B; color:#fff; }
    .seller-products .products-clear { font-size:10px; font-weight:600; padding:5px 2px; }
    .seller-products .products-filter-caret { transition:transform .2s ease; }
    .seller-products #products-filter-toggle[aria-expanded="true"] .products-filter-caret { transform:rotate(180deg); }
    .seller-products .products-advanced-fields { display:grid; grid-template-columns:minmax(0,1fr); gap:12px; padding:12px; margin-top:10px; background:#F8FAF8; border:1px solid #EDF1EF; border-radius:10px; }
    .seller-products .products-advanced-fields select { margin-top:5px; }
    .seller-products .products-active { display:flex; flex-wrap:wrap; gap:6px; }
    .seller-products .products-active:has(span) { margin-top:8px; }
    .seller-products .products-active span { padding:3px 8px; border-radius:20px; background:#EEF5F1; color:#1F6F5B; font-size:10px; overflow-wrap:anywhere; }
    .seller-products .products-list-context { display:flex; flex-wrap:wrap; justify-content:space-between; gap:6px; padding:10px 14px; border-bottom:1px solid #EDF1EF; color:#728078; font-size:9px; }
    .seller-products .products-selection-bar { display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:9px 14px; color:#7B8982; font-size:9px; border-bottom:1px solid #EDF1EF; }
    .seller-products .products-selection-bar button { color:#1F6F5B; font-size:9px; font-weight:600; }
    .seller-products .products-selection-bar button:disabled { opacity:.45; cursor:default; }
    .seller-products .products-table-wrap { overflow-x:auto; }
    .seller-products table { width:100%; min-width:1120px; border-collapse:collapse; text-align:left; }
    .seller-products :is(th,td) { padding:13px 10px; border-bottom:1px solid #EDF1EF; vertical-align:top; }
    .seller-products th { background:#F6F8F7; color:#728078; font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; }
    .seller-products td { color:#52635B; font-size:10px; line-height:1.6; }
    .seller-products tbody tr:hover { background:#FAFCFB; }
    .seller-products tbody tr[data-selected="true"] { background:#EEF5F1; }
    .seller-products .products-select-cell { width:28px; padding-left:14px; padding-right:4px; }
    .seller-products .products-identity { display:flex; align-items:start; gap:10px; min-width:180px; max-width:260px; }
    .seller-products .products-identity strong { color:#34483F; font-size:11px; font-weight:600; }
    .seller-products .products-sku { margin-top:2px; font-size:9px; overflow-wrap:anywhere; }
    .seller-products .products-thumbnail { display:grid; place-items:center; flex-shrink:0; width:34px; height:34px; border:1px solid #E5EBE7; border-radius:10px; overflow:hidden; background:#F1F5F3; color:#85958D; }
    .seller-products .products-thumbnail img { width:100%; height:100%; object-fit:cover; }
    .seller-products .products-thumbnail :is(i,svg) { width:17px; height:17px; }
    .seller-products .products-price { color:#173F35; font-weight:600; min-width:100px; }
    .seller-products .products-badge { display:inline-block; padding:2px 9px; border:1px solid #A7F3D0; border-radius:20px; background:#ECFDF5; color:#047857; font-size:9px; font-weight:500; white-space:nowrap; }
    .seller-products .products-badge[data-state="Inactive"], .seller-products .products-badge[data-state="No active variants"] { background:#F3F4F6; border-color:#E5E7EB; color:#6B7280; }
    .seller-products .products-badge[data-state="Low stock"] { background:#FFFBEB; border-color:#FCD34D; color:#B45309; }
    .seller-products .products-badge[data-state="Out of stock"] { background:#FEF2F2; border-color:#FECACA; color:#B91C1C; }
    .seller-products .products-review { color:#728078; font-size:9px; }
    .seller-products .products-review[data-state="REJECTED"] { color:#B91C1C; }
    .seller-products .products-review[data-state="PENDING"] { color:#B45309; }
    .seller-products .products-variant-details { margin-top:4px; font-size:9px; color:#1F6F5B; }
    .seller-products .products-variant-details summary { cursor:pointer; }
    .seller-products .products-variant-list { padding-left:12px; margin-top:6px; font-size:9px; }
    .seller-products .products-variant-list li { margin-top:5px; }
    .seller-products .products-updated { min-width:85px; color:#7B8982; font-size:9px; }
    .seller-products .products-updated span { display:block; font-size:8px; }
    .seller-products .products-actions { display:flex; flex-wrap:wrap; align-items:center; gap:5px; }
    .seller-products .products-actions form { margin:0; }
    .seller-products .products-icon-button { display:grid; place-items:center; width:27px; height:27px; border:1px solid #DDE6E1; border-radius:8px; background:#fff; color:#52635B; cursor:pointer; }
    .seller-products .products-icon-button:hover { background:#EEF5F1; color:#173F35; }
    .seller-products .products-icon-button :is(i,svg) { width:13px; height:13px; }
    .seller-products .products-empty { padding:24px 16px; text-align:center; }
    .seller-products .products-empty > :is(i,svg) { width:25px; height:25px; margin:0 auto 8px; color:#91A099; }
    .seller-products .products-empty h3 { margin-bottom:4px; }
    .seller-products .products-empty .products-clear { display:inline-block; margin-top:8px; }
    .seller-products .products-list-footer { padding:12px 14px; color:#7B8982; font-size:9px; }
    .seller-products .products-list-footer nav { margin-top:10px; }
    .seller-products .products-alert { margin-bottom:14px; padding:10px 14px; border:1px solid #DDE6E1; border-radius:10px; background:#EEF5F1; font-size:11px; }
    .seller-products .products-sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; }
    .seller-products :is(a,button,summary,input,select):focus-visible { outline:2px solid #1F6F5B; outline-offset:3px; }
    @media(min-width:640px) { .seller-products .products-advanced-fields { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media(min-width:900px) { .seller-products .products-summary { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    @media(max-width:767px) {
        .seller-products h2 { font-size:23px; }
        .seller-products .products-summary { gap:10px; }
        .seller-products .products-summary > div { padding:12px; }
        .seller-products .products-metric-main strong { font-size:21px; }
        .seller-products .products-metric-icon { width:28px; height:28px; }
        .seller-products table { display:block; min-width:0; }
        .seller-products thead { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); }
        .seller-products tbody { display:block; }
        .seller-products tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); margin:10px; padding:10px; border:1px solid #E1E8E4; border-radius:12px; }
        .seller-products td { display:block; padding:8px; border:0; }
        .seller-products td::before { content:attr(data-label); display:block; margin-bottom:5px; color:#728078; font-size:9px; font-weight:600; }
        .seller-products .products-select-cell { width:auto; padding:4px 8px; grid-column:1/-1; }
        .seller-products td[data-label="Product"] { grid-column:1/-1; }
        .seller-products .products-identity { max-width:none; min-width:0; }
        .seller-products .products-identity > div:last-child { min-width:0; overflow-wrap:anywhere; }
        .seller-products .products-price { min-width:0; }
        .seller-products .products-primary-filters > label:nth-child(2) { flex:1 1 120px; }
    }
    @media(prefers-reduced-motion:reduce) { .seller-products .products-filter-caret { transition:none; } }
</style>
