/**
 * Analysis Maps Module - Isolated Pan & High-Contrast Style
 * Feature: Hidden/Gray for disabled locations
 */
export const AnalysisMaps = {
    instances: { state: null, county: null },
    topology: null,
    fipsMap: {},
    stateBounds: {},
    enabledStates: new Set(),
    enabledCounties: new Set(),

    async initAndRender(stateData, countyData, selectedAbbr) {
        if (!this.topology) {
            const res = await fetch(OC.webroot + '/apps/stech_timesheet/js/us-atlas.json');
            this.topology = await res.json();
            const states = topojson.feature(this.topology, this.topology.objects.states).features;
            states.forEach(s => { this.fipsMap[s.id] = s.properties.name; });

            // Fetch enablement status from the filters endpoint
            const filters = await StechAPI.request('get', '/api/analysis/filters');
            this.enabledStates = new Set(filters.states.filter(s => s.is_enabled == 1).map(s => s.state_name));
            // Note: County enablement usually requires a state context; 
            // here we assume the backend provides enabled counties in the filter data or stats.

            const stateList = document.getElementById('state-list');
            if (stateList) {
                const fullOpt = document.createElement('option');
                fullOpt.value = "Full US Map";
                fullOpt.setAttribute('data-value', 'full');
                stateList.prepend(fullOpt);
            }
        }
        this.renderStateMap(stateData, selectedAbbr);
        this.renderCountyMap(countyData, selectedAbbr);
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
            filter: (f) => (!abbr || abbr === 'full' || f.properties.name === stateName),
            style: (f) => {
                const isEnabled = this.enabledStates.has(f.properties.name);
                return this.getStyle(data[f.properties.name] || 0, isEnabled);
            },
            onEachFeature: (f, l) => {
                this.stateBounds[f.properties.name] = l.getBounds();
                l.bindTooltip(`<strong>${f.properties.name}</strong>: ${data[f.properties.name] || 0}`);
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
                return (!abbr || abbr === 'full' || parentState === stateName);
            },
            style: (f) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                const countyName = f.properties.name;
                const val = data[parentState + '|' + countyName] || 0;
                
                // For counties, we check if the state is enabled. 
                // (Optionally add specific county enablement check if your API supports it)
                const isEnabled = this.enabledStates.has(parentState);
                return this.getStyle(val, isEnabled);
            },
            onEachFeature: (f, l) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                l.bindTooltip(`<strong>${f.properties.name}, ${parentState}</strong>: ${data[parentState + '|' + f.properties.name] || 0}`);
            }
        }).addTo(this.instances.county);

        if (stateName && abbr !== 'full' && this.stateBounds[stateName]) {
            this.instances.county.flyToBounds(this.stateBounds[stateName], { padding: [20, 20], duration: 1.5 });
        } else {
            this.instances.county.flyTo([37.8, -96], 4, { duration: 1.5 });
        }
    },

    getStyle(val, isEnabled = true) {
        // If disabled, return neutral gray regardless of value
        if (!isEnabled) {
            return { 
                fillColor: '#D3D3D3', 
                weight: 1, 
                opacity: 0.5, 
                color: '#AAA', 
                fillOpacity: 0.3 
            };
        }

        const color = val > 100 ? '#800026' : 
                      val > 50  ? '#BD0026' :
                      val > 20  ? '#E31A1C' :
                      val > 10  ? '#FC4E2A' :
                      val > 5   ? '#FD8D3C' :
                      val > 0   ? '#FEB24C' : '#FFEDA0';
        
        return { 
            fillColor: color, 
            weight: 2, 
            opacity: 1, 
            color: 'white', 
            dashArray: '3', 
            fillOpacity: 0.7 
        };
    },

    refresh(type) {
        setTimeout(() => this.instances[type]?.invalidateSize(), 200);
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