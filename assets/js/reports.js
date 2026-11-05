/**
 * RHU Reports — Chart.js dashboards and print layout.
 */
document.addEventListener('DOMContentLoaded', () => {
    const payload = window.RHU_REPORTS;
    if (!payload || typeof Chart === 'undefined') {
        return;
    }

    Chart.defaults.font.family = systemFontStack();
    Chart.defaults.color = '#64748b';

    const palette = {
        blue: '#1a6fc4',
        teal: '#0d9488',
        amber: '#d97706',
        rose: '#e11d48',
        slate: '#64748b',
        violet: '#7c3aed',
        green: '#16a34a',
    };

    const doughnutColors = [
        palette.blue,
        palette.teal,
        palette.amber,
        palette.rose,
        palette.violet,
        palette.green,
        palette.slate,
        '#f59e0b',
        '#6366f1',
        '#ec4899',
    ];

    const daily = payload.consultations_daily || {};
    const dailyLabels = daily.short_labels || daily.labels || [];
    const dailyValues = daily.values || [];
    const dailyTotal = daily.total ?? dailyValues.reduce((a, b) => a + b, 0);

    const commonScaleX = {
        grid: { display: false },
        ticks: {
            font: { size: 10 },
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: Math.min(12, dailyLabels.length || 12),
        },
    };

    const commonScaleY = {
        beginAtZero: true,
        grid: { color: '#f1f5f9' },
        ticks: { font: { size: 10 }, precision: 0, stepSize: 1 },
    };

    const tooltip = chartTooltip();

    const charts = {};

    charts.column = new Chart(document.getElementById('rptChartColumn'), {
        type: 'bar',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Consultations',
                data: dailyValues,
                backgroundColor: 'rgba(26, 111, 196, 0.82)',
                borderRadius: 5,
                maxBarThickness: dailyLabels.length > 20 ? 14 : 28,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip,
            },
            scales: {
                x: commonScaleX,
                y: commonScaleY,
            },
        },
    });

    charts.line = new Chart(document.getElementById('rptChartLine'), {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Consultations',
                data: dailyValues,
                borderColor: palette.teal,
                backgroundColor: 'rgba(13, 148, 136, 0.15)',
                fill: true,
                tension: 0.35,
                pointRadius: dailyLabels.length > 20 ? 0 : 4,
                pointHoverRadius: 6,
                pointBackgroundColor: palette.teal,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip,
            },
            scales: {
                x: commonScaleX,
                y: commonScaleY,
            },
        },
    });

    const brgy = payload.cases_by_barangay || payload.barangays || {};
    const brgyLabels = brgy.labels || [];
    const brgyValues = brgy.values || [];
    const brgyWrap = document.getElementById('rptBarangayChartWrap');
    if (brgyWrap && brgyLabels.length) {
        brgyWrap.style.setProperty('--rpt-bar-rows', String(brgyLabels.length));
    }

    charts.barH = new Chart(document.getElementById('rptChartBarH'), {
        type: 'bar',
        data: {
            labels: brgyLabels,
            datasets: [{
                label: 'Cases',
                data: brgyValues,
                backgroundColor: 'rgba(217, 119, 6, 0.85)',
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip,
            },
            scales: {
                x: { ...commonScaleY, grid: { color: '#f1f5f9' } },
                y: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '500' }, color: '#334155' },
                },
            },
        },
    });

    const sex = payload.sex_mix || {};
    const sexLabels = sex.labels || [];
    const sexValues = sex.values || [];
    const sexWrap = document.getElementById('rptSexChartWrap');
    if (sexWrap && sexLabels.length) {
        sexWrap.style.setProperty('--rpt-bar-rows', String(sexLabels.length));
    }
    charts.sexBar = buildDemoHorizontalBar(
        document.getElementById('rptChartSexBar'),
        sexLabels,
        sexValues,
        'Consults',
        palette.rose,
    );

    const diseases = payload.common_diseases || {};
    const diseaseLabels = diseases.labels || [];
    const diseaseValues = diseases.values || [];
    const diseasesCanvas = document.getElementById('rptChartDoughnutDiseases');
    if (diseasesCanvas && diseaseLabels.length > 0) {
        charts.doughnutDiseases = new Chart(diseasesCanvas, {
            type: 'doughnut',
            data: {
                labels: diseaseLabels,
                datasets: [{
                    data: diseaseValues,
                    backgroundColor: doughnutColors,
                    borderWidth: diseaseLabels.length === 1 ? 0 : 2,
                    borderColor: '#fff',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, padding: 8, font: { size: 10 } },
                    },
                    tooltip,
                },
            },
        });
    }

    toggleEmptyState('column', dailyTotal === 0);
    toggleEmptyState('line', dailyTotal === 0);
    toggleEmptyState('barH', !brgyValues.length || brgyValues.every((v) => v === 0));
    toggleEmptyState('sexBar', demoMixIsEmpty(sexLabels, sexValues));
    toggleEmptyState('doughnutDiseases', diseaseLabels.length === 0);

    const age = payload.age_mix || {};
    const ageLabels = age.labels || [];
    const ageValues = age.values || [];
    const ageWrap = document.getElementById('rptAgeChartWrap');
    if (ageWrap && ageLabels.length) {
        ageWrap.style.setProperty('--rpt-bar-rows', String(ageLabels.length));
    }
    charts.ageBar = buildDemoHorizontalBar(
        document.getElementById('rptChartAgeBar'),
        ageLabels,
        ageValues,
        'Consults',
        palette.green,
    );
    toggleEmptyState('ageBar', demoMixIsEmpty(ageLabels, ageValues));

    const fc = payload.forecast_series || {};
    const fcCanvas = document.getElementById('rptChartForecast');
    if (fcCanvas && fc.labels?.length) {
        charts.forecast = new Chart(fcCanvas, {
            type: 'line',
            data: {
                labels: fc.labels,
                datasets: [
                    {
                        label: 'Recorded',
                        data: fc.historical || [],
                        borderColor: palette.violet,
                        backgroundColor: 'rgba(124, 58, 237, 0.12)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                    {
                        label: 'Projected',
                        data: fc.forecast || [],
                        borderColor: palette.amber,
                        borderDash: [6, 4],
                        fill: false,
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                    tooltip,
                    title: {
                        display: !!fc.disease,
                        text: fc.disease || '',
                        font: { size: 11, weight: '600' },
                        padding: { bottom: 8 },
                    },
                },
                scales: {
                    x: commonScaleX,
                    y: commonScaleY,
                },
            },
        });
    }

    const printBtn = document.getElementById('rptPrintBtn');
    printBtn?.addEventListener('click', () => {
        const from = document.getElementById('rptFrom')?.value || '';
        const to = document.getElementById('rptTo')?.value || '';
        const sex = document.querySelector('#rptFilterForm select[name="sex"]')?.value || '';
        const ageGroup = document.querySelector('#rptFilterForm select[name="age_group"]')?.value || '';
        const base = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
        const qs = new URLSearchParams({ from, to, preview: '1' });
        if (sex) qs.set('sex', sex);
        if (ageGroup) qs.set('age_group', ageGroup);
        const url = `${base}/reports-print.php?${qs.toString()}`;
        window.open(url, '_blank', 'noopener,noreferrer');
    });
});

