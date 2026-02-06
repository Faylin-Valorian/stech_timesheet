import { StechAPI } from '../api.js';

export const HolidayAdmin = {
    allHolidays: [],

    async load() {
        this.allHolidays = await StechAPI.request('get', '/api/admin/holidays');
        // Setup listeners once on load (idempotent check inside)
        this.setupColorSync();
        this.render();
    },

    setupColorSync() {
        const colorInput = document.getElementById('holiday-color');
        const colorText = document.getElementById('holiday-color-text');
        
        // Remove old listeners to avoid duplicates if called multiple times (cloning)
        if(colorInput && colorText) {
            const newColor = colorInput.cloneNode(true);
            const newText = colorText.cloneNode(true);
            colorInput.parentNode.replaceChild(newColor, colorInput);
            colorText.parentNode.replaceChild(newText, colorText);
            
            newColor.addEventListener('input', (e) => { newText.value = e.target.value; });
            newText.addEventListener('input', (e) => {
                let val = e.target.value;
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-F]{6}$/i.test(val)) newColor.value = val;
            });
            newText.addEventListener('change', (e) => {
                 let val = e.target.value;
                 if (!val.startsWith('#')) val = '#' + val;
                 if (/^#[0-9A-F]{6}$/i.test(val)) {
                     e.target.value = val;
                     newColor.value = val;
                 } else {
                     e.target.value = newColor.value; 
                 }
            });
        }
    },

    render() {
        const term = (document.getElementById('holiday-search-input')?.value || '').toLowerCase();
        const status = document.querySelector('input[name="holiday-status"]:checked')?.value || 'active';
        const list = document.getElementById('holiday-list');
        if (!list) return;
        list.innerHTML = '';

        this.allHolidays.filter(h => {
            const active = (h.holiday_archive == 0 || h.holiday_archive == null);
            const matchesSearch = (h.holiday_name || '').toLowerCase().includes(term);
            const matchesStatus = (status === 'all' || (status === 'active' && active) || (status === 'archived' && !active));
            return matchesSearch && matchesStatus;
        }).forEach(h => {
            const active = (h.holiday_archive == 0 || h.holiday_archive == null);
            const item = document.createElement('div');
            item.className = 'list-item';
            item.style.opacity = active ? '1' : '0.6';
            
            const colorDot = `<span style="display:inline-block; width:12px; height:12px; background:${h.holiday_bg || '#e67e22'}; border-radius:50%; margin-right:8px; vertical-align: middle;"></span>`;

            item.innerHTML = `
                <span style="flex:1; cursor:pointer;">
                    <strong>${colorDot} ${h.holiday_name}</strong><br>
                    <small style="margin-left: 24px;">${h.holiday_start_date}</small>
                </span>
                <label class="admin-switch">
                    <input type="checkbox" ${active ? 'checked' : ''}>
                    <span class="admin-slider"></span>
                </label>
            `;
            item.querySelector('span').addEventListener('click', () => this.edit(h));
            item.querySelector('input').addEventListener('change', () => this.toggleArchive(h.holiday_id));
            list.appendChild(item);
        });
    },

    edit(h) {
        document.getElementById('holiday-id').value = h.holiday_id;
        document.getElementById('holiday-name').value = h.holiday_name;
        document.getElementById('holiday-start').value = h.holiday_start_date;
        document.getElementById('holiday-end').value = h.holiday_end_date;
        
        const color = h.holiday_bg || '#e67e22';
        document.getElementById('holiday-color').value = color;
        document.getElementById('holiday-color-text').value = color;
        
        document.getElementById('btn-save-holiday').innerText = "Update Holiday";
        document.getElementById('holiday-form-title').innerText = "Edit Holiday";
        document.getElementById('btn-cancel-holiday').classList.remove('hidden');
    },

    resetForm() {
        document.getElementById('form-holiday').reset();
        document.getElementById('holiday-id').value = '';
        
        document.getElementById('holiday-color').value = '#e67e22';
        document.getElementById('holiday-color-text').value = '#e67e22';

        document.getElementById('btn-save-holiday').innerText = "Add Holiday";
        document.getElementById('holiday-form-title').innerText = "Add Holiday";
        document.getElementById('btn-cancel-holiday').classList.add('hidden');
    },

    async submit(e) {
        e.preventDefault();
        const payload = {
            id: document.getElementById('holiday-id').value,
            name: document.getElementById('holiday-name').value,
            start: document.getElementById('holiday-start').value,
            end: document.getElementById('holiday-end').value,
            bg: document.getElementById('holiday-color-text')?.value || '#e67e22'
        };
        await StechAPI.request('post', '/api/admin/holidays', payload);
        this.resetForm();
        this.load();
    },

    async toggleArchive(id) {
        await StechAPI.request('post', `/api/admin/holidays/${id}/toggle`);
        this.load();
    }
};