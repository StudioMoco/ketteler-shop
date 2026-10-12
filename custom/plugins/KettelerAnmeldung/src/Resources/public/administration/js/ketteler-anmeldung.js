/*
 * Verwaltung: Karte „Widerruf“ in der Bestellung (Reiter Allgemein). Zeigt die eingegangenen Widerrufe und den Knopf
 * „Widerruf bearbeitet“ (mit Rücknahme). Bewusst ohne Bauschritt geschrieben (kein Webpack), Shopware lädt diese Datei
 * direkt. Speichern über POST /api/_action/ketteler/widerruf/{id}/bearbeitung (Zeit, Bearbeiter, Kennzeichen, Verlauf).
 */
(function () {
    'use strict';

    var AKTIONEN = { bearbeitet: 'als bearbeitet markiert', zurueckgenommen: 'Markierung zurückgenommen', neuer_widerruf: 'neuer Widerruf eingegangen, wieder offen' };

    function datum(wert) {
        return wert ? new Date(wert).toLocaleString('de-DE', { timeZone: 'Europe/Berlin' }) + ' Uhr' : '';
    }

    Shopware.Component.register('ketteler-widerruf-karte', {
        template: '' +
            '<sw-card v-if="widerrufe.length" class="ketteler-widerruf-karte" title="Widerruf" position-identifier="ketteler-widerruf-karte" :isLoading="laedt">' +
            '  <div data-kt="widerruf-stand" style="margin-bottom:16px">' +
            '    <sw-label v-if="stand.bearbeitet" variant="success" appearance="pill">Bearbeitet am {{ datum(stand.zeit) }} von {{ stand.benutzer }}</sw-label>' +
            '    <sw-label v-else variant="warning" appearance="pill">Offen: noch nicht bearbeitet</sw-label>' +
            '  </div>' +
            '  <div v-for="(w, i) in widerrufe" :key="i" style="margin-bottom:12px">' +
            '    <strong>Eingang {{ datum(w.eingang) }}</strong>: {{ w.alle ? \'alle Positionen\' : \'einzelne Positionen\' }}' +
            '    <ul style="margin:4px 0 0 20px"><li v-for="p in (w.positionen || [])">{{ p.nummer }} {{ p.label }}<span v-if="p.online"> (Online-Zugang)</span></li></ul>' +
            '    <div v-if="w.online_mit_zustimmung" style="margin-top:4px">Online-Zugang mit Zustimmung zum sofortigen Beginn: Widerrufsrecht prüfen.</div>' +
            '    <div v-if="w.kommentar" style="margin-top:4px;white-space:pre-wrap">Kommentar: {{ w.kommentar }}</div>' +
            '  </div>' +
            '  <sw-button v-if="!stand.bearbeitet" variant="primary" :disabled="laedt || !darf" data-kt="widerruf-bearbeitet" @click="setzen(true)">Widerruf bearbeitet</sw-button>' +
            '  <sw-button v-else :disabled="laedt || !darf" data-kt="widerruf-zuruecknehmen" @click="setzen(false)">Markierung zurücknehmen</sw-button>' +
            '  <div v-if="verlauf.length" style="margin-top:16px">' +
            '    <strong>Verlauf</strong>' +
            '    <ul style="margin:4px 0 0 20px"><li v-for="(v, i) in verlauf" :key="i">{{ datum(v.zeit) }}: {{ aktion(v.aktion) }} ({{ v.benutzer }})</li></ul>' +
            '  </div>' +
            '</sw-card>',
        inject: ['acl', 'loginService'],
        props: { order: { type: Object, required: true } },
        data: function () {
            return { laedt: false, eigen: null };
        },
        computed: {
            felder: function () {
                return (this.order && this.order.customFields) || {};
            },
            widerrufe: function () {
                return Array.isArray(this.felder.ketteler_widerrufe) ? this.felder.ketteler_widerrufe : [];
            },
            stand: function () {
                return this.eigen || this.felder.ketteler_widerruf_bearbeitung || { bearbeitet: false, verlauf: [] };
            },
            verlauf: function () {
                return (this.stand.verlauf || []).slice().reverse();
            },
            darf: function () {
                return this.acl.can('order.editor');
            }
        },
        methods: {
            datum: datum,
            aktion: function (a) {
                return AKTIONEN[a] || a;
            },
            setzen: function (bearbeitet) {
                var self = this;
                this.laedt = true;
                Shopware.Application.getContainer('init').httpClient.post(
                    '/_action/ketteler/widerruf/' + this.order.id + '/bearbeitung',
                    { bearbeitet: bearbeitet },
                    { headers: { Authorization: 'Bearer ' + this.loginService.getToken() } }
                ).then(function (antwort) {
                    self.eigen = antwort.data;
                    self.$emit('geaendert');
                }).catch(function (fehler) {
                    var text = (fehler.response && fehler.response.data && fehler.response.data.fehler) || 'Speichern fehlgeschlagen.';
                    Shopware.State.dispatch('notification/createNotification', { variant: 'error', title: 'Widerruf', message: text });
                }).finally(function () {
                    self.laedt = false;
                });
            }
        }
    });

    Shopware.Component.override('sw-order-detail-general', {
        template: '' +
            '{% block sw_order_detail_general_info_card %}' +
            '{% parent %}' +
            '<ketteler-widerruf-karte v-if="order" :order="order" @geaendert="kettelerNeuLaden" />' +
            '{% endblock %}',
        methods: {
            kettelerNeuLaden: function () {
                // Kennzeichen in „Allgemein“ aktualisieren
                if (typeof this.swOrderDetailOnReloadEntityData === 'function') {
                    this.swOrderDetailOnReloadEntityData();
                }
            }
        }
    });
})();
