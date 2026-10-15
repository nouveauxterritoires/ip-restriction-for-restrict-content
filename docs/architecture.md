# Architecture

Ce document décrit la forme prévue du code. Au moment où il est écrit, les
lots 0 à 5 sont livrés : l'amorçage conditionnel, la sonde de capacités,
l'écran d'état, le moteur de correspondance, la résolution de l'adresse du
visiteur, les réglages globaux avec leur écran, et l'autorisation globale
branchée sur la dépendance. Tout le reste est une cible, pas un état. Les cases non livrées
sont signalées comme telles dans l'arborescence du §3.

Référence : cahier des charges §4 (règles de décision), §5 (intégration),
§6 (modèle de données), §8 (points d'extension).

---

## 1. Les trois couches

Tout traitement traverse trois couches, et chacune est testable seule.

| Couche | Rôle | Ce qu'elle a le droit de faire | Ce qu'elle n'a pas le droit de faire |
|---|---|---|---|
| **Transport** | Lire l'environnement de la requête et les réglages | Lire `$_SERVER`, les options, les métadonnées | Décider quoi que ce soit |
| **Décision** | Appliquer les règles du cahier des charges §4 | Recevoir des données, renvoyer un objet de valeur | Lire une option, une base, un en-tête ; écrire quoi que ce soit ; appeler une fonction de WordPress |
| **Application** | Traduire une décision en effet dans WordPress | Brancher des filtres, rendre des écrans, écrire des réglages | Contenir une règle métier |

La frontière la plus importante est celle du milieu. Le moteur de
correspondance — le cœur du plugin, cahier des charges §4.2 à §4.4 — ne sait
pas que WordPress existe. Deux conséquences pratiques : il s'exécute dans la
suite `unit` en quelques millisecondes, et il se relit sans connaître ni
WordPress ni la dépendance.

### 1.1 Pourquoi cette séparation ici en particulier

Un moteur de correspondance d'adresses couplé à WordPress serait testable
uniquement par bout-en-bout : chaque cas de la grammaire exigerait un site, une
requête, un contenu restreint. Le cahier des charges §11.6 exige 100 % de lignes
et de branches sur ce composant, et le §11.3 exige onze scénarios d'attaque. Ce
volume n'est atteignable que si chaque cas coûte une milliseconde.

C'est la même raison qui fait que la **résolution de l'adresse** est coupée en
deux : un lecteur d'environnement, qui n'a aucune règle, et un résolveur pur,
qui reçoit la chaîne de transfert et la liste des proxys et n'a aucun accès à
l'environnement. Les onze scénarios du §11.3 portent tous sur le second.

---

## 2. Le flux, de la requête à la décision

```
requête HTTP
   │
   │  [transport]
   ├── Http\Server_Environment      lit $_SERVER, ne décide rien
   ├── Settings\Settings_Repository lit l'option, les métas de niveau et de contenu
   │
   │  [décision — pur]
   ├── Address\Client_Address_Resolver ──► Address\Client_Address
   │        reçoit : adresse de connexion, chaîne de transfert, proxys déclarés
   │        renvoie : l'adresse retenue et sa provenance
   │
   ├── Rule\Rule_Parser ──► Rule\Rule_Set          texte ──► règles valides + rejets
   ├── Rule\Matcher     ──► Rule\Match_Result      adresse × règles ──► correspondance
   ├── Policy\Access_Policy ──► Policy\Access_Decision
   │        applique la non-transitivité des portées (FNC-04)
   ├── Policy\Registration_Policy ──► Policy\Registration_Decision
   │
   │  [application]
   ├── Integration\Content_Access   filtre rcp_member_can_access
   ├── Integration\Term_Access      filtre rcp_get_term_restrictions
   ├── Integration\Registration_Guard action rcp_form_errors
   └── Admin\*                      écrans, enregistrement, avis
```

Une seule flèche traverse la frontière dans le mauvais sens, et elle est
interdite : aucun objet de la couche décision ne reçoit un service de
transport en argument. Les objets de décision reçoivent des **valeurs**.

---

## 3. Arborescence prévue de `src/`

Un dossier par domaine, cohésion haute, couplage bas. Les fichiers marqués
« livré » existent ; les autres sont la cible.

```
src/
├── Plugin.php                          livré — sonde et enregistrement conditionnel
│
├── Domain/                             décision pure — prérequis et capacités
│   ├── Requirements.php                livré — verdict sur les prérequis
│   ├── Dependency_Diagnosis.php        livré — absente | branche historique | utilisable (E-05)
│   ├── Capability.php                  livré — objet de valeur : symbole, fonctionnalité, contexte, preuve
│   ├── Capability_Catalogue.php        livré — les capacités attendues, en donnée
│   └── Capability_Report.php           livré — présente | absente | non observable
│
├── Address/                            décision pure — adresse du visiteur, livré
│   ├── Ip_Number.php                   livré — entier de longueur fixe, arithmétique d'adresse
│   ├── Client_Address.php              livré — adresse + provenance + raison de l'indétermination
│   ├── Address_Source.php              livré — énumération : connexion | en-tête | indéterminée
│   └── Client_Address_Resolver.php     livré — R-01 à R-07, lecture de droite à gauche
│
├── Rule/                               décision pure — le moteur, livré
│   ├── Rule.php                        livré — intervalle + famille + texte d'origine
│   ├── Rule_Family.php                 livré — énumération : IPv4 | IPv6
│   ├── Rule_Parser.php                 livré — grammaire §4.2, contraintes G-01 à G-07
│   ├── Rule_Rejection.php              livré — ligne, numéro, motif
│   ├── Rule_Set.php                    livré — règles valides + rejets, immuable
│   ├── Matcher.php                     livré — §4.3 et §4.4
│   └── Match_Result.php                livré — correspondance + règle responsable
│
├── Policy/                             décision pure — les portées
│   ├── Scope.php                       livré — énumération : globale | niveau | contenu
│   ├── Access_Policy.php               livré — FNC-01, FNC-05 ; non-transitivité au lot 7
│   ├── Access_Decision.php             livré — accordé | non traité + portée et règle responsables
│   ├── Registration_Policy.php         FNC-07 à FNC-09
│   └── Registration_Decision.php       objet de valeur
│
├── Settings/                           transport — lecture et écriture des réglages
│   ├── Settings.php                    livré — objet de valeur : l'ensemble des réglages
│   ├── Settings_Validator.php          livré — validation à l'écriture, A-02, FNC-28
│   ├── Validated_Settings.php          livré — réglages normalisés + refus par liste
│   └── Settings_Repository.php         livré — l'option ; métas de niveau et de contenu aux lots 7 et 8
│
├── Http/                               transport — environnement de la requête
│   └── Server_Environment.php          livré — seul point du plugin qui lise $_SERVER
│
├── Integration/                        application — branchements sur la dépendance
│   ├── Capability_Probe.php            livré — sonde du §5.2, jamais mémorisée (P-01…P-04)
│   ├── Content_Access.php              livré — filtre rcp_member_can_access, FNC-31
│   ├── Cache_Guard.php                 livré — SEC-14, non-cachabilité d'une réponse élargie
│   ├── Term_Access.php                 rcp_get_term_restrictions, garde-fous du §5.1.1
│   └── Registration_Guard.php          rcp_form_errors
│
├── Admin/                              application — écrans
│   ├── Requirements_Notice.php         livré — avis de prérequis, cause et action de retour
│   ├── Capability_Status_Page.php      livré — écran d'état des capacités, lecture seule
│   ├── Capability_Labels.php           livré — libellés des fonctionnalités et des états
│   ├── Dependency_Labels.php           livré — libellés de E-05, cause constatée et retour
│   ├── Settings_Page.php               livré — FNC-24, FNC-27, FNC-29, SEC-01, SEC-03
│   ├── Rejection_Notice.php            livré — FNC-22, A-03, SEC-11
│   ├── Rejection_Labels.php            livré — un libellé par motif de refus
│   ├── Level_Field.php                 FNC-25
│   └── Post_Metabox.php                FNC-26
│
└── Support/
    ├── Redactor.php                    livré — SEC-06, expurgation avant journalisation
    └── Extension_Points.php            livré — noms des hooks du §8, en un seul endroit
```

Trois répertoires — `Address/`, `Rule/`, `Policy/` — contiennent la totalité de
la logique métier et **aucun appel à WordPress**. C'est ce périmètre qui porte
le plancher de couverture renforcé du cahier des charges §11.6, et c'est la
seule frontière qu'un test peut vérifier mécaniquement : `tests/unit/Tooling/PurityTest.php`
parcourt ces trois répertoires par analyse lexicale et échoue si un appel à une
fonction qui n'est pas interne à PHP y apparaît. Le critère est plus strict que
« aucune fonction de WordPress », délibérément : une fonction globale que le
plugin déclarerait ailleurs serait un couplage tout aussi contraire à
l'architecture.

**Les deux listes n'ont aucun chemin commun, et c'est vérifié.** A-01 n'est pas
une consigne de style : les deux listes se ressemblent — deux champs de texte,
la même grammaire, le même moteur — et n'ont pas du tout les mêmes conséquences
en cas d'erreur. Aucun accesseur de `Settings`, aucun accesseur de
`Validated_Settings` n'est donc paramétré par un nom de liste, et un test de la
suite `unit` inspecte l'interface publique pour l'établir. L'écran les rend par
deux appels distincts, avec deux champs nommés séparément.

**L'adresse du visiteur est résolue une fois par requête.** La mémorisation est
portée par l'instance du branchement, et l'instance vit le temps d'une requête :
un contenu restreint peut être évalué des dizaines de fois dans une page, et
résoudre l'adresse autant de fois n'apprendrait rien de plus. La frontière est
la même que celle de la sonde de capacités, et pour la même raison — ce qui peut
changer entre deux requêtes ne doit pas être mémorisé au-delà.

**L'arithmétique d'adresse est dans `Ip_Number`, et nulle part ailleurs.** Le
masquage d'un préfixe CIDR et la composition d'une adresse depuis ses octets
sont du calcul d'adresse : les écrire au niveau de l'octet dans `Rule` **et**
dans `Ip_Number` aurait fini par diverger. Les méthodes de `Ip_Number`
composent en outre leurs résultats sans passer par une fabrique nullable, ce
qui supprime des branches qu'aucun test ne pouvait prendre (JE-019).

---

## 4. Objets de valeur

Tous immuables : constructeur privé, fabriques statiques nommées, accesseurs en
lecture seule, aucun setter. Aucune méthode ne mute un argument reçu ; une
méthode qui reçoit un tableau en renvoie une copie modifiée, et un test le
vérifie explicitement.

| Objet | Porte | Fabriques |
|---|---|---|
| `Client_Address` | L'adresse retenue, sa provenance, et la raison de son indétermination | `from_connection()`, `from_header()`, `undetermined()` |
| `Ip_Number` | Un entier de longueur fixe et sa famille | `from_address()`, `from_ipv4_bytes()`, `min_of()`, `max_of()` |
| `Rule` | Un intervalle borné, la famille, le texte d'origine | `literal()`, `wildcard()`, `cidr()`, `range()` |
| `Rule_Rejection` | Le texte refusé, son numéro de ligne, le motif | `because()` |
| `Rule_Set` | Les règles valides et les rejets | `from_text()`, `empty()` |
| `Match_Result` | S'il y a correspondance, et par quelle règle | `matched()`, `no_match()` |
| `Access_Decision` | Si l'accès est accordé, et par quelle portée | `granted_by()`, `not_handled()` |
| `Registration_Decision` | Si l'inscription est bloquée, et le message | `blocked_with()`, `allowed()` |
| `Settings` | L'ensemble des réglages, validés | `from_array()`, `defaults()` |

Deux choix de nommage valent une justification.

**`Client_Address::undetermined()`.** Une adresse indéterminable est une valeur,
pas une absence. La représenter par `null` obligerait chaque appelant à se souvenir
de tester, et le premier oubli deviendrait une erreur fatale sur un chemin rare
— exactement le chemin d'un visiteur derrière un proxy mal configuré. Un objet
`unknown()` ne correspond à aucune règle, ce qui est la fermeture exigée par
SEC-10, et il porte la raison pour laquelle l'adresse est inconnue, ce que
FNC-29 affiche. Le même raisonnement vaut pour sa **provenance** : `source()`
n'est pas nullable, et l'énumération porte un troisième cas, « indéterminée ».
Rendre l'accesseur nullable aurait obligé chaque appelant à se souvenir de
tester, et le premier oubli serait une erreur fatale sur le chemin d'un
visiteur derrière un proxy mal configuré — c'est-à-dire le chemin rare, celui
qu'on ne voit qu'en production.

**`Access_Decision::not_handled()`.** Le plugin ne décide jamais de **refuser**
un accès : il accorde, ou il ne se prononce pas et laisse la dépendance décider
(FNC-31). Nommer le second cas « refusé » induirait en erreur le lecteur et,
tôt ou tard, l'auteur d'un branchement qui traduirait ce refus en blocage réel.

---

## 5. Points d'extension

Les noms des hooks du cahier des charges §8 sont déclarés dans
`Support\Extension_Points` sous forme de constantes, et nulle part ailleurs.
Un hook dont le nom est écrit en clair à deux endroits finit par diverger d'un
caractère, et le filtre cesse silencieusement d'être appelé. La constante rend
la divergence impossible et la renomination sûre.

Deux règles d'implémentation portent une conséquence de sécurité :

Les deux règles sont livrées au lot 5 sur la décision d'accès, et vérifiées par
trois tests : l'un établit qu'un filtre peut retirer un octroi, l'autre qu'il ne
peut pas en créer, le troisième qu'une valeur de retour qui n'est pas une
décision retire l'octroi plutôt que de le laisser passer — la fermeture sur
l'inconnu vaut aussi pour ce que renvoie un tiers.

| Règle | Motif |
|---|---|
| `ip_restriction_for_restrict_content_match` ne peut que **retirer** une correspondance | Un filtre capable d'en ajouter mettrait l'accès au contenu payant hors de portée de l'administrateur qui a rempli les listes. Le retour du filtre est intersecté avec le résultat d'origine, et un test le vérifie |
| Le retour de `ip_restriction_for_restrict_content_blocked_message` est échappé après le filtre | Un filtre est du code tiers : lui faire confiance pour l'échappement revient à ne pas échapper |

---

## 6. `Integration\Capability_Probe` — une contrainte de conception, pas une classe utilitaire

Cette classe est celle dont on aura le plus envie de « corriger » le
comportement, et il faut donc dire pourquoi elle est écrite ainsi.

| Propriété | Raison |
|---|---|
| Elle sonde à **chaque requête**, sur `plugins_loaded` | La branche chargée par la dépendance peut changer entre deux requêtes, par une bascule d'administration (JE-003) |
| Elle n'écrit **rien** : ni option, ni métadonnée, ni transient, ni fichier | Un résultat persisté serait faux dès la première bascule, et faux silencieusement : le plugin continuerait d'enregistrer ses filtres sur une API absente, ce qui produit une erreur fatale au premier appel |
| Elle ne conclut **rien** au crochet d'activation | L'environnement d'activation ne préjuge pas de l'environnement des requêtes suivantes |
| Elle mémorise en revanche son résultat **dans la requête** | La frontière est la requête, et elle est exactement à sa place : une seule conclusion par requête, transportée en mémoire |

La mémorisation est portée par l'**instance** et non par une variable statique,
et ce choix est délibéré : un cache statique serait indiscernable d'un cache de
requête dans le code, mais il rendrait P-03 invérifiable — deux amorçages dans
un même processus liraient la même conclusion, et le test qui exige un constat
neuf à chaque amorçage ne pourrait plus échouer. Une propriété qu'aucun test ne
peut contredire n'est pas une propriété.

Le contexte d'administration est en outre **reçu en argument** plutôt que lu
dans la sonde : `is_admin()` est une information de requête, et la passer
explicitement rend la sonde exerçable dans les deux contextes sans bricoler
l'état global de WordPress — ce dont la suite `contract` se sert pour établir
JE-015 dans les deux sens.

La tentation est réelle : sonder à chaque requête ressemble à du gaspillage. Le
coût réel est une poignée d'appels à `function_exists()`, et le prix de
l'optimisation serait un écran blanc chez quelqu'un qui a cliqué sur un bouton
documenté. La suite `integration` interdit la régression en échouant si le
plugin écrit une option ou un transient à l'amorçage.

---

## 7. Ce que le plugin ne fait pas, et pourquoi c'est architectural

| Non-fait | Motif |
|---|---|
| Aucune table | Rien à requêter, rien à volumétrie. ADR-0003 |
| Aucune persistance par visiteur | L'adresse d'un visiteur est une donnée personnelle sans finalité de conservation ici |
| Aucun cache de décision partagé | Un cache partagé entre visiteurs est un contournement en puissance. SEC-14 |
| Aucun appel réseau | SEC-16, vérifié par `bin/scan-secrets.sh` sur le code livré et par un test d'intégration à l'exécution |
| Aucun filtre sur `rcp_get_ip` | Corriger un comportement de la dépendance par un filtre global changerait le comportement d'autres extensions sans qu'elles le sachent. R-06 |
| Aucune lecture de `restrict_content_chosen_version` pour décider | Cette option dit quelle branche la dépendance a chargée, mais c'est un détail d'implémentation amont. Seule la présence effective des fonctions appelées fait foi (B-02, JE-003). L'option peut être **citée** dans un avis, jamais servir à décider |
| Aucune mémorisation du résultat de la sonde | La branche chargée par la dépendance est un état mutable, retourné depuis un écran d'administration sans désactivation ni mise à jour. Une présence constatée à une requête peut être fausse à la suivante. P-01 à P-04, §5.2.1, JE-003 |
| Aucune lecture de `$_SERVER` hors de `Http\Server_Environment` | Un seul point d'entrée pour la donnée la plus sensible du plugin rend les onze scénarios du §11.3 exhaustifs |

---

## 8. Style

Conventions de développement §3, sans exception :
`declare( strict_types = 1 )` en tête de chaque fichier, garde `ABSPATH`
(SEC-15), DocBlock complet avec description courte, fonctions de moins de
50 lignes, fichiers de 200 à 400 lignes, sorties anticipées plutôt
qu'imbrications, constantes nommées plutôt que valeurs magiques.

Les commentaires expliquent **pourquoi**. Dans ce plugin, le « pourquoi » est
presque toujours une contrainte de sécurité ou un constat sur la dépendance : un
commentaire qui explique un choix du §4.7 cite la ligne de
`core/includes/misc-functions.php` qui l'a motivé, et un contournement cite
l'entrée de `docs/journal-empirique.md` qui l'établit.
