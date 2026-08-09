// assets/js/script.js
// Global JS for Sunshine Florist

function initAll() {
    // 1. Confirmations for important actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = el.getAttribute('data-confirm') || 'Yakin mau lanjutkan?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // 2. Initialize all select elements with searchable dropdowns
    document.querySelectorAll('select').forEach(makeSearchableSelect);

    // 3. Observe dynamically added select elements (for dynamic rows)
    const observer = new MutationObserver((mutations) => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.tagName === 'SELECT') {
                        makeSearchableSelect(node);
                    } else {
                        node.querySelectorAll('select').forEach(makeSearchableSelect);
                    }
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // 4. Automatically wrap all tables inside .panel in a .table-responsive wrapper (only for wide tables with > 4 columns)
    document.querySelectorAll('.panel table').forEach(function(table) {
        const firstRow = table.querySelector('tr');
        const cols = firstRow ? firstRow.querySelectorAll('th, td').length : 0;
        if (cols > 4) {
            if (!table.parentElement.classList.contains('table-responsive')) {
                var wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        }
    });
}

// Execute immediately if DOM is ready, otherwise wait for event
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}

function makeSearchableSelect(select) {
    if (select.classList.contains('tomselected') || select.tomselect) {
        return; // Already initialized
    }
    
    // Ignore hidden select elements or elements inside an existing TomSelect wrapper
    if (select.style.display === 'none' || select.closest('.ts-wrapper')) {
        return;
    }
    
    // Initialize Tom Select with search input & full empty option support
    try {
        const ts = new TomSelect(select, {
            create: false,
            allowEmptyOption: true,
            controlInput: '<input>',
            plugins: ['dropdown_input'],
            dropdownParent: 'body'
        });

        // Dispatch native change event so inline onchange handlers and form submits execute seamlessly
        ts.on('change', function() {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    } catch (e) {
        console.warn('TomSelect init failed:', e);
    }
}