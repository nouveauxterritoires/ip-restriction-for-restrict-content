# Architecture

Ce document décrit la forme prévue du code. Au moment où il est écrit, seul le
lot 0 est livré : `src/Plugin.php` et `src/Admin/Requirements_Notice.php`. Tout
le reste est une cible, pas un état. Les cases non livrées sont signalées comme
telles dans l'arborescence du §3.

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
├── Address/                            décision pure — adresse du visiteur
│   ├── Client_Address.php              objet de valeur : adresse + provenance
│   ├── Address_Source.php              énumération : connexion | en-tête
│   ├── Client_Address_Resolver.php     R-01 à R-07, lecture de droite à gauche
│   └── Ip_Number.php                   conversion en entier de longueur fixe
│
├── Rule/                               décision pure — le moteur
│   ├── Rule.php                        objet de valeur : intervalle + famille + texte d'origine
│   ├── Rule_Family.php                 énumération : IPv4 | IPv6
│   ├── Rule_Parser.php                 grammaire §4.2, contraintes G-01 à G-07
│   ├── Rule_Rejection.php              objet de valeur : ligne, numéro, motif
│   ├── Rule_Set.php                    règles valides + rejets, immuable
│   ├── Matcher.php                     §4.3 et §4.4
│   └── Match_Result.php                objet de valeur : correspondance + règle responsable
│
├── Policy/                             décision pure — les portées
│   ├── Scope.php                       énumération : globale | niveau | contenu
│   ├── Access_Request.php              objet de valeur : adresse, contenu, restrictions applicables
│   ├── Access_Policy.php               FNC-01 à FNC-06, non-transitivité
│   ├── Access_Decision.php             objet de valeur : accordé | non traité + portée responsable
│   ├── Registration_Policy.php         FNC-07 à FNC-09
│   └── Registration_Decision.php       objet de valeur
│
├── Settings/                           transport — lecture et écriture des réglages
│   ├── Settings.php                    objet de valeur : l'ensemble des réglages
│   ├── Settings_Repository.php         option, métas de niveau, métas de contenu
│   └── Settings_Validator.php          validation à l'écriture, A-02
│
├── Http/                               transport — environnement de la requête
│   └── Server_Environment.php          seul point du plugin qui lise $_SERVER
│
├── Integration/                        application — branchements sur la dépendance
│   ├── Capability_Probe.php            sonde étendue du §5.2, branche chargée (JE-003)
│   ├── Content_Access.php              rcp_member_can_access
│   ├── Term_Access.php                 rcp_get_term_restrictions, garde-fous du §5.1.1
│   ├── Registration_Guard.php          rcp_form_errors
│   └── Cache_Guard.php                 SEC-14, non-cachabilité d'une réponse élargie
│
├── Admin/                              application — écrans
│   ├── Requirements_Notice.php         livré
│   ├── Settings_Page.php               FNC-24, FNC-29
│   ├── Level_Field.php                 FNC-25
│   ├── Post_Metabox.php                FNC-26
│   └── Rejection_Notice.php            FNC-22, A-03
│
└── Support/
    ├── Redactor.php                    SEC-06, expurgation avant journalisation
    └── Extension_Points.php            noms des hooks du §8, en un seul endroit
```

Trois répertoires — `Address/`, `Rule/`, `Policy/` — contiennent la totalité de
la logique métier et **aucun appel à WordPress**. C'est ce périmètre qui porte
le plancher de couverture renforcé du cahier des charges §11.6, et c'est la
seule frontière qu'un test peut vérifier mécaniquement : un test de la suite
`unit` parcourt ces trois répertoires et échoue si une fonction de WordPress y
est appelée.

---

## 4. Objets de valeur

Tous immuables : constructeur privé, fabriques statiques nommées, accesseurs en
lecture seule, aucun setter. Aucune méthode ne mute un argument reçu ; une
méthode qui reçoit un tableau en renvoie une copie modifiée, et un test le
vérifie explicitement.

| Objet | Porte | Fabriques |
|---|---|---|
| `Client_Address` | L'adresse retenue, sa famille, sa provenance | `from_connection()`, `from_header()`, `unknown()` |
| `Ip_Number` | Un entier de longueur fixe et sa famille | `from_address()`, `min_of()`, `max_of()` |
| `Rule` | Un intervalle borné, la famille, le texte d'origine | `literal()`, `wildcard()`, `cidr()`, `range()` |
| `Rule_Rejection` | Le texte refusé, son numéro de ligne, le motif | `because()` |
| `Rule_Set` | Les règles valides et les rejets | `from_text()`, `empty()` |
| `Match_Result` | S'il y a correspondance, et par quelle règle | `matched()`, `no_match()` |
| `Access_Decision` | Si l'accès est accordé, et par quelle portée | `granted_by()`, `not_handled()` |
| `Registration_Decision` | Si l'inscription est bloquée, et le message | `blocked_with()`, `allowed()` |
| `Settings` | L'ensemble des réglages, validés | `from_array()`, `defaults()` |

Deux choix de nommage valent une justification.

**`Client_Address::unknown()`.** Une adresse indéterminable est une valeur, pas
une absence. La représenter par `null` obligerait chaque appelant à se souvenir
de tester, et le premier oubli deviendrait une erreur fatale sur un chemin rare
— exactement le chemin d'un visiteur derrière un proxy mal configuré. Un objet
`unknown()` ne correspond à aucune règle, ce qui est la fermeture exigée par
SEC-10, et il porte la raison pour laquelle l'adresse est inconnue, ce que
FNC-29 affiche.

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

| Règle | Motif |
|---|---|
| `ip_restriction_for_restrict_content_match` ne peut que **retirer** une correspondance | Un filtre capable d'en ajouter mettrait l'accès au contenu payant hors de portée de l'administrateur qui a rempli les listes. Le retour du filtre est intersecté avec le résultat d'origine, et un test le vérifie |
| Le retour de `ip_restriction_for_restrict_content_blocked_message` est échappé après le filtre | Un filtre est du code tiers : lui faire confiance pour l'échappement revient à ne pas échapper |

---

## 6. Ce que le plugin ne fait pas, et pourquoi c'est architectural

| Non-fait | Motif |
|---|---|
| Aucune table | Rien à requêter, rien à volumétrie. ADR-0003 |
| Aucune persistance par visiteur | L'adresse d'un visiteur est une donnée personnelle sans finalité de conservation ici |
| Aucun cache de décision partagé | Un cache partagé entre visiteurs est un contournement en puissance. SEC-14 |
| Aucun appel réseau | SEC-16, vérifié par `bin/scan-secrets.sh` sur le code livré et par un test d'intégration à l'exécution |
| Aucun filtre sur `rcp_get_ip` | Corriger un comportement de la dépendance par un filtre global changerait le comportement d'autres extensions sans qu'elles le sachent. R-06 |
| Aucune lecture de `restrict_content_chosen_version` | Cette option dit quelle branche la dépendance a chargée, mais c'est un détail d'implémentation amont. Seule la présence effective des fonctions appelées fait foi (B-02, JE-003). L'option peut être **citée** dans un avis, jamais servir à décider |
| Aucune lecture de `$_SERVER` hors de `Http\Server_Environment` | Un seul point d'entrée pour la donnée la plus sensible du plugin rend les onze scénarios du §11.3 exhaustifs |

---

## 7. Style

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
