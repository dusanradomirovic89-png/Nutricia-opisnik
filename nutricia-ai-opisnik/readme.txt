=== Nutricia AI Opisnik ===
Contributors: nutriciamarket
Tags: woocommerce, ai, seo, cro, openrouter, rank math, product descriptions
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatska SEO i CRO optimizacija WooCommerce proizvoda pomoću AI-a (OpenRouter).

== Description ==

Nutricia AI Opisnik šalje sve dostupne podatke o proizvodu (naslov, kratki i
dugački opis, kompletnu hijerarhiju kategorija, meta polje sa sastavom, tagove
i po potrebi sliku) LLM-u preko OpenRouter-a, a zatim automatski popunjava:

* CRO optimizovan **kratki opis** (prodajni, sažet)
* SEO optimizovan **dugački opis** (kvalitetan HTML)
* **Alt tekst** glavne slike
* do 5 **tagova** (koristi postojeće i dodaje nove relevantne)
* **Rank Math meta naslov i opis** (+ opciono fokus ključna reč)

= Ključne mogućnosti =

* Podešavanja u WP adminu pod **Alati (Tools)**
* Sakriven OpenRouter API ključ
* Izbor modela (uključujući vision modele)
* Cron interval: 1, 2, 5, 10, 30 minuta, 1 sat, 2 sata — obrađuje po jedan proizvod
* Vision režim: nikad / uvek / automatski (kada proizvod ima malo teksta)
* Ručna obrada pojedinačnog proizvoda i grupna (bulk) obrada
* Kolona statusa i meta box na stranici proizvoda
* Dnevnik aktivnosti, statistika i dugme "Testiraj vezu"
* Kontekst sajta koji se šalje uz svaki proizvod

== Installation ==

1. Postavite folder `nutricia-ai-opisnik` u `/wp-content/plugins/`.
2. Aktivirajte plugin kroz meni "Plugins" u WordPress-u.
3. Idite na **Alati → Nutricia AI Opisnik** i unesite OpenRouter API ključ, model i interval.
4. Uključite automatsku obradu ili pokrenite obradu ručno.

== Frequently Asked Questions ==

= Da li mi treba Rank Math? =
Ne obavezno. Meta naslov i opis se upisuju u `rank_math_title` i
`rank_math_description`, koje Rank Math koristi kada je aktivan.

= Kako se bira koji proizvod se obrađuje? =
Cron uzima prvi neobrađeni proizvod (ili onaj u redu), obradi ga i označi kao
"Obrađeno". Proizvodi sa greškom se ponavljaju do zadatog broja pokušaja.

= Da li WordPress cron radi pouzdano? =
WP cron zavisi od poseta sajtu. Za precizne intervale podesite pravi system
cron koji poziva `wp-cron.php`.

== Changelog ==

= 1.0.0 =
* Prva verzija.
