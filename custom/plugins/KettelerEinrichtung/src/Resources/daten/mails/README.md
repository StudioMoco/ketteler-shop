# Mailtexte des Shops

Je Mailart ein Ordner mit dem technischen Namen der Shopware-Vorlage: `betreff.txt`, `inhalt.html.twig`, `inhalt.txt.twig`.
`_kopf_fuss` ist Kopf und Fuß aller Mails. `bin/console ketteler:einrichten` (bzw. `make einrichten`) spielt sie ein.

Ton: Sie-Form, freundlich und sachlich, keine Gedankenstriche, in jeder Mail der Kundenservice.
Kundenservice, Servicezeiten und Impressum nach `content/rechtstexte/impressum.md` (Geschäftsführer Josef Singer).

Gestaltung (13.10.2026): Papier-Grund, weiße Karte, Logo oben, Fuß in Sand mit Kundenservice, Servicezeiten, Links auf
AGB, Widerrufsbelehrung, Datenschutzerklärung, Impressum und „Verträge hier kündigen“. Schriften IBM Plex mit Ersatz
(Segoe UI, Arial bzw. Georgia), Akzent Magenta #b7005c. Platzhalter, die `ketteler:einrichten` ersetzt: `{{LOGO}}` (Logo
als eingebettetes Bild, damit es auch hinter dem Passwortschutz von Staging erscheint) und `{{SEO:<Kategorie>}}` (SEO-Pfad
der Kategorie, ohne ID).
