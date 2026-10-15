# IP Restriction for Restrict Content

Restriction par adresse IP pour Restrict Content et Restrict Content Pro.

Extension indépendante, ni développée ni approuvée par les éditeurs de Restrict
Content ou de Restrict Content Pro.

---

## État réel du projet

**Le plugin ne restreint rien pour le moment.** Les lots 0 à 3 sont livrés : le
moteur de correspondance et la résolution de l'adresse du visiteur existent et
sont exhaustivement testés, mais rien ne les branche encore sur la dépendance.
Ce qui est livré et vérifié :

| Livré | Ce qui l'atteste |
|---|---|
| Environnement de développement reproductible en une commande | `make up` provisionne WordPress, la base, la base de test, la capture d'e-mails et la dépendance |
| Amorçage conditionnel du plugin | `src/Plugin.php`, suite `integration` |
| Contrôle des prérequis par **capacité**, jamais par version ni par nom de plugin | `src/Plugin.php`, `src/Domain/`, suites `unit` et `integration` |
| Sonde étendue aux quatorze capacités du cahier des charges §5.1, jamais mémorisée | `src/Integration/Capability_Probe.php`, `tests/integration/ProbePersistenceTest.php` |
| Avis d'administration nommant la cause **constatée** — dépendance absente, ou active sur sa base de code historique — et l'action de retour | `src/Admin/Dependency_Labels.php`, suites `unit` et `integration` |
| Écran d'état des capacités, en lecture seule, disponible même quand la dépendance ne l'est pas | `src/Admin/Capability_Status_Page.php`, `tests/integration/CapabilityStatusPageTest.php` |
| Preuve par l'exécution que chaque accroche du §5.1 se déclenche | `tests/contract/`, 36 tests contre Restrict Content 4.0.5 |
| Preuve par l'exécution que la bascule de branche amont est réversible, sous capacité et sous nonce | `tests/contract/BranchSwitchContractTest.php` |
| Moteur de correspondance : grammaire close, quatre formes normalisées en intervalles, IPv4 et IPv6, décision sur un ensemble | `src/Rule/`, `src/Address/Ip_Number.php`, 93 tests de la suite `matching` |
| Couverture de 100 % des lignes **et des branches** sur le moteur | `bin/check-coverage.sh`, `bin/lib/check-branches.php` |
| Aucun appel à WordPress dans la couche de décision, vérifié mécaniquement | `tests/unit/Tooling/PurityTest.php` |
| Résolution de l'adresse du visiteur : `REMOTE_ADDR` par défaut, en-tête lu seulement derrière un proxy déclaré, chaîne remontée de droite à gauche | `src/Address/Client_Address_Resolver.php`, `src/Http/Server_Environment.php` |
| Expurgation avant toute écriture : jamais d'adresse complète dans un message du plugin | `src/Support/Redactor.php` |
| Cinq suites de tests déclarées, dont quatre peuplées — 229 tests | `phpunit.xml.dist`, `tests/` |
| Scanners de sécurité et d'hygiène, bloquants | `bin/scan-secrets.sh`, `bin/scan-placeholders.sh` |
| Intégration continue : lint, analyse statique, scanners, suites peuplées, seuil de couverture | `.github/workflows/ci.yml` |

**Rien d'autre.** Ni liste d'autorisation, ni liste de blocage, ni écran de
réglages. Le plugin sait quelle adresse évaluer et si elle correspond à une
liste ; personne ne l'interroge encore. Ce que le plugin
fera est spécifié dans le cahier des charges ; le découpage en lots est au §13.

La suite `contract` est exécutée contre les **deux** variantes en intégration
continue, Restrict Content Pro compris : `OK (36 tests, 77 assertions)`. En
local, la variante commerciale demande son archive, qui n'est jamais versionnée
(`make test-contract-pro`, voir les prérequis ci-dessous).

### Formats acceptés par le moteur

| Forme | Exemple | IPv4 | IPv6 |
|---|---|---|---|
| Littéral | `226.216.136.78`, `2001:db8::1` | oui | oui |
| Joker sur les octets de fin | `200.122.216.*`, `186.*.*.*`, `149.58.*` | oui | **non** (ADR-0005) |
| Plage, bornes incluses | `159.7.89.231-159.7.89.240` | oui | oui |
| CIDR | `10.0.0.0/24`, `2001:db8::/32` | oui | oui |
| Plage à bornes jokers | `149.58.*-149.60.*` | oui | **non** |

### Quelle adresse est évaluée

Par défaut, **l'adresse de la connexion TCP** (`REMOTE_ADDR`), et rien d'autre :
aucun en-tête HTTP n'est lu, parce que tout en-tête est écrit par le client. Un
visiteur qui envoie `X-Forwarded-For: <adresse autorisée>` n'obtient rien.

