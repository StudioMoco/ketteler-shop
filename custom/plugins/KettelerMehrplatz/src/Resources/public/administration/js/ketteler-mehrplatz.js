/*
 * Verwaltung: Kunden > Mehrplatz-Anfragen (Liste und Ansicht). Bewusst ohne Bauschritt geschrieben (kein Webpack),
 * Shopware lädt diese Datei direkt. Nur lesen; Bearbeiten geschieht per E-Mail mit der anfragenden Einrichtung.
 */
(function () {
    'use strict';

    var Criteria = Shopware.Data.Criteria;
    var ENTITY = 'ketteler_mehrplatz_anfrage';

    Shopware.Component.register('ketteler-mehrplatz-liste', {
        template: '' +
            '<sw-page class="ketteler-mehrplatz-liste">' +
            '  <template #smart-bar-header><h2>Mehrplatz-Anfragen <span v-if="total">({{ total }})</span></h2></template>' +
            '  <template #content>' +
            '    <sw-entity-listing v-if="anfragen" :items="anfragen" :repository="repository" :columns="spalten"' +
            '        :showSelection="false" :showActions="true" :isLoading="laedt" detailRoute="ketteler.mehrplatz.detail"' +
            '        :allowInlineEdit="false" :allowDelete="false">' +
            '      <template #column-createdAt="{ item }">{{ datum(item.createdAt) }}</template>' +
            '    </sw-entity-listing>' +
            '    <sw-empty-state v-else-if="!laedt" title="Noch keine Anfragen" icon="regular-users"></sw-empty-state>' +
            '  </template>' +
            '</sw-page>',
        inject: ['repositoryFactory'],
        data: function () {
            return { anfragen: null, laedt: true, total: 0 };
        },
        computed: {
            repository: function () {
                return this.repositoryFactory.create(ENTITY);
            },
            spalten: function () {
                return [
                    { property: 'createdAt', label: 'Eingang', allowResize: true },
                    { property: 'einrichtung', label: 'Einrichtung', routerLink: 'ketteler.mehrplatz.detail', primary: true, allowResize: true },
                    { property: 'ansprechperson', label: 'Ansprechperson', allowResize: true },
                    { property: 'anzahl', label: 'Lizenzen', align: 'right' },
                    { property: 'werk', label: 'Werk' },
                    { property: 'email', label: 'E-Mail', allowResize: true }
                ];
            }
        },
        created: function () {
            var self = this;
            var criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            this.repository.search(criteria).then(function (ergebnis) {
                self.anfragen = ergebnis.total ? ergebnis : null;
                self.total = ergebnis.total;
                self.laedt = false;
            });
        },
        methods: {
            datum: function (wert) {
                return wert ? new Date(wert).toLocaleString('de-DE') : '';
            }
        }
    });

    Shopware.Component.register('ketteler-mehrplatz-detail', {
        template: '' +
            '<sw-page class="ketteler-mehrplatz-detail">' +
            '  <template #smart-bar-header><h2 v-if="anfrage">{{ anfrage.einrichtung }}</h2></template>' +
            '  <template #content>' +
            '    <sw-card-view>' +
            '      <sw-card v-if="anfrage" title="Anfrage mehrere Lizenzen" :isLoading="!anfrage">' +
            '        <dl class="ketteler-mehrplatz-felder">' +
            '          <template v-for="feld in felder" :key="feld.name">' +
            '            <dt><strong>{{ feld.label }}</strong></dt><dd style="margin:0 0 12px;white-space:pre-wrap">{{ wert(feld.name) }}</dd>' +
            '          </template>' +
            '        </dl>' +
            '        <a v-if="anfrage.email" class="sw-button sw-button--primary" :href="\'mailto:\' + anfrage.email">Antworten</a>' +
            '      </sw-card>' +
            '    </sw-card-view>' +
            '  </template>' +
            '</sw-page>',
        inject: ['repositoryFactory'],
        data: function () {
            return {
                anfrage: null,
                felder: [
                    { name: 'createdAt', label: 'Eingang' },
                    { name: 'einrichtung', label: 'Einrichtung' },
                    { name: 'ansprechperson', label: 'Ansprechperson' },
                    { name: 'email', label: 'E-Mail-Adresse' },
                    { name: 'telefon', label: 'Telefon' },
                    { name: 'anzahl', label: 'Anzahl der Lizenzen' },
                    { name: 'werk', label: 'Werk' },
                    { name: 'artikel', label: 'Artikel' },
                    { name: 'nachricht', label: 'Nachricht' }
                ]
            };
        },
        created: function () {
            var self = this;
            this.repositoryFactory.create(ENTITY).get(this.$route.params.id).then(function (anfrage) {
                self.anfrage = anfrage;
            });
        },
        methods: {
            wert: function (name) {
                var w = this.anfrage[name];
                if (name === 'createdAt' && w) {
                    return new Date(w).toLocaleString('de-DE');
                }
                return w === null || w === undefined || w === '' ? '(keine Angabe)' : w;
            }
        }
    });

    Shopware.Module.register('ketteler-mehrplatz', {
        type: 'plugin',
        name: 'ketteler-mehrplatz',
        title: 'Mehrplatz-Anfragen',
        description: 'Anfragen für mehrere Lizenzen aus dem Shop',
        color: '#b7005c',
        icon: 'regular-users',
        entity: ENTITY,
        routes: {
            liste: { component: 'ketteler-mehrplatz-liste', path: 'liste' },
            detail: { component: 'ketteler-mehrplatz-detail', path: 'detail/:id', meta: { parentPath: 'ketteler.mehrplatz.liste' } }
        },
        navigation: [{
            id: 'ketteler-mehrplatz',
            label: 'Mehrplatz-Anfragen',
            color: '#b7005c',
            path: 'ketteler.mehrplatz.liste',
            icon: 'regular-users',
            parent: 'sw-customer',
            position: 100
        }]
    });
})();