function systemFontStack() {
    return '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
}

function chartTooltip() {
    return {
        backgroundColor: '#0f172a',
        titleFont: { size: 12 },
        bodyFont: { size: 11 },
        padding: 10,
        cornerRadius: 8,
    };
}

function toggleEmptyState(key, isEmpty) {
    const el = document.querySelector(`.rpt-chart-empty[data-empty-for="${key}"]`);
    if (!el) {
        return;
    }
    el.hidden = !isEmpty;
}

function demoMixIsEmpty(labels, values) {
    if (!labels?.length || labels[0] === 'No data') {
        return true;
    }
    return !values?.length || values.every((v) => !v);
}

function buildDemoHorizontalBar(canvas, labels, values, datasetLabel, color) {
    if (!canvas) {
        return null;
    }
    const fills = labels.map((_, i) => {
        const hex = color;
        if (hex.startsWith('#')) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            const shade = 1 - (i % 4) * 0.12;
            return `rgba(${Math.round(r * shade)}, ${Math.round(g * shade)}, ${Math.round(b * shade)}, 0.88)`;
        }
        return hex;
    });
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: datasetLabel,
                data: values,
                backgroundColor: fills,
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: chartTooltip(),
            },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, stepSize: 1, font: { size: 10 } } },
                y: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '500' }, color: '#334155' },
                },
            },
        },
    });
}
