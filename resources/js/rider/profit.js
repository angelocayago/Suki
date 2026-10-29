
const readPayload = (id) => {
    const element = document.getElementById(id);

    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const formatPeso = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
};

const initProfit = () => {
    const chartElement = document.getElementById('profitChart');
    const data = readPayload('rider-profit-data');

    if (!chartElement || !data || !window.Chart) {
        return;
    }

    new window.Chart(chartElement.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'COD Collected',
                data: data.values || [],
                backgroundColor: 'rgba(78, 115, 223, 0.5)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1,
                borderRadius: 5,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) =>
                            `COD Collected: ${formatPeso(context.parsed.y)}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => formatPeso(value),
                    },
                    title: {
                        display: true,
                        text: 'COD Amount (PHP)',
                    },
                },
                x: {
                    title: {
                        display: true,
                        text: 'Collection Date',
                    },
                },
            },
        },
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfit, {
        once: true,
    });
} else {
    initProfit();
}

export { initProfit };
