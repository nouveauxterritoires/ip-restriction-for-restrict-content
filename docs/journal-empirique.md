# Journal empirique

Chaque comportement surprenant d'une dépendance ou de l'outillage est consigné
ici **avec la preuve qui l'établit** : commande exécutée, sortie obtenue,
fichier et ligne, date, version. C'est ce qui évite qu'un successeur
« simplifie » une précaution qu'il ne comprend pas.

Chaque entrée est référencée depuis le code qui la contourne.

Sauf mention contraire, les chemins `core/…` et `pro/…` se lisent depuis
`~/WORK/rcp-stripe-sepa/ressources/restrict-content-pro/` (Restrict Content Pro
v3.5.51).

---

## JE-001 — Un processus PHPUnit peut sortir en succès sans avoir exécuté les tests

**Date** : 22 septembre 2026 · **Contexte** : mise en place de l'intégration
continue.

Deux situations distinctes produisent le même faux signal : le code de sortie
vaut 0, et rien n'indique que quoi que ce soit ait été testé.

**Première situation — le processus meurt en silence.** Chaque fichier livré
commence par `defined( 'ABSPATH' ) || exit;` (SEC-15). Dans la suite `unit`,
WordPress n'est pas chargé : sans définition préalable d'`ABSPATH`, la première
inclusion d'une classe du plugin appelle `exit`. Le processus s'arrête, PHPUnit
n'écrit aucun récapitulatif, et le code de sortie est 0.

**Seconde situation — la suite est vide.** Une suite déclarée mais dépourvue de
tests sort en succès avec « No tests executed! ». Le signal est donc faux tant
que la suite n'est pas peuplée.

**Conséquences, toutes en place au lot 0.**

1. `tests/bootstrap.php` définit `ABSPATH` avant tout chargement dans la suite
   `unit`, vers un chemin qui ne désigne rien d'exécutable — la suite `unit` ne
   charge jamais WordPress, elle le remplace par Brain Monkey
   (`tests/bootstrap.php:89-102`).
2. `bin/run-suite.sh` ne se contente pas du code de sortie : il exige que la
   sortie porte un récapitulatif PHPUnit (`^OK`, `^OK, but`, `^Tests:` ou
   `^No tests executed!`) et échoue sinon, avec un message qui nomme la cause
   probable (`bin/run-suite.sh:27-43`). `bin/coverage.sh:39` applique le même
   garde.
3. Les suites `contract`, `matching` et `access` ne sont pas déclarées comme
   jobs d'intégration continue tant qu'elles sont vides
   (`.github/workflows/ci.yml:195`).

Les deux gardes ne se recouvrent pas : le premier porte sur la fin du
processus, le second sur la présence de tests. Il ne faut pas les confondre —
« No tests executed! » est un récapitulatif comme un autre pour le premier.

---

## JE-002 — La bibliothèque de tests de WordPress ne crée pas les tables de la dépendance

**Date** : 22 septembre 2026 · **Contexte** : amorçage des suites qui chargent
un WordPress réel.

La dépendance crée ses tables à l'activation du plugin et sur `admin_init`. La
bibliothèque de tests de WordPress ne fait ni l'un ni l'autre. Sans
intervention, toute création de niveau d'adhésion échoue avec
« level_not_added », et la table de métadonnées de niveau reste absente — ce qui
condamne d'avance les suites `contract`, `matching` et `access`, qui ont toutes
besoin de niveaux réels.

L'échec se manifeste loin de sa cause : le message parle d'un niveau qui n'a pas
pu être ajouté, jamais d'une table manquante.

**Conséquence** : `tests/bootstrap.php:169-213` sollicite directement les
interfaces de table de chaque composant enregistré, plutôt que de déclencher
`do_action( 'admin_init' )` qui entraînerait tout le reste de l'initialisation
de l'administration. Le registre des composants est **parcouru** plutôt qu'une
liste écrite à la main : une liste oubliée laisserait une table absente, et
l'erreur reparaîtrait sous son déguisement habituel.

Le DDL n'étant pas transactionnel, les tables survivent au rollback opéré entre
deux tests : la création n'a donc lieu qu'une fois, à l'amorçage.

---

## JE-003 — Restrict Content charge deux bases de code mutuellement exclusives, et la branche historique n'expose aucune fonction `rcp_*`

**Date** : 22 septembre 2026 · **Version constatée** : Restrict Content 4.0.4 ·
**Contexte** : exécution de la suite `integration` dans la pile Docker du
projet, puis lecture de la source.