Derrière un proxy ou un CDN, il faut déclarer deux choses : la liste des
**proxys de confiance** (même grammaire que les listes d'adresses) et le nom de
l'en-tête à lire, parmi `X-Forwarded-For`, `CF-Connecting-IP`, `True-Client-IP`
et `X-Real-IP`. L'en-tête n'est alors lu que si la connexion vient d'un proxy
déclaré, et la chaîne est remontée **de droite à gauche** en sautant les proxys
connus : la valeur qu'un client aurait préfixée n'est jamais retenue.

Si l'adresse ne peut pas être déterminée — en-tête absent ou illisible, chaîne
entièrement composée de proxys —, elle est **indéterminable** : aucune règle ne
correspond, et aucun accès n'est accordé. Le plugin ne se replie jamais sur
l'adresse du proxy, qui est celle du CDN.

Une entrée par ligne ; les lignes vides, les espaces de bordure et les
commentaires introduits par `#` sont ignorés. Une entrée que l'analyseur ne
comprend pas est **rejetée avec un motif nommé**, jamais interprétée par
approximation : familles mêlées, plage inversée, préfixe hors bornes, joker
devant un littéral, règle couvrant toutes les adresses, joker dans un CIDR, nom
d'hôte, joker IPv6, octet à zéro de tête.

---

## Démarrage en trois commandes

```bash
cp .env.example .env
make up
make test-unit
```

`make up` démarre la pile Docker et provisionne le site sur
`http://localhost:8082` (capture d'e-mails sur `http://localhost:8027`). Les
ports sont paramétrables dans `.env`.

`make help` liste toutes les cibles et ce qu'elles font réellement.

### Commandes utiles

| Commande | Effet |
|---|---|
| `make test` | Toutes les suites, séquentiellement |
| `make test-unit` | Logique pure et outillage, WordPress mocké |
| `make test-integration` | WordPress réel |
| `make test-contract` | Accroches contre Restrict Content |
| `make test-contract-pro` | Accroches contre Restrict Content Pro |
| `make lint` / `make fix` | PHPCS / PHPCBF |
| `make stan` | PHPStan niveau 5 |
| `make scan` | Scanners de secrets et de marqueurs |
| `make ci` | L'enchaînement complet |

Les suites de tests **ne peuvent pas être exécutées dans une même invocation** :
la suite `unit` remplace les fonctions de WordPress, les autres les chargent.
`tests/bootstrap.php` refuse une invocation sans `--testsuite` et le dit.

---

## Prérequis

| Pour développer | Pour exécuter le plugin |
|---|---|
| Docker et Docker Compose v2 | PHP 8.1 ou plus récent |
| | WordPress 6.4 ou plus récent |
| | Restrict Content ou Restrict Content Pro, actif **et chargeant sa base de code moderne** |

La dernière condition n'est pas rhétorique. Restrict Content embarque deux bases
de code mutuellement exclusives, et la branche historique n'expose aucune des
fonctions dont ce plugin dépend. Pire : le choix entre les deux est **réversible
à tout moment** depuis l'administration de la dépendance. Un administrateur peut
donc désactiver ce plugin en cours de vie du site, sans le désinstaller — et
recevra alors un avis d'administration qui le lui dit et nomme le retour en
arrière.

Le constat, avec ses preuves et ses numéros de ligne, est en
`docs/journal-empirique.md` (JE-003).

La variante commerciale n'est jamais versionnée dans ce dépôt : déposez son
archive dans `vendor-plugins/`, ou désignez un répertoire décompressé par
`RCP_PRO_SOURCE`.

---

## Documents

| Document | Contenu |
|---|---|
| [`docs/cahier-des-charges.md`](docs/cahier-des-charges.md) | Le document central : exigences, règles de décision, sécurité, tests, lots, points ouverts |
| [`docs/architecture.md`](docs/architecture.md) | Les trois couches, l'arborescence `src/` prévue, les objets de valeur, les points d'extension |
| [`docs/tests.md`](docs/tests.md) | Rôle de chaque suite, ce que chacune doit établir, fixtures, seuils |
| [`docs/securite.md`](docs/securite.md) | Les dix-huit exigences `SEC-xx`, leur état réel, le test qui les couvre, le modèle de menace |
| [`docs/journal-empirique.md`](docs/journal-empirique.md) | Les constats sur la dépendance et l'outillage, avec leur preuve |
| [`CHANGELOG.md`](CHANGELOG.md) | Format Keep a Changelog |

### Décisions d'architecture

| ADR | Décision |
|---|---|
| [0001](docs/adr/0001-forme-du-prefixe.md) | Deux formes du préfixe |
| [0002](docs/adr/0002-planchers-php-et-wordpress.md) | PHP 8.1, WordPress 6.4 |
| [0003](docs/adr/0003-aucune-table-dediee.md) | Aucune table dédiée |
| [0004](docs/adr/0004-stockage-du-reglage-de-niveau.md) | Réglage de niveau en métadonnée de niveau |
| [0005](docs/adr/0005-etendue-du-support-ipv6.md) | Étendue du support IPv6 |
| [0006](docs/adr/0006-adresse-du-visiteur.md) | `REMOTE_ADDR` par défaut, en-tête sur déclaration explicite |
| [0007](docs/adr/0007-asymetrie-des-deux-listes.md) | Les deux listes ne partagent ni stockage ni chemin de décision |
| [0008](docs/adr/0008-slug-et-mention-de-non-affiliation.md) | Slug conservé, mention de non-affiliation maintenue |

---

## Identité

| Élément | Valeur |
|---|---|
| Slug | `ip-restriction-for-restrict-content` |
| Namespace | `Ip_Restriction_For_Restrict_Content\` |
| Préfixe des fonctions et des hooks | `ip_restriction_for_restrict_content_` |
| Préfixe court | `RCIPR_` / `rcipr_` |
| Text domain | `ip-restriction-for-restrict-content` |
| Port de développement | 8082 |

Le slug commence par `ip-restriction`, une fonction générique, et mentionne la
marque après « for » : c'est la forme que recommande la directive 17 du
répertoire WordPress.org, qui n'interdit que le **début** du slug. Le nommage
est donc arrêté — voir [ADR-0008](docs/adr/0008-slug-et-mention-de-non-affiliation.md).

La mention de non-affiliation en tête de ce document reste nécessaire : elle
répond à une exigence distincte de la conformité du slug, et l'une ne dispense
pas de l'autre (cahier des charges §14.3).

---

## Licence

GPL-2.0-or-later. Voir [`LICENSE`](LICENSE).
