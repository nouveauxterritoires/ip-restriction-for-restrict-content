# IP Restriction for Restrict Content

Restriction par adresse IP pour Restrict Content et Restrict Content Pro.

Extension indépendante, ni développée ni approuvée par les éditeurs de Restrict
Content ou de Restrict Content Pro.

---

## État réel du projet

**Le plugin ne restreint rien pour le moment.** Le dépôt est au lot 0. Ce qui
est livré et vérifié :

| Livré | Ce qui l'atteste |
|---|---|
| Environnement de développement reproductible en une commande | `make up` provisionne WordPress, la base, la base de test, la capture d'e-mails et la dépendance |
| Amorçage conditionnel du plugin | `src/Plugin.php`, suite `integration` |
| Contrôle des prérequis par **capacité**, jamais par version ni par nom de plugin | `src/Plugin.php`, suite `unit` et suite `integration` |
| Avis d'administration nommant précisément le prérequis absent | `src/Admin/Requirements_Notice.php`, suite `unit` |
| Cinq suites de tests déclarées, dont deux peuplées | `phpunit.xml.dist`, `tests/` |
| Scanners de sécurité et d'hygiène, bloquants | `bin/scan-secrets.sh`, `bin/scan-placeholders.sh` |
| Intégration continue : lint, analyse statique, scanners, suites peuplées, seuil de couverture | `.github/workflows/ci.yml` |

**Rien d'autre.** Ni liste d'autorisation, ni liste de blocage, ni écran de
réglages, ni moteur de correspondance d'adresses. Ce que le plugin fera est
spécifié dans le cahier des charges ; le découpage en lots est au §13.

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

Le nom du plugin mentionne une marque tierce. La forme retenue — « fonction pour
marque » — est celle qu'autorise la directive 17 du répertoire WordPress.org,
mais la question n'est pas close : elle est tenue comme décision ouverte `O-03`
au cahier des charges §15.1.

---

## Licence

GPL-2.0-or-later. Voir [`LICENSE`](LICENSE).