**Source de la preuve.** La variante gratuite est lisible en local sous
`~/WORK/ressources-restrict-content/restrict-content/` (v4.0.4, `core/` 6,8 Mo,
`legacy/` 688 Ko). Tous les numéros de ligne de cette entrée y sont vérifiés
directement. Lire cette source ne contrevient pas au développement en salle
blanche : le §0.3 du cahier des charges interdit de lire le code de l'**add-on
commercial** IP Restriction, pas celui de la dépendance elle-même — dont la
lecture est au contraire la méthode prescrite par les conventions §11.

**Fait annexe, mais parlant.** L'en-tête de `restrictcontent.php` (lignes 3 et 6)
annonce `Plugin Name: Kadence Memberships`, version 4.0.4 — et son écran de
réglages s'intitule « Kadence Memberships Pro Settings »
(`core/includes/admin/admin-pages.php:28`). Le plugin dont on dépend ne porte
donc même pas le nom sous lequel on le cherche. Une détection par nom de plugin
aurait échoué avant même d'atteindre la question des branches.

**C'est le constat le plus lourd de conséquences du projet.** Il ne porte pas
sur un détail d'outillage : il détermine si le plugin peut fonctionner du tout
sur une installation donnée.

### Le fait

Le plugin `restrict-content` embarque deux bases de code distinctes et n'en
charge **qu'une**.

| Branche | Taille | API moderne `rcp_*` |
|---|---|---|
| `legacy/` | 716 Ko | **aucune** des fonctions vérifiées n'y est définie |
| `core/` | 7,3 Mo | toutes y sont définies |

Fonctions sondées, par `grep -rl "function <nom>"` dans chaque branche de
l'installation réelle :

| Fonction | `legacy/` | `core/` |
|---|---|---|
| `rcp_get_membership_levels` | 0 | 1 |
| `rcp_get_discounts` | 0 | 2 |
| `rcp_validate_discount` | 0 | 4 |
| `rcp_is_active` | 0 | 1 |
| `rcp_get_customer_by_user_id` | 0 | 1 |

Le choix initial est fait par `RC_Requirements_Check::load()`
(`restrictcontent.php`, lignes 186-215) et **persisté** dans l'option
`restrict_content_chosen_version`. La valeur enregistrée prime ensuite à chaque
amorçage (lignes 189-199) :

```php
if ( get_option('restrict_content_chosen_version') ) {
    // la valeur enregistrée prime
} else {
    if ( get_option('rc_settings')
        || $this->restrict_content_check_posts_for_meta()
        || $this->restrict_content_check_posts_for_shortcode() ) {
        update_option('restrict_content_chosen_version', 'legacy');
        $this->load_legacy_restrict_content();
    } else {
        update_option('restrict_content_chosen_version', '3.0');
        $this->load_restrict_content_3();
    }
}
```

### Le choix n'est pas figé : il est réversible en cours de vie du site

C'est la correction la plus importante à apporter à une première lecture de
`load()`. La valeur de l'option n'est pas une propriété de l'installation, fixée
une fois pour toutes à la première activation : c'est un **état mutable**, que
l'administrateur peut retourner à tout moment depuis l'administration.

`restrictcontent.php` expose un gestionnaire AJAX, `rc_process_legacy_switch()`
(ligne 676), enregistré sur `wp_ajax_rc_process_legacy_switch` (ligne 731), qui
bascule l'option d'une branche à l'autre :

```php
if ( get_option('restrict_content_chosen_version') == 'legacy' ) {
    $redirectUrl = admin_url('admin.php?page=restrict-content-settings');
    update_option('restrict_content_chosen_version', '3.0');   // ligne 695
    // …
} else {
    $redirectUrl = admin_url('admin.php?page=rcp-members');
    update_option('restrict_content_chosen_version', 'legacy'); // ligne 706
    // …
}
```

| Élément | Ligne |
|---|---|
| `function rc_process_legacy_switch()` | 676 |
| Capacité exigée : `current_user_can( 'manage_options' )` | 680 |
| Nonce exigé : `rc_process_legacy_nonce` | 681 |
| Bascule vers la branche moderne | 695 |
| Bascule vers la branche historique | 706 |
| Troisième écriture, lorsque l'option est absente | 718 |
| Enregistrement du gestionnaire AJAX | 731 |
| Émission du nonce dans le formulaire d'administration | 743 |
| Lecture de l'option pour décider d'un affichage | 768 |

