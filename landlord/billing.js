'use strict';

const utilityForm = document.querySelector('[data-utility-form]');

if (utilityForm) {
    const fields = utilityForm.elements;
    const property = fields.namedItem('property_id');
    const room = fields.namedItem('room_id');
    const month = fields.namedItem('month');
    const mode = fields.namedItem('split_mode');
    const amounts = [
        ['electricity', fields.namedItem('electricity_amount')],
        ['water', fields.namedItem('water_amount')],
    ];
    const preview = utilityForm.querySelector('[data-utility-preview]');
    const feedback = utilityForm.querySelector('[data-utility-error]');
    const meterInputs = utilityForm.querySelector('[data-meter-inputs]');
    const roomOptions = Array.from(room.options, option => option.cloneNode(true));
    const format = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    let tenants = [];
    let request = 0;
    let message = '';
    let scopeChecked = false;

    const renderSplit = () => {
        const previous = new Map(Array.from(meterInputs.querySelectorAll('input'), input => [input.name, input.value]));
        meterInputs.replaceChildren();
        preview.textContent = message;
        if (!tenants.length) return;

        const count = tenants.length;
        const summary = count + (count === 1 ? ' active tenant' : ' active tenants');
        if (tenants.some(tenant => !tenant.invoice_id)) {
            preview.textContent = summary + '. Generate missing invoices before applying a split.';
            return;
        }
        const active = amounts.filter(([, field]) => Number(field.value) > 0);
        if (mode.value === 'flat') {
            const total = active.reduce((subtotal, [, field]) => subtotal + Number(field.value), 0);
            preview.textContent = total
                ? summary + ' · Estimated share ' + format.format(total / count) + ' per tenant'
                : summary + ' · Enter an amount to estimate each share';
            return;
        }

        preview.textContent = active.length ? summary + ' · Enter the meter usage for each tenant.' : summary + ' · Enter an amount to add meter readings.';
        for (const [type] of active) {
            const utility = type[0].toUpperCase() + type.slice(1);
            for (const tenant of tenants) {
                const label = document.createElement('label');
                label.textContent = utility + ' usage for ' + tenant.tenant_name + ' · Room ' + tenant.room_number;
                const input = document.createElement('input');
                input.type = 'number';
                input.name = 'meter_units[' + type + '][' + tenant.invoice_id + ']';
                input.min = '0';
                input.max = '99999.999';
                input.step = '0.001';
                input.value = previous.get(input.name) || '';
                input.required = true;
                label.append(input);
                meterInputs.append(label);
            }
        }
    };

    const loadTenants = async () => {
        const change = ++request;
        tenants = [];
        scopeChecked = false;
        feedback.textContent = '';
        message = property.value ? 'Checking active tenants…' : 'Choose a property to see the active tenants.';
        renderSplit();
        if (!property.value || !month.value) return;
        const params = new URLSearchParams({ property_id: property.value, month: month.value });
        if (room.value) params.set('room_id', room.value);
        try {
            const response = await fetch(utilityForm.dataset.splitUrl + '?' + params);
            if (!response.ok) throw new Error('Unable to load tenant counts');
            const result = await response.json();
            if (change !== request) return;
            tenants = result.tenants;
            scopeChecked = true;
            message = tenants.length ? '' : 'No active tenants in this scope.';
        } catch {
            if (change !== request) return;
            message = 'Tenant counts are unavailable. Change the property or room to try again.';
        }
        renderSplit();
    };

    const showRooms = () => {
        const previous = room.value;
        room.replaceChildren(...roomOptions
            .filter(option => !option.value || option.dataset.property === property.value)
            .map(option => option.cloneNode(true)));
        if (Array.from(room.options).some(option => option.value === previous)) room.value = previous;
    };

    property.addEventListener('change', () => { showRooms(); loadTenants(); });
    room.addEventListener('change', loadTenants);
    mode.addEventListener('change', renderSplit);
    amounts.forEach(([, field]) => field.addEventListener('input', () => {
        feedback.textContent = '';
        renderSplit();
    }));
    utilityForm.addEventListener('submit', (event) => {
        if (!amounts.some(([, field]) => Number(field.value) > 0)) {
            event.preventDefault();
            feedback.textContent = 'Enter the electricity or water bill amount.';
            amounts[0][1].focus();
        } else if (scopeChecked && (!tenants.length || tenants.some((tenant) => !tenant.invoice_id))) {
            event.preventDefault();
            feedback.textContent = tenants.length
                ? 'Generate the missing invoices before applying utility charges.'
                : 'Choose a property or room with active tenants.';
        }
    });
    showRooms();
    loadTenants();
}
