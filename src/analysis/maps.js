/**
 * Analysis Maps Module - Side-by-Side & "Hot Zone" Intensity Style
 */
export const AnalysisMaps = {
    instances: { state: null, county: null },
    topology: null,
    fipsMap: {},
    stateBounds: {},

    async initAndRender(stateData, countyData, selectedAbbr) {
        if (!this.topology) {
            const res = await fetch(OC.webroot + '/apps/stech_timesheet/js/us-atlas.json');
            this.topology = await res.json();
            const states = topojson.feature(this.topology, this.topology.objects.states).features;
            states.forEach(s => { this.fipsMap[s.id] = s.properties.name; });

            // Ensure "Full US Map" reset option exists
            const stateList = document.getElementById('state-list');
            if (stateList) {
                const fullOpt = document.createElement('option');
                fullOpt.value = "Full US Map";
                fullOpt.setAttribute('data-value', 'full');
                stateList.prepend(fullOpt);
            }
        }

        const mode = selectedAbbr || 'full';
        this.renderStateMap(stateData, mode);
        this.renderCountyMap(countyData, mode);
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
                const info = data[f.properties.name] || { count: 0, is_enabled: false };
                return this.getStyle(info.count, info.is_enabled);
            },
            onEachFeature: (f, l) => {
                this.stateBounds[f.properties.name] = l.getBounds();
                const count = data[f.properties.name]?.count || 0;
                l.bindTooltip(`<strong>${f.properties.name}</strong>: ${count} Records`);
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
                if (!abbr || abbr === 'full') return true;
                return this.fipsMap[f.id.substring(0, 2)] === stateName;
            },
            style: (f) => {
                const parentState = this.fipsMap[f.id.substring(0, 2)];
                const info = data[parentState + '|' + f.properties.name] || { count: 0, is_enabled: false };
                return this.getStyle(info.count, info.is_enabled);
            }
        }).addTo(this.instances.county);

        if (stateName && abbr !== 'full' && this.stateBounds[stateName]) {
            this.instances.county.flyToBounds(this.stateBounds[stateName], { padding: [20, 20], duration: 1.5 });
        } else {
            this.instances.county.flyTo([37.8, -96], 4, { duration: 1.5 });
        }
    },

    /**
     * "Hot Zone" Style: Grayscale baseline to Red transition
     */
    getStyle(val, isEnabled) {
        // Disabled regions are transparent/grayscale
        if (!isEnabled) return { fillOpacity: 0, weight: 1, color: '#ccc', pointerEvents: 'none' };
        
        // Gradual transition from gray (#eee) to red (#800026) based on intensity
        const color = val > 100 ? '#800026' : 
                      val > 50  ? '#BD0026' :
                      val > 20  ? '#E31A1C' : 
                      val > 10  ? '#FC4E2A' : 
                      val > 5   ? '#FD8D3C' : 
                      val > 0   ? '#FEB24C' : '#EEEEEE'; // Grayscale baseline for 0 records

        return { 
            fillColor: color, 
            weight: 2, 
            color: 'white', 
            fillOpacity: 0.75, 
            dashArray: '3' 
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