**La bascule est protégée par une capacité et un nonce : ce n'est pas une
faille.** C'est une fonctionnalité, et c'est précisément pour cela qu'il faut en
tenir compte : elle sera utilisée.

**Ce que cela impose.** Un plugin peut fonctionner correctement pendant des mois,
puis cesser de fonctionner parce qu'un administrateur a basculé sur la branche
historique depuis un écran. Trois conséquences, portées en exigences au cahier
des charges §5.2 sous les références P-01 à P-04 :

| Conséquence | Exigence |
|---|---|
| Le résultat de la sonde de capacités ne doit **jamais** être mémorisé au-delà de la requête — ni en option, ni en métadonnée, ni en transient, ni décidé à l'activation | P-01, P-02 |
| Le contrôle des prérequis à chaque requête, sur `plugins_loaded`, cesse d'être un détail d'implémentation pour devenir une **propriété à préserver**, vérifiée par un test | P-03 |
| L'avis d'administration prend tout son sens : celui qui vient de basculer est exactement celui qui peut revenir en arrière, et il a besoin qu'on lui dise que sa bascule a désactivé ce plugin | P-04, E-05 |

Un plugin qui écrirait « la dépendance est présente » à l'activation se
tromperait dès la première bascule, et se tromperait **silencieusement** : il
continuerait d'enregistrer ses filtres sur une API absente, ce qui produit une
erreur fatale au premier appel — exactement ce que le §5.3 interdit.

### Le défaut amont qui fausse le routage

`restrict_content_check_posts_for_shortcode()` (`restrictcontent.php`, lignes
243-263) émet une requête SQL invalide :

```sql
SELECT * FROM wp_posts WHERE
              post_content CONTAINS '[restrict]' OR
              post_content CONTAINS '[not_logged_in]' OR
              post_content CONTAINS '[login_form]' OR
              post_content CONTAINS '[register_form]' OR
              LIMIT 1
```

Deux erreurs : `CONTAINS` n'est pas un opérateur MySQL — la comparaison de
sous-chaîne s'écrit `LIKE '%…%'` — et un `OR` orphelin précède le `LIMIT`. La
requête échoue toujours, `$wpdb->get_results()` renvoie un tableau vide, et la
méthode renvoie invariablement `false`.

Trace observée à l'amorçage de la suite `integration`, avec `WP_DEBUG` actif :

```
WordPress database error You have an error in your SQL syntax […] near
'CONTAINS '[restrict]' OR post_content CONTAINS '[not_l' at line 2
[…] RC_Requirements_Check->__construct, RC_Requirements_Check->load,
RC_Requirements_Check->restrict_content_check_posts_for_shortcode
```

Conséquence : un site dont le seul signe d'ancienneté est un code court dans le
contenu d'un article n'est pas détecté comme ancien, et bascule sur la branche
moderne.

### Ce que cela impose à ce plugin

| Réf. | Conséquence |
|---|---|
| 1 | Sur un site où `restrict_content_chosen_version` vaut `legacy`, **ce plugin ne peut pas fonctionner** : l'API dont il dépend n'existe pas dans le processus. Ce n'est pas un cas théorique — c'est le sort de toute installation antérieure portant l'option `rc_settings` ou une méta `rcp_user_level`, **et de toute installation dont l'administrateur a basculé** |
| 2 | L'avis de prérequis doit **nommer cette cause précise**. Dire « Restrict Content absent » à un administrateur dont le plugin est visiblement actif est incompréhensible. La formulation passe par la capacité : « Restrict Content est actif mais charge sa base de code historique, qui n'expose pas l'API nécessaire. » Cahier des charges §5.3 |
| 3 | **La détection par capacité n'est pas une précaution de style, c'est la seule qui marche.** Ni le nom du plugin — qui est « Kadence Memberships » —, ni son numéro de version, ni la constante `RC_PLUGIN_VERSION` ne disent quelle branche est en mémoire. Seule la présence effective des fonctions appelées le dit, **et seulement pour la requête en cours**. C'est la validation empirique de la règle posée par les conventions de développement §2 |
| 4 | Le défaut SQL peut être corrigé en amont à tout moment, ce qui **changerait le routage** des sites installés après le correctif. Aucun code de ce plugin ne doit dépendre de ce comportement |
| 5 | La suite `contract` vérifie que la branche chargée est bien la moderne, et échoue **de façon diagnosticable** — plutôt que de laisser une suite métier échouer plus loin sur une fonction absente |
| 6 | Le résultat de la sonde n'est jamais mémorisé au-delà de la requête (P-01 à P-04), parce que la branche chargée peut changer entre deux requêtes |

