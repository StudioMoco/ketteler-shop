/*
 * Verwaltung > Bestellungen > Bestellung: Karte „Online-Zugänge (Lizenzen)“ mit dem Stand je Position aus dem Zusatzfeld
 * ketteler_lizenz (offen, wartet, angelegt mit Lizenz-ID, fehler mit Grund, beendet). Ohne Bauschritt, Shopware lädt die
 * Datei direkt.
 */
(function () {
    'use strict';

    var NAMEN = { offen: 'offen', wartet: 'wartet auf die Lizenz-Schnittstelle', angelegt: 'Lizenz angelegt', fehler: 'Fehler', beendet: 'beendet' };

    Shopware.Component.register('ketteler-lizenzen-bestellung', {
        template: '' +
            '<sw-card v-if="zeilen.length" title="Online-Zugänge (Lizenzen)" position-identifier="ketteler-lizenzen-bestellung">' +
            '  <ul style="margin:0;padding:0 0 0 18px" data-kt="lizenzen-bestellung">' +
            '    <li v-for="z in zeilen" :key="z.id">{{ z.nummer }} {{ z.label }}: <strong>{{ z.status }}</strong>' +
            '      <span v-if="z.lizenzId">, Lizenz-ID {{ z.lizenzId }}</span><span v-if="z.versuche">, Versuche {{ z.versuche }}</span>' +
            '      <span v-if="z.fehler" style="color:#b7005c">, {{ z.fehler }}</span></li>' +
            '  </ul>' +
            '</sw-card>',
        props: { order: { type: Object, required: true } },
        computed: {
            zeilen: function () {
                var positionen = (this.order && this.order.lineItems) || [];
                var liste = [];
                positionen.forEach(function (p) {
                    var stand = p.customFields && p.customFields.ketteler_lizenz;
                    if (!stand) { return; }
                    liste.push({
                        id: p.id,
                        nummer: (p.payload && p.payload.productNumber) || '',
                        label: p.label,
                        status: NAMEN[stand.status] || stand.status,
                        lizenzId: stand.lizenz_id || null,
                        versuche: stand.versuche || null,
                        fehler: stand.fehler || null
                    });
                });
                return liste;
            }
        }
    });

    Shopware.Component.override('sw-order-detail-general', {
        template: '' +
            '{% block sw_order_detail_general_info_card %}' +
            '{% parent %}' +
            '<ketteler-lizenzen-bestellung v-if="order" :order="order" />' +
            '{% endblock %}'
    });
})();
