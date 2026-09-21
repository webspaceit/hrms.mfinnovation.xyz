const { createApp } = Vue;

const PAYMENTS = window.MVC_PAYMENTS || [];
const LEASES = window.MVC_LEASES || [];
const LANG = window.MVC_LANG || {};
const YEARS = window.MVC_YEARS || [];
const DEFAULTS = window.MVC_DEFAULT || {};
const BASE = window.MVC_BASE || '/oop-rms/';

const CHARGE_LIST = [
    { key: 'utility', name: 'utility_fee', label: () => LANG.utility_fee },
    { key: 'parking', name: 'parking_amount', label: () => LANG.parking_bill },
    { key: 'gas', name: 'gas_amount', label: () => LANG.gas_bill },
    { key: 'water', name: 'water_fee', label: () => LANG.water_bill },
    { key: 'waste', name: 'waste_fee', label: () => LANG.waste_bill }
];

function emptyCharges() {
    const initials = { utility: true, parking: false, gas: false, water: false, waste: false };
    const c = {};
    CHARGE_LIST.forEach(item => {
        c[item.key] = { enabled: !!initials[item.key], amount: '0' };
    });
    return c;
}

function todayStr() {
    const d = new Date();
    return String(d.getDate()).padStart(2, '0') + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear();
}