Ce constat est la raison pour laquelle `Plugin::from_environment()`
(`src/Plugin.php`) sonde `rcp_get_membership_levels()` et non un nom de plugin,
depuis le lot 0.

### Le défaut SQL, vérifié dans la source libre

`restrict_content_check_posts_for_shortcode()` est déclarée à la ligne 243 de
`restrictcontent.php`, et les quatre `CONTAINS` fautifs sont aux lignes 251 à
254. Le constat du §« défaut amont » est donc établi sur la variante gratuite
elle-même, et non par analogie.

### Ce qui reste à vérifier

Porté au cahier des charges §15.2 : V-10, V-11 et V-13. Les points V-07 et V-12
sont **levés** par la disponibilité locale de la source.

---

## JE-004 — `wp i18n make-pot` produit un diff à chaque exécution

**Date** : 22 septembre 2026 · **Version** : WP-CLI 2.12.0.

L'en-tête du `.pot` porte `POT-Creation-Date`, réécrit à chaque génération :

```
$ head -15 languages/ip-restriction-for-restrict-content.pot | grep POT-Creation
"POT-Creation-Date: 2026-09-22T10:12:52+00:00\n"
```

Un job qui vérifierait la fraîcheur du `.pot` par `git diff --quiet` échouerait
donc sur **toute** exécution, y compris quand aucune chaîne traduisible n'a
changé — ce qui apprend vite à ignorer le job.

**Conséquence** : le job `i18n` régénère dans un fichier temporaire et compare
en retirant la ligne datée (`.github/workflows/ci.yml:217-222`). Seul le contenu
traduisible est comparé.

---

## JE-005 — `rcp_get_ip()` fait confiance à des en-têtes que le visiteur contrôle

**Date** : 22 septembre 2026 · **Version** : Restrict Content Pro 3.5.51,
fonction présente à l'identique dans la variante gratuite
(`restrict-content.stub.php:28530`).

`rcp_get_ip()` (`core/includes/misc-functions.php:731`) retient, dans cet
ordre :

| Ordre | Source | Ligne | Falsifiable par le client |
|---|---|---|---|
| 1 | `$_SERVER['HTTP_CLIENT_IP']` | 734 | **oui** |
| 2 | Premier élément de `$_SERVER['HTTP_X_FORWARDED_FOR']` | 737-742 | **oui** |
| 3 | `$_SERVER['REMOTE_ADDR']` | 743 | non |

Sur un site sans proxy, un visiteur qui envoie `Client-Ip: 192.0.2.10` fait dire
à `rcp_get_ip()` qu'il vient de `192.0.2.10`. Seul `REMOTE_ADDR` est renseigné
par le serveur web à partir de la connexion TCP.

Le choix de l'ordre 2 est aggravant en soi : `X-Forwarded-For` s'accroît **par
la gauche** à chaque saut. Retenir le premier élément, c'est retenir exactement
la valeur qu'un client malveillant aurait injectée.

**Conséquence** : ce plugin n'utilise pas `rcp_get_ip()` comme source de
décision, et ne filtre pas `rcp_get_ip` pour le corriger — un autre code peut
dépendre de son comportement actuel, et le corriger globalement le changerait
sans que ce code le sache. Il résout l'adresse pour lui-même, selon R-01 à R-07
du cahier des charges §4.7, et cette résolution porte les exigences SEC-07 à
SEC-09.

La fonction expose un filtre `rcp_get_ip` en sortie
(`core/includes/misc-functions.php:755`). Le tenir pour un point de correction
serait une erreur d'appréciation : un filtre appliqué après coup ne répare pas
un ordre de priorité fautif pour les autres consommateurs, il les fait
seulement dépendre de nous.

---

## JE-006 — Le noyau expose `rcp_member_can_access` délibérément pour cet usage

**Date** : 22 septembre 2026 · **Version** : 3.5.51.

`rcp_user_can_access()` (`core/includes/member-functions.php:70`) traite
séparément le visiteur sans enregistrement client et le visiteur qui en a un.
Sur le premier chemin, juste avant d'appliquer le filtre, le noyau porte ce
commentaire (`core/includes/member-functions.php:100`) :

> « We need to apply the filter here so that it can be used on logged-out
> users, like in IP Restriction. »

