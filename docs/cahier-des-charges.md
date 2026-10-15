# Cahier des charges — IP Restriction for Restrict Content

- **Version du document** : 1.0, 22 septembre 2026
- **Version du plugin visée** : 1.0.0
- **État du dépôt à la rédaction** : lot 0 livré (outillage, amorçage, contrôle
  des prérequis). Aucun code métier.

Ce document est la source de vérité du projet. Le code est écrit **après** lui,
en développement piloté par les tests, exigence par exigence.

**Règle de lecture.** Une affirmation technique est soit sourcée — URL de
documentation, ou chemin de fichier et numéro de ligne dans la source réelle de
la dépendance — soit portée en §15 comme point ouvert. Il n'y a pas de troisième
catégorie. Trois sources locales sont citées, et la distinction compte :

| Notation | Source | Version |
|---|---|---|
| **RC** `core/…`, `restrictcontent.php` | `~/WORK/ressources-restrict-content/restrict-content/` — variante **gratuite** | 4.0.4 |
| **RCP** `core/…`, `pro/…` | `~/WORK/rcp-stripe-sepa/ressources/restrict-content-pro/` — variante **commerciale** | 3.5.51 |
| `restrict-content.stub.php` | `~/WORK/rcp-group-accounts/stubs/` — stub généré depuis une installation réelle de la variante gratuite | 4.0.4 |

Quand une ligne est citée sans préfixe, elle vaut pour les deux variantes à la
même position. Quand les positions diffèrent, les deux sont données.

Lire ces sources ne contrevient pas au §0.3 : ce qui est interdit est la lecture
du code de l'**add-on commercial** IP Restriction, pas celle de la dépendance,
dont la lecture est au contraire la méthode prescrite par les conventions §11.

---

## §0 Cadre

### §0.1 Objet

Le plugin ajoute à Restrict Content et à Restrict Content Pro une restriction
par **adresse IP**, à deux fonctions distinctes et de sens opposé :

| Fonction | Effet | Sens |
|---|---|---|
| **Liste d'autorisation** | Une adresse listée accède au contenu restreint sans satisfaire les conditions d'adhésion | Accorde |
| **Liste de blocage** | Une adresse listée ne peut pas s'inscrire par le formulaire d'inscription | Refuse |

Référence fonctionnelle documentée :

> « Any IP addresses on the whitelist will be able to bypass content
> restrictions and gain full access to restricted content, even if they don't
> meet the requirements. »
> « Any IP addresses on the blacklist will not be able to sign up through your
> Restrict Content Pro registration form. »
> — <https://docs.nexcess.com/software/kadence/restrict-content-pro/ip-restriction/>

### §0.2 Périmètre et hors-périmètre

| Dans le périmètre v1.0.0 | Hors périmètre v1.0.0 |
|---|---|
| Liste d'autorisation aux trois portées : globale, par niveau d'adhésion, par contenu | Redirections personnalisées après connexion ou inscription (autre plugin) |
| Liste de blocage à la portée globale, sur le seul formulaire d'inscription de la dépendance | Blocage de l'accès au site, du formulaire de connexion, de `wp-login.php` |
| Moteur de correspondance d'adresses : adresse unique, joker, plage, CIDR, IPv4 et IPv6 | Géolocalisation, détection de VPN ou de proxy anonymisant, réputation d'adresse |
| Résolution de l'adresse réelle du visiteur derrière un proxy déclaré de confiance | Découverte automatique des proxys de confiance |
| Message d'erreur de blocage personnalisable et traduisible | Limitation de débit, journal d'audit des refus |
| Points d'extension préfixés | Interface de gestion par listes importées depuis un fichier |

### §0.3 Développement en salle blanche

Le code de l'add-on commercial équivalent **n'est jamais lu ni copié**. Seule sa
documentation publique sert de référence fonctionnelle, citée en annexe A.

Ce n'est pas une précaution de principe : au moment de la rédaction, le checkout
de Restrict Content Pro v3.5.51 disponible localement ne contient **pas** cet
add-on. Son répertoire `pro/addons/` ne contient que `custom-redirects` et
`index.php`. La contrainte est donc aussi un fait matériel.

Deux conséquences outillées :

1. `bin/scan-placeholders.sh` bloque l'intégration continue sur toute occurrence
   des identifiants de l'add-on commercial dans le code livré, les tests et
   l'outillage. Le présent document et l'annexe A les citent à titre de
   correspondance de conception ; `docs/` est hors du périmètre du scanner et
   n'est pas distribué.
2. Aucun nom d'option, de méta, de hook ou de classe du plugin ne cherche à
   coïncider avec ceux de l'add-on commercial. La compatibilité de données avec
   lui n'est pas un objectif, et une migration depuis lui n'est pas livrée.

---

## §1 Contexte — la dépendance et ses deux variantes

La dépendance existe en deux variantes bâties sur le même noyau :

| Variante | Distribution | Répertoire du checkout | Rôle |
|---|---|---|---|
| **Restrict Content** | WordPress.org, gratuite | `core/` | Adhésions, restriction de contenu, formulaire d'inscription |
| **Restrict Content Pro** | Commerciale | `core/` + `pro/` | Ajoute passerelles avancées, add-ons, fonctions payantes |

La distinction est décisive : un plugin « pour Restrict Content » ne peut
s'appuyer que sur ce que `core/` fournit. Toutes les capacités sur lesquelles ce
plugin repose sont dans `core/` — voir §5.1 pour la matrice et les lignes.

### §1.1 « Restrict Content actif » ne suffit pas

La variante gratuite embarque **deux bases de code mutuellement exclusives** et
n'en charge qu'une. Constat établi par exécution, sur Restrict Content 4.0.4 :
voir JE-003.

| Branche chargée | Ce qu'elle expose | Ce plugin peut-il fonctionner |
|---|---|---|
| Moderne (`core/`, 7,3 Mo) | Toutes les fonctions `rcp_*` du §5.1 | Oui |
| Historique (`legacy/`, 716 Ko) | **Aucune** des fonctions `rcp_*` vérifiées | **Non, en aucune façon** |

Le choix initial est fait par `RC_Requirements_Check::load()`
(`restrictcontent.php`, lignes 186-215) et persisté dans l'option
`restrict_content_chosen_version`. Une installation qui portait l'option
`rc_settings` ou une métadonnée `rcp_user_level` part sur la branche historique.

**Et ce choix est réversible à tout moment.** `restrictcontent.php` expose un
gestionnaire AJAX, `rc_process_legacy_switch()` (ligne 676), enregistré sur
`wp_ajax_rc_process_legacy_switch` (ligne 731) et protégé par la capacité
`manage_options` (ligne 680) et le nonce `rc_process_legacy_nonce` (ligne 681),
qui retourne l'option d'une branche à l'autre (lignes 695 et 706). La branche
chargée n'est donc **pas une propriété de l'installation** : c'est un état
mutable, qui peut changer entre deux requêtes.

La bascule est protégée par une capacité et un nonce : ce n'est pas une faille,
c'est une fonctionnalité — et c'est précisément pour cela qu'il faut en tenir
compte.

Trois conséquences, portées partout dans ce document :

| Réf. | Conséquence |
|---|---|
| B-01 | Un site en branche historique est un site où ce plugin ne peut pas fonctionner. Ce n'est pas un cas dégradé : l'API n'existe pas dans le processus |
| B-02 | **Ni le nom du plugin, ni son numéro de version, ni la constante `RC_PLUGIN_VERSION` ne disent quelle branche est en mémoire.** Seule la présence effective des fonctions appelées le dit. La règle « détecter les capacités, pas les versions » des conventions de développement §2 n'est donc pas une précaution de style : c'est la seule méthode qui fonctionne, et JE-003 en est la validation empirique |
| B-03 | Le routage entre les deux branches repose en partie sur une requête SQL invalide côté amont (JE-003). Elle peut être corrigée à tout moment, ce qui changerait le routage des installations ultérieures. Aucun code de ce plugin ne doit en dépendre, et la suite `contract` surveille le point |
| B-04 | **La présence de la dépendance est une propriété de la requête, pas de l'installation.** Elle peut être vraie à une requête et fausse à la suivante, sans désactivation ni mise à jour d'aucun plugin. Toute la §5.2 en découle |

### §1.2 Ce que fournit la variante gratuite

Fait annexe, mais qui illustre B-02 : l'en-tête de `restrictcontent.php`
(lignes 3 et 6) annonce `Plugin Name: Kadence Memberships`, version 4.0.4, et
son écran de réglages s'intitule « Kadence Memberships Pro Settings »
(RC `core/includes/admin/admin-pages.php:28`). Le plugin dont ce plugin dépend
ne porte pas le nom sous lequel on le cherche : une détection par nom aurait
échoué avant même d'atteindre la question des branches.

**Vérifié dans le stub v4.0.4** :

| Capacité | Ligne du stub |
|---|---|
| `rcp_user_can_access()` | `restrict-content.stub.php:30867` |
| `rcp_user_can_access_term()` | `restrict-content.stub.php:30879` |
| `rcp_is_restricted_content()` | `restrict-content.stub.php:28552` |
| `rcp_get_post_restrictions()` | `restrict-content.stub.php:28567` |
| `rcp_errors()` | `restrict-content.stub.php:27819` |
| `rcp_get_membership_levels()` | `restrict-content.stub.php:28024` |
| `rcp_get_ip()` | `restrict-content.stub.php:28530` |
| `rcp_add_membership_level_meta()` | `restrict-content.stub.php:33887` |
| `rcp_get_membership_level_meta()` | `restrict-content.stub.php:33918` |

Un stub porte des signatures, pas des corps : il établit que ces fonctions
existent dans la variante gratuite, **pas** que les hooks internes qu'elles
appliquent y sont déclenchés à l'identique. C'est précisément ce que la suite
`contract` a pour rôle d'établir contre une installation réelle (§11.2).

---

## §2 Acteurs et cas d'usage

| Acteur | Ce qu'il fait |
|---|---|
| **Administrateur du site** | Renseigne les listes aux trois portées, rédige le message de blocage, déclare les proxys de confiance |
| **Éditeur de contenu** | Renseigne une liste d'autorisation propre à un contenu qu'il peut modifier |
| **Visiteur autorisé** | Lit un contenu restreint sans compte ni adhésion, depuis une adresse listée |
| **Visiteur bloqué** | Tente de s'inscrire depuis une adresse listée en blocage, et reçoit le message d'erreur |
| **Visiteur ordinaire** | N'est concerné par aucune liste ; le comportement de la dépendance est inchangé |
| **Intégrateur** | Branche ses propres règles sur les points d'extension du §8 |

Cas d'usage principaux :

| Réf. | Cas d'usage |
|---|---|
| UC-1 | Ouvrir tout le contenu restreint au réseau interne d'une entreprise cliente |
| UC-2 | Ouvrir les seuls contenus d'un niveau donné à la plage d'un partenaire |
| UC-3 | Ouvrir un contenu précis à un évaluateur, sans lui créer de compte |
| UC-4 | Empêcher l'inscription depuis une plage d'où proviennent des inscriptions frauduleuses |
| UC-5 | Faire fonctionner UC-1 à UC-4 derrière un CDN, sur l'adresse réelle du visiteur |

---

## §3 Exigences fonctionnelles

### §3.1 Portées de la liste d'autorisation

| Réf. | Exigence |
|---|---|
| FNC-01 | Une adresse figurant dans la liste d'autorisation **globale** accède à tout contenu restreint du site, tous niveaux confondus |
| FNC-02 | Une adresse figurant dans la liste d'autorisation **d'un niveau d'adhésion** accède aux seuls contenus restreints à ce niveau |
| FNC-03 | Une adresse figurant dans la liste d'autorisation **d'un contenu** accède à ce seul contenu |
| FNC-04 | Les portées ne sont pas transitives : une autorisation posée sur le niveau « Argent » ne donne aucun accès à un contenu réservé au niveau « Or » |
| FNC-05 | L'accès accordé ne dépend ni de l'état de connexion du visiteur ni de l'existence d'un compte |
| FNC-06 | Les portées se cumulent par disjonction : une seule portée satisfaite suffit à accorder l'accès au contenu considéré |

