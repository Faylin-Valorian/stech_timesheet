export const Modals = {
    /**
     * Shows the Custom Error Overlay
     */
    showError(msg) {
        let overlay = document.getElementById('stech-centered-error');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stech-centered-error';
            // Note: Styles should be in your CSS, or you can inline them here if preferred
            overlay.innerHTML = `
                <div class="stech-error-content">
                    <h3>Access Denied</h3>
                    <p id="stech-error-msg-text"></p>
                    <button class="primary-button" id="btn-err-close">Close</button>
                </div>`;
            document.body.appendChild(overlay);
            document.getElementById('btn-err-close').onclick = () => overlay.style.display = 'none';
        }
        document.getElementById('stech-error-msg-text').textContent = msg;
        overlay.style.display = 'flex';
    },

    /**
     * Shows a Confirmation Overlay (Archive/Restore)
     * @param {string} type - 'archive' or 'restore'
     * @param {Function} onConfirm - Callback to run if user clicks Yes
     */
    showConfirm(type, onConfirm) {
        const id = `stech-confirm-${type}`;
        let overlay = document.getElementById(id);
        
        // Colors & Text based on type
        const isRestore = type === 'restore';
        const color = isRestore ? '#28a745' : '#e67e22';
        const title = isRestore ? 'Restore Record?' : 'Archive Record?';
        const msg = isRestore 
            ? 'This will move the record back to the active list.' 
            : 'Are you sure? It will be hidden from the main view.';
        const btnText = isRestore ? 'Yes, Restore' : 'Yes, Archive';

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = id;
            overlay.style.cssText = `position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10002;display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);`;
            document.body.appendChild(overlay);
        }

        overlay.innerHTML = `
            <div class="stech-error-content" style="border-top-color:${color};background:white;padding:20px;border-radius:8px;">
                <h3 style="color:${color};margin-top:0;">${title}</h3>
                <p>${msg}</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;">
                    <button id="btn-${type}-yes" class="primary-button" style="background:${color};">${btnText}</button>
                    <button id="btn-${type}-no" class="secondary-button">Cancel</button>
                </div>
            </div>`;
            
        // Bind Callbacks
        const yesBtn = document.getElementById(`btn-${type}-yes`);
        // Clone button to remove old listeners if reusing DOM
        const newYesBtn = yesBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYesBtn, yesBtn);
        
        newYesBtn.onclick = () => {
            overlay.style.display = 'none';
            if (onConfirm) onConfirm();
        };

        document.getElementById(`btn-${type}-no`).onclick = () => overlay.style.display = 'none';
        overlay.style.display = 'flex';
    }
};