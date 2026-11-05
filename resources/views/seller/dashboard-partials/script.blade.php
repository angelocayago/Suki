<script>
(() => {
    document.querySelectorAll('[data-dashboard-image]').forEach(image => {
        image.addEventListener('error', () => image.hidden = true);
        if (image.complete && !image.naturalWidth) image.hidden = true;
    });
    const panel = document.querySelector('[data-sales-dashboard]');
    if (!panel || panel.dataset.initialized) return;
    panel.dataset.initialized = 'true';
    const series = JSON.parse(document.getElementById('sellerSalesData').textContent);
    const svg = panel.querySelector('svg'), buttons = [...panel.querySelectorAll('[data-sales-range]')];
    const money = value => {
        const minor = BigInt(value), absolute = minor < 0n ? -minor : minor;
        return (minor < 0n ? '-₱' : '₱') + (absolute / 100n).toLocaleString('en-PH') + '.' + String(absolute % 100n).padStart(2, '0');
    };
    const node = (name, attributes, text) => {
        const element = document.createElementNS('http://www.w3.org/2000/svg', name);
        Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
        if (text !== undefined) element.textContent = text;
        return element;
    };
    function render(range) {
        const data = series[range]; if (!data) return;
        const max = Math.max(1, ...data.points.map(point => Number(point.raw_minor)));
        const coords = data.points.map((point, index) => ({x:80+index*620/(data.points.length-1), y:210-Number(point.raw_minor)/max*180}));
        const path = coords.map((point, index) => {
            if (!index) return `M${point.x},${point.y}`;
            const previous = coords[index-1], middle = (previous.x+point.x)/2;
            return `C${middle},${previous.y} ${middle},${point.y} ${point.x},${point.y}`;
        }).join(' ');
        panel.querySelector('[data-sales-line]').setAttribute('d', path);
        panel.querySelector('[data-sales-area]').setAttribute('d', path+' L700,210 L80,210 Z');
        const group = panel.querySelector('[data-sales-points]'), labels = panel.querySelector('[data-sales-labels]'), table = panel.querySelector('[data-sales-table]');
        group.replaceChildren(); labels.replaceChildren(); table.replaceChildren();
        data.points.forEach((point, index) => {
            const description = point.period+': '+money(point.raw_minor);
            const circle = node('circle', {cx:coords[index].x, cy:coords[index].y, r:4, tabindex:0, role:'img', 'aria-label':description});
            circle.appendChild(node('title', {}, description));
            const detail = () => panel.querySelector('[data-sales-detail]').textContent = description;
            circle.addEventListener('mouseenter', detail); circle.addEventListener('focus', detail);
            group.appendChild(circle);
            if (index % Math.ceil((data.points.length-1)/5) === 0 || index === data.points.length-1) labels.appendChild(node('text', {x:coords[index].x, y:244, 'text-anchor':'middle'}, point.label));
            const row = document.createElement('tr'), date = document.createElement('td'), amount = document.createElement('td');
            date.textContent = point.period; amount.textContent = money(point.raw_minor); row.appendChild(date); row.appendChild(amount); table.appendChild(row);
        });
        buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.salesRange === range)));
        panel.querySelector('[data-sales-total]').textContent = money(data.total_minor);
        panel.querySelector('[data-sales-period]').textContent = `${data.from} – ${data.to} · Asia/Manila · ${data.granularity === 'month' ? 'Monthly' : 'Daily'}`;
        panel.querySelector('[data-sales-empty]').hidden = BigInt(data.total_minor) !== 0n;
        panel.querySelector('[data-sales-detail]').textContent = 'Hover or focus a point to see the exact amount.';
        const maxMinor = data.points.reduce((value, point) => BigInt(point.raw_minor) > value ? BigInt(point.raw_minor) : value, 0n);
        panel.querySelector('[data-sales-axis="max"]').textContent = money(maxMinor || 1n);
        panel.querySelector('[data-sales-axis="mid"]').textContent = money((maxMinor || 1n)/2n);
        svg.querySelector('desc').textContent = `Raw completed merchandise sales, ${data.granularity === 'month' ? 'monthly' : 'daily'}, ${data.from} to ${data.to}, Asia/Manila. Focus points or use the exact data table.`;
    }
    buttons.forEach(button => button.addEventListener('click', () => render(button.dataset.salesRange)));
    render('7d');
})();
</script>
