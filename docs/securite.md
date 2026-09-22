# Sécurité

Ce document tient les dix-huit exigences de sécurité du cahier des charges §10,
avec **l'état réel de chacune** et le test qui la couvre. Une exigence encore
non couverte y figure comme telle : la présenter comme acquise serait le
contraire de ce que ce document sert à établir.

Mis à jour au **lot 0**. À ce stade, le plugin ne prend aucune décision d'accès :
la plupart des exigences sont donc « à couvrir », avec le lot qui les couvrira.

---

## 1. État des exigences

| Réf. | Exigence | État | Couverte par |
|---|---|---|---|
| SEC-01 | Nonce **et** capacité `rcp_manage_settings` sur l'enregistrement des réglages ; la capacité est vérifiée **après** le nonce | à couvrir — lot 4 | `access` : trois refus — nonce absent, nonce emprunté à une autre action, capacité absente. L'ordre est établi en soumettant une requête sans droit **et** sans nonce : le motif du refus doit être le nonce |
| SEC-02 | Nonce **et** capacité `edit_post` sur l'enregistrement de la liste d'un contenu, avec test de contournement par identifiant | à couvrir — lot 8 | `access` : l'enregistrement est rejoué avec l'identifiant d'une publication que l'utilisateur ne peut pas modifier, et il est refusé |
| SEC-03 | Assainissement à l'entrée, échappement à la sortie | à couvrir — lot 4 | `access` : un message de blocage porteur de balises est enregistré puis rendu inerte ; PHPCS `WordPress.Security.EscapeOutput` reste actif sans exclusion sur `src/` et `templates/` |
| SEC-04 | Aucune requête SQL directe : tout passe par les API d'options et de métadonnées | à couvrir — lot 4 | `unit` : parcours de `src/`, échec sur toute occurrence de `$wpdb` |
| SEC-05 | Aucun secret, jeton, mot de passe ni adresse de courriel dans le code livré | **couverte** | `unit` (`ScannersTest`) et `bin/scan-secrets.sh`, bloquant en intégration continue. Le scanner distingue le code livré, où la règle est stricte, du reste du dépôt, où les adresses des domaines réservés par la RFC 2606 sont admises |
| SEC-06 | Expurgation avant journalisation : aucune adresse complète dans un message écrit par le plugin | à couvrir — lot 3 | `unit` : `Support\Redactor` masque la partie hôte d'une adresse IPv4 et les 64 bits bas d'une adresse IPv6, et conserve ce qui sert au diagnostic |
| SEC-07 | Par défaut, l'adresse évaluée est `REMOTE_ADDR` seul ; **aucun** en-tête HTTP n'est lu | à couvrir — lot 3 | `matching` : requête portant `X-Forwarded-For` puis `Client-Ip` valant une adresse autorisée, sans proxy déclaré ; l'adresse retenue reste celle de la connexion et aucun accès n'est accordé |
| SEC-08 | Un en-tête de transfert n'est lu que si `REMOTE_ADDR` correspond à un proxy de confiance déclaré | à couvrir — lot 3 | `matching` : proxy déclaré, requête directe portant l'en-tête ; l'en-tête est ignoré |
| SEC-09 | L'en-tête est lu **de droite à gauche** en sautant les proxys déclarés ; une valeur injectée à gauche n'est jamais retenue | à couvrir — lot 3 | `matching` : chaîne `<adresse autorisée>, <adresse réelle>` reçue derrière un proxy déclaré ; l'adresse retenue est la réelle. Et chaîne entièrement composée de proxys : adresse indéterminable |
| SEC-10 | Fermeture sur l'inconnu : règle illisible, adresse absente ou famille discordante n'accordent jamais d'accès | à couvrir — lot 2 et lot 3 | `matching` : un cas par situation ; et `access` : une liste entièrement invalide se comporte comme une liste vide |
| SEC-11 | Une entrée rejetée est signalée par un avis persistant, la liste de blocage comprise | à couvrir — lot 4 | `access` : l'avis est présent après rechargement de l'écran, et porte le numéro de ligne et le motif |
| SEC-12 | Une règle qui désigne l'ensemble des adresses est refusée à l'écriture et ignorée à la lecture | à couvrir — lot 2 et lot 4 | `matching` : `*`, `*.*.*.*`, `0.0.0.0/0`, `::/0` sont rejetés par l'analyseur ; `access` : l'enregistrement du réglage les refuse avec un message nommant la ligne |
| SEC-13 | Une autorisation accorde la lecture d'un contenu restreint et aucune capacité WordPress | à couvrir — lot 5 | `access` : depuis une adresse autorisée, `current_user_can()` est inchangé, l'administration reste fermée, et aucune écriture n'est possible |
| SEC-14 | Une réponse élargie par une autorisation n'entre pas dans un cache partagé | à couvrir — lot 5 | `access` : la réponse porte le marquage de non-cachabilité dès qu'une autorisation s'applique, et ne le porte pas sinon. La tenue de ce marquage par les caches réels est portée en V-08 |
| SEC-15 | `defined( 'ABSPATH' ) || exit;` en tête de chaque fichier PHP livré | **couverte** | `unit` (`RepositoryHygieneTest`) : parcours du dépôt, garde exigée sur tout fichier livré |
| SEC-16 | Aucun appel réseau sortant | **couverte** | `bin/scan-secrets.sh` sur le code livré, qui attrape l'intention dès la relecture ; `integration` (`BootTest`) l'établira à l'exécution par un bouchon `pre_http_request` au lot 1 |
| SEC-17 | Réponse minimale : le message de blocage ne révèle ni l'adresse, ni la règle, ni l'existence d'une liste | à couvrir — lot 6 | `access` : le message rendu ne contient ni l'adresse du visiteur, ni le texte d'une règle, et il est identique quel que soit le motif |
| SEC-18 | Cloisonnement test / production | **couverte** | `unit` : `tests/bootstrap.php` refuse une invocation sans `--testsuite` ; aucun code de test n'entre dans l'archive, vérifié à l'ouverture du zip au lot 10 |

