/*
 * Erlebniswelten: eigene Blöcke des KETTELER-Verlags (Schaufenster, Kategorien mit Cover-Stapel, Werke, Hinweis mit
 * Zeichen, Drei Beiträge, Nummerierte Liste, Produktreihe). Bewusst ohne Bauschritt geschrieben (kein Webpack),
 * Shopware lädt diese Datei direkt. Jeder Block hat genau ein Element gleichen Namens; dessen Einstellungen beschreibt
 * ein Feldschema (FELDER), aus dem das Einstellungsfenster gebaut wird. Darstellung im Shop:
 * Resources/views/storefront/element/cms-element-ketteler-*.html.twig, Daten lädt Cms/KettelerElementResolver.php.
 *
 * Links speichern „kategorie:<id>“, „produkt:<id>“ oder eine Adresse (/pfad, https://…, mailto:, tel:).
 */
(function () {
    'use strict';

    var Criteria = Shopware.Data.Criteria;

    var FLAECHEN = [{ value: 'sand', label: 'Sand' }, { value: 'rose', label: 'Rosé' }];

    // Feldschema je Element. typ: text | textarea | link | produkt | produkte | kategorie | medium | auswahl | schalter | liste
    var ELEMENTE = {
        schaufenster: {
            label: 'Schaufenster',
            beschreibung: 'Große Kachel in Magenta mit Produkt und Video, dazu zwei Nebenkacheln',
            felder: [
                { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                { name: 'titel', label: 'Überschrift (einzige Hauptüberschrift der Seite)', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'knopfText', label: 'Hauptknopf: Text', typ: 'text' },
                { name: 'knopfLink', label: 'Hauptknopf: Link (ohne Video)', typ: 'link' },
                { name: 'video', label: 'Hauptknopf öffnet Video (MP4 aus den Medien)', typ: 'medium', akzeptiert: 'video/mp4' },
                { name: 'videoPoster', label: 'Video: Vorschaubild', typ: 'medium', akzeptiert: 'image/*' },
                { name: 'videoUntertitel', label: 'Video: Untertitel (WebVTT, .vtt)', typ: 'medium', akzeptiert: 'text/vtt,.vtt' },
                { name: 'nebenLinkText', label: 'Nebenlink: Text', typ: 'text' },
                { name: 'nebenLink', label: 'Nebenlink: Link', typ: 'link' },
                { name: 'produkt', label: 'Produkt (Cover, Name, Preis)', typ: 'produkt' },
                { name: 'preisPraefix', label: 'Vor dem Preis, z. B. „Online ab“', typ: 'text' },
                {
                    name: 'kacheln', label: 'Nebenkacheln', typ: 'liste', max: 2, eintrag: 'Kachel',
                    felder: [
                        { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'linkText', label: 'Linktext', typ: 'text' },
                        { name: 'link', label: 'Link', typ: 'link' },
                        { name: 'flaeche', label: 'Fläche', typ: 'auswahl', optionen: FLAECHEN },
                        { name: 'bild', label: 'Bild (statt Produktcover)', typ: 'medium', akzeptiert: 'image/*' },
                        { name: 'produkte', label: 'Produktcover (eins oder bis zu drei als Stapel)', typ: 'produkte', max: 3 }
                    ]
                }
            ]
        },
        kategorien: {
            label: 'Kategorien mit Cover-Stapel',
            beschreibung: 'Bis zu sechs Kategorien, je mit drei Covern',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'linkText', label: 'Link rechts: Text', typ: 'text' },
                { name: 'link', label: 'Link rechts: Ziel', typ: 'link' },
                {
                    name: 'eintraege', label: 'Kategorien', typ: 'liste', max: 6, eintrag: 'Kategorie',
                    felder: [
                        { name: 'kategorie', label: 'Kategorie', typ: 'kategorie' },
                        { name: 'titel', label: 'Titel (leer: Name der Kategorie)', typ: 'text' },
                        { name: 'untertitel', label: 'Untertitel', typ: 'text' },
                        { name: 'bilder', label: 'Bilder (leer: die ersten drei Produktbilder)', typ: 'medien', max: 3 }
                    ]
                }
            ]
        },
        werke: {
            label: 'Werke',
            beschreibung: 'Redaktionelle Zeilen mit Cover, Text, Formaten und Preis',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                {
                    name: 'eintraege', label: 'Werke', typ: 'liste', max: 8, eintrag: 'Werk',
                    felder: [
                        { name: 'produkt', label: 'Produkt (Cover und „ab“-Preis)', typ: 'produkt' },
                        { name: 'bild', label: 'Coverbild (statt Produktcover)', typ: 'medium', akzeptiert: 'image/*' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'formate', label: 'Formatzeile, z. B. „Online · Print · im Abo“', typ: 'text' },
                        { name: 'preisPraefix', label: 'Vor dem Preis', typ: 'text' },
                        { name: 'linkText', label: 'Linktext', typ: 'text' },
                        { name: 'link', label: 'Link', typ: 'link' }
                    ]
                }
            ]
        },
        hinweis: {
            label: 'Hinweis mit Zeichen',
            beschreibung: 'Großes Zeichen, Haupttext, Unterzeile, Text mit Link',
            felder: [
                { name: 'zeichen', label: 'Zeichen', typ: 'text' },
                { name: 'haupttext', label: 'Haupttext (*Wörter zwischen Sternchen* werden kursiv)', typ: 'textarea' },
                { name: 'unterzeile', label: 'Unterzeile', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                { name: 'linkText', label: 'Linktext', typ: 'text' },
                { name: 'link', label: 'Link', typ: 'link' }
            ]
        },
        beitraege: {
            label: 'Drei Beiträge',
            beschreibung: 'Überschrift und Beiträge auf Sand',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                {
                    name: 'eintraege', label: 'Beiträge', typ: 'liste', max: 3, eintrag: 'Beitrag',
                    felder: [
                        { name: 'dachzeile', label: 'Dachzeile', typ: 'text' },
                        { name: 'titel', label: 'Titel', typ: 'text' },
                        { name: 'text', label: 'Text', typ: 'textarea' },
                        { name: 'link', label: 'Link', typ: 'link' }
                    ]
                }
            ]
        },
        liste: {
            label: 'Nummerierte Liste mit Einleitung',
            beschreibung: 'Überschrift, Text und bis zu acht Einträge',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'text', label: 'Text', typ: 'textarea' },
                {
                    name: 'eintraege', label: 'Einträge', typ: 'liste', max: 8, eintrag: 'Eintrag',
                    felder: [
                        { name: 'text', label: 'Text', typ: 'text' },
                        { name: 'link', label: 'Link', typ: 'link' }
                    ]
                }
            ]
        },
        produktreihe: {
            label: 'Produktreihe',
            beschreibung: 'Produkte mit Cover, Autor, Preis und „In den Warenkorb“',
            felder: [
                { name: 'ueberschrift', label: 'Überschrift', typ: 'text' },
                { name: 'linkText', label: 'Link rechts: Text', typ: 'text' },
                { name: 'link', label: 'Link rechts: Ziel', typ: 'link' },
                { name: 'produkte', label: 'Produkte (Reihenfolge wie gewählt)', typ: 'produkte', max: 10 }
            ]
        }
    };

    function leer(feld) {
        if (feld.typ === 'liste' || feld.typ === 'produkte' || feld.typ === 'medien') {
            return [];
        }
        if (feld.typ === 'schalter') {
            return false;
        }
        if (feld.typ === 'auswahl') {
            return feld.optionen[0].value;
        }
        return null;
    }

    function eintragLeer(liste) {
        var e = {};
        liste.felder.forEach(function (f) { e[f.name] = leer(f); });
        return e;
    }

    // Feld für einen Link: Kategorie, Produkt oder Adresse; gespeichert als Zeichenkette
    Shopware.Component.register('ketteler-link-feld', {
        template: '' +
            '<div class="ketteler-link-feld">' +
            '  <sw-single-select :label="label" :options="arten" :value="art" @update:value="artWechseln" />' +
            '  <sw-entity-single-select v-if="art === \'kategorie\'" entity="category" :value="id" @update:value="setzen(\'kategorie\', $event)" />' +
            '  <sw-entity-single-select v-else-if="art === \'produkt\'" entity="product" :value="id" @update:value="setzen(\'produkt\', $event)" />' +
            '  <sw-text-field v-else placeholder="/pfad oder https://…" :value="adresse" @update:value="$emit(\'update:value\', $event)" />' +
            '</div>',
        props: { value: { type: String, required: false, default: null }, label: { type: String, required: false, default: '' } },
        emits: ['update:value'],
        data: function () {
            return { gewaehlt: null, arten: [{ value: 'kategorie', label: 'Kategorie' }, { value: 'produkt', label: 'Produkt' }, { value: 'adresse', label: 'Adresse' }] };
        },
        computed: {
            art: function () {
                if (this.gewaehlt) { return this.gewaehlt; }
                var m = /^(kategorie|produkt):/.exec(this.value || '');
                return m ? m[1] : (this.value ? 'adresse' : 'kategorie');
            },
            id: function () {
                var m = /^(?:kategorie|produkt):([0-9a-f]{32})$/.exec(this.value || '');
                return m ? m[1] : null;
            },
            adresse: function () {
                return /^(kategorie|produkt):/.test(this.value || '') ? '' : (this.value || '');
            }
        },
        methods: {
            artWechseln: function (art) {
                this.gewaehlt = art;
                this.$emit('update:value', null);
            },
            setzen: function (art, id) {
                this.$emit('update:value', id ? art + ':' + id : null);
            }
        }
    });

    // Ein Feld nach Schema (rekursiv für Listen)
    Shopware.Component.register('ketteler-schema-feld', {
        template: '' +
            '<div class="ketteler-schema-feld" style="margin-bottom:16px">' +
            '  <sw-text-field v-if="feld.typ === \'text\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <sw-textarea-field v-else-if="feld.typ === \'textarea\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <ketteler-link-feld v-else-if="feld.typ === \'link\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <sw-entity-single-select v-else-if="feld.typ === \'produkt\'" :label="feld.label" entity="product" :value="wert" @update:value="setzen" />' +
            '  <sw-entity-multi-id-select v-else-if="feld.typ === \'produkte\'" :label="feld.label" :repository="produktRepo" :ids="wert || []" @update:ids="setzen" />' +
            '  <sw-entity-single-select v-else-if="feld.typ === \'kategorie\'" :label="feld.label" entity="category" :value="wert" @update:value="setzen" />' +
            '  <sw-single-select v-else-if="feld.typ === \'auswahl\'" :label="feld.label" :options="feld.optionen" :value="wert" @update:value="setzen" />' +
            '  <sw-switch-field v-else-if="feld.typ === \'schalter\'" :label="feld.label" :value="wert" @update:value="setzen" />' +
            '  <div v-else-if="feld.typ === \'medium\'">' +
            '    <sw-media-field :label="feld.label" :value="wert" :fileAccept="feld.akzeptiert || \'*/*\'" @update:value="setzen" />' +
            '  </div>' +
            '  <div v-else-if="feld.typ === \'medien\'">' +
            '    <label class="sw-field__label">{{ feld.label }}</label>' +
            '    <div v-for="i in feld.max" :key="i" style="margin-bottom:8px">' +
            '      <sw-media-field :value="(wert || [])[i - 1] || null" fileAccept="image/*" @update:value="medienSetzen(i - 1, $event)" />' +
            '    </div>' +
            '  </div>' +
            '  <div v-else-if="feld.typ === \'liste\'" class="ketteler-schema-liste">' +
            '    <h4 style="margin:8px 0">{{ feld.label }}</h4>' +
            '    <sw-card v-for="(eintrag, i) in (wert || [])" :key="i" :title="feld.eintrag + \' \' + (i + 1)" position-identifier="ketteler-schema-liste">' +
            '      <ketteler-schema-feld v-for="unter in feld.felder" :key="unter.name" :feld="unter" :wert="eintrag[unter.name]" @update:wert="eintragSetzen(i, unter.name, $event)" />' +
            '      <sw-button size="small" @click="verschieben(i, -1)" :disabled="i === 0">Nach oben</sw-button>' +
            '      <sw-button size="small" @click="entfernen(i)">Entfernen</sw-button>' +
            '    </sw-card>' +
            '    <sw-button v-if="(wert || []).length < feld.max" size="small" variant="primary" @click="hinzufuegen">{{ feld.eintrag }} hinzufügen</sw-button>' +
            '  </div>' +
            '</div>',
        inject: ['repositoryFactory'],
        props: { feld: { type: Object, required: true }, wert: { required: false, default: null } },
        emits: ['update:wert'],
        computed: {
            produktRepo: function () { return this.repositoryFactory.create('product'); }
        },
        methods: {
            setzen: function (v) { this.$emit('update:wert', v === undefined ? null : v); },
            medienSetzen: function (i, id) {
                var liste = (this.wert || []).slice();
                liste[i] = id || null;
                this.$emit('update:wert', liste.filter(function (x) { return !!x; }));
            },
            eintragSetzen: function (i, name, v) {
                var liste = (this.wert || []).map(function (e) { return Object.assign({}, e); });
                liste[i][name] = v;
                this.$emit('update:wert', liste);
            },
            hinzufuegen: function () { this.$emit('update:wert', (this.wert || []).concat([eintragLeer(this.feld)])); },
            entfernen: function (i) { var l = (this.wert || []).slice(); l.splice(i, 1); this.$emit('update:wert', l); },
            verschieben: function (i, d) {
                var l = (this.wert || []).slice(); var e = l.splice(i, 1)[0]; l.splice(i + d, 0, e); this.$emit('update:wert', l);
            }
        }
    });

    Object.keys(ELEMENTE).forEach(function (kurz) {
        var def = ELEMENTE[kurz];
        var name = 'ketteler-' + kurz;
        var defaultConfig = {};
        def.felder.forEach(function (f) { defaultConfig[f.name] = { source: 'static', value: leer(f) }; });

        // Element im Editor: kurze Zusammenfassung statt Nachbau der Gestaltung
        Shopware.Component.register('sw-cms-el-' + name, {
            template: '' +
                '<div class="ketteler-cms-el" style="padding:18px 20px;border:1px dashed #d9cdbd;border-radius:8px;background:#fbf8f3;color:#2a2326">' +
                '  <div style="font-size:12px;color:#6b5f63">' + def.label + '</div>' +
                '  <div style="font-family:Georgia,serif;font-size:20px;margin-top:4px">{{ titel }}</div>' +
                '  <div v-if="anzahl !== null" style="font-size:13px;color:#6b5f63;margin-top:4px">{{ anzahl }}</div>' +
                '</div>',
            mixins: [Shopware.Mixin.getByName('cms-element')],
            computed: {
                titel: function () {
                    var c = this.element.config || {};
                    var t = (c.titel && c.titel.value) || (c.ueberschrift && c.ueberschrift.value) || (c.haupttext && c.haupttext.value) || '';
                    return t || '(noch ohne Text)';
                },
                anzahl: function () {
                    var c = this.element.config || {};
                    var l = (c.eintraege && c.eintraege.value) || (c.kacheln && c.kacheln.value) || (c.produkte && c.produkte.value);
                    return Array.isArray(l) ? l.length + ' Einträge' : null;
                }
            },
            created: function () { this.initElementConfig(name); }
        });

        // Einstellungen: Felder nach Schema
        Shopware.Component.register('sw-cms-el-config-' + name, {
            template: '' +
                '<div class="ketteler-cms-el-config">' +
                '  <p style="margin-bottom:16px;color:#6b5f63">' + def.beschreibung + '</p>' +
                '  <ketteler-schema-feld v-for="feld in felder" :key="feld.name" :feld="feld" :wert="element.config[feld.name].value" @update:wert="setzen(feld.name, $event)" />' +
                '</div>',
            mixins: [Shopware.Mixin.getByName('cms-element')],
            data: function () { return { felder: def.felder }; },
            created: function () { this.initElementConfig(name); },
            methods: {
                setzen: function (feld, wert) {
                    this.element.config[feld].value = wert;
                    this.$emit('element-update', this.element);
                }
            }
        });

        Shopware.Component.register('sw-cms-el-preview-' + name, {
            template: '<div style="padding:12px;font-size:13px;color:#2a2326">' + def.label + '</div>'
        });

        Shopware.Service('cmsService').registerCmsElement({
            name: name,
            label: def.label,
            component: 'sw-cms-el-' + name,
            configComponent: 'sw-cms-el-config-' + name,
            previewComponent: 'sw-cms-el-preview-' + name,
            defaultConfig: defaultConfig
        });

        // Block mit genau diesem Element
        Shopware.Component.register('sw-cms-block-' + name, {
            template: '<div class="sw-cms-block-' + name + '"><slot name="inhalt"></slot></div>'
        });
        Shopware.Component.register('sw-cms-preview-' + name, {
            template: '' +
                '<div style="padding:14px 16px;background:#fbf8f3;border:1px solid #e8e0d4;border-radius:6px">' +
                '  <div style="font-family:Georgia,serif;font-size:15px;color:#2a2326">' + def.label + '</div>' +
                '  <div style="font-size:12px;color:#6b5f63;margin-top:2px">' + def.beschreibung + '</div>' +
                '</div>'
        });
        Shopware.Service('cmsService').registerCmsBlock({
            name: name,
            label: 'KETTELER: ' + def.label,
            category: 'text-image',
            component: 'sw-cms-block-' + name,
            previewComponent: 'sw-cms-preview-' + name,
            defaultConfig: { marginBottom: '0', marginTop: '0', marginLeft: '0', marginRight: '0', sizingMode: 'full_width' },
            slots: { inhalt: name }
        });
    });
})();
