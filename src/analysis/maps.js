/**
 * Analysis Maps Module
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
        }
        this.renderStateMap(stateData);
        this.renderCountyMap(countyData, selectedAbbr);
    },

    renderStateMap(data) {
        if (!this.instances.state) {
            this.instances.state = L.map('map-state-container', { scrollWheelZoom: false }).setView([37.8, -96], 4);
        }
        this.instances.state.eachLayer(l => this.instances.state.removeLayer(l));
        
        const geojson = topojson.feature(this.topology, this.topology.objects.states);
        const layer = L.geoJson(geojson, {
            style: (f) => this.getStyle(data[f.properties.name] || 0),
            onEachFeature: (f, l) => {
                this.stateBounds[f.properties.name] = l.getBounds();
                l.bindTooltip(`<strong>${f.properties.name}</strong>: ${data[f.properties.name] || 0}`);
            }
        }).addTo(this.instances.state);
    },

    renderCountyMap(data, abbr) {
        if (!this.instances.county) {
            this.instances.county = L.map('map-county-container').setView([37.8, -96], 4);
        }
        this.instances.county.eachLayer(l => this.instances.county.removeLayer(l));
        document.getElementById('county-map-placeholder').style.display = 'none';

        const geojson = topojson.feature(this.topology, this.topology.objects.counties);
        L.geoJson(geojson, {
            style: (f) => {
                const stateName = this.fipsMap[f.id.substring(0, 2)];
                const val = data[stateName + '|' + f.properties.name] || 0;
                return this.getStyle(val);
            }
        }).addTo(this.instances.county);

        // Auto-zoom to state if selected
        const stateName = this.getStateNameFromAbbr(abbr);
        if (stateName && this.stateBounds[stateName]) {
            this.instances.county.fitBounds(this.stateBounds[stateName]);
        }
    },

    getStyle(val) {
        const color = val > 50 ? '#800026' : val > 10 ? '#E31A1C' : val > 0 ? '#FEB24C' : '#EEEEEE';
        return { fillColor: color, weight: 1, color: 'white', fillOpacity: 0.7 };
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