Sources de FNC-01 à FNC-03 : la portée triple est documentée
(« configured on a global, subscription level, and per-post/page basis. » —
<https://restrictcontentpro.com/add-on/ip-restriction/>), et détaillée par
la documentation d'exploitation : le champ global ouvre « all material on the
site », le champ de niveau le seul « content that is restricted specifically to
this subscription level »
(<https://docs.nexcess.com/software/kadence/restrict-content-pro/ip-restriction/>).

FNC-04 traduit ce même texte : la portée reste strictement liée au périmètre où
l'adresse a été inscrite. C'est une exigence à part entière parce que
l'implémentation naïve — une seule liste consultée quelle que soit la
restriction rencontrée — la viole silencieusement et transforme chaque
autorisation de niveau en autorisation globale.

### §3.2 Liste de blocage

| Réf. | Exigence |
|---|---|
| FNC-07 | Une adresse figurant dans la liste de blocage ne peut pas soumettre le formulaire d'inscription de la dépendance ; la soumission est rejetée avec un message d'erreur |
| FNC-08 | La liste de blocage n'a **aucun** effet sur l'accès au contenu : une adresse bloquée qui consulte un contenu public le voit, et un contenu restreint reste régi par la dépendance seule |
| FNC-09 | La liste de blocage n'a aucun effet sur la connexion d'un compte existant, ni sur `wp-login.php` |
| FNC-10 | Le message d'erreur affiché est un réglage de texte libre, avec une valeur par défaut fournie et traduisible |
| FNC-11 | Le message par défaut ne révèle ni l'adresse évaluée, ni la règle qui a filtré, ni l'existence d'une liste |
| FNC-12 | La liste de blocage existe à la seule portée globale |

FNC-12 : la documentation ne décrit le champ de blocage que sur l'écran de
réglages global ; les champs de niveau et de contenu qu'elle décrit sont des
champs d'autorisation. Étendre le blocage à d'autres portées serait une
invention, pas une reprise.

### §3.3 Formats d'entrée acceptés

| Réf. | Exigence | Exemple documenté |
|---|---|---|
| FNC-13 | Une adresse IPv4 littérale est acceptée | `226.216.136.78` |
| FNC-14 | Un joker `*` remplace un ou plusieurs octets de fin | `200.122.216.*`, `186.*.*.*` |
| FNC-15 | Une plage bornée par deux adresses est acceptée, **bornes incluses** | `159.7.89.231-159.7.89.240` |
| FNC-16 | Un bloc CIDR est accepté | `10.0.0.0/24` |
| FNC-17 | Une plage dont les bornes portent des jokers est acceptée | `149.58.*-149.60.*` |
| FNC-18 | Les adresses IPv6 sont acceptées, sous les formes littérale et CIDR | — |
| FNC-19 | Une entrée par ligne ; les lignes vides et les espaces de bordure sont ignorés |  |

Source de FNC-13 à FNC-19, y compris le caractère inclusif des plages et la
règle « one entry per line » :
<https://docs.nexcess.com/software/kadence/restrict-content-pro/ip-restriction/>.
La documentation amont annonce « Both IPv4 and IPv6 are supported » sans donner
un seul exemple IPv6 : l'étendue exacte du support IPv6 amont est donc inconnue
et porte le point ouvert V-03. Le présent cahier des charges tranche pour ce
plugin en O-05.

### §3.4 Entrées invalides

| Réf. | Exigence |
|---|---|
| FNC-20 | Une entrée que l'analyseur ne comprend pas est **rejetée**, jamais interprétée par approximation |
| FNC-21 | Une entrée rejetée n'entre pas dans la décision : elle ne correspond à aucune adresse |
| FNC-22 | Une entrée rejetée est conservée telle quelle dans le réglage, et signalée à l'administrateur sur l'écran où elle a été saisie, avec son numéro de ligne et le motif du rejet |
| FNC-23 | Une liste dont **toute** entrée est rejetée se comporte comme une liste vide |

FNC-22 mérite sa justification. Deux traitements étaient possibles : refuser
l'enregistrement du réglage entier, ou l'enregistrer en signalant les lignes
fautives. Le premier fait perdre à l'administrateur les quarante lignes valides
qu'il venait de coller à cause d'une faute de frappe sur la onzième. Le second
laisse une ligne inerte dans un champ, ce qui est visible et corrigeable. On
retient le second, à la condition que le signalement soit persistant et non un
avis éphémère.

### §3.5 Réglages et écrans

| Réf. | Exigence |
|---|---|
| FNC-24 | Un écran de réglages dédié, sous le menu de la dépendance, porte la liste d'autorisation globale, la liste de blocage, le message d'erreur et la déclaration des proxys de confiance |
| FNC-25 | L'écran d'édition d'un niveau d'adhésion porte un champ de liste d'autorisation propre au niveau |
| FNC-26 | L'éditeur de contenu porte une boîte de métadonnées de liste d'autorisation propre au contenu |
| FNC-27 | Tous les champs de liste sont **vides par défaut** ; le plugin fraîchement activé ne change aucun comportement observable |
| FNC-28 | À l'enregistrement, chaque liste est normalisée : lignes ajustées, entrées en doublon fusionnées, ordre d'origine conservé |
| FNC-29 | L'écran de réglages affiche l'adresse que le plugin retient **pour la requête en cours**, et la source dont elle provient, afin que l'administrateur puisse vérifier sa configuration de proxy sans instrumenter le serveur |

FNC-27 n'est pas une facilité : c'est la seule forme sous laquelle un plugin qui
accorde des accès peut être installé sans risque. La documentation amont ne
l'affirme pas — elle laisse seulement déduire que les champs sont vides
(point ouvert V-04) — mais pour ce plugin c'est une exigence.

FNC-29 répond au fait que la résolution de l'adresse derrière un proxy est la
partie la plus facile à mal configurer et la plus difficile à observer. Sans cet
écran, un administrateur ne peut pas distinguer « ma règle est fausse » de
« l'adresse évaluée n'est pas celle que je crois ».

### §3.6 Absence d'effet de bord

| Réf. | Exigence |
|---|---|
| FNC-30 | L'autorisation accorde la lecture d'un contenu restreint et **rien d'autre** : aucune capacité WordPress, aucune adhésion, aucune écriture, aucun accès à l'administration |
| FNC-31 | Un visiteur qui ne correspond à aucune entrée d'aucune liste obtient exactement le comportement de la dépendance seule |
| FNC-32 | Le plugin n'effectue aucun appel réseau sortant |
| FNC-33 | Le plugin ne crée aucune table et ne persiste aucune donnée par visiteur |

---

## §4 Règles de décision — la logique pure

Cette section décrit le cœur du plugin. Il ne lit ni base, ni option, ni
en-tête HTTP : il reçoit des données en argument et renvoie un objet de valeur.
C'est ce qui le rend couvrable par des tests rapides et relisible sans
connaître WordPress.

### §4.1 Chaîne de traitement

```
en-têtes de la requête + proxys déclarés
        │
        ▼  (transport)
  Client_Address_Resolver ──► Client_Address      « quelle adresse, et d'où »
        │
        ▼  (décision)
  Rule_Set ◄── Rule_Parser ◄── texte du réglage
        │
        ▼
  Matcher( Client_Address, Rule_Set ) ──► Match_Result
        │
        ▼  (application)
  Access_Policy( Match_Result, portée demandée ) ──► Access_Decision
```

Chaque flèche est une frontière testable seule. Le `Matcher` est le seul objet
dont la couverture doit être exhaustive sur les formats (§11.6).

### §4.2 Grammaire d'entrée

Une liste est un texte. Chaque ligne non vide est une **règle**. La grammaire
est close : ce qu'elle ne décrit pas est rejeté.

```
liste        ::= ( ligne "\n" )*
ligne        ::= espaces* ( règle espaces* )? ( "#" commentaire )?
règle        ::= plage | motif
plage        ::= motif "-" motif
motif        ::= cidr | joker | littéral
cidr         ::= littéral "/" entier
joker        ::= segments séparés, dont au moins un vaut "*"
littéral      ::= adresse IPv4 pointée | adresse IPv6 (RFC 4291, y compris forme compressée)
```

Contraintes de validité, au-delà de la forme :

| Réf. | Contrainte | Entrée refusée |
|---|---|---|
| G-01 | Les deux bornes d'une plage relèvent de la même famille d'adresses | `10.0.0.1-::1` |
| G-02 | La borne basse d'une plage est inférieure ou égale à la borne haute | `10.0.0.9-10.0.0.1` |
| G-03 | Le préfixe d'un CIDR est compris entre 0 et 32 en IPv4, entre 0 et 128 en IPv6 | `10.0.0.0/33` |
| G-04 | Un joker n'apparaît pas à gauche d'un segment littéral | `10.*.0.1` |
| G-05 | Une règle ne peut pas désigner l'ensemble des adresses | `*`, `*.*.*.*`, `0.0.0.0/0`, `::/0` |
| G-06 | Un CIDR ne porte pas de joker | `10.0.*.0/24` |
| G-07 | Un nom d'hôte n'est pas une adresse | `exemple.test` |

G-04 mérite un mot : `10.*.0.1` est syntaxiquement lisible mais sémantiquement
piégeux, parce que le lecteur humain et la machine ne s'accordent pas sur ce
qu'il désigne. Le refuser coûte une ligne de documentation ; l'accepter coûte un
incident.

G-05 est une exigence de sécurité autant qu'une règle de grammaire : voir
SEC-12. Une règle qui désigne toutes les adresses est presque toujours une
erreur, et son effet — ouvrir tout le contenu restreint à l'internet — est
exactement celui qu'on ne veut pas produire par inadvertance.

**Trois précisions apportées par l'écriture de l'analyseur au lot 2**, chacune
dans le sens du refus.

G-05 est contrôlé sur l'**intervalle normalisé**, et non sur l'écriture. Les
formes citées — `*`, `*.*.*.*`, `0.0.0.0/0`, `::/0` — ne sont pas une liste
close : la propriété dangereuse est sémantique, et `0.0.0.0-255.255.255.255`
ouvre tout aussi largement sans figurer dans la liste. Le contrôle porte donc
sur « l'intervalle va-t-il d'un extrême à l'autre de sa famille », ce qui couvre
toutes les écritures, présentes et à venir.

Un octet portant un **zéro de tête** est refusé, avec un motif qui lui est
propre. `inet_pton()` lit `010.0.0.1` comme `10.0.0.1` ; d'autres bibliothèques
y lisent `8.0.0.1`, par interprétation octale. L'auteur de la règle et la machine
ne s'accordent donc pas sur ce qu'elle désigne, et FNC-20 tranche (JE-020).

Un joker peut porter sur **moins de quatre segments** : `149.58.*` vaut
`149.58.*.*`. C'est la lecture qu'impose FNC-14 — « un joker remplace un ou
plusieurs octets de fin » — et sans elle l'exemple documenté
`149.58.*-149.60.*` serait une faute de forme.

### §4.3 Décision sur une règle

Chaque forme de règle se ramène à une comparaison sur l'adresse convertie en
entier de longueur fixe : 32 bits en IPv4, 128 bits en IPv6. C'est ce qui rend
les quatre formes comparables et la combinaison joker + plage définissable.

| Forme | Normalisation | Correspond si |
|---|---|---|
| Littéral | borne basse = borne haute = l'adresse | l'adresse est égale |
| Joker | borne basse = segments jokers à 0 ; borne haute = segments jokers au maximum | l'adresse est dans l'intervalle |
| CIDR | borne basse = adresse du réseau ; borne haute = adresse de diffusion | l'adresse est dans l'intervalle |
| Plage | borne basse = borne basse du motif gauche ; borne haute = borne **haute** du motif droit | l'adresse est dans l'intervalle |

La dernière ligne définit FNC-17. `149.58.*-149.60.*` se normalise en
`149.58.0.0` – `149.60.255.255` : la borne gauche prend son minimum, la borne
droite son maximum, ce qui est la seule lecture qui rende la plage inclusive
comme l'exige FNC-15 et qui englobe `149.60.255.255`. C'est une interprétation
raisonnée, pas une reprise : la documentation amont donne l'exemple sans le
définir. Elle porte donc le point ouvert O-06.

Une règle et une adresse de familles différentes ne correspondent pas — elles ne
produisent pas une erreur. Une règle IPv4 ne filtre pas un visiteur IPv6, et
réciproquement : c'est le comportement attendu, et c'est un piège
d'exploitation qui doit figurer dans la documentation utilisateur, parce qu'un
site dual-stack peut recevoir le même visiteur sous les deux familles.

### §4.4 Décision sur un ensemble de règles

| Réf. | Règle |
|---|---|
| D-01 | Une liste correspond si **au moins une** de ses règles valides correspond |
| D-02 | Une règle invalide ne correspond à rien, et ne rend pas la liste invalide |
| D-03 | Le résultat porte la règle qui a produit la correspondance, pour le diagnostic |
| D-04 | Une liste vide ne correspond jamais |
| D-05 | Une adresse absente ou non analysable ne correspond jamais, quelle que soit la liste |

### §4.5 Fermeture sur l'inconnu

**Une règle illisible n'élargit jamais l'accès.** C'est la règle de conception
dont tout le reste découle, et elle a deux faces :

| Situation | Sur une liste d'autorisation | Sur une liste de blocage |
|---|---|---|
| Règle illisible | La règle ne correspond pas → **l'accès n'est pas accordé** | La règle ne correspond pas → **le blocage n'est pas appliqué** |
| Adresse du visiteur indéterminable | Aucune autorisation → comportement de la dépendance | Aucun blocage → l'inscription suit son cours |
| Conséquence d'une faute de configuration | Un accès légitime est refusé : l'administrateur s'en aperçoit, personne n'est exposé | Une inscription indésirable passe : personne ne s'en aperçoit |

Cette asymétrie est la raison d'être du §4.6.

### §4.6 Asymétrie des deux listes — règle de conception

Les deux listes se ressemblent : deux champs de texte, la même grammaire, le
même moteur. Elles n'ont pas les mêmes conséquences en cas d'erreur.

| | Liste d'autorisation | Liste de blocage |
|---|---|---|
| Effet | Accorde un accès | Refuse une inscription |
| Erreur par excès (la règle couvre trop) | **Fuite d'accès** : du contenu payant devient public. Silencieuse, durable, difficile à constater | Inscriptions légitimes refusées. Bruyante : les personnes concernées écrivent au support |
| Erreur par défaut (la règle ne couvre pas assez) | Accès légitime refusé. Bruyante | **Blocage inopérant**. Silencieuse |
| Défaillance de l'analyseur | Vers le refus d'accès | Vers l'absence de blocage |
| Gravité maximale | Élevée — contournement d'un contrôle d'accès | Modérée — perte de filtrage d'un formulaire public |

Trois règles de conception en découlent :

| Réf. | Règle |
|---|---|
| A-01 | Les deux listes ne partagent jamais un stockage, un cache ni un chemin de décision. Une confusion de champ ne peut pas transformer un blocage en autorisation |
| A-02 | Toute règle de la liste d'autorisation est validée **à l'écriture** avec la même sévérité qu'à la lecture, et une règle trop couvrante (G-05) est refusée à l'écriture |
| A-03 | Une entrée rejetée dans la liste de **blocage** produit un avis d'administration persistant, parce que son échec est silencieux. Une entrée rejetée dans la liste d'**autorisation** produit le même avis, mais son échec est de toute façon visible |

### §4.7 Résolution de l'adresse du visiteur

C'est le principal risque de sécurité du plugin. La documentation amont n'en dit
rien (point ouvert V-02), et la dépendance elle-même fait le mauvais choix.

**Le constat.** `rcp_get_ip()` (`core/includes/misc-functions.php:731`) retient,
dans cet ordre : `$_SERVER['HTTP_CLIENT_IP']` (ligne 734), puis le premier
élément de `$_SERVER['HTTP_X_FORWARDED_FOR']` (lignes 737-742), puis
`$_SERVER['REMOTE_ADDR']` (ligne 743). Les deux premières valeurs sont des
en-têtes HTTP : **n'importe quel client peut les écrire**. Sur un site sans
proxy, un visiteur qui envoie `Client-Ip: 10.0.0.1` fait dire à `rcp_get_ip()`
qu'il vient de `10.0.0.1`.

Seul `REMOTE_ADDR` est renseigné par le serveur web à partir de la connexion
TCP, et n'est donc pas falsifiable par le client.

**La conséquence.** Une liste d'autorisation évaluée sur une valeur que le
visiteur contrôle n'est pas un contrôle d'accès : c'est un contournement
d'authentification à une ligne d'en-tête. C'est l'objet de SEC-07 à SEC-09.

**La règle.**

| Réf. | Règle |
|---|---|
| R-01 | Par défaut, l'adresse évaluée est `REMOTE_ADDR`, et rien d'autre |
| R-02 | Un en-tête de transfert n'est lu que si l'administrateur a déclaré explicitement une liste de proxys de confiance **et** le nom de l'en-tête à lire |
| R-03 | Même alors, l'en-tête n'est lu que si `REMOTE_ADDR` correspond à l'un des proxys déclarés. Sinon, `REMOTE_ADDR` est retenu |
| R-04 | Dans l'en-tête, la chaîne est lue **de droite à gauche** : on remonte la liste en sautant les proxys déclarés, et la première valeur qui n'en est pas un est l'adresse du client |
| R-05 | Une valeur d'en-tête non analysable, ou qui laisse l'adresse indéterminable, produit une adresse absente — jamais un repli sur une valeur arbitraire |
| R-06 | Le plugin n'utilise **pas** `rcp_get_ip()` comme source de décision, et ne filtre pas `rcp_get_ip` pour le corriger : un autre code peut dépendre de son comportement actuel |
| R-07 | L'objet `Client_Address` porte la source dont il provient — connexion, ou en-tête et rang — pour que FNC-29 puisse l'afficher et qu'un test puisse l'asserter |

R-04 mérite sa justification. `X-Forwarded-For` s'accroît par la gauche à chaque
saut : un client malveillant qui envoie déjà `X-Forwarded-For: 10.0.0.1` fait
arriver au serveur `10.0.0.1, <vraie adresse>`. Lire le premier élément, comme
le fait `rcp_get_ip()`, retient donc la valeur injectée. Lire de droite à gauche
en sautant les proxys connus retient la dernière valeur qu'aucun proxy de
confiance n'a pu avoir inventée : c'est la seule lecture sûre.

R-06 est une règle de bonne conduite envers l'écosystème : corriger un
comportement de la dépendance par un filtre global changerait le comportement
d'autres extensions sans qu'elles le sachent. Le plugin résout l'adresse pour
lui-même.

---

## §5 Intégration à la dépendance

### §5.1 Capacités requises, et où elles sont établies

Toutes les capacités de ce tableau sont dans `core/`, donc disponibles dans les
deux variantes. Aucune capacité de `pro/` n'est requise — et c'est le résultat le
plus important de l'instruction du dossier.

Chaque ligne est vérifiée **dans les deux sources**, la variante gratuite (RC
4.0.4) et la variante commerciale (RCP 3.5.51). Les positions coïncident presque
partout ; là où elles diffèrent, les deux sont données, et cette divergence est
en soi une information — elle mesure la dérive entre les deux distributions d'un
même noyau, et c'est ce que la suite `contract` surveille.

| Capacité | Rôle pour ce plugin | Fichier | RC 4.0.4 | RCP 3.5.51 |
|---|---|---|---|---|
| `rcp_user_can_access()` | Fonction par laquelle la dépendance décide l'accès à un contenu | `core/includes/member-functions.php` | 70 | 70 |
| Filtre `rcp_member_can_access` — chemin **déconnecté** | Point d'accroche de FNC-05 : accorder l'accès à un visiteur sans compte | `core/includes/member-functions.php` | **101** | **101** |
| Filtre `rcp_member_can_access` — chemin **connecté** | Même accroche pour un visiteur ayant un compte client | `core/includes/customers/class-rcp-customer.php` | 772, 777, 786, 791, 806 | idem |
| Filtre `rcp_member_can_access` — chemin historique | Même accroche sur l'objet `RCP_Member` | `core/includes/class-rcp-member.php` | 970, 975, 987 | idem |
| Filtre `rcp_get_term_restrictions` | Seule accroche disponible pour les restrictions de **taxonomie** (§5.1.1) | `core/includes/misc-functions.php` | 1002 | 998 |
| Action `rcp_form_errors` | Point d'accroche de FNC-07 : ajouter une erreur qui annule l'inscription | `core/includes/registration-functions.php` | **164** | **148** |
| Action `rcp_before_form_errors` | Accroche plus précoce, non retenue : elle précède la validation du niveau | `core/includes/registration-functions.php` | 93 | 93 |
| `rcp_errors()` | Collecteur d'erreurs `WP_Error` du formulaire d'inscription | `core/includes/error-tracking.php` | 19 | 19 |
| Collecte et renvoi des erreurs | Établit que le message remonte en JSON, sans rechargement (§7.5) | `core/includes/registration-functions.php` | 167, 175 | 151, 158 |
| Action `rcp_edit_subscription_form` | Point d'accroche de FNC-25 sur l'écran d'édition d'un niveau | `core/includes/admin/subscriptions/edit-subscription.php` | 164 | 164 |
| Action `rcp_add_subscription_form` | Même champ sur l'écran de création d'un niveau | `core/includes/admin/subscriptions/subscription-levels.php` | 195 | 195 |
| Action `rcp_edit_subscription_level` | Enregistrement du champ de niveau | `core/includes/membership-levels/membership-level-functions.php` | 381 | 381 |
| Action `rcp_add_subscription` | Enregistrement à la création d'un niveau | `core/includes/membership-levels/membership-level-functions.php` | 278 | 278 |
| API de métadonnées de niveau | Stockage du champ de niveau (ADR-0004) | `core/includes/levels/meta.php` | 26, 48, 69, 94 | idem |
| Actions `rcp_metabox_fields_before` / `_after` | Point d'accroche de FNC-26 dans la boîte de restriction de l'éditeur | `core/includes/admin/metabox.php` | 77, 81 | 77, 81 |
| Action `rcp_metabox_additional_options_before` | Variante de placement dans le même écran | `core/includes/admin/metabox-view.php` | 104 | 104 |
| Action `rcp_save_post_meta` | Enregistrement du champ de contenu | `core/includes/admin/metabox.php` | 226 | 226 |
| Capacité `rcp_manage_settings` | Capacité qui garde l'écran de réglages de la dépendance, réutilisée par FNC-24 | `core/includes/admin/admin-pages.php` (écran) et `core/includes/class-rcp-capabilities.php` (attribution) | 28 ; 53 | 28 ; 53 |
| Filtre `rcp_is_restricted_content` | Non retenu comme accroche, mais utile au diagnostic | `core/includes/misc-functions.php` | 815 | 811 |
| `rcp_get_membership_levels()` | Sonde de présence de la dépendance, déjà en place | `core/includes/membership-levels/…` ; stub | présente | présente |

Deux divergences de position méritent d'être notées : `rcp_form_errors` est
16 lignes plus bas dans la variante gratuite, et `rcp_get_term_restrictions`
4 lignes plus bas. Aucune ne change de nom, de signature ni d'ordre
d'application. C'est le rappel qu'un numéro de ligne date une **preuve**, et ne
constitue jamais un contrat : le contrat, c'est la suite `contract`.

**Vérifié à nouveau au lot 1, contre Restrict Content 4.0.5**, la version
publiée au moment de l'écriture de la suite `contract` : aucun nom, aucune
signature, aucun ordre d'application n'a changé ; deux positions ont glissé —
`rcp_save_post_meta` de 226 à 233, `rcp_user_can_access_term()` de 134 à 135.
Le relevé complet est en JE-016. La démonstration est faite : les positions de
ce tableau datent une preuve, et les 36 tests de la suite `contract` tiennent le
contrat.

**Le commentaire qui confirme l'intention amont.** À
`core/includes/member-functions.php:100` — à la même ligne dans les deux
variantes —, juste avant l'application du filtre, le noyau porte ce
commentaire :

> « We need to apply the filter here so that it can be used on logged-out users,
> like in IP Restriction. »

C'est la preuve la plus forte disponible : le noyau libre expose délibérément ce
filtre sur le chemin déconnecté **pour cet usage précis**. Le point d'accroche
de FNC-05 n'est pas un détournement.

Ce commentaire et le `apply_filters` qui le suit sont lus **directement dans la
source de la variante gratuite**, et non déduits d'un stub. Le point ouvert V-07,
qui portait ce doute, est levé (§15.2).

**Le précédent d'usage.** Le noyau lui-même consomme ce filtre pour une
intégration tierce : `core/includes/integrations/class-rcp-wp-approve-user.php:38`
(`add_filter( 'rcp_member_can_access', …, 10, 4 )`). De même, le noyau consomme
`rcp_form_errors` pour valider son captcha :
`core/includes/captcha-functions.php:157`. Les deux accroches sont donc
employées par la dépendance elle-même, ce qui les rend difficiles à retirer
sans casser son propre code.

#### §5.1.1 Contrainte majeure — les restrictions de taxonomie

`rcp_user_can_access_term()` (`core/includes/member-functions.php:134`)
**n'applique aucun filtre** : elle renvoie sa décision sans passer par
`rcp_member_can_access` ni par aucun autre point d'accroche. Elle renvoie même
`false` sans filtre lorsqu'aucun client ne correspond à l'utilisateur
(lignes 148-153). Il est donc **impossible** d'accorder l'accès à un contenu
restreint par catégorie ou par étiquette en filtrant la décision.

La seule accroche disponible est en amont de la décision :
`rcp_get_term_restrictions` (`core/includes/misc-functions.php:998`), qui filtre
les **restrictions** d'un terme et non la décision. Renvoyer un tableau vide
depuis ce filtre neutralise la restriction du terme pour la requête en cours.

| | Filtrer la décision | Filtrer les restrictions du terme |
|---|---|---|
| Accroche | `rcp_member_can_access` | `rcp_get_term_restrictions` |
| Ce qui est modifié | La réponse à « cette personne peut-elle accéder » | La donnée « ce terme est-il restreint » |
| Portée de l'effet | Le visiteur en cours | Tout ce qui interroge ce terme pendant la requête |
| Risque | Aucun effet indirect | Un autre code qui interroge les restrictions pendant la même requête reçoit une réponse altérée |

Conséquence retenue pour v1.0.0 : les restrictions de taxonomie sont couvertes
par `rcp_get_term_restrictions`, avec trois garde-fous — le filtre n'est branché
que si une liste d'autorisation non vide existe, il n'est évalué qu'après
résolution de l'adresse, et il n'est jamais branché pour une portée qui ne
concerne pas le terme demandé. Le point ouvert V-06 porte la vérification en
recette de l'absence d'effet indirect.

Ce point est signalé ici parce qu'il est exactement le genre de contrainte qu'on
découvre autrement à la moitié du lot 2.

### §5.2 Détection par capacité

La sonde est livrée au lot 0 et **étendue au lot 1**. Elle vérifie la présence
effective des symboles, jamais un numéro de version ni un nom de plugin
(`src/Integration/Capability_Probe.php`). Pour chaque accroche du tableau du
§5.1, elle vérifie ce qui est vérifiable sans exécuter la requête — existence de
la fonction, et pour un filtre, l'existence de la fonction qui l'applique.

Le catalogue des capacités attendues est une donnée
(`src/Domain/Capability_Catalogue.php`), et chaque entrée porte trois choses en
plus du symbole : la fonctionnalité de ce plugin qui en dépend — c'est ce qui
rend E-04 applicable —, la preuve amont qui l'a établie, et le **contexte** dans
lequel la dépendance charge le symbole.

Ce dernier point n'est pas une subtilité. La dépendance n'inclut ses fichiers
d'administration que si `is_admin()`
(`core/includes/class-restrict-content.php:247`), et deux accroches du §5.1 y
vivent. Une sonde qui les chercherait sur une requête de façade concluerait à
leur absence, et l'avis de E-04 annoncerait la perte d'une fonctionnalité
intacte (JE-015). Le rapport distingue donc trois états et non deux : présente,
absente, **non observable sur cette requête**. Une fonctionnalité dont aucune
capacité n'est observable est réputée disponible — affirmer une perte sur une
absence d'information serait l'erreur de E-05 par un autre chemin.

L'écran d'état des capacités (`src/Admin/Capability_Status_Page.php`) expose ce
rapport en lecture seule, capacité par capacité, avec sa preuve amont. Il est
rangé sous l'outillage de WordPress et gardé par `manage_options`, et non sous
le menu de la dépendance : c'est quand celle-ci est absente ou revenue à sa
branche historique que l'écran sert, et son menu n'existe alors pas.

Un filtre ne se sonde pas par introspection : on ne peut pas savoir qu'un
`apply_filters` existe dans un corps de fonction. C'est pourquoi la suite
`contract` (§11.2) établit chaque accroche **par l'exécution** : elle branche un
témoin, provoque le chemin, et constate que le témoin a été appelé.

#### §5.2.1 La sonde n'est jamais mémorisée

C'est l'exigence que le constat JE-003 rend indispensable, et elle est
contre-intuitive : sonder l'environnement à chaque requête ressemble à du
gaspillage, et la première optimisation qui vienne à l'esprit est de retenir le
résultat.

**Il ne faut pas.** La branche chargée par la dépendance est un état mutable, que
l'administrateur retourne depuis un écran, sans désactiver ni mettre à jour quoi
que ce soit (§1.1, B-04). Une présence constatée à une requête peut être fausse à
la suivante.

| Réf. | Exigence |
|---|---|
| **P-01** | Le résultat de la sonde n'est **jamais** persisté : ni option, ni métadonnée, ni transient, ni fichier de cache |
| **P-02** | Le résultat de la sonde n'est **jamais** décidé à l'activation. Le crochet d'activation ne conclut rien sur l'environnement d'exécution des requêtes suivantes |
| **P-03** | Le contrôle des prérequis a lieu **à chaque requête**, sur `plugins_loaded`. Ce n'est pas un détail d'implémentation : c'est une propriété à préserver, et un test la vérifie |
| **P-04** | Une bascule de branche produit, dès la requête suivante, l'avis de E-05 — sans qu'aucune action d'administration ni réenregistrement ne soit nécessaire |

La mémorisation **au sein d'une même requête** reste permise et souhaitable : la
sonde conclut une fois, et son résultat circule en mémoire. C'est la frontière
exacte — la requête — et elle n'est pas négociable.

Pourquoi le défaut serait grave et non seulement gênant : un plugin qui aurait
écrit « la dépendance est présente » à l'activation continuerait d'enregistrer
ses filtres après une bascule, sur une API absente. Le premier appel produirait
une erreur fatale — exactement ce que le §5.3 existe pour empêcher. L'économie
d'une poignée d'appels à `function_exists()` se paierait en écran blanc.

P-03 est vérifié par la suite `integration` : le plugin est amorcé, l'option
`restrict_content_chosen_version` est retournée, le plugin est réamorcé, et
l'avis attendu apparaît. Le test inspecte en outre les options et les transients
du site après amorçage, et échoue si le plugin en a écrit un seul.

### §5.3 Échec propre

| Réf. | Règle |
|---|---|
| E-01 | Un prérequis absent n'enregistre aucune fonctionnalité et produit un avis d'administration nommant précisément ce qui manque. Jamais d'erreur fatale |
| E-02 | Aucune classe métier n'est chargée avant que la sonde n'ait conclu |
| E-03 | Un prérequis absent ne change pas le comportement du site : le contenu reste régi par la dépendance, le formulaire d'inscription reste ouvert |
| E-04 | Une capacité d'accroche absente désactive la fonctionnalité qui en dépend, et la nomme dans l'avis — sans désactiver les autres |
| E-05 | L'avis nomme la **cause constatée**, pas la cause supposée. En particulier, un site dont la dépendance est active mais charge sa branche historique reçoit un avis qui le dit, et non « dépendance absente » (§1.1, §5.2.1, JE-003) |

E-01 et E-02 sont livrés au lot 0 : `src/Plugin.php`,
`src/Admin/Requirements_Notice.php`, et l'amorçage en priorité 20 sur
`plugins_loaded` dans le fichier principal.

E-03 est la contrepartie de §4.5 au niveau du plugin entier. E-04 est la
conséquence de §5.1.1 : si `rcp_get_term_restrictions` disparaît, la couverture
des taxonomies tombe, pas la couverture des contenus.

E-05 est la conséquence directe de JE-003, et c'est une exigence de
compréhensibilité autant que d'exactitude. Un administrateur qui voit
« Restrict Content ou Restrict Content Pro, actif » manquant alors que le plugin
figure, actif, dans sa liste d'extensions, conclut que l'avis est faux et
cherche ailleurs. Le libellé porte donc la capacité constatée :

> « Restrict Content est actif, mais charge sa base de code historique, qui
> n'expose pas l'interface de programmation nécessaire. »

La sonde distingue les deux situations par la même méthode — l'absence de
`rcp_get_membership_levels()` — et par une seule information supplémentaire :
le plugin de la dépendance est-il actif. Elle ne lit **pas** l'option
`restrict_content_chosen_version` pour décider : cette option est un détail
d'implémentation amont, susceptible de changer, et B-02 pose que seule la
capacité fait foi. L'option peut en revanche être citée dans l'avis, à titre
d'indication pour l'administrateur.

L'avis est en outre le seul canal utile, parce que **celui qui vient de basculer
est exactement celui qui peut revenir en arrière** : la bascule exige
`manage_options` (`restrictcontent.php:680`), et l'avis de prérequis n'est rendu
qu'à qui détient `activate_plugins` (`src/Admin/Requirements_Notice.php`). Les
deux publics coïncident en pratique. L'avis nomme donc l'action de retour, et non
seulement la cause.

---

## §6 Modèle de données

### §6.1 Aucune table

Le plugin ne crée aucune table. Il ne stocke que de la configuration, en
quantité bornée par le nombre de niveaux et de contenus configurés, et il ne
persiste rien par visiteur ni par requête. Une table dédiée ne se justifierait
que par un besoin de requêtage ou de volume : il n'y en a aucun. Voir ADR-0003.

### §6.2 Options

| Option | Type | Contenu | Justification |
|---|---|---|---|
| `ip_restriction_for_restrict_content_settings` | tableau | Liste d'autorisation globale, liste de blocage, message d'erreur, en-tête de transfert retenu, proxys de confiance | Un seul enregistrement, lu à chaque requête : une option unique est chargée avec les options automatiques et évite quatre lectures |
| `ip_restriction_for_restrict_content_version` | chaîne | Version du schéma de réglages | Permet une migration de forme sans deviner l'état antérieur |

Les deux listes sont stockées **dans des clés distinctes** du même tableau, et
ne sont jamais manipulées par un code commun paramétré par un nom de clé : c'est
l'application de A-01.

### §6.3 Métadonnées de niveau d'adhésion

| Clé | Portée | Justification |
|---|---|---|
| `rcipr_ip_allow_list` | Métadonnée de niveau, via `rcp_add_membership_level_meta()` et suivantes | La dépendance fournit une API de métadonnées de niveau (`core/includes/levels/meta.php:26, 69, 94`) : la réutiliser fait que la suppression d'un niveau emporte son réglage, sans code de nettoyage. Voir ADR-0004 |

### §6.4 Métadonnées de contenu

| Clé | Portée | Justification |
|---|---|---|
| `_rcipr_ip_allow_list` | Métadonnée de publication | Le préfixe `_` la retire de l'interface des champs personnalisés : un réglage d'accès n'a pas à être modifiable par un champ libre. Elle suit le contenu dans les exports, les révisions et la corbeille |

### §6.5 Ce qui n'est pas stocké

| Donnée | Pourquoi elle n'est pas stockée |
|---|---|
| Adresse d'un visiteur | Donnée personnelle au sens du RGPD. Aucune finalité ne la justifie : la décision est prise et oubliée |
| Journal des décisions | Idem. Un besoin d'audit, s'il apparaît, se traitera par un point d'extension que l'exploitant branche sur son propre journal (§8) |
| Résultat de décision mis en cache entre requêtes | Un cache partagé entre visiteurs est un contournement en puissance : voir SEC-14 |

### §6.6 Désinstallation

`uninstall.php` est en place depuis le lot 0 et ne supprime rien, puisque rien
n'est encore écrit. La règle qui s'appliquera dès le premier réglage écrit y est
déjà énoncée, et elle est asymétrique de façon délibérée :

| Objet | À la désinstallation |
|---|---|
| Accès conféré par le plugin | Révoqué inconditionnellement — le plugin absent, aucune liste n'accorde plus rien |
| Réglages, métadonnées de niveau et de contenu | Supprimés **seulement** si l'administrateur l'a demandé par un réglage |
| Comptes utilisateurs | Jamais touchés, dans aucun scénario |

---

## §7 Interface

### §7.1 Écran de réglages

Sous le menu de la dépendance, gardé par `rcp_manage_settings`
(`core/includes/admin/admin-pages.php:28`).

| Champ | Type | Défaut |
|---|---|---|
| Liste d'autorisation globale | zone de texte, une entrée par ligne | vide |
| Liste de blocage d'inscription | zone de texte, une entrée par ligne | vide |
| Message d'erreur de blocage | ligne de texte | voir §7.4 |
| Proxys de confiance | zone de texte, une entrée par ligne, même grammaire que les listes | vide |
| En-tête de transfert à lire | liste de choix : aucun, `X-Forwarded-For`, `CF-Connecting-IP`, `True-Client-IP`, `X-Real-IP` | aucun |
| Supprimer les réglages à la désinstallation | case à cocher | décochée |

L'écran affiche en outre, en lecture seule (FNC-29) : l'adresse retenue pour la
requête en cours, sa source, et le cas échéant la liste des entrées rejetées
avec leur numéro de ligne et le motif.

Le champ « en-tête de transfert » est une liste fermée et non une saisie libre :
un nom d'en-tête arbitraire est une surface d'erreur sans bénéfice, et les
quatre valeurs proposées couvrent les hébergements et CDN courants. Elle reste
sur « aucun » par défaut, ce qui est l'application de R-01.

### §7.2 Champ sur l'écran d'un niveau d'adhésion

Une zone de texte, ajoutée par `rcp_edit_subscription_form`
(`core/includes/admin/subscriptions/edit-subscription.php:164`) et
`rcp_add_subscription_form`
(`core/includes/admin/subscriptions/subscription-levels.php:195`). Le libellé
énonce la non-transitivité (FNC-04) : l'autorisation ne vaut que pour les
contenus restreints à ce niveau.

### §7.3 Boîte de métadonnées de contenu

Une zone de texte dans la boîte de restriction de la dépendance, par
`rcp_metabox_fields_after` (`core/includes/admin/metabox.php:81`). Elle n'est
rendue qu'à qui peut modifier le contenu, et son enregistrement exige nonce et
capacité (SEC-02).

### §7.4 Chaînes

| Chaîne | Valeur par défaut |
|---|---|
| Message de blocage | « L'inscription n'est pas possible depuis cette connexion. » |
| Titre de l'écran de réglages | « Restriction par adresse IP » |
| Avis d'entrée rejetée | « Ligne %d ignorée : %s » |

Le message de blocage par défaut est délibérément avare : il ne nomme ni
l'adresse, ni la règle, ni l'existence d'une liste (FNC-11, SEC-17). Il est
traduisible, et s'il est personnalisé par l'administrateur, il est échappé à la
sortie sans être interprété comme du balisage (SEC-03).

### §7.5 Front

Aucun écran, aucune feuille de style, aucun script. La seule manifestation
visible au front est le message d'erreur du formulaire d'inscription, rendu par
la mécanique d'erreurs de la dépendance : `rcp_process_registration()` collecte
les erreurs de `rcp_errors()` et les renvoie par
`wp_send_json_error()` avec `rcp_get_error_messages_html( 'register' )`
(`core/includes/registration-functions.php:151-166`). Le message apparaît donc
dans le formulaire sans rechargement de page — point que la documentation amont
laissait indéterminé, et que la lecture du code tranche.

---

## §8 Points d'extension

Tous préfixés par la forme longue (ADR-0001). Les paramètres sont donnés dans
l'ordre.

### §8.1 Filtres

| Filtre | Paramètres | Usage |
|---|---|---|
| `ip_restriction_for_restrict_content_client_address` | `Client_Address|null $address` | Dernier mot sur l'adresse retenue, pour un hébergement dont l'en-tête n'est pas dans la liste fermée du §7.1 |
| `ip_restriction_for_restrict_content_trusted_proxies` | `list<string> $proxies` | Déclarer les proxys de confiance par code plutôt que par réglage, par exemple depuis `wp-config.php` |
| `ip_restriction_for_restrict_content_rules` | `list<string> $rules`, `string $scope`, `int|null $scope_id` | Compléter une liste par une source externe |
| `ip_restriction_for_restrict_content_match` | `Match_Result $result`, `Client_Address $address` | Observer ou restreindre une correspondance ; ne peut que **retirer** une correspondance, jamais en ajouter |
| `ip_restriction_for_restrict_content_access_decision` | `Access_Decision $decision`, `Client_Address $address`, `int $post_id` | Dernier mot sur la décision d'accès |
| `ip_restriction_for_restrict_content_registration_blocked` | `bool $blocked`, `Client_Address $address` | Dernier mot sur le blocage d'inscription |
| `ip_restriction_for_restrict_content_blocked_message` | `string $message`, `Client_Address $address` | Message affiché ; le retour est échappé |

`ip_restriction_for_restrict_content_match` ne peut que retirer une
correspondance : un filtre qui pourrait en ajouter transformerait tout plugin
tiers en fournisseur d'accès au contenu payant, et mettrait le contrôle hors de
portée de l'administrateur qui a rempli les listes. La contrainte est vérifiée
par un test.

### §8.2 Actions

| Action | Paramètres | Usage |
|---|---|---|
| `ip_restriction_for_restrict_content_access_granted` | `Access_Decision $decision`, `int $post_id` | Journalisation par l'exploitant |
| `ip_restriction_for_restrict_content_registration_blocked` | `Client_Address $address` | Idem |
| `ip_restriction_for_restrict_content_rule_rejected` | `string $line`, `int $number`, `string $reason` | Alimenter une supervision sur les réglages fautifs |

Ces actions ne transportent que ce qui est nécessaire, et l'objet
`Client_Address` est expurgé avant toute écriture par le plugin lui-même
(SEC-06). Ce qu'un exploitant journalise ensuite relève de sa responsabilité.

---

## §9 Internationalisation

| Réf. | Règle |
|---|---|
| I18N-01 | Toute chaîne visible passe par une fonction de traduction avec le text domain `ip-restriction-for-restrict-content` |
| I18N-02 | Aucune concaténation : les valeurs variables passent par `sprintf` avec arguments numérotés et commentaire `translators:` |
| I18N-03 | Le `.pot` est régénéré par `make pot` et sa fraîcheur vérifiée en intégration continue, en ignorant la ligne `POT-Creation-Date` (JE-004) |
| I18N-04 | Le message de blocage personnalisé par l'administrateur n'est pas traduit : c'est sa chaîne. Seul le défaut l'est |

---

## §10 Exigences de sécurité

Une ligne par exigence, avec la suite qui la couvrira. Le détail et l'état réel
de chacune sont tenus dans `docs/securite.md`.

| Réf. | Exigence | Couverte par |
|---|---|---|
| SEC-01 | Nonce **et** capacité `rcp_manage_settings` sur l'enregistrement des réglages ; la capacité est vérifiée après le nonce | `access` |
| SEC-02 | Nonce **et** capacité `edit_post` sur l'enregistrement de la liste d'un contenu ; test de contournement par identifiant d'une autre publication | `access` |
| SEC-03 | Assainissement à l'entrée, échappement à la sortie ; le message personnalisé porteur de balises est rendu inerte | `access` |
| SEC-04 | Aucune requête SQL directe : tout passe par les API d'options et de métadonnées | `unit` |
| SEC-05 | Aucun secret ni adresse de courriel dans le code livré | `unit`, `bin/scan-secrets.sh` |
| SEC-06 | Expurgation avant journalisation : aucune adresse complète dans un message écrit par le plugin | `unit` |
| SEC-07 | Par défaut, l'adresse évaluée est `REMOTE_ADDR` seul ; aucun en-tête HTTP n'est lu | `matching` |
| SEC-08 | Un en-tête de transfert n'est lu que si `REMOTE_ADDR` correspond à un proxy de confiance déclaré | `matching` |
| SEC-09 | L'en-tête est lu de droite à gauche en sautant les proxys déclarés ; une valeur injectée à gauche n'est jamais retenue | `matching` |
| SEC-10 | Fermeture sur l'inconnu : une règle illisible, une adresse absente ou une famille discordante n'accordent jamais d'accès | `matching` |
| SEC-11 | Une entrée rejetée est signalée par un avis persistant, la liste de blocage comprise, dont l'échec est silencieux | `access` |
| SEC-12 | Une règle qui désigne l'ensemble des adresses est refusée à l'écriture et ignorée à la lecture | `matching` |
| SEC-13 | Une autorisation accorde la lecture d'un contenu restreint et aucune capacité WordPress | `access` |
| SEC-14 | Une réponse servie à un visiteur autorisé n'est pas mise en cache pour les autres : le plugin marque la requête comme non cachable dès qu'une autorisation s'applique | `access` |
| SEC-15 | `defined( 'ABSPATH' ) || exit;` en tête de chaque fichier PHP livré | `unit` |
| SEC-16 | Aucun appel réseau sortant | `integration`, `bin/scan-secrets.sh` |
| SEC-17 | Réponse minimale : le message de blocage ne révèle ni l'adresse, ni la règle, ni l'existence d'une liste | `access` |
| SEC-18 | Cloisonnement test / production : aucun code de test dans l'archive, aucune suite exécutable sans `--testsuite` | `unit` |

SEC-07 à SEC-09 forment ensemble l'exigence centrale du plugin. Une liste
d'autorisation qui accorde l'accès sur la foi d'un en-tête falsifiable est un
contournement d'authentification, au même titre qu'un mot de passe accepté sans
vérification. Elles sont traitées comme telles : trois exigences distinctes,
trois familles de tests, et un scénario d'attaque nommé par test.

SEC-14 mérite sa place ici. Un site qui met ses pages en cache et qui sert à un
visiteur autorisé la version complète d'un contenu restreint expose cette
version à tous les visiteurs suivants servis par le cache — le contournement se
propage alors sans qu'aucune liste ne le décide. L'exigence n'est pas de
désactiver le cache, mais de ne jamais laisser une réponse élargie par une
autorisation entrer dans un cache partagé.

---

## §11 Tests

### §11.1 Suites

Cinq suites, déclarées dans `phpunit.xml.dist`. Elles **ne partagent pas de
processus** : la suite `unit` remplace les fonctions de WordPress par Brain
Monkey, les autres les chargent. Les exécuter dans une même invocation fait
échouer Patchwork avec « DefinedTooEarly ». `tests/bootstrap.php` refuse donc
explicitement une invocation sans `--testsuite`, et la couverture se fusionne a
posteriori.

| Suite | Contexte | Rôle |
|---|---|---|
| `unit` | WordPress mocké | Logique pure et outillage, sous la seconde |
| `integration` | WordPress réel | Amorçage, écrans, persistance des réglages |
| `contract` | Dépendance réelle | Les accroches du §5.1 existent et se déclenchent |
| `matching` | WordPress réel | Moteur de correspondance et résolution d'adresse |
| `access` | WordPress réel | Contournement des restrictions et blocage d'inscription |

### §11.2 Périmètre de chaque suite

**`unit`** — grammaire et analyseur (§4.2), normalisation des quatre formes en
intervalles (§4.3), décision sur un ensemble (§4.4), immuabilité et absence de
mutation d'argument, hygiène du dépôt et honnêteté du `Makefile`. Déjà peuplée
au lot 0 par `RequirementsNoticeTest`, `MakefileTest`,
`RepositoryHygieneTest`, `ScannersTest`.

**`integration`** — le plugin s'amorce dans un WordPress réel sans émettre
d'erreur PHP, les écrans se rendent, les réglages survivent à un aller-retour
d'enregistrement, aucune requête HTTP sortante n'est tentée. Déjà peuplée par
`BootTest`.

C'est aussi la suite qui établit P-01 à P-04, livrés au lot 1
(`tests/integration/ProbePersistenceTest.php`) : après amorçage, le plugin n'a
écrit **aucune** option et **aucun** transient, pas davantage au second
amorçage ; le contrôle est bien accroché à `plugins_loaded` en priorité 20 ;
chaque amorçage produit un constat neuf ; l'option de branche de la dépendance
peut être retournée sans que le verdict du plugin en dépende — il lit des
capacités, pas une option (B-02) ; et un diagnostic de branche historique
produit bien l'avis de E-05, avec sa cause et son action de retour.

**Ce qu'un processus unique ne peut pas établir** : PHP ne permet pas de retirer
une fonction déjà définie. La disparition effective de l'interface amont entre
deux requêtes n'est donc pas reproductible dans une suite. Elle est établie
autrement, et de deux côtés : la suite `contract` exécute le mécanisme de
bascule amont lui-même et constate qu'il retourne l'option dans les deux sens,
sous capacité et sous nonce (`tests/contract/BranchSwitchContractTest.php`) ; et
la recette du §11.7 rejoue la bascule sur le site Docker, sur deux requêtes
réelles. Dire que la suite l'établit seule serait faux, et c'est exactement le
genre d'affirmation que ce document existe pour empêcher.

Ces tests sont la seule chose qui empêche quelqu'un d'« optimiser » la sonde en
la mémorisant (§5.2.1).

**`contract`** — une accroche par test, établie **par l'exécution** et non par
introspection.

Son **premier** test ne porte sur aucune accroche : il vérifie que la branche
chargée est bien la branche moderne (§1.1). Il échoue avec un message qui nomme
la cause — branche historique chargée, API absente — plutôt que de laisser une
suite métier échouer plus loin sur une fonction introuvable. Sans ce test, un
environnement mal provisionné produit une cascade d'échecs dont aucun ne
désigne la vraie cause.

Le reste de la suite : Pour chaque ligne du tableau §5.1 : brancher un témoin, provoquer
le chemin, constater l'appel. Cette suite est le seul endroit qui puisse
établir que `rcp_member_can_access` est bien appliqué sur le chemin déconnecté
dans la variante **gratuite** — le stub ne le dit pas. Elle s'exécute contre les
deux variantes : `make test-contract` pour la gratuite, `make test-contract-pro`
pour la commerciale.

**`matching`** — le cœur. Pour chaque format de FNC-13 à FNC-18 : une adresse
qui correspond, une adresse limitrophe qui ne correspond pas, et les deux bornes
d'un intervalle. Pour chaque contrainte de G-01 à G-07 : l'entrée est rejetée et
le motif est celui attendu. Pour la résolution d'adresse : le jeu de scénarios
d'attaque du §11.3.

**`access`** — les trois portées et leur non-transitivité (FNC-01 à FNC-04), le
blocage d'inscription et son message (FNC-07 à FNC-11), l'asymétrie des deux
listes (A-01 à A-03), les exigences d'autorisation SEC-01 à SEC-03 et SEC-11 à
SEC-17.

### §11.3 Scénarios d'attaque, un test chacun

Cette table est la traduction de SEC-07 à SEC-09 en tests. Elle n'est pas
indicative : chaque ligne est un test nommé.

| Scénario | Configuration | Attendu |
|---|---|---|
| En-tête injecté sans proxy déclaré | Aucun proxy de confiance ; requête portant `X-Forwarded-For: <adresse autorisée>` | L'adresse retenue est `REMOTE_ADDR` ; aucun accès accordé |
| En-tête `Client-Ip` injecté | Idem, avec `Client-Ip` | Idem |
| Proxy déclaré, mais requête directe | Proxy de confiance `203.0.113.7` ; requête depuis `198.51.100.9` portant l'en-tête | L'en-tête est ignoré ; l'adresse retenue est `198.51.100.9` |
| Chaîne préfixée par le client | Proxy déclaré ; en-tête reçu `<adresse autorisée>, <adresse réelle>` | L'adresse retenue est `<adresse réelle>` (R-04) |
| Chaîne entièrement composée de proxys | Proxy déclaré ; en-tête ne contenant que des proxys déclarés | Adresse indéterminable ; aucun accès accordé (R-05) |
| Valeur d'en-tête non analysable | Proxy déclaré ; en-tête `bonjour` | Adresse indéterminable ; aucun accès accordé |
| Règle universelle | Liste contenant `*` | Refusée à l'écriture, ignorée à la lecture (SEC-12) |
| Famille discordante | Liste IPv4 ; visiteur IPv6 | Aucune correspondance, aucune erreur |
| Portée croisée | Autorisation sur le niveau « Argent » ; contenu réservé à « Or » | Accès refusé (FNC-04) |
| Confusion de liste | Adresse dans la liste de blocage seule ; contenu restreint | Aucun accès accordé, et l'inscription est bloquée (A-01) |
| Autorisation et capacités | Adresse autorisée ; tentative d'accès à l'administration | Refusée (SEC-13) |

### §11.4 Fixtures réelles

Les charges utiles fabriquées à la main mentent. Pour ce plugin, les fixtures
réelles à capturer et versionner sont :

| Fixture | Ce qu'elle établit | Origine |
|---|---|---|
| Jeux d'en-têtes `$_SERVER` réels derrière un proxy | La forme exacte de la chaîne de transfert, qui n'est pas celle qu'on imagine | Capture sur un site derrière CDN ; à défaut, fixture nommée `synthetic-*` et remplacée dès qu'une capture est possible |
| Soumission réelle du formulaire d'inscription | La forme de la charge `$_POST` et le canal de retour des erreurs | Capture sur l'environnement Docker du dépôt |
| Structure d'un niveau d'adhésion et de ses métadonnées | Les clés et les types réels de la dépendance | Environnement Docker, variantes gratuite et commerciale |
| Réglages exportés de listes longues | Le comportement de l'analyseur sur des listes de production | Listes anonymisées, adresses réécrites dans les plages de documentation de la RFC 5737 |

Aucune fixture ne contient d'adresse réelle attribuée : les exemples utilisent
`192.0.2.0/24`, `198.51.100.0/24` et `203.0.113.0/24` (RFC 5737), et
`2001:db8::/32` (RFC 3849). Les adresses des exemples de la documentation amont
sont conservées telles quelles en annexe A, parce que ce sont des citations.

### §11.5 Pas de fausse complétion

| Interdit | Motif |
|---|---|
| `markTestSkipped`, `markTestIncomplete` | Un test qui ne s'exécute pas masque toujours une faiblesse de conception. Rendre le test exécutable partout, au besoin en lisant la configuration depuis l'environnement |
| `.only` | Idem |
| `TODO:`, `FIXME:`, `XXX:` | Une question ouverte se note en §15, pas en marqueur dans le code |
| Branche non implémentée dans le code livré | Un chemin qui ne fait rien est indistinguable d'un chemin correct, jusqu'à l'incident |
| `@phpstan-ignore` | Écrire le stub manquant ou corriger la cause (§12.4) |
| Identifiants de l'add-on commercial | §0.3 |

Ces interdits sont vérifiés par `bin/scan-placeholders.sh`, bloquant en
intégration continue et couvert par `tests/unit/Tooling/ScannersTest.php`.

Une précision qui compte, tirée de JE-001 : **une suite vide sort en succès**.
Un job qui exécute une suite non peuplée est donc un faux signal. Les suites
`matching` et `access` ne seront déclarées dans l'intégration continue qu'au lot
où leurs premiers tests les peupleront ; la suite `contract` l'est depuis le
lot 1, par les jobs `contract-free` et `contract-pro`.

### §11.6 Seuils de couverture

| Périmètre | Plancher | Contrôle |
|---|---|---|
| `src/` entier | 80 % des lignes | `bin/check-coverage.sh` sur un rapport Clover fusionné |
| Moteur de correspondance et résolution d'adresse | 100 % des lignes et des branches | Même script, plancher propre au répertoire |
| Évolution d'une version à l'autre | Aucune régression | `.coverage-baseline` |

Le plancher renforcé sur le moteur n'est pas du zèle : c'est le seul composant
dont un défaut se traduit directement par un accès accordé à qui ne devait pas
l'avoir, et c'est aussi le seul qui soit entièrement pur, donc le seul où 100 %
soit atteignable sans contorsion.

**Comment les branches sont mesurées.** Le rapport Clover n'en transporte pas
l'information : `bin/lib/check-branches.php` relit la mesure sérialisée
fusionnée de php-code-coverage. La couverture de chemins d'Xdebug demande en
outre de restreindre l'instrumentation à `src/`, faute de quoi le processus est
tué par le système sur une suite qui charge WordPress (JE-018).

Ce plancher a produit un effet qu'on n'attendait pas : sa première mesure a
débusqué seize branches qu'**aucun test ne pouvait prendre**, toutes du code
défensif inatteignable. Elles ont été supprimées, et non annotées (JE-019). Une
seule exclusion subsiste, structurelle : la garde `ABSPATH` de SEC-15, dont la
seconde branche arrête le processus.

### §11.7 Scénarios d'activation

| Scénario | Attendu |
|---|---|
| Activation sans la dépendance | Avis d'administration nommant la dépendance ; aucune erreur fatale ; site inchangé |
| Activation avec la variante gratuite | Toutes les fonctionnalités s'enregistrent |
| Activation avec la variante commerciale | Idem |
| Désactivation de la dépendance, plugin actif | Avis ; aucune erreur fatale ; les accès conférés cessent |
| Réactivation | Les réglages sont retrouvés intacts |
| Activation sur PHP ou WordPress sous le plancher | Avis nommant la version requise et la version installée |
| **Bascule de branche en cours de vie** | La dépendance reste active, mais son option de branche est retournée vers la branche historique : dès la requête suivante, les fonctionnalités ne s'enregistrent plus, l'avis de E-05 apparaît, et aucune erreur fatale n'est émise. Retour en arrière : tout refonctionne, sans réactivation |
| Activation sur un site multisite | Les réglages sont propres à chaque site du réseau |

Ces huit scénarios sont des tests d'acceptation avec capture des erreurs PHP : ce que
« aucune erreur fatale » établit, c'est qu'aucun `E_ERROR`, `E_WARNING` ni
`E_DEPRECATED` n'a été émis au passage.

---

## §12 Environnement et outillage

### §12.1 Cloisonnement test / production et dépendances propriétaires

| Réf. | Règle |
|---|---|
| T-01 | L'archive commerciale de Restrict Content Pro **n'est jamais versionnée**. `.gitignore` bloque `vendor-plugins/` et les archives ; l'exploitant dépose son archive ou désigne un répertoire par `RCP_PRO_SOURCE` |
| T-02 | Le job d'intégration continue qui teste contre la variante commerciale ne s'exécute que si un secret de dépôt le permet, en `continue-on-error`, pour ne pas bloquer une contribution externe |
| T-03 | La variante gratuite est téléchargée depuis WordPress.org par `bin/install-wp-tests.sh` : la chaîne publique de test est reproductible sans licence |
| T-04 | La base de test est séparée de la base de développement, dans un service distinct |
| T-05 | Aucun code de test n'entre dans l'archive distribuable |
| T-06 | Les ports sont paramétrés par `.env`, jamais en dur. Le port de développement de ce projet est **8082** |

### §12.2 Makefile honnête

L'aide d'une cible décrit ce que la cible **fait réellement**. Une cible qui
annonce un outil qu'elle n'exécute pas est un mensonge, et une cible qui
référence un script inexistant est une panne différée. Les deux sont vérifiés
par `tests/unit/Tooling/MakefileTest.php`.

Cibles utiles au quotidien :

| Cible | Ce qu'elle fait |
|---|---|
| `make up` | Démarre la pile et provisionne le site |
| `make test-matching` | Suite du moteur de correspondance |
| `make test-access` | Suite du contournement et du blocage |
| `make test-contract` | Accroches contre la variante gratuite |
| `make test-contract-pro` | Accroches contre la variante commerciale |
| `make ci` | Enchaîne lint, analyse, scanners, toutes les suites, seuil de couverture |

### §12.3 Analyse statique et stubs

PHPStan au niveau 5 minimum, avec `szepeviktor/phpstan-wordpress` et les stubs
de `stubs/`.

### §12.4 Stubs générés, jamais édités ; aucune erreur d'analyse masquée

| Réf. | Règle |
|---|---|
| S-01 | `stubs/` est produit par `bin/generate-stubs.sh` depuis la dépendance installée. Un stub édité à la main cesse de décrire la réalité qu'il prétend décrire |
| S-02 | Le diff d'une régénération se relit : c'est un signal de rupture amont |
| S-03 | Une erreur d'analyse ne se fait jamais taire — ni par `@phpstan-ignore`, ni par un cast, ni par un élargissement de type. On écrit le stub manquant ou on corrige la cause |
| S-04 | `bin/scan-placeholders.sh` bloque sur `@phpstan-ignore` |

---

## §13 Lots de livraison

Chaque lot énonce ce qu'il livre et **ce qui l'atteste**. Un lot n'est livré que
si son attestation a tourné.

| Lot | Contenu | Atteste |
|---|---|---|
| **0 — livré** | Outillage reproductible, amorçage conditionnel, contrôle des prérequis, avis d'administration, cinq suites déclarées, scanners, intégration continue | `make test-unit` et `make test-integration` verts ; `bin/scan-secrets.sh` et `bin/scan-placeholders.sh` verts ; `make lint` et `make stan` verts |
| **1 — livré contre la variante gratuite** | Suite `contract` peuplée : contrôle de la branche chargée (§1.1), puis une accroche du §5.1 par test, contre les deux variantes. Sonde de capacités étendue et non mémorisée (§5.2.1), avec le libellé d'avis de E-05. Écran d'état des capacités | 36 tests `contract` passent contre Restrict Content 4.0.5 ; P-01 à P-04 sont établis dans la suite `integration` autant qu'un processus unique le permet (§11.2) ; les jobs `contract-free` et `contract-pro` sont déclarés. **Reste** : exécuter la suite contre la variante commerciale, ce qui lèvera V-10 ; V-11 demande un site 2.x provisionné ; V-13 est surveillé par un test |
| **2 — livré** | Grammaire, analyseur, normalisation des quatre formes, IPv4 et IPv6, décision sur un ensemble. Aucun branchement WordPress | Suite `matching` : 93 tests ; chaque format de FNC-13 à FNC-18 et chaque contrainte de G-01 à G-07 ont le leur, avec le motif de refus attendu. 100 % des lignes **et des branches** sur `src/Address/` et `src/Rule/`, mesuré par `bin/lib/check-branches.php`. Un test de la suite `unit` échoue si un appel à WordPress apparaît dans ces répertoires. **Reste** : la résolution de l'adresse du visiteur, qui est le lot 3 |
| **3 — résolution de l'adresse** | `Client_Address_Resolver`, proxys de confiance, lecture de droite à gauche, source de l'adresse | Suite `matching` : les onze scénarios du §11.3 passent. SEC-07 à SEC-10 et SEC-12 couvertes |
| **4 — réglages globaux** | Écran de réglages, les deux listes, message d'erreur, proxys, validation à l'écriture, signalement des entrées rejetées, écran de diagnostic FNC-29 | Suites `integration` et `access` : aller-retour d'enregistrement, nonce et capacité exigés, entrée rejetée signalée. SEC-01, SEC-03, SEC-11 couvertes |
| **5 — autorisation globale** | Branchement sur `rcp_member_can_access`, chemins connecté et déconnecté, contenus | Suite `access` : FNC-01, FNC-05, FNC-06, FNC-30, FNC-31. SEC-13, SEC-14 couvertes |
| **6 — blocage d'inscription** | Branchement sur `rcp_form_errors`, message personnalisé, échappement | Suite `access` : FNC-07 à FNC-12, A-01 à A-03. SEC-17 couverte |
| **7 — portée par niveau d'adhésion** | Champ sur les écrans de niveau, stockage en métadonnée de niveau, décision limitée aux contenus du niveau | Suite `access` : FNC-02 et surtout FNC-04, le test de portée croisée |
| **8 — portée par contenu** | Boîte de métadonnées, stockage en métadonnée de publication, nonce et capacité | Suite `access` : FNC-03, SEC-02 avec son test de contournement par identifiant |
| **9 — taxonomies** | Couverture des restrictions de terme par `rcp_get_term_restrictions`, avec les garde-fous du §5.1.1 | Suite `access` : un contenu restreint par catégorie est accessible à une adresse autorisée ; aucun effet indirect constaté sur la même requête. V-06 tranché |
| **10 — livraison** | Désinstallation, `.pot`, `readme.txt`, construction de l'archive, documentation utilisateur | `make build` ; ouverture de l'archive : aucun fichier de test, aucune dépendance de développement ; `make check-coverage` au-dessus du plancher |

Ce qui n'est **pas** dans ces lots, et qui doit le rester : une interface
d'import de listes, un journal d'audit, une détection de VPN. Chacun de ces
sujets est un plugin, pas une fonctionnalité de celui-ci.

---

## §14 Livraison

### §14.1 Versionnage

Versionnage sémantique. La version est déclarée à deux endroits — l'en-tête du
plugin et la constante `RCIPR_VERSION` — et leur égalité est vérifiée par la
suite `integration` (`tests/integration/BootTest.php`) aussi bien que par
`bin/build.sh`. Le `CHANGELOG.md` suit Keep a Changelog.

### §14.2 Contenu de l'archive

| Inclus | Exclu |
|---|---|
| `ip-restriction-for-restrict-content.php`, `uninstall.php` | `tests/`, `docs/`, `docker/`, `bin/`, `stubs/`, `vendor-plugins/` |
| `src/`, `templates/`, `assets/`, `languages/` | `phpunit.xml.dist`, `phpcs.xml.dist`, `phpstan.neon.dist`, `.env.example`, `docker-compose.yml`, `Makefile` |
| `readme.txt`, `LICENSE` | `composer.json`, `composer.lock`, `.github/` |
| `vendor/autoload.php` et le strict nécessaire au chargement automatique | Le reste de `vendor/`, et toute dépendance de développement |

L'archive ne charge aucun code depuis un serveur externe (directive 8 du
répertoire WordPress.org) : l'autoloader est embarqué, et le fichier principal
porte déjà un autoloader PSR-4 de repli pour qu'une copie de travail sans
`vendor/` reste activable.

### §14.3 Nommage et non-affiliation — deux exigences distinctes

Le plugin est destiné au répertoire WordPress.org. Deux exigences s'y appliquent,
que la ressemblance de sujet fait souvent confondre. **Satisfaire l'une ne
dispense jamais de l'autre.**

| | Conformité du slug | Mention de non-affiliation |
|---|---|---|
| Ce qu'elle traite | La **forme** du nom : ce qui est en tête du slug | L'**impression** produite : qui édite ce plugin |
| Ce qu'elle empêche | Qu'un slug s'approprie la marque d'autrui | Qu'un utilisateur croie installer un produit officiel de l'éditeur |
| Qui l'impose | Directive 17 du répertoire | Le droit des marques, et l'honnêteté envers l'utilisateur |
| Où elle s'exerce | Le slug, une fois | La description de l'en-tête, le `readme.txt`, le `README.md` |
| État | **Satisfaite** : le slug commence par `ip-restriction`, une fonction générique (ADR-0008) | **Satisfaite** : mention présente dans l'en-tête du plugin (ligne 5) et en tête du `README.md` |

La formulation retenue, identique aux deux endroits :

> Extension indépendante, ni développée ni approuvée par les éditeurs de
> Restrict Content ou de Restrict Content Pro.

**Avertissement au successeur.** Constater que le slug est conforme n'autorise
pas à retirer la mention : un slug conforme ne dit rien de l'éditeur du plugin.
Réciproquement, ajouter la mention ne rendrait pas conforme un slug qui
commencerait par une marque. Les deux se vérifient séparément, au lot 10.

---

---

## §15 Points ouverts

Deux familles : `O-xx` décisions à prendre, `V-xx` vérifications à mener. Cette
section est ce qui empêche de présenter une hypothèse comme un acquis.

### §15.1 Décisions

| Réf. | Décision | État | Options et conséquences |
|---|---|---|---|
| **O-01** | Forme du préfixe | **tranchée** | ADR-0001. Forme longue pour les symboles globaux et les hooks, forme courte `RCIPR_` pour les constantes, métas et chaînes de balisage |
| **O-02** | Planchers PHP et WordPress | **tranchée** | ADR-0002. PHP 8.1, WordPress 6.4 |
| **O-03** | Slug et marque tierce (directive 17 de WordPress.org) | **tranchée le 22 septembre 2026** | ADR-0008. **Le slug est conservé.** La directive 17 n'interdit que le **début** du slug : `ip-restriction-for-restrict-content` commence par une fonction générique, et la mention de la marque après « for » est précisément la forme recommandée. Renommer se serait écarté de la recommandation pour se prémunir d'une règle inapplicable, au prix de la découvrabilité et d'une migration de text domain, de namespace et de préfixes (ADR-0001). La mention de non-affiliation est **maintenue** : elle traite une exigence distincte (§14.3). Révision si le produit amont change de nom ou si l'éditeur réclame |
| **O-04** | Stockage du réglage de niveau | **tranchée** | ADR-0004. Métadonnée de niveau via l'API de la dépendance |
| **O-05** | Étendue du support IPv6 | **tranchée** | ADR-0005. Littéral et CIDR IPv6 ; jokers et plages à bornes jokers **refusés** en IPv6 |
| **O-06** | Sémantique de la plage à bornes jokers | **tranchée provisoirement, et implémentée au lot 2** | §4.3 : la borne gauche prend son minimum, la borne droite son maximum. La documentation amont donne l'exemple `149.58.*-149.60.*` sans le définir. Autre lecture possible : intersection du joker et de la plage, qui exclurait `149.59.x.x`. La lecture retenue est la seule compatible avec le caractère inclusif documenté des plages, mais elle n'est pas établie par la source. Le lot 2 l'a écrite et la fixe par un test nommé : `149.58.*-149.60.*` accepte `149.58.0.0`, `149.59.1.1` et `149.60.255.255`, refuse `149.57.255.255` et `149.61.0.0`. **Ce qui reste dû est la documentation utilisateur** : la sémantique retenue y sera énoncée, puisque la source ne la donne pas |
| **O-07** | Liste fermée ou saisie libre pour le nom de l'en-tête de transfert | **tranchée** | §7.1 : liste fermée de quatre valeurs. Une saisie libre ajoute une surface d'erreur sans bénéfice ; le filtre `ip_restriction_for_restrict_content_client_address` couvre le cas restant |
| **O-08** | Comportement sur multisite : réglages par site ou par réseau | **ouverte** | **(a)** Par site, ce que §11.7 suppose : simple, mais un réseau de cent sites se configure cent fois. **(b)** Réglage de réseau avec surcharge par site : utile, mais double le modèle de données et les écrans. **À trancher au lot 4** |

### §15.2 Vérifications

| Réf. | Vérification | Ce que la recherche établit | Recette qui la lèvera |
|---|---|---|---|
| **V-01** | Une adresse autorisée l'emporte-t-elle sur une adhésion **expirée** ? | Rien. La documentation amont ne traite pas l'interaction avec l'expiration | Sur l'environnement Docker : créer un client, lui donner une adhésion au niveau « Or », la faire expirer, inscrire son adresse dans la liste d'autorisation globale, puis demander un contenu réservé à « Or ». Refaire avec une autorisation posée sur le niveau « Or ». **Attendu retenu par ce cahier des charges** : l'autorisation l'emporte, parce que FNC-05 pose que l'accès ne dépend ni de l'état de connexion ni de l'adhésion — une adhésion expirée n'est pas un cas particulier, c'est l'absence d'adhésion. Le test qui l'établira est dans la suite `access`, lot 5. Si la recette montre que le filtre n'est pas atteint sur ce chemin — un client existe, donc le chemin passe par `class-rcp-customer.php` et non par `member-functions.php:101` —, le point remonte en contrainte de conception et l'attendu est révisé, pas contourné |
| **V-02** | Comportement réel de l'add-on amont derrière un proxy ou un CDN | Rien : la documentation amont n'en dit pas un mot. La dépendance, elle, fait confiance à `HTTP_CLIENT_IP` puis `HTTP_X_FORWARDED_FOR` (`core/includes/misc-functions.php:734-742`) | Non vérifiable sans lire le code de l'add-on, ce que §0.3 interdit. **Ce point reste donc ouvert par construction**, et c'est la raison pour laquelle ce plugin ne cherche pas à reproduire un comportement amont inconnu mais applique R-01 à R-07 |
| **V-03** | Étendue du support IPv6 amont | La documentation annonce « Both IPv4 and IPv6 are supported » sans un seul exemple IPv6 | Idem : non vérifiable sans lire le code amont. O-05 tranche pour ce plugin, et la documentation utilisateur dira exactement ce qui est accepté |
| **V-04** | Valeurs par défaut des champs amont | Déductible — champs vides — mais non affirmé par la documentation | Sans objet pour ce plugin : FNC-27 l'impose indépendamment de l'amont |
| **V-05** | Existence d'un hook ou d'un filtre propre à l'add-on amont | Aucun n'est mentionné dans la documentation officielle, et aucune page de référence développeur n'a été trouvée | Sans objet : le plugin expose ses propres points d'extension (§8) et ne prétend à aucune compatibilité de hooks avec l'amont |
| **V-06** | Effet indirect du filtre `rcp_get_term_restrictions` | Le filtre existe (`core/includes/misc-functions.php:998`), mais il modifie une donnée et non une décision (§5.1.1) | Au lot 9 : instrumenter une requête qui interroge deux fois les restrictions du même terme, une fois pour la décision d'accès et une fois pour un autre usage, et constater si la seconde lecture est altérée. Si elle l'est, la couverture des taxonomies passe en fonctionnalité optionnelle, désactivée par défaut |
| **V-07** | ~~Le filtre `rcp_member_can_access` est-il appliqué à l'identique dans la variante **gratuite** ?~~ | **Levé le 22 septembre 2026.** La source de la variante gratuite (RC 4.0.4) est lisible en local : le commentaire est à `core/includes/member-functions.php:100` et le `apply_filters` à la ligne 101, aux mêmes positions que dans la variante commerciale. Les cinq applications du chemin connecté et les trois du chemin historique sont également aux mêmes lignes (§5.1) | Le test de contrat reste, mais il n'établit plus une inconnue : il **surveille** une propriété établie. La lecture prouve que le code est là ; seule l'exécution prouve qu'il est atteint sur le chemin qui nous intéresse |
| **V-08** | Interaction avec les caches de pages et les CDN | Rien dans la documentation amont | Au lot 5 : servir un contenu restreint à une adresse autorisée derrière un cache de pages, puis le redemander depuis une adresse non autorisée. SEC-14 pose l'attendu ; la recette établit que le marquage de non-cachabilité est bien honoré par les caches courants |
| **V-09** | Absence de chevauchement avec les redirections personnalisées | La recherche conclut que l'add-on de redirection ne traite que la redirection après connexion et après inscription, par niveau d'adhésion | Sans objet pour ce plugin : les deux périmètres sont disjoints, et §0.2 le pose en hors-périmètre |
| **V-10** | Quelle valeur prend `restrict_content_chosen_version` lorsque **Restrict Content Pro** est installé à la place de la variante gratuite ? Le mécanisme de routage est-il seulement atteint ? | **Instrumenté au lot 1, pas encore relevé.** `RC_Requirements_Check` et `rc_process_legacy_switch()` sont dans `restrictcontent.php`, fichier principal de la variante **gratuite**. Le checkout commercial v3.5.51 n'a ni ce fichier ni ce mécanisme, ce qui suggère qu'il ne s'applique pas — mais l'absence d'un fichier dans un checkout n'établit pas l'absence du comportement | `tests/contract/BranchSwitchContractTest.php` affirme, pour chaque variante, ce qui doit être vrai : contre la gratuite, que le mécanisme existe et retourne l'option dans les deux sens ; contre la commerciale, qu'il est absent et que l'option de branche n'apparaît pas du fait de la dépendance. Les deux affirmations sont écrites et la première est vérifiée ; la seconde attend une exécution de `make test-contract-pro`, donc l'archive commerciale. Aucun test n'est ignoré : c'est la même suite qui conclut, selon la variante qu'elle constate |
| **V-11** | Lors d'une mise à jour depuis une version 2.x historique, l'option est-elle écrite rétroactivement, et le site peut-il en sortir ? | **Partiellement levé, et inchangé au lot 1.** Le code établit que la valeur enregistrée prime à chaque amorçage (`restrictcontent.php:189-199`), et le lot 1 établit **par exécution** que la sortie est possible dans les deux sens, sous capacité et sous nonce (`BranchSwitchContractTest`) — le site n'y reste donc pas « indéfiniment ». Reste inconnu : le comportement de la **première** mise à jour depuis une 2.x, et sur quelle valeur l'option est alors écrite | Provisionner un site portant `rc_settings` et une méta `rcp_user_level`, mettre à jour, relever la valeur retenue. Rien dans le code de ce plugin n'en dépend : il ne lit pas cette option (B-02, et un test d'intégration l'établit) |
| **V-12** | ~~Existe-t-il un chemin d'interface permettant de basculer d'une branche à l'autre ?~~ | **Levé le 22 septembre 2026, et la réponse va plus loin que la question.** Oui : `rc_process_legacy_switch()` (`restrictcontent.php:676`), gestionnaire AJAX enregistré sur `wp_ajax_rc_process_legacy_switch` (ligne 731), protégé par `manage_options` (ligne 680) et le nonce `rc_process_legacy_nonce` (ligne 681), bascule l'option **dans les deux sens** (lignes 695 et 706). Le choix de branche est donc réversible à tout moment, et non figé à la première activation | Conséquences portées en exigences : B-04 (§1.1), P-01 à P-04 (§5.2.1), E-05 (§5.3), et le scénario de bascule du §11.7 |
| **V-13** | Le défaut SQL de `restrict_content_check_posts_for_shortcode()` a-t-il été signalé ou corrigé après la 4.0.4 ? | **Toujours ouvert, et désormais surveillé.** Le défaut est vérifié dans la source gratuite elle-même — déclaration à `restrictcontent.php:243`, `CONTAINS` fautifs aux lignes suivantes (JE-003) — et il est **toujours présent en 4.0.5** : l'erreur SQL s'affiche à chaque amorçage de la suite. Ce qu'on ignore est ce qu'en feront les versions ultérieures | `BranchSwitchContractTest::test_the_upstream_routing_defect_is_still_present()` échouera le jour où le défaut sera corrigé, et le déclencheur quotidien de la suite `contract` s'exécute contre la dernière version publiée. Aucun code de ce plugin n'en dépend (B-03) |

---

## Annexe A — Correspondance fonctionnelle avec l'add-on de référence

Une ligne par fonctionnalité documentée de l'add-on commercial, avec la citation
qui l'établit et le traitement retenu ici. Aucune de ces lignes ne provient de
son code : §0.3.

| Fonctionnalité documentée | Citation | Traitement ici |
|---|---|---|
| Contournement des restrictions par liste d'autorisation | « Any IP addresses on the whitelist will be able to bypass content restrictions and gain full access to restricted content, even if they don't meet the requirements. » | FNC-01 à FNC-06 |
| Blocage d'inscription par liste de blocage | « Any IP addresses on the blacklist will not be able to sign up through your Restrict Content Pro registration form. » | FNC-07 à FNC-12 |
| Portée globale | « Any IP addresses entered here will be able to access **all** material on the site » | FNC-01 |
| Portée par niveau d'adhésion | accès au seul « content that is restricted specifically to this subscription level » | FNC-02, FNC-04 |
| Portée triple | « configured on a global, subscription level, and per-post/page basis. » | FNC-01 à FNC-03 |
| Message d'erreur personnalisable | champ « Blacklist Error Message » | FNC-10, FNC-11 |
| Formats acceptés | adresse unique `226.216.136.78` ; jokers `200.122.216.*`, `186.*.*.*` ; plages inclusives `159.7.89.231-159.7.89.240` ; CIDR `10.0.0.0/24` ; joker et plage `149.58.*-149.60.*` | FNC-13 à FNC-17 |
| Familles d'adresses et format de saisie | « Both IPv4 and IPv6 are supported; one entry per line. » | FNC-18, FNC-19, O-05 |

Sources des citations :

- <https://docs.nexcess.com/software/kadence/restrict-content-pro/ip-restriction/>
  (portées, formats, deux fonctions, message d'erreur)
- <https://restrictcontentpro.com/add-on/ip-restriction/> (portée triple)

L'annonce d'origine de l'add-on
(`https://restrictcontentpro.com/blog/new-add-on-for-restricting-content-by-ip/`)
renvoyait un code HTTP 410 au moment de la recherche : son contenu n'a pas été
récupéré et n'est pas cité.

Aucune des pages consultées ne porte de numéro de version ni de date de mise à
jour : leur fraîcheur n'est pas datable. C'est une raison supplémentaire de
traiter cette annexe comme une référence fonctionnelle et non comme une
spécification.

## Annexe B — Ce que l'add-on de référence ne documente pas

Ce tableau est le complément nécessaire de l'annexe A : il dit ce que la source
ne dit pas, pour qu'aucun silence ne soit lu comme un accord.

| Sujet | État de la documentation amont | Point ouvert |
|---|---|---|
| Adresse réelle derrière un proxy ou un CDN | Aucune mention | V-02, traité par SEC-07 à SEC-09 |
| Formats IPv6 concrets, CIDR et plages IPv6 | Annoncé, jamais illustré | V-03, tranché par O-05 |
| Adhésion expirée et adresse autorisée | Aucune mention | V-01 |
| Détection de VPN ou de proxy anonymisant | Aucune mention | Hors périmètre (§0.2) |
| Valeurs par défaut des champs | Déductibles, non affirmées | V-04, tranché par FNC-27 |
| Hooks ou filtres exposés | Aucun documenté | V-05 |
| Rendu de l'erreur d'inscription | Non précisé | Tranché par lecture du code : `core/includes/registration-functions.php:151-166` |
