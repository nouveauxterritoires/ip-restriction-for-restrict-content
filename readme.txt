=== IP Restriction for Restrict Content ===
Contributors: nouveauxterritoires
Tags: restrict content, adresse ip, contrôle d'accès, adhésion, inscription
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Listes d'autorisation par adresse IP qui ouvrent un contenu restreint par Restrict Content, et liste de blocage qui refuse une inscription.

== Description ==

Ce plugin ajoute deux choses à Restrict Content et à Restrict Content Pro :

1. des **listes d'autorisation** par adresse IP, qui ouvrent un contenu
   restreint sans adhésion — par exemple pour les postes d'une entreprise
   abonnée, ou pour un réseau de consultation sur place ;
2. une **liste de blocage** d'inscription, qui refuse la soumission du
   formulaire d'adhésion depuis les adresses qu'on y inscrit.

**Les deux listes ne se ressemblent que de loin, et c'est volontaire.** Une
adresse autorisée n'est jamais bloquée à l'inscription ; une adresse bloquée
n'obtient ni plus ni moins d'accès au contenu. Elles ne partagent ni stockage,
ni chemin de décision : une erreur de champ ne peut pas transformer un blocage
en autorisation.

= Ce que le plugin fait, et ce qu'il ne fait pas =

Il **élargit** une décision de la dépendance, il ne la restreint jamais. Un
contenu accessible sans ce plugin reste accessible avec. Une adresse autorisée
obtient la lecture du contenu restreint, et **rien d'autre** : aucune
capacité WordPress, aucune adhésion, aucun accès à l'administration, aucune
écriture.

Il n'enregistre **aucune adresse de visiteur** : la décision est prise, puis
oubliée. Aucun journal, aucune statistique, aucune table.

= Les trois portées =

* **Globale** : l'adresse ouvre tout contenu restreint du site.
* **Par niveau d'adhésion** : l'adresse ouvre les contenus réservés à ce niveau,
  et à lui seul.
* **Par contenu** : l'adresse ouvre ce contenu, et lui seul.

Les portées étroites **ne sont pas transitives**. Une autorisation posée sur le
niveau « Argent » n'ouvre aucun contenu réservé à « Or », et une autorisation
posée sur un article n'en ouvre aucun autre. C'est la chose que l'on devine le
moins et dont l'erreur coûte le plus.

= Les quatre façons d'écrire une adresse =

* une adresse seule : `198.51.100.9`, `2001:db8::1` ;
* un bloc CIDR : `198.51.100.0/24`, `2001:db8::/32` ;
* une plage : `198.51.100.10-198.51.100.20` ;
* un joker : `198.51.100.*`, `198.51.*.*`.

Une plage peut avoir des bornes à joker, par exemple `149.58.*-149.60.*`. La
documentation de l'add-on commercial dont ce plugin reprend la grammaire donne
cet exemple sans le définir : **ce plugin retient que la borne gauche prend sa
valeur minimale et la borne droite sa valeur maximale**. `149.58.*-149.60.*`
accepte donc `149.58.0.0`, `149.59.1.1` et `149.60.255.255`, et refuse
`149.57.255.255` et `149.61.0.0`. C'est la seule lecture compatible avec le
caractère inclusif des plages, et elle est fixée par un test.

Les deux familles ne se mélangent pas : une plage ou un bloc va d'une adresse
IPv4 à une adresse IPv4, ou d'une IPv6 à une IPv6.

= Ce que le plugin ne devine pas =

Une ligne qu'il ne comprend pas est **ignorée**, jamais interprétée au mieux, et
l'écran de réglages dit laquelle, à quelle ligne, et pourquoi. Un nom de domaine
n'est pas une adresse : il est refusé, parce qu'une liste d'accès qui dépend
d'une résolution DNS dépend de quelque chose qu'un attaquant peut influencer.

== Installation ==

