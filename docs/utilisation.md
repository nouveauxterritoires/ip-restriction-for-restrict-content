# Guide d'utilisation

Ce document est la **documentation utilisateur** du plugin : ce que
l'administrateur d'un site doit savoir pour s'en servir sans se tromper. Les
autres documents du dossier `docs/` s'adressent à qui modifie le code.

Il énonce aussi les choses que le plugin **décide** et que la documentation de
l'add-on commercial dont il reprend la grammaire laisse indéterminées — la
sémantique des plages à bornes jokers en particulier (O-06). Un comportement
que la source ne définit pas doit être écrit quelque part, sans quoi chaque
utilisateur en fera une hypothèse différente.

---

## 1. Ce que le plugin fait

Il ajoute deux fonctions à Restrict Content et à Restrict Content Pro :

- des **listes d'autorisation** par adresse IP, qui ouvrent un contenu restreint
  sans adhésion ;
- une **liste de blocage** d'inscription, qui refuse la soumission du formulaire
  d'adhésion depuis les adresses qu'on y inscrit.

Il **élargit** une décision de la dépendance ; il ne la restreint jamais. Un
contenu accessible sans lui reste accessible avec lui. Une adresse autorisée
obtient la lecture du contenu restreint, et rien d'autre : aucune capacité
WordPress, aucune adhésion, aucun accès à l'administration, aucune écriture.

Il n'enregistre aucune adresse de visiteur. La décision est prise, puis oubliée.

---

## 2. Les trois portées, et leur non-transitivité

