<style>
    .seller-orders { min-width:0; color:#24312C; font-family:'Poppins',sans-serif; }
    .seller-orders a { color:#1F6F5B; text-decoration:none; }
    .seller-orders h2 { font-size:28px; font-weight:600; letter-spacing:-.04em; }
    .seller-orders h3 { font-size:14px; font-weight:600; }
    .seller-orders p { margin-top:4px; }
    .seller-orders .orders-intro { margin-bottom:24px; }
    .seller-orders .orders-muted { color:#728078; font-size:12px; line-height:1.7; }
    .seller-orders .orders-panel { min-width:0; overflow:hidden; margin-bottom:20px; border:1px solid #E1E8E4; border-radius:16px; background:#fff; }
    .seller-orders .orders-panel-heading { padding:16px 20px; border-bottom:1px solid #EDF1EF; }
    .seller-orders .orders-panel-body { padding:20px; }
    .seller-orders .orders-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:24px; }
    .seller-orders .orders-summary > div { padding:16px; border:1px solid #E1E8E4; border-radius:16px; background:#fff; }
    .seller-orders .orders-summary p { font-size:10px; font-weight:600; color:#839189; text-transform:uppercase; letter-spacing:.1em; }
    .seller-orders .orders-summary strong { display:block; margin-top:10px; font-size:24px; font-weight:600; }
    .seller-orders .orders-primary-filters { display:flex; flex-wrap:wrap; align-items:end; gap:12px; }
    .seller-orders .orders-primary-filters > label { flex:1 1 220px; }
    .seller-orders label { display:block; font-size:12px; font-weight:600; color:#52635B; }
    .seller-orders :is(input,select) { display:block; width:100%; min-width:0; margin-top:6px; padding:10px 12px; border:1px solid #DDE6E1; border-radius:10px; background:#fff; font:inherit; font-weight:400; color:#24312C; }
    .seller-orders .orders-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:38px; padding:9px 12px; border:1px solid #DDE6E1; border-radius:10px; background:#fff; font-size:12px; font-weight:600; color:#1F6F5B; cursor:pointer; }
    .seller-orders .orders-button-primary { background:#1F6F5B; border-color:#1F6F5B; color:#fff; }
    .seller-orders .orders-button:hover { background:#EEF5F1; }
    .seller-orders .orders-button-primary:hover { background:#173F35; color:#fff; }
    .seller-orders .orders-advanced { margin-top:16px; }
    .seller-orders .orders-advanced > summary { width:max-content; list-style:none; }
    .seller-orders .orders-advanced > summary::-webkit-details-marker { display:none; }
    .seller-orders .orders-advanced > summary::after { content:'+'; margin-left:8px; }
    .seller-orders .orders-advanced[open] > summary::after { content:'\2212'; }
    .seller-orders .orders-advanced-fields { display:grid; grid-template-columns:minmax(0,1fr); gap:12px; margin-top:16px; padding:16px; background:#F8FAF8; border:1px solid #EDF1EF; border-radius:12px; }
    .seller-orders .orders-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:12px; }
    .seller-orders .orders-actions form { margin:0; }
    .seller-orders .orders-active { display:flex; flex-wrap:wrap; gap:8px; margin-top:16px; font-size:11px; }
    .seller-orders .orders-active span { border-radius:20px; padding:5px 10px; background:#EEF5F1; color:#1F6F5B; overflow-wrap:anywhere; }
    .seller-orders .orders-table-wrap { overflow-x:auto; }
    .seller-orders table { width:100%; min-width:940px; border-collapse:collapse; text-align:left; }
    .seller-orders th { background:#F6F8F7; font-size:9px; text-transform:uppercase; letter-spacing:.08em; color:#728078; }
    .seller-orders :is(th,td) { padding:14px 16px; border-bottom:1px solid #EDF1EF; vertical-align:top; }
    .seller-orders td { font-size:12px; color:#52635B; }
    .seller-orders tbody tr:hover { background:#FAFCFB; }
    .seller-orders .orders-badge { display:inline-block; padding:4px 9px; border:1px solid #DDE6E1; border-radius:20px; background:#EEF5F1; color:#1F6F5B; font-size:10px; font-weight:600; white-space:nowrap; }
    .seller-orders .orders-badge[data-status="PLACED"] { background:#FFFBEB; border-color:#FDE68A; color:#92400E; }
    .seller-orders .orders-badge[data-status="CANCELLED"], .seller-orders .orders-badge[data-status="DELIVERY_FAILED"] { background:#FEF2F2; border-color:#FECACA; color:#B91C1C; }
    .seller-orders .orders-item-list { list-style:none; padding:0; margin:0; }
    .seller-orders .orders-item-list li + li { margin-top:10px; }
    .seller-orders .orders-item-list small { display:block; margin-top:3px; font-size:10px; color:#728078; }
    .seller-orders .orders-empty { padding:40px 20px; text-align:center; }
    .seller-orders .orders-alert { margin-bottom:16px; padding:12px 16px; border:1px solid #DDE6E1; border-radius:12px; background:#EEF5F1; font-size:12px; }
    .seller-orders .orders-alert-error { background:#FEF2F2; border-color:#FECACA; color:#B91C1C; }
    .seller-orders .orders-details-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:20px; }
    .seller-orders dl { display:grid; grid-template-columns:minmax(0,1fr); gap:16px; font-size:12px; }
    .seller-orders dt { margin-bottom:4px; color:#728078; }
    .seller-orders dd { margin:0; overflow-wrap:anywhere; }
    .seller-orders .orders-totals { margin-top:20px; border-top:1px solid #EDF1EF; padding-top:16px; }
    .seller-orders .orders-totals > div { display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px; margin-top:8px; font-size:12px; }
    .seller-orders .orders-history { padding-left:20px; font-size:12px; }
    .seller-orders .orders-history li { margin-top:14px; }
    .seller-orders .orders-history time { display:block; color:#728078; font-size:11px; }
    .seller-orders :is(a,button,summary,input,select):focus-visible { outline:2px solid #1F6F5B; outline-offset:3px; }
    @media(min-width:640px) { .seller-orders .orders-advanced-fields { grid-template-columns:repeat(2,minmax(0,1fr)); } .seller-orders dl { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(min-width:1280px) { .seller-orders .orders-summary { grid-template-columns:repeat(6,minmax(0,1fr)); } .seller-orders .orders-details-grid { grid-template-columns:minmax(0,2fr) minmax(280px,1fr); } }
    @media(max-width:767px) {
        .seller-orders table { min-width:0; display:block; }
        .seller-orders thead { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); }
        .seller-orders tbody { display:block; }
        .seller-orders tr { display:grid; grid-template-columns:minmax(0,1fr); margin:12px; border:1px solid #E1E8E4; border-radius:12px; overflow:hidden; }
        .seller-orders td { display:block; padding:10px 12px; }
        .seller-orders td::before { content:attr(data-label); display:block; margin-bottom:6px; color:#728078; font-size:10px; font-weight:600; }
    }
</style>