C'est la preuve la plus forte disponible que le point d'accroche de FNC-05 n'est
pas un détournement : le noyau **libre** applique ce filtre sur le chemin
déconnecté pour cet usage précis.

Le filtre est appliqué à cinq endroits supplémentaires sur le chemin connecté
(`core/includes/customers/class-rcp-customer.php:772, 777, 786, 791, 806`) et à
trois sur le chemin historique (`core/includes/class-rcp-member.php:970, 975,
987`). Le noyau le consomme lui-même pour une intégration tierce
(`core/includes/integrations/class-rcp-wp-approve-user.php:38`), ce qui le rend
difficile à retirer sans casser son propre code.

**Conséquence** : c'est l'accroche retenue au cahier des charges §5.1. Elle
reste établie **par l'exécution** dans la suite `contract`, et contre la
variante gratuite en particulier — le commentaire est dans `core/`, donc
partagé, mais un stub ne porte pas de corps (V-07).

---

## JE-007 — Un `die()` pendant un test arrête le processus en silence, avec le code 0

**Date** : 22 septembre 2026 · **Contexte** : conception de `bin/run-suite.sh`.

La dépendance émet des `die()`, notamment depuis `wp_send_json_error()`, que le
traitement du formulaire d'inscription appelle
(`core/includes/registration-functions.php:34, 51, 155`). Atteint pendant un
test, ce `die()` arrête le processus PHP **en silence et avec le code de sortie
0**. PHPUnit n'écrit alors aucun récapitulatif, et l'intégration continue lit un
succès là où la moitié des tests n'a pas été exécutée.

Le point mérite d'être souligné parce qu'il frappera précisément la suite
`access`, dont tous les tests de blocage d'inscription traversent ce chemin.

**Conséquence** : `bin/run-suite.sh` exige le récapitulatif final plutôt que de
se fier au code de sortie, et nomme la cause probable dans son message d'échec
(`bin/run-suite.sh:34-42`). Les tests d'inscription devront en outre intercepter
la terminaison plutôt que de la subir — c'est un point de conception de la
suite `access`, pas une fatalité.

---

## JE-008 — `rcp_user_can_access_term()` n'applique aucun filtre

**Date** : 22 septembre 2026 · **Version** : 3.5.51.

Contrairement à `rcp_user_can_access()`, la fonction qui décide l'accès à un
**terme** de taxonomie ne passe par aucun point d'accroche
(`core/includes/member-functions.php:134-175`). Elle renvoie sa décision
directement, et renvoie même `false` sans filtre lorsqu'aucun enregistrement
client ne correspond à l'utilisateur (lignes 148-153).

Il est donc **impossible** d'accorder l'accès à un contenu restreint par
catégorie ou par étiquette en filtrant la décision.

La seule accroche disponible est en amont : `rcp_get_term_restrictions`
(`core/includes/misc-functions.php:998`) filtre les **restrictions** du terme,
pas la décision. Renvoyer un tableau vide neutralise la restriction pour la
requête en cours, ce qui est un instrument plus grossier : il modifie une donnée
que d'autres codes peuvent lire pendant la même requête.

**Conséquence** : cahier des charges §5.1.1. Les restrictions de taxonomie sont
traitées au lot 9, par `rcp_get_term_restrictions` et sous trois garde-fous, et
l'absence d'effet indirect est portée en V-06. La suite `contract` établit cette
absence de filtre par un test qui la constate : le jour où la dépendance
ajouterait enfin un point d'accroche sur la décision, ce test échouera, et ce
sera une bonne nouvelle à traiter.

---

## JE-009 — Le formulaire d'inscription répond en JSON, pas par un rechargement

**Date** : 22 septembre 2026 · **Version** : 3.5.51.

La documentation de l'add-on de référence ne précise pas si le message d'erreur
de blocage apparaît par rechargement de la page ou en place. La lecture du code
tranche : `rcp_process_registration()` collecte les erreurs par `rcp_errors()`,
puis les renvoie par `wp_send_json_error()` avec
`rcp_get_error_messages_html( 'register' )`
(`core/includes/registration-functions.php:151-166`). Le message apparaît donc
dans le formulaire, sans rechargement.

Le noyau consomme lui-même l'action `rcp_form_errors`
(`core/includes/registration-functions.php:148`) pour valider son captcha
(`core/includes/captcha-functions.php:157`) : le précédent d'usage est établi.

**Conséquence** : le blocage d'inscription se branche sur `rcp_form_errors` et
ajoute une erreur à `rcp_errors()`, sans rien rendre lui-même. Cahier des
charges §7.5 et FNC-07.