| Autorisation posée sur | Ouvre | N'ouvre pas |
|---|---|---|
| La liste globale | Tout contenu restreint du site, tous niveaux confondus | — |
| Un niveau d'adhésion | Les contenus restreints **à ce niveau** | Les contenus d'un autre niveau, ni ceux restreints autrement (rôle, niveau d'accès) |
| Un contenu | **Ce seul** contenu | Aucun autre contenu, même du même niveau ou du même auteur |

**C'est le point qu'on devine le moins.** Une autorisation de niveau n'est pas
une autorisation globale restreinte : c'est une autorisation qui ne s'applique
qu'aux contenus de ce niveau. Un contenu réservé à « Or » ne regarde jamais la
liste d'« Argent », et un article ne regarde jamais la liste d'un autre article.

Les portées se cumulent par **disjonction** : une seule portée satisfaite
suffit, et deux portées satisfaites n'ouvrent rien de plus.

### Où se remplit chaque liste

| Portée | Écran |
|---|---|
| Globale | **Restrict > Restriction par IP** |
| Par niveau d'adhésion | L'écran d'édition du niveau, champ « Liste d'autorisation par adresse IP » |
| Par contenu | La boîte « Restrict this content » de l'éditeur, sous les champs de la dépendance |

---

## 3. Comment écrire une entrée

Une entrée par ligne. Les lignes vides sont ignorées, les espaces de début et de
fin aussi, et les doublons sont fusionnés à l'enregistrement.

| Forme | Exemples |
|---|---|
| Adresse seule | `198.51.100.9`, `2001:db8::1` |
| Bloc CIDR | `198.51.100.0/24`, `2001:db8::/32` |
| Plage | `198.51.100.10-198.51.100.20` |
| Joker | `198.51.100.*`, `198.51.*.*`, `2001:db8:*` |

Quelques règles qui évitent les mauvaises surprises :

- **Les familles ne se mélangent pas.** Une plage ou un bloc va d'une adresse
  IPv4 à une adresse IPv4, ou d'une IPv6 à une IPv6.
- **Une plage se lit dans l'ordre.** `198.51.100.20-198.51.100.10` est refusée :
  l'inverser silencieusement reviendrait à deviner l'intention.
- **Les zéros de tête sont refusés.** `010.0.0.1` n'est pas accepté, parce que
  selon l'outil qui la lit cette écriture désigne `10.0.0.1` ou `8.0.0.1`.
- **Un nom de domaine n'est pas une adresse.** Il est refusé : une liste d'accès
  qui dépend d'une résolution DNS dépend de quelque chose qu'un attaquant peut
  influencer.
- **Une règle qui couvrirait tout l'espace d'adressage est refusée**, sous toutes
  ses écritures — `0.0.0.0/0`, `*.*.*.*`, `0.0.0.0-255.255.255.255`, `::/0`. Ce
  n'est jamais ce que l'on veut, et c'est trop facile à écrire par accident.

### Les plages à bornes jokers

Une plage peut avoir des bornes à joker : `149.58.*-149.60.*`.

**Ce plugin retient que la borne gauche prend sa valeur minimale et la borne
droite sa valeur maximale.** `149.58.*-149.60.*` couvre donc tout ce qui va de
`149.58.0.0` à `149.60.255.255` : elle accepte `149.58.0.0`, `149.59.1.1` et
`149.60.255.255`, et refuse `149.57.255.255` et `149.61.0.0`.

Cette lecture est écrite ici parce que la documentation de l'add-on commercial
donne cet exemple **sans le définir**. Une autre lecture était concevable —
l'intersection du joker et de la plage, qui aurait exclu `149.59.x.x` —, et
elle est incompatible avec le caractère inclusif des plages. La lecture retenue
est fixée par un test nommé : elle ne changera pas sans que ce test change.

### Une ligne incomprise est ignorée, et signalée

Le plugin n'interprète jamais « au mieux » une ligne qu'il ne comprend pas : il
l'ignore, et le dit. L'écran où la liste a été saisie affiche le numéro de la
ligne, son contenu et le motif du refus — famille incohérente, bornes inversées,
préfixe hors limites, zéro de tête, nom de domaine, règle universelle… La ligne
reste dans le champ pour être corrigée ; elle n'accorde rien en attendant.

---

## 4. Derrière un proxy ou un CDN

**Par défaut, seule l'adresse de la connexion est évaluée**, et aucun en-tête de
transfert n'est lu. Ce n'est pas une limitation : un en-tête que n'importe quel
visiteur peut écrire laisserait chacun s'annoncer à l'adresse de son choix et
s'accorder un accès.

Pour un site derrière un proxy, un répartiteur de charge ou un CDN :

1. déclarez les **proxys de confiance** dans les réglages, une entrée par ligne,
   dans les mêmes formes que ci-dessus ;
2. choisissez l'**en-tête à lire** dans la liste proposée.

Le plugin remonte alors la chaîne de la droite vers la gauche et retient la
première adresse qui n'est pas un proxy de confiance. C'est le seul sens de
lecture sûr : la partie gauche d'un tel en-tête est écrite par le client.

**Vérifiez votre configuration sur l'écran de diagnostic**, qui affiche
l'adresse retenue pour la requête en cours et sa provenance — connexion directe
ou en-tête de transfert. Sans lui, on ne peut pas distinguer « ma règle est
fausse » de « l'adresse évaluée n'est pas celle que je crois ».

---

## 5. Le blocage d'inscription

La liste de blocage refuse la **soumission du formulaire d'inscription** depuis
les adresses qu'on y inscrit, avec un message que vous choisissez. Elle ne fait
rien d'autre : elle ne retire aucun accès au contenu, et ne touche pas aux
adhésions existantes.

**Les deux listes sont indépendantes**, et doivent le rester dans votre tête
comme elles le sont dans le code :

| | Liste d'autorisation | Liste de blocage |
|---|---|---|
| Effet | Accorde la lecture d'un contenu restreint | Refuse une inscription |
| Effet sur l'autre fonction | Aucun : une adresse autorisée n'est pas bloquée à l'inscription | Aucun : une adresse bloquée n'obtient ni plus ni moins d'accès au contenu |
| Portées | Globale, par niveau, par contenu | Globale uniquement |
| Erreur par excès | Une fuite d'accès **silencieuse** | Des inscriptions légitimes refusées, donc signalées |

Le message de blocage ne contient ni l'adresse du visiteur, ni le texte de la
règle qui a produit le refus : un formulaire public n'a pas à renseigner qui le
sonde.

---

## 6. Les catégories et les étiquettes

Un contenu restreint **par sa catégorie** est couvert comme les autres : la
liste globale, ou celle d'un niveau que la catégorie exige, l'ouvre.

La **page d'archive** d'une catégorie restreinte est un cas à part, et la seule
option du plugin. Restrict Content n'offre aucun point de décision pour ces
pages : la seule voie est de neutraliser la restriction de la catégorie demandée
pendant la requête — ce qui la rend aussi invisible pour tout autre code qui la
relit pendant cette même requête.

L'option **« Couvrir aussi les archives de catégorie et d'étiquette
restreintes » est donc désactivée par défaut**. Si vous l'activez, sachez ce que
vous activez :

- l'effet est borné à la catégorie **réellement demandée** par la requête ;
- aucune autre catégorie n'est affectée ;
- aucun écran d'administration n'est affecté — l'écran d'édition d'une catégorie
  voit toujours ses restrictions réelles ;
- un greffon tiers qui, pendant l'affichage de cette archive, relit les
  restrictions de cette catégorie, les verra vides.

---

## 7. Caches

Le plugin marque toute réponse qu'il élargit comme non cachable. Si votre cache
— extension, reverse proxy, CDN — honore ces en-têtes, une page ouverte par une
autorisation n'entre pas dans un cache partagé.

**À vérifier sur votre installation** : un cache qui les ignore servirait à un
visiteur quelconque une page destinée à une adresse autorisée. C'est le seul
contournement que le plugin ne peut pas empêcher seul.

---

## 8. Données personnelles

| Donnée | Ce qu'en fait le plugin |
|---|---|
| Adresse d'un visiteur | Évaluée, puis oubliée. Jamais stockée, jamais journalisée |
| Journal des décisions | Aucun. Un point d'extension permet à l'exploitant de brancher le sien, sous sa responsabilité |
| Comptes utilisateurs | Jamais touchés |

Les messages et avis produits par le plugin n'affichent qu'une adresse
**expurgée** — dernier octet masqué en IPv4, réseau `/64` en IPv6. La seule
exception est l'écran de diagnostic, réservé à qui administre les réglages, et
qui n'a d'utilité que complet.

---

## 9. Désinstallation

| Objet | À la désinstallation |
|---|---|
| Accès conférés par le plugin | Révoqués immédiatement : le plugin absent, aucune liste n'accorde plus rien |
| Réglages, listes de niveau et de contenu | **Conservés**, sauf si vous avez coché « Supprimer ces réglages si le plugin est désinstallé » |
| Comptes utilisateurs | Jamais touchés |

Conserver par défaut est délibéré : désinstaller pour déboguer ou le temps d'une
migration ne doit pas faire perdre une liste de plusieurs dizaines de lignes, et
une liste d'adresses ne se retrouve pas de mémoire.

---

## 10. Quand la dépendance manque

Le plugin ne s'active pas en silence. Si Restrict Content — libre ou
commercial — est absent, ou s'il est actif sur sa base de code historique, un
avis d'administration nomme ce qui manque et l'action à mener. L'écran
**Outils > État des capacités** détaille point d'accroche par point d'accroche
ce qui a été trouvé, ce qui manque, et ce qui n'est pas observable depuis la
requête en cours.

C'est cet écran qu'il faut joindre à une demande d'assistance : il dit l'état
réel de l'installation, là où un numéro de version ne dit rien de ce qui est
réellement chargé.