---

## 2. Modèle de menace

### 2.1 L'attaque principale — l'en-tête forgé

C'est la menace centrale du plugin, et elle tient en une ligne de requête HTTP.

| | |
|---|---|
| **Attaquant** | N'importe quel visiteur, sans compte, sans outil particulier |
| **Prérequis** | Connaître ou deviner une adresse figurant dans la liste d'autorisation |
| **Action** | Envoyer `X-Forwarded-For: <adresse autorisée>` ou `Client-Ip: <adresse autorisée>` avec une requête ordinaire |
| **Effet si l'en-tête est cru** | Accès complet au contenu restreint, sans compte ni adhésion. Aucune trace distinctive dans les journaux |
| **Gravité** | Critique — contournement d'un contrôle d'accès, à coût nul et à grande échelle |
| **Contre-mesure** | SEC-07 à SEC-09 : `REMOTE_ADDR` seul par défaut, en-tête lu uniquement derrière un proxy déclaré, lecture de droite à gauche |
| **Ce qui établit la contre-mesure** | Les onze scénarios du cahier des charges §11.3, suite `matching` |

**Le fait aggravant** : la dépendance elle-même fait le mauvais choix.
`rcp_get_ip()` (`core/includes/misc-functions.php:731`) retient, dans l'ordre,
`HTTP_CLIENT_IP` (ligne 734), puis le **premier** élément de
`HTTP_X_FORWARDED_FOR` (lignes 737-742), puis `REMOTE_ADDR` (ligne 743). Sur un
site sans proxy, la valeur qu'elle renvoie est entièrement sous le contrôle du
visiteur.

Ce plugin ne l'utilise donc pas comme source de décision, et ne filtre pas
`rcp_get_ip` pour le corriger : un autre code peut dépendre de son comportement
actuel, et le corriger globalement changerait ce comportement sans que ce code
le sache (R-06).