---

## JE-010 — Sans `$post` global, `rcp_user_can_access()` accorde l'accès

**Date** : 22 septembre 2026 · **Version** : 3.5.51.

Appelée sans identifiant de contenu et hors d'une boucle, la fonction ne trouve
pas de `$post` global et renvoie `true` sans consulter la moindre restriction
(`core/includes/member-functions.php:78-86`), avec ce commentaire du noyau :
« If we can't find a global `$post` object, assume the user can access the
page. »

Le comportement n'est pas anormal — c'est un choix défendable pour un contexte
indéterminé — mais il est piégeux pour un test : une assertion d'accès refusé
écrite sans identifiant explicite passerait au vert pour une raison sans rapport
avec ce qu'elle prétend vérifier.

**Conséquence** : tout test des suites `access` et `contract` passe un
identifiant de contenu explicite. Aucune assertion d'accès ne repose sur le
`$post` global.

---

## JE-011 — L'add-on de référence est absent du checkout, et sa documentation n'est pas datable

**Date** : 22 septembre 2026.

Le checkout de Restrict Content Pro v3.5.51 disponible localement ne contient
pas l'add-on IP Restriction : son répertoire `pro/addons/` ne contient que
`custom-redirects` et `index.php`. Le développement en salle blanche (cahier des
charges §0.3) est donc aussi un fait matériel, pas seulement une règle.

Par ailleurs, aucune des pages de documentation consultées ne porte de numéro de
version ni de date de mise à jour : leur fraîcheur n'est pas datable. L'annonce
d'origine de l'add-on renvoyait un code HTTP 410 au moment de la recherche, et
son contenu n'a pas été récupéré.

**Conséquence** : l'annexe A du cahier des charges est une référence
fonctionnelle, pas une spécification, et l'annexe B énumère explicitement ce que
la source **ne dit pas**, pour qu'aucun silence ne soit lu comme un accord.
C'est aussi pourquoi V-02 et V-03 restent ouverts par construction.

---

## JE-012 — `phpcov merge` sort en succès sans écrire aucun rapport

**Date** : 22 septembre 2026 · **Contexte** : fusion des mesures de couverture.

`phpcov merge` était l'outil prévu par le cahier des charges §11.6. Sur cette
combinaison de versions, il sort avec le code 0 sans écrire le moindre rapport
et sans le moindre message.

La cause est la même que celle de JE-001, par un autre chemin.
`processUncoveredFiles` demande à `php-code-coverage` d'**inclure** les fichiers
jamais exécutés, afin d'en compter les lignes exécutables — sans quoi un fichier
non testé n'apparaîtrait pas du tout, et le pourcentage mesurerait la couverture
de ce qui est couvert. Or chaque fichier du plugin commence par la garde d'accès
direct de SEC-15 : hors WordPress, `ABSPATH` n'existe pas, l'inclusion appelle
`exit`, et le processus meurt en silence.

**Conséquence** : `bin/lib/merge-coverage.php` définit `ABSPATH` avant toute
inclusion (lignes 27-40) et passe directement par les classes de
`php-code-coverage`, que `phpcov` ne fait qu'envelopper. L'outil enveloppant
n'offrait aucun moyen d'intervenir avant l'inclusion.

Ce constat et JE-001 partagent une morale : **la garde `ABSPATH` transforme
toute inclusion hors WordPress en arrêt silencieux à succès**. Tout outil qui
inclut les fichiers du plugin en dehors de WordPress doit définir `ABSPATH` au
préalable. C'est le prix de SEC-15, et il vaut d'être payé sciemment.

---

## JE-013 — « Dernière version » de WordPress ne désigne pas la même chose pour le cœur et pour la bibliothèque de tests

**Date** : 15 octobre 2026 · **Contexte** : première exécution réelle des suites
`contract` et `integration` au lot 1.

`bin/install-wp-tests.sh` traduisait `WP_VERSION=latest` de deux façons
incompatibles dans le même script : le cœur de WordPress était téléchargé
depuis `latest.tar.gz`, donc la dernière version **publiée**, tandis que la
bibliothèque de tests était récupérée sur `trunk`, donc la version **en
développement**.

Les deux coïncident la plupart du temps. Dès qu'une version majeure est en
préparation, elles divergent, et l'amorçage échoue sur un symbole que seule la
branche de développement définit :

```
Error in bootstrap script: Error: Interface "WP_Secrets_Store" not found
```

