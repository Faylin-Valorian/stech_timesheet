export const TimeWidgets = {
    init() {
        const widgets = document.querySelectorAll('.time-split-widget');
        widgets.forEach(widget => this.bindWidget(widget));
    },

    bindWidget(widget) {
        const hourSelect = widget.querySelector('.hour-select');
        const minSelect = widget.querySelector('.minute-select');
        const ampmSelect = widget.querySelector('.ampm-select');
        const hiddenInput = widget.querySelector('.combined-time-input');

        if (!hourSelect || !hiddenInput) return;

        // UI -> Hidden Input
        const updateHidden = () => {
            if (hourSelect.value && minSelect.value && ampmSelect.value) {
                let h = parseInt(hourSelect.value, 10);
                const m = minSelect.value;
                const amp = ampmSelect.value;
                if (amp === 'PM' && h < 12) h += 12;
                if (amp === 'AM' && h === 12) h = 0;
                
                hiddenInput.value = `${h.toString().padStart(2, '0')}:${m}`;
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                hiddenInput.value = '';
            }
        };

        // Hidden Input -> UI (Refresh)
        hiddenInput.refreshWidget = function() {
            const val = hiddenInput.value;
            if (val && val.includes(':')) {
                const parts = val.split(':');
                let h = parseInt(parts[0], 10);
                const m = parts[1];
                let amp = 'AM';
                
                if (h >= 12) { amp = 'PM'; if (h > 12) h -= 12; }
                if (h === 0) h = 12;
                
                hourSelect.value = h.toString().padStart(2, '0');
                minSelect.value = m;
                ampmSelect.value = amp;
            } else {
                hourSelect.value = ""; minSelect.value = ""; ampmSelect.value = "AM";
            }
        };

        hourSelect.addEventListener('change', updateHidden);
        minSelect.addEventListener('change', updateHidden);
        ampmSelect.addEventListener('change', updateHidden);
    }
};