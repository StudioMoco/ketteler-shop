/*
 * Verwaltung: Nachweis der USt-IdNr.-Prüfung (VIES) am Kunden (Karte unter „Konto“) und an der Bestellung (Bestellung ohne
 * Umsatzsteuer wegen bestätigter USt-IdNr.). Nur lesen. Bewusst ohne Bauschritt geschrieben (kein Webpack).
 */
(function () {
    'use strict';

    var STATUS = {
        gueltig: 'gültig (über VIES bestätigt)',
        ungueltig: 'ungültig laut VIES',
        nicht_pruefbar: 'nicht prüfbar (VIES nicht erreichbar), keine Befreiung',
        inland: 'deutsche Nummer, keine Befreiung',
        format: 'kein gültiges Format'
    };

    function datum(wert) {
        return wert ? new Date(wert).toLocaleString('de-DE', { timeZone: 'Europe/Berlin' }) + ' Uhr' : '';
    }

    function naechste(wert) {
        if (!wert) {
            return '';
        }
        var d = new Date(wert);
        d.setFullYear(d.getFullYear() + 1);
        return d.toLocaleDateString('de-DE', { timeZone: 'Europe/Berlin' });
    }

    var gemeinsam = {
        methods: {
            datum: datum,
            naechste: naechste,
            status: function (s) {
                return STATUS[s] || s || '';
            }
        }
    };

    Shopware.Component.register('ketteler-ustid-nachweis', {
        mixins: [gemeinsam],
        template: '' +
            '<sw-card v-if="stand" title="USt-IdNr.: Prüfung über VIES" position-identifier="ketteler-ustid-nachweis" class="ketteler-ustid-nachweis">' +
            '  <dl data-kt="ustid-nachweis" style="display:grid;grid-template-columns:220px 1fr;gap:6px 16px;margin:0">' +
            '    <dt>USt-IdNr.</dt><dd>{{ stand.ustid }}</dd>' +
            '    <dt>Ergebnis</dt><dd>{{ status(stand.status) }}</dd>' +
            '    <dt>Geprüft am</dt><dd>{{ datum(stand.geprueft) }}</dd>' +
            '    <dt>Abfragenummer (VIES)</dt><dd data-kt="ustid-abfragenummer">{{ stand.anfrageNummer || \'keine (ohne eigene USt-IdNr. als Anfragender)\' }}</dd>' +
            '    <template v-if="stand.name"><dt>Name laut VIES</dt><dd>{{ stand.name }}</dd></template>' +
            '    <template v-if="stand.fehler"><dt>Hinweis</dt><dd>{{ stand.fehler }}</dd></template>' +
            '    <template v-if="stand.status === \'gueltig\'"><dt>Erneute Prüfung</dt><dd>beim nächsten Kauf ab {{ naechste(stand.geprueft) }}</dd></template>' +
            '  </dl>' +
            '  <div v-if="verlauf.length" style="margin-top:16px">' +
            '    <strong>Frühere Prüfungen</strong>' +
            '    <ul style="margin:4px 0 0 20px"><li v-for="(v, i) in verlauf" :key="i">{{ datum(v.geprueft) }}: {{ v.ustid }}, {{ status(v.status) }}<span v-if="v.anfrageNummer">, Abfragenummer {{ v.anfrageNummer }}</span></li></ul>' +
            '  </div>' +
            '</sw-card>',
        props: { customer: { type: Object, required: true } },
        computed: {
            stand: function () {
                var f = this.customer && this.customer.customFields;
                return f && f.ketteler_ustid ? f.ketteler_ustid : null;
            },
            verlauf: function () {
                return (this.stand && Array.isArray(this.stand.verlauf)) ? this.stand.verlauf.slice(1) : [];
            }
        }
    });

    Shopware.Component.override('sw-customer-detail-base', {
        template: '' +
            '{% block sw_customer_detail_base_info_card %}' +
            '{% parent %}' +
            '<ketteler-ustid-nachweis v-if="customer" :customer="customer" />' +
            '{% endblock %}'
    });

    Shopware.Component.register('ketteler-ustid-bestellung', {
        mixins: [gemeinsam],
        template: '' +
            '<sw-card v-if="nachweis" title="Ohne Umsatzsteuer: USt-IdNr. bestätigt" position-identifier="ketteler-ustid-bestellung">' +
            '  <p data-kt="ustid-bestellung">{{ nachweis.ustid }}{{ nachweis.name ? \' (\' + nachweis.name + \')\' : \'\' }}: {{ status(nachweis.status) }},' +
            '  geprüft am {{ datum(nachweis.geprueft) }}<span v-if="nachweis.anfrageNummer">, Abfragenummer {{ nachweis.anfrageNummer }}</span>.</p>' +
            '</sw-card>',
        props: { order: { type: Object, required: true } },
        computed: {
            nachweis: function () {
                var f = this.order && this.order.customFields;
                return f && f.ketteler_ustid_nachweis ? f.ketteler_ustid_nachweis : null;
            }
        }
    });

    Shopware.Component.override('sw-order-detail-general', {
        template: '' +
            '{% block sw_order_detail_general_info_card %}' +
            '{% parent %}' +
            '<ketteler-ustid-bestellung v-if="order" :order="order" />' +
            '{% endblock %}'
    });
})();
