import L from 'leaflet'; 
import * as topojson from 'topojson-client';
import us from 'us-atlas/counties-10m.json';

export const AnalysisMaps = {
    map: null,
    topology: null,
    fipsMap: {},
    layers: { states: null, counties: null },
    currentData: { states: {}, counties: {} },
    activeStateName: null,

    // Initialize the single map
    initAndRender(stateData, countyData) {
        this.currentData.states = stateData;
        this.currentData.counties = countyData;

        // 1. Setup Topology
        if (!this.topology) {
            this.topology = us; 
            const states = topojson.feature(this.topology, this.topology.objects.states).features;
            states.forEach(s => { this.fipsMap[s.id] = s.properties.name; });
            
            // Bind Back Button
            document.getElementById('btn-reset-map')?.addEventListener('click', () => {
                this.renderUSView();
            });
        }

        // 2. Setup Map Container
        if (!this.map) {
            this.map = L.map('map-main-container').setView([37.8, -96], 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.map);
        }

        // 3. Render Default View (US States) if no active state
        if (!this.activeStateName) {
            this.renderUSView();
        } else {
            // Re-render current county view with new data
            this.renderStateDetailView(this.activeStateName);
        }
    },

    // View 1: All States
    renderUSView() {
        this.activeStateName = null;
        this.updateDetailPanel("National Overview", null, true);
        const resetBtn = document.getElementById('btn-reset-map');
        if (resetBtn) resetBtn.style.display = 'none';

        // Clear layers
        if (this.layers.counties) this.map.removeLayer(this.layers.counties);
        if (this.layers.states) this.map.removeLayer(this.layers.states);

        const geojson = topojson.feature(this.topology, this.topology.objects.states);
        
        this.layers.states = L.geoJson(geojson, {
            style: (f) => {
                const info = this.currentData.states[f.properties.name] || { count: 0, is_enabled: true };
                return this.getStyle(info.count, info.is_enabled);
            },
            onEachFeature: (f, l) => {
                const info = this.currentData.states[f.properties.name];
                
                // Click State -> Drill Down
                l.on('click', () => {
                    this.renderStateDetailView(f.properties.name, l.getBounds());
                });

                l.bindTooltip(`<strong>${f.properties.name}</strong>`);
            }
        }).addTo(this.map);

        this.map.flyTo([37.8, -96], 4, { duration: 1 });
    },

    // View 2: Specific State Counties
    renderStateDetailView(stateName, bounds = null) {
        this.activeStateName = stateName;
        const resetBtn = document.getElementById('btn-reset-map');
        if (resetBtn) resetBtn.style.display = 'block';
        
        // Show state totals immediately in panel
        const stateInfo = this.currentData.states[stateName];
        this.updateDetailPanel(stateName, stateInfo);

        // Swap Layers
        if (this.layers.states) this.map.removeLayer(this.layers.states);
        if (this.layers.counties) this.map.removeLayer(this.layers.counties);

        const geojson = topojson.feature(this.topology, this.topology.objects.counties);

        this.layers.counties = L.geoJson(geojson, {
            filter: (f) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                return parentState === stateName;
            },
            style: (f) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                const key = parentState + '|' + f.properties.name;
                const info = this.currentData.counties[key] || { count: 0, is_enabled: true };
                return this.getStyle(info.count, info.is_enabled);
            },
            onEachFeature: (f, l) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                const key = parentState + '|' + f.properties.name;
                const info = this.currentData.counties[key];

                // Click County -> Update Panel
                l.on('click', () => {
                    this.updateDetailPanel(`${f.properties.name}, ${parentState}`, info);
                    // Highlight
                    this.layers.counties.eachLayer(layer => { 
                         if(layer.setStyle) layer.setStyle({ weight: 1, color: 'white', fillOpacity: 0.75 }); 
                    });
                    l.setStyle({ weight: 3, color: '#333', fillOpacity: 0.9 });
                });

                l.bindTooltip(`<strong>${f.properties.name}</strong>`);
            }
        }).addTo(this.map);

        if (bounds) {
            this.map.flyToBounds(bounds, { padding: [50, 50], duration: 1 });
        }
    },

    updateDetailPanel(title, data, isNational = false) {
        const titleEl = document.getElementById('detail-title');
        const contentEl = document.getElementById('detail-content');
        if (!titleEl || !contentEl) return;

        titleEl.innerText = title;
        
        if (isNational) {
            contentEl.innerHTML = `<p style="color:var(--color-text-maxcontrast); padding-top:20px; text-align:center;">Select a State on the map to view County details.</p>`;
            return;
        }

        if (!data || !data.visitors || Object.keys(data.visitors).length === 0) {
            contentEl.innerHTML = `
                <div style="text-align:center; padding:20px; color:var(--color-text-maxcontrast);">
                    No visits recorded for this location in the selected period.
                </div>`;
            return;
        }

        let html = `<ul style="list-style:none; padding:0; margin:0;">`;
        let total = 0;
        const sortedVisitors = Object.entries(data.visitors).sort((a,b) => b[1] - a[1]);

        sortedVisitors.forEach(([user, count]) => {
            total += count;
            html += `
                <li style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--color-border);">
                    <span style="font-weight:bold;">${user}</span>
                    <span style="background:var(--color-primary); color:#fff; padding:2px 8px; border-radius:10px; font-size:12px;">${count}</span>
                </li>
            `;
        });
        html += `</ul>`;
        html += `<div style="margin-top:20px; padding-top:10px; border-top:2px solid var(--color-text-maxcontrast); text-align:right; font-weight:bold; font-size:1.1em;">Total Visits: ${total}</div>`;

        contentEl.innerHTML = html;
    },

    getStyle(val, isEnabled) {
        if (!isEnabled) return { fillOpacity: 0, weight: 1, color: '#ccc', pointerEvents: 'none' };
        const color = val > 100 ? '#800026' : val > 50  ? '#BD0026' : val > 20  ? '#E31A1C' : val > 10  ? '#FC4E2A' : val > 5   ? '#FD8D3C' : val > 0   ? '#FEB24C' : '#A9A9A9';
        return { fillColor: color, weight: 2, color: 'white', fillOpacity: 0.75, dashArray: '3' };
    },

    refresh(type) {
        if (this.map) this.map.invalidateSize();
    }
};