createApp({
    data() {
        return {
            payments: PAYMENTS,
            leases: LEASES,
            lang: LANG,
            years: YEARS,
            monthLabels: LANG.months || {},
            baseUrl: BASE,
            selectedMonth: DEFAULTS.month || 0,
            selectedYear: DEFAULTS.year || new Date().getFullYear(),
            query: '',
            form: {
                id: '',
                lease_id: '',
                month: new Date().getMonth() + 1,
                year: DEFAULTS.year || new Date().getFullYear(),
                amount: '',
                receipt_type: 'rent',
                receipt_disabled: false,
                charges: emptyCharges(),
                method: 'cash',
                date: todayStr(),
                note: ''
            },
            lockLeasePrefill: false,
            modalTitle: '',
            markTarget: null,
            markMethod: 'cash',
            chargeList: CHARGE_LIST.map(item => ({
                key: item.key,
                name: item.name,
                label: item.label()
            }))
        };
    },
    computed: {
        invoiceMap() {
            const m = {};
            this.payments.forEach(p => {
                const k = p.lease_id + '-' + p.month + '-' + p.year;
                if (p.inv_due > 0) {
                    if (!m[k]) {
                        m[k] = { due: p.inv_due, paid: p.inv_paid, rentSum: 0, status: p.inv_payment_status };
                    }
                    m[k].rentSum += parseFloat(p.amount) || 0;
                }
            });
            return m;
        },
        filtered() {
            let list = this.payments;
            if (this.selectedMonth) {
                list = list.filter(p => p.month === Number(this.selectedMonth));
            }
            if (this.selectedYear) {
                list = list.filter(p => p.year === Number(this.selectedYear));
            }
            if (this.query) {
                const q = this.query.toLowerCase();
                list = list.filter(p =>
                    String(p.tenant_name || '').toLowerCase().includes(q) ||
                    String(p.building_name || '').toLowerCase().includes(q) ||
                    String(p.flat_no || '').toLowerCase().includes(q)
                );
            }
            return list;
        },
        filteredTotal() {
            return this.filtered.reduce((sum, p) => {
                return sum + (parseFloat(p.amount) || 0) + (parseFloat(p.utility_fee) || 0)
                    + (parseFloat(p.parking_amount) || 0) + (parseFloat(p.gas_amount) || 0)
                    + (parseFloat(p.water_fee) || 0) + (parseFloat(p.waste_fee) || 0);
            }, 0);
        },
        formTotal() {
            const num = v => parseFloat(toAsciiDigits(v)) || 0;
            let sum = num(this.form.amount);
            CHARGE_LIST.forEach(item => {
                if (this.form.charges[item.key].enabled) {
                    sum += num(this.form.charges[item.key].amount);
                }
            });
            return sum;
        },
        remainingHint() {
            if (!this.form.lease_id) return '';
            const k = this.form.lease_id + '-' + this.form.month + '-' + this.form.year;
            const inv = this.invoiceMap[k];
            if (!inv) return '';
            const rem = Math.max(0, inv.due - inv.rentSum);
            if (rem <= 0) return LANG.already_fully_paid;
            return LANG.remaining_due + ': ' + formatMoney(rem) + ' (' + this.statusLabel(inv.status) + ')';
        }
    },
    methods: {
        bd(n) {
            return document.documentElement.lang === 'bn' ? toBengaliDigits(String(n)) : String(n);
        },
        money(amount) {
            return formatMoney(amount);
        },
        mon(m) {
            return (this.monthLabels && this.monthLabels[m]) || m;
        },
        methodLabel(m) {
            return (this.lang.method_labels && this.lang.method_labels[m]) || m;
        },
        statusLabel(s) {
            if (s === 'paid') return this.lang.status_paid;
            if (s === 'partial') return this.lang.status_partial;
            return this.lang.status_unpaid;
        },
        statusBadge(s) {
            return s === 'partial'
                ? 'badge bg-warning-subtle text-warning-emphasis'
                : 'badge bg-danger-subtle text-danger-emphasis';
        },
        openAdd() {
            this.form.id = '';
            this.form.lease_id = '';
            this.form.month = new Date().getMonth() + 1;
            this.form.year = DEFAULTS.year || new Date().getFullYear();
            this.form.amount = '';
            this.form.receipt_type = 'rent';
            this.form.receipt_disabled = false;
            this.form.charges = emptyCharges();
            this.form.method = 'cash';
            this.form.date = todayStr();
            this.form.note = '';
            this.lockLeasePrefill = false;
            this.modalTitle = this.lang.add_payment;
            this.showModal('paymentModal');
        },
        openEdit(p) {
            this.form.id = p.id;
            this.form.lease_id = p.lease_id;
            this.form.month = p.month;
            this.form.year = p.year;
            this.lockLeasePrefill = true;

            if (!this.leases.some(l => l.id === p.lease_id)) {
                this.leases.push({
                    id: p.lease_id,
                    label: (p.tenant_name || '') + ' · ' + (p.building_name || '') + ' ' + (p.flat_no || ''),
                    rent_amount: parseFloat(p.amount) || 0,
                    utility_fee: parseFloat(p.utility_fee) || 0,
                    unit_type: p.unit_type
                });
            }

            const bn = document.documentElement.lang === 'bn';
            this.form.amount = bn ? toBengaliDigits(p.amount || '0') : String(p.amount || '0');

            const values = {
                utility: p.utility_fee, parking: p.parking_amount,
                gas: p.gas_amount, water: p.water_fee, waste: p.waste_fee
            };
            CHARGE_LIST.forEach(item => {
                const v = parseFloat(toAsciiDigits(values[item.key] || '0')) || 0;
                this.form.charges[item.key].amount = bn ? toBengaliDigits(values[item.key] || '0') : String(values[item.key] || '0');
                this.form.charges[item.key].enabled = v > 0;
            });

            this.form.method = p.payment_method;
            this.form.date = p.payment_date;
            this.form.receipt_type = p.receipt_type;
            this.form.receipt_disabled = p.unit_type === 'shop';
            this.form.note = p.note || '';
            this.lockLeasePrefill = false;
            this.modalTitle = this.lang.edit_payment;
            this.showModal('paymentModal');
        },
        onLeaseChange() {
            if (this.lockLeasePrefill) return;
            const l = this.leases.find(x => x.id === this.form.lease_id);
            if (!l) return;
            const bn = document.documentElement.lang === 'bn';
            const util = parseFloat(l.utility_fee) || 0;
            this.form.amount = bn ? toBengaliDigits(String(l.rent_amount || '0')) : String(l.rent_amount || '0');
            this.form.charges.utility.amount = bn ? toBengaliDigits(String(l.utility_fee || '0')) : String(l.utility_fee || '0');
            this.form.charges.utility.enabled = util > 0;
            this.form.receipt_disabled = l.unit_type === 'shop';
            if (l.unit_type === 'shop') this.form.receipt_type = 'rent';
        },
        showModal(id) {
            openModal(id);
        },
        save() {
            const isEdit = !!this.form.id;
            const url = BASE + 'mvc.php?r=payments/' + (isEdit ? 'update' : 'save');
            const num = v => parseFloat(toAsciiDigits(v)) || 0;
            const data = {
                id: this.form.id,
                lease_id: this.form.lease_id,
                month: this.form.month,
                year: this.form.year,
                amount: num(this.form.amount),
                utility_fee: this.form.charges.utility.enabled ? num(this.form.charges.utility.amount) : 0,
                parking_amount: this.form.charges.parking.enabled ? num(this.form.charges.parking.amount) : 0,
                gas_amount: this.form.charges.gas.enabled ? num(this.form.charges.gas.amount) : 0,
                water_fee: this.form.charges.water.enabled ? num(this.form.charges.water.amount) : 0,
                waste_fee: this.form.charges.waste.enabled ? num(this.form.charges.waste.amount) : 0,
                payment_method: this.form.method,
                receipt_type: this.form.receipt_type,
                payment_date: toAsciiDigits(this.form.date),
                note: this.form.note
            };
            if (!data.lease_id || !data.month || !data.year) {
                showToast(this.lang.no_data, 'danger');
                return;
            }
            ajax(url, data, result => {
                if (result.success) {
                    showToast(result.message || 'OK', 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    showToast(result.message || 'Error', 'danger');
                }
            });
        },
        openMarkPaid(p) {
            this.markTarget = p;
            this.markMethod = 'cash';
            this.showModal('markPaidModal');
        },
        markPaidConfirm() {
            const p = this.markTarget;
            if (!p) return;
            ajax(BASE + 'mvc.php?r=payments/mark-paid', {
                id: p.id,
                payment_method: this.markMethod
            }, result => {
                if (result.success) {
                    showToast(result.message || 'OK', 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    showToast(result.message || 'Error', 'danger');
                }
            });
        },
        confirmDeletePayment(p) {
            confirmDelete(BASE + 'mvc.php?r=payments/delete&id=' + p.id, this.lang.delete_confirm);
        }
    }
}).mount('#payments-app');