Le message ne nomme pas sa cause. Rien n'y évoque une divergence de versions, et
le premier réflexe est de chercher le défaut dans le plugin.

**Conséquence** : `latest` est désormais **résolu** en numéro de version par
l'API de WordPress.org, et le même numéro sert au cœur et à la bibliothèque. Le
numéro retenu est enregistré dans `$WP_CORE_DIR/.rcipr-wp-version` : le cœur et
la bibliothèque vivent dans un volume persistant, et une version retenue
précédemment doit être remplacée, sans quoi la divergence survit d'une exécution
à l'autre. `WP_VERSION=trunk` reste possible, et prend alors la version de
développement des **deux** côtés.

Deux morales. La première : une chaîne d'outils qui lit deux fois la même
variable doit en tirer deux fois la même conclusion, et il faut le vérifier
plutôt que l'espérer. La seconde : ce défaut était présent depuis le lot 0 et
n'a été vu qu'à la première exécution réelle — une intégration continue qui
n'a jamais tourné ne prouve rien.

---

## JE-014 — `wp_die()` choisit son gestionnaire selon la nature de la requête, et le filtre évident ne suffit pas

**Date** : 15 octobre 2026 · **Contexte** : test de contrat sur
`rcp_form_errors`.

La dépendance termine le traitement d'une inscription par
`wp_send_json_success()` ou `wp_send_json_error()`. Pour qu'un test puisse
observer ce chemin au lieu de le subir, il faut intercepter l'arrêt. Deux
surprises, dans l'ordre où elles se présentent.

**Premièrement**, `wp_send_json()` n'appelle `wp_die()` — donc un gestionnaire
filtrable — que si la requête est déclarée AJAX. Sinon elle appelle `die`
directement, et aucun filtre ne peut l'intercepter. Il faut donc d'abord
déclarer la requête AJAX (`add_filter( 'wp_doing_ajax', '__return_true' )`).

**Deuxièmement**, `wp_die()` ne choisit pas son gestionnaire par un filtre mais
par cinq, selon la nature de la requête : `wp_die_handler`,
`wp_die_ajax_handler`, `wp_die_json_handler`, `wp_die_jsonp_handler`,
`wp_die_xmlrpc_handler`. Brancher le seul `wp_die_handler` — le nom évident —
laissait passer l'arrêt, et le symptôme était exactement celui de JE-007 : la
suite s'arrêtait au milieu, en silence, et seul `bin/run-suite.sh` l'a signalé.

**Conséquence** : `tests/Support/Request_Halted` déclare la requête AJAX et
branche les cinq gestionnaires. Le chemin devient observable, et le processus
survit.

Ce constat valide `bin/run-suite.sh` après coup : c'est lui, et non PHPUnit, qui
a dit qu'il manquait des tests. Sans lui, la suite aurait été lue comme un
succès à huit tests.

---

## JE-015 — Plusieurs accroches de la dépendance n'existent qu'en administration

**Date** : 15 octobre 2026 · **Contexte** : écriture de la sonde de capacités
étendue (§5.2) au lot 1.

La dépendance n'inclut ses fichiers d'administration que si `is_admin()`
(`core/includes/class-restrict-content.php:247`). Deux des accroches du §5.1 y
vivent : `rcp_metabox_fields_before` / `_after` et `rcp_save_post_meta`, portées
par `rcp_render_meta_box()` et `rcp_save_meta_data()`
(`core/includes/admin/metabox.php`). Les écrans de niveau d'adhésion sont dans
le même cas.

Une sonde naïve, qui vérifierait la présence de ces symboles à chaque requête,
conclurait donc à leur absence sur **toute** requête de façade, et l'avis de
E-04 annoncerait la perte d'une fonctionnalité intacte. Le défaut serait
d'autant plus pénible qu'il ne se manifesterait jamais en administration, là où
l'avis est lu.

**Conséquence** : le catalogue de capacités déclare, pour chaque symbole, le
contexte dans lequel la dépendance le charge, et le rapport distingue trois
états au lieu de deux — présente, absente, **non observable sur cette requête**.
Une fonctionnalité dont aucune capacité n'est observable est réputée
disponible : affirmer la perte sur une absence d'information serait exactement
l'erreur que E-05 proscrit, par un autre chemin.

La suite `contract` charge explicitement ces fichiers d'administration et
provoque les chemins : c'est le prix d'un contrat établi par l'exécution, et il
est préférable à une lecture de source.

---