1. Installez et activez Restrict Content ou Restrict Content Pro.
2. Installez et activez ce plugin.
3. Ouvrez **Restrict > Restriction par IP** et remplissez les listes.

Si la dépendance est absente, le plugin ne s'active pas en silence : il affiche
un avis d'administration qui nomme ce qui manque, et un écran d'état qui
détaille les points d'accroche trouvés et manquants.

== Frequently Asked Questions ==

= Le site est derrière un proxy ou un CDN : quelle adresse est évaluée ? =

Par défaut, celle de la connexion (`REMOTE_ADDR`), et elle seule. Un en-tête de
transfert est **ignoré** tant que vous n'avez pas déclaré les proxys de
confiance dans les réglages : sans cette précaution, n'importe quel visiteur
pourrait s'annoncer à l'adresse de son choix et s'accorder un accès.

Une fois des proxys déclarés, la chaîne est remontée de la droite vers la
gauche, et la première adresse qui n'est pas un proxy de confiance est retenue.
L'écran de diagnostic affiche l'adresse retenue pour la requête en cours et sa
provenance : c'est le moyen de vérifier une configuration de proxy sans
deviner.

= Une adresse autorisée voit-elle le contenu si son adhésion a expiré ? =

Oui. L'autorisation par adresse l'emporte sur une adhésion expirée : c'est son
objet.

= Et les contenus restreints par catégorie ? =

Ils sont couverts comme les autres : la liste globale, ou celle d'un niveau que
la catégorie exige, les ouvre.

La **page d'archive** de la catégorie est un cas à part, et la seule option du
plugin. Restrict Content n'offre aucun point de décision pour ces pages : la
seule voie est de neutraliser la restriction de la catégorie demandée pendant
la requête, ce qui la rend aussi invisible pour tout autre code qui la relit
pendant cette même requête. L'option est donc **désactivée par défaut**, et son
effet est borné à la catégorie réellement demandée — jamais une autre, jamais un
écran d'administration.

= Les réponses élargies peuvent-elles être servies à tout le monde par un cache ? =

Le plugin marque toute réponse qu'il élargit comme non cachable. Si votre cache
honore ces en-têtes, une page ouverte par une autorisation n'entre pas dans un
cache partagé. Vérifiez-le sur votre installation : un cache qui les ignore
servirait à un visiteur quelconque une page destinée à une adresse autorisée.

= Que devient ma configuration si je désinstalle le plugin ? =

Les accès qu'il conférait disparaissent immédiatement — le plugin absent, plus
aucune liste n'accorde rien. Les réglages, eux, sont **conservés** par défaut,
listes de niveau et de contenu comprises : désinstaller pour déboguer ou pour
migrer ne doit pas faire perdre une liste de plusieurs dizaines de lignes. Une
case à cocher dans les réglages demande leur suppression complète.

Aucun compte utilisateur n'est touché, dans aucun scénario.

= Le plugin est-il officiel ? =

Non. Extension indépendante, ni développée ni approuvée par les éditeurs de
Restrict Content ou de Restrict Content Pro.

== Changelog ==

= 1.0.0 =

* Première version publiée.
* Listes d'autorisation par adresse IP, aux portées globale, par niveau
  d'adhésion et par contenu, non transitives.
* Liste de blocage d'inscription, sans aucun chemin commun avec les listes
  d'autorisation.
* Grammaire close : adresse, bloc CIDR, plage et joker, en IPv4 et en IPv6 ;
  une ligne incomprise est ignorée et signalée avec son motif.
* Résolution de l'adresse du visiteur derrière un proxy déclaré, chaîne remontée
  de droite à gauche, et écran de diagnostic.
* Marquage de non-cachabilité des réponses élargies.
* Couverture des contenus restreints par catégorie ; couverture des archives de
  catégorie en option désactivée par défaut.
* Points d'extension pour compléter les listes, observer les décisions et
  journaliser, dont un filtre qui ne peut que retirer un octroi.
