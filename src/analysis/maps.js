import L from 'leaflet'; 
import * as topojson from 'topojson-client';
import us from 'us-atlas/counties-10m.json';

export const AnalysisMaps = {
    instances: { state: null, county: null },
    topology: null,
    fipsMap: {},
    stateBounds: {},

    // Initialize
    initAndRender(stateData, countyData, selectedAbbr) {
        if (!this.topology) {
            this.topology = us; 

            // Initialize FIPS Map immediately
            const states = topojson.feature(this.topology, this.topology.objects.states).features;
            states.forEach(s => { this.fipsMap[s.id] = s.properties.name; });

            // Ensure "Full US Map" reset option exists
            const stateList = document.getElementById('state-list');
            if (stateList) {
                if (!stateList.querySelector('option[data-value="full"]')) {
                    const fullOpt = document.createElement('option');
                    fullOpt.value = "Full US Map";
                    fullOpt.setAttribute('data-value', 'full');
                    stateList.prepend(fullOpt);
                }
            }
        }

        const mode = (selectedAbbr && selectedAbbr !== '') ? selectedAbbr : 'full';
        
        this.renderStateMap(stateData, mode);
        this.renderCountyMap(countyData, mode);
    },

    // Helper: Update the Info Panel
    updateDetailPanel(title, data) {
        const titleEl = document.getElementById('detail-title');
        const contentEl = document.getElementById('detail-content');
        if (!titleEl || !contentEl) return;

        titleEl.innerText = title;
        
        if (!data || !data.visitors || Object.keys(data.visitors).length === 0) {
            contentEl.innerHTML = `
                <div style="text-align:center; padding:20px; color:var(--color-text-maxcontrast);">
                    No visits recorded for this location in the selected period.
                </div>`;
            return;
        }

        let html = `<ul style="list-style:none; padding:0; margin:0;">`;
        let total = 0;
        
        // Sort visitors by count descending
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

        html += `
            <div style="margin-top:20px; padding-top:10px; border-top:2px solid var(--color-text-maxcontrast); text-align:right; font-weight:bold; font-size:1.1em;">
                Total Visits: ${total}
            </div>
        `;

        contentEl.innerHTML = html;
    },

    renderStateMap(data, abbr) {
        if (!this.instances.state) {
            this.instances.state = L.map('map-state-container', { scrollWheelZoom: false }).setView([37.8, -96], 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.instances.state);
        }
        this.instances.state.eachLayer(l => { if (l instanceof L.GeoJSON) this.instances.state.removeLayer(l); });

        const stateName = this.getStateNameFromAbbr(abbr);
        const geojson = topojson.feature(this.topology, this.topology.objects.states);
        
        L.geoJson(geojson, {
            filter: (f) => (abbr === 'full' || !abbr || f.properties.name === stateName),
            style: (f) => {
                const info = data[f.properties.name] || { count: 0, is_enabled: true };
                return this.getStyle(info.count, info.is_enabled);
            },
            onEachFeature: (f, l) => {
                this.stateBounds[f.properties.name] = l.getBounds();
                const info = data[f.properties.name];
                
                // CLICK EVENT
                l.on('click', () => {
                    this.updateDetailPanel(f.properties.name, info);
                    
                    // Optional: Highlight Selection
                    this.instances.state.eachLayer(layer => { 
                         if(layer.setStyle) layer.setStyle({ weight: 1, color: 'white', fillOpacity: 0.75 }); 
                    });
                    l.setStyle({ weight: 3, color: '#333', fillOpacity: 0.9 });
                });

                l.bindTooltip(`<strong>${f.properties.name}</strong>`);
            }
        }).addTo(this.instances.state);

        if (stateName && abbr !== 'full' && this.stateBounds[stateName]) {
            this.instances.state.flyToBounds(this.stateBounds[stateName], { padding: [20, 20], duration: 1.5 });
        } else {
            this.instances.state.flyTo([37.8, -96], 4, { duration: 1.5 });
        }
    },

    renderCountyMap(data, abbr) {
        if (!this.instances.county) {
            this.instances.county = L.map('map-county-container').setView([37.8, -96], 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.instances.county);
        }
        this.instances.county.eachLayer(l => { if (l instanceof L.GeoJSON) this.instances.county.removeLayer(l); });

        const stateName = this.getStateNameFromAbbr(abbr);
        const geojson = topojson.feature(this.topology, this.topology.objects.counties);
        
        L.geoJson(geojson, {
            filter: (f) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                return (abbr === 'full' || !abbr || parentState === stateName);
            },
            style: (f) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                const key = parentState + '|' + f.properties.name;
                const info = data[key] || { count: 0, is_enabled: true };
                return this.getStyle(info.count, info.is_enabled);
            },
            onEachFeature: (f, l) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                const key = parentState + '|' + f.properties.name;
                const info = data[key];

                // CLICK EVENT
                l.on('click', () => {
                    this.updateDetailPanel(`${f.properties.name}, ${parentState}`, info);
                    
                    // Optional: Highlight Selection
                    this.instances.county.eachLayer(layer => { 
                         if(layer.setStyle) layer.setStyle({ weight: 1, color: 'white', fillOpacity: 0.75 }); 
                    });
                    l.setStyle({ weight: 3, color: '#333', fillOpacity: 0.9 });
                });

                l.bindTooltip(`<strong>${f.properties.name}</strong>`);
            }
        }).addTo(this.instances.county);

        if (stateName && abbr !== 'full' && this.stateBounds[stateName]) {
            this.instances.county.flyToBounds(this.stateBounds[stateName], { padding: [20, 20], duration: 1.5 });
        } else {
            this.instances.county.flyTo([37.8, -96], 4, { duration: 1.5 });
        }
    },

    getStyle(val, isEnabled) {
        if (!isEnabled) return { fillOpacity: 0, weight: 1, color: '#ccc', pointerEvents: 'none' };
        
        const color = val > 100 ? '#800026' : 
                      val > 50  ? '#BD0026' :
                      val > 20  ? '#E31A1C' : 
                      val > 10  ? '#FC4E2A' : 
                      val > 5   ? '#FD8D3C' : 
                      val > 0   ? '#FEB24C' : '#A9A9A9';

        return { 
            fillColor: color, 
            weight: 2, 
            color: 'white', 
            fillOpacity: 0.75, 
            dashArray: '3' 
        };
    },

    refresh(type) {
        const map = this.instances[type];
        if (map) {
            map.invalidateSize();
        }
    },

    getStateNameFromAbbr(abbr) {
        const opts = document.getElementById('state-list')?.options;
        if (!opts) return null;
        for (let i=0; i<opts.length; i++) {
            if (opts[i].getAttribute('data-value') === abbr) return opts[i].value;
        }
        return null;
    }
};