## JE-016 — Les positions du §5.1 tiennent en 4.0.5, à deux lignes près

**Date** : 15 octobre 2026 · **Contexte** : lot 1, vérification des positions
amont contre la version réellement installée.

Le §5.1 a été établi sur Restrict Content 4.0.4. La version publiée au moment du
lot 1 est la 4.0.5. Relevé, fichier par fichier :

| Accroche | 4.0.4 (cahier des charges) | 4.0.5 (constaté) |
|---|---|---|
| `rcp_user_can_access()` | 70 | 70 |
| `rcp_member_can_access`, déconnecté | 101 | 101 |
| `rcp_member_can_access`, connecté | 772, 777, 786, 791, 806 | identiques |
| `rcp_member_can_access`, historique | 970, 975, 987 | identiques |
| `rcp_get_term_restrictions` | 1002 | 1002 |
| `rcp_form_errors` | 164 | 164 |
| `rcp_errors()` | 19 | 19 |
| Métadonnées de niveau | 26, 48, 69, 94 | identiques |
| `rcp_metabox_fields_before` / `_after` | 77, 81 | identiques |
| `rcp_save_post_meta` | **226** | **233** |
| `rcp_user_can_access_term()` | **134** | **135** |
| `rcp_manage_settings` | 53 | 53 |

Aucun nom, aucune signature, aucun ordre d'application n'a changé. Deux
positions ont glissé de sept et d'une ligne.

C'est la démonstration de ce que le §5.1 affirmait sans preuve : **un numéro de
ligne date une preuve, il ne constitue pas un contrat**. Les positions sont
conservées dans le catalogue de capacités à titre de preuve datée, et c'est la
suite `contract` — 36 tests, exécutés contre la version installée — qui tient le
contrat.

---

## JE-017 — Une capacité ajoutée en cours de processus reste invisible, et la suite ne verdissait qu'au second passage

**Date** : 15 octobre 2026 · **Contexte** : première exécution réelle de
l'intégration continue, après la création du dépôt distant.

La suite `contract` passait en local et échouait en intégration continue, sur
deux tests et toujours les mêmes : l'attribution de `rcp_manage_settings` à
l'administrateur, et l'accroche de champ de l'écran de création d'un niveau.
Les deux messages accusaient l'amont — « la dépendance n'accorde plus… », « ne
déclenche plus… » — alors que ni WordPress (7.1.3), ni Restrict Content (4.0.5),
ni PHP (8.1), ni l'ordre des tests ne différaient entre les deux
environnements.

La différence n'était pas l'environnement mais son **historique**. Reproduit en
détruisant la base de tests locale :

| Passage sur une base vierge | Résultat |
|---|---|
| 1 | 2 échecs |
| 2 | 36 tests passants |
| 3 | 36 tests passants |

L'intégration continue est, par construction, toujours un premier passage.

La cause est en deux temps. La dépendance accorde ses capacités à
**l'activation** de son plugin, que la bibliothèque de tests n'effectue pas :
c'est JE-002, et les tests rejouaient donc `RCP_Capabilities::add_caps()`
explicitement. Mais `WP_Roles::add_cap()` écrit la capacité dans son tableau
`roles` et dans l'option qui le mémorise, **et pas dans les objets `WP_Role`
déjà construits** — or c'est d'eux que `WP_User::get_role_caps()` dérive les
capacités effectives. Le rejeu restait donc sans effet observable dans le
processus courant. Au passage suivant, l'option portait les capacités écrites
par le passage précédent, les objets `WP_Role` en héritaient dès leur
construction, et la suite verdissait.

L'écran de création d'un niveau échouait pour la même raison : son accroche est
à l'intérieur d'une garde `current_user_can( 'rcp_manage_levels' )`
(`core/includes/admin/subscriptions/subscription-levels.php:43`).

**Conséquence** : `tests/Support/Dependency_Capabilities` rejoue l'attribution
puis réinitialise les rôles depuis l'option (`wp_roles()->for_site()`). La suite
passe désormais au premier passage sur une base vierge, et au suivant.

Deux morales. La première : un test qui écrit dans un état persistant peut se
rendre vrai lui-même au passage suivant — un vert obtenu au second passage n'est
pas un vert, et seule une base détruite entre deux exécutions le dit. La
seconde : les deux messages d'échec, écrits pour accuser l'amont, ont désigné le
mauvais coupable pendant tout le diagnostic. Un message de contrat devrait
nommer ce qu'il a constaté, pas la conclusion qu'il en tire.