**Pourquoi `X-Forwarded-For` se lit de droite à gauche.** L'en-tête s'accroît
par la gauche à chaque saut de proxy. Un client qui envoie déjà
`X-Forwarded-For: 192.0.2.10` fait arriver au serveur
`192.0.2.10, <sa vraie adresse>`. Lire le premier élément retient donc la valeur
injectée. Remonter depuis la droite en sautant les proxys déclarés retient la
dernière valeur qu'aucun proxy de confiance n'a pu inventer.

### 2.2 Les autres menaces

| Menace | Effet | Contre-mesure |
|---|---|---|
| Règle trop couvrante saisie par erreur (`*`, `0.0.0.0/0`) | Tout le contenu restreint devient public, silencieusement | SEC-12 : refus à l'écriture **et** à la lecture |
| Règle illisible interprétée par approximation | Une règle qui ne dit pas ce que son auteur croit | SEC-10 et G-01 à G-07 : grammaire close, rejet explicite |
| Confusion entre les deux listes | Une liste de blocage lue comme une liste d'autorisation accorderait l'accès à ce qu'on voulait bloquer | A-01 : stockages, caches et chemins de décision disjoints ; test dédié |
| Contournement de portée | Une autorisation de niveau lue comme globale ouvre tout le site | FNC-04 : test de portée croisée, le test central de la suite `access` |
| Empoisonnement de cache de pages | Une réponse élargie servie à tous les visiteurs suivants | SEC-14 |
| Contournement par identifiant sur la boîte de métadonnées | Un contributeur pose une autorisation sur un contenu qu'il ne peut pas modifier | SEC-02, avec son test de contournement |
| Élévation de privilèges par l'autorisation | Une adresse autorisée obtiendrait plus que la lecture | SEC-13 |
| Injection par le message personnalisé | Du balisage injecté dans une page publique | SEC-03 |
| Fuite d'information par le message de blocage | Un attaquant apprend qu'une liste existe et déduit son contenu par essais | SEC-17 |
| Fuite d'adresses par les journaux | Données personnelles écrites en clair | SEC-06 |
| Filtre tiers qui **ajoute** une correspondance | Un plugin tiers devient fournisseur d'accès au contenu payant | Le filtre `ip_restriction_for_restrict_content_match` ne peut que retirer ; son retour est intersecté, et un test le vérifie |

---

## 3. Valeurs sensibles

| Valeur | Sensibilité | Traitement |
|---|---|---|
| Adresse du visiteur | Donnée personnelle | Jamais persistée, jamais journalisée en clair, expurgée par `Support\Redactor` (SEC-06) |
| Listes d'adresses configurées | Révèlent le réseau d'un client ou d'un partenaire | Stockées en option, lisibles par qui a `rcp_manage_settings` ; jamais exposées au front ni dans un message d'erreur (SEC-17) |
| Liste des proxys de confiance | Révèle l'architecture de l'hébergement | Même traitement |
| Message de blocage personnalisé | Écrit par un administrateur, affiché au public | Assaini à l'entrée, échappé à la sortie, jamais interprété comme du balisage (SEC-03) |

Le plugin ne manipule aucun secret : ni clé, ni jeton, ni identifiant de
paiement. C'est un point favorable, et c'est aussi pourquoi `bin/scan-secrets.sh`
peut être strict sans liste d'exceptions sur le code livré.

---

## 4. Ce qui reste à vérifier

Les points ouverts de sécurité sont tenus au cahier des charges §15.2. Les deux
qui pèsent le plus :

| Réf. | Question | Pourquoi elle n'est pas tranchée |
|---|---|---|
| **V-02** | Comment l'add-on de référence résout-il l'adresse derrière un proxy ? | Non vérifiable sans lire son code, ce que le développement en salle blanche (§0.3) interdit. Ce plugin n'essaie donc pas de reproduire un comportement inconnu : il applique R-01 à R-07 |
| **V-08** | Les caches de pages courants honorent-ils le marquage de non-cachabilité ? | SEC-14 pose l'attendu côté plugin ; la tenue côté cache se constate en recette au lot 5, sur les caches réellement déployés |
