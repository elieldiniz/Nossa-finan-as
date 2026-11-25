import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const GREEN = '#14614F';
const PALETTE = [GREEN, '#5BB89A', '#E8A34B', '#B9442F', '#27458F', '#6B4600', '#7A2415', '#0B5A44'];
const brl = (cents) => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const monthLabel = (ym) => new Date(`${ym}-01T12:00:00`).toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '');

/** Componente Alpine: desenha linha, barras e rosca a partir das séries calculadas no servidor. */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('nfCharts', (series) => ({
        charts: [],
        init() {
            const tooltip = { callbacks: { label: (ctx) => ` ${brl(ctx.parsed.y ?? ctx.parsed)}` } };
            const make = (ref, config) => this.$refs[ref] && this.charts.push(new Chart(this.$refs[ref], config));

            const keys = Object.keys(series.line);
            make('line', {
                type: 'line',
                data: { labels: keys.map(monthLabel), datasets: [{ data: Object.values(series.line), borderColor: GREEN, backgroundColor: GREEN, tension: 0.3, pointRadius: 4 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: { y: { ticks: { callback: (v) => brl(v) } } } },
            });

            const cats = Object.keys(series.bars);
            make('bars', {
                type: 'bar',
                data: { labels: cats, datasets: [
                    { label: 'Mês anterior', data: cats.map((c) => series.bars[c].previous), backgroundColor: '#C9D4CF' },
                    { label: 'Este mês', data: cats.map((c) => series.bars[c].current), backgroundColor: GREEN },
                ] },
                options: { maintainAspectRatio: false, plugins: { tooltip }, scales: { y: { ticks: { callback: (v) => brl(v) } } } },
            });

            const donut = Object.keys(series.donut);
            make('donut', {
                type: 'doughnut',
                data: { labels: donut, datasets: [{ data: donut.map((c) => series.donut[c].amount), backgroundColor: PALETTE }] },
                options: { maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${brl(ctx.parsed)}` } } } },
            });
        },
        destroy() { this.charts.forEach((c) => c.destroy()); },
    }));
});
