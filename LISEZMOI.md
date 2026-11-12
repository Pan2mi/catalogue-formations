# Extension « Catalogue de formations » — guide rapide

## 1. Installer
1. WordPress → **Extensions → Ajouter → Téléverser une extension**
2. Choisir `catalogue-formations.zip` → **Installer** → **Activer**
3. Un menu **Formation** apparaît dans l'administration.

## 2. Régler (une seule fois)
1. **Réglages → Général** : cocher « Tout le monde peut s'enregistrer », rôle par défaut **Abonné**.
2. **Réglages → Permaliens** : choisir « Titre de la publication » puis **Enregistrer**.
3. Créer 2 pages :
   - **Catalogue** avec le contenu `[if_catalogue]`
     - Variantes : `[if_catalogue gniveau="college"]` (un seul groupe), `[if_catalogue niveau="6eme"]` (un seul niveau), `filtres="non"` (sans listes déroulantes)
   - **Mon tableau de bord** avec le contenu `[if_tableau_de_bord]`
4. **Formation → Réglages** : choisir ces 2 pages, l'e-mail des alertes, **les filtres affichés** dans le catalogue (Groupe de niveaux, Niveau, Groupe de matières, Matière) et le **rangement** (par niveau ou par matière d'abord).
5. Ajouter ces pages au menu du site.

## 3. Créer le catalogue
1. **Formation → Niveaux** :
   - **Listes prêtes à cocher** (« Ajouter des niveaux prêts à l'emploi », en haut à gauche) : cocher puis **Ajouter la sélection**. Chaque niveau est rangé **automatiquement dans ses groupes** (ex. 6ème → Collège + Cycle 3, CM1 → Primaire + Cycle 3). Les groupes manquants sont créés.
   - **Saisie libre** juste en dessous, avec les cases **Groupes** (un niveau peut être dans plusieurs groupes). Les groupes classiques (Primaire, Collège, Cycle 3…) sont **proposés** même s'ils n'existent pas encore, et se **cochent tout seuls** quand on tape un niveau connu (ex. « 6ème » → Collège + Cycle 3). Champ **« + Nouveau groupe »** pour en créer un autre (plusieurs : séparés par des virgules).
   - **Harmoniser les noms** : corrige 6ième → 6ème, 2nd → 2de, etc.
   - **Ordre** : automatique (CP… Terminale, A1… C2…). Pour le changer : bloc « Ordre d'affichage » sous la liste (⠿ ou ▲▼, puis **Enregistrer l'ordre**).
2. **Formation → Groupes de niveaux** : Primaire, Collège, Cycle 3… Sur la fiche d'un groupe, cocher les niveaux qu'il contient. Colonne « Contient » pour vérifier.
3. **Formation → Matières** et **Groupes de matières** : même fonctionnement (ex. Anglais → Langues).
4. **Formation → Ajouter une notion** :
   - Titre, extrait (résumé affiché dans le catalogue), image mise en avant
   - Cocher un ou **plusieurs niveaux** et une ou plusieurs **matières** (la notion apparaît dans chacun). Les groupes suivent automatiquement.
   - **Construire la page librement**, au choix :
     - **Éditeur WordPress** : encadré **« Ajouter un contenu suivi »** dans la colonne de droite (onglets Notion et Bloc) → boutons **🎬 Vidéo / 📄 Document / ✏️ Exercice** : le bloc est ajouté juste après l'endroit où vous êtes. Aussi via **+** → catégorie **Formation**. À placer où vous voulez : entre du texte, des images, dans des colonnes… Chaque bloc suivi **contient de vrais blocs WordPress** :
       - **Vidéo suivie** → bloc Vidéo (fichier) ou Intégration (YouTube, Vimeo…), avec tous leurs réglages ;
       - **Document suivi** → bloc Fichier (aperçu du PDF, bouton de téléchargement), lien protégé automatiquement ;
       - **Exercice** → consigne construite avec n'importe quels blocs (texte, images, listes, tableaux…).
       - L'enveloppe a les réglages habituels : **alignement** (gauche, centre, droite, large, pleine largeur), **couleurs**, **marges**, **bordure**, **ombre**, **taille du texte**, **ancre HTML**. Onglet ⚙ : titre et « Afficher le titre » ; onglet ◐ : styles.
     - **Elementor** (« Modifier avec Elementor ») : catégorie **Formation** en tête du panneau → widgets **Vidéo suivie**, **Document suivi**, **Exercice**.
       - Contenu : titre, afficher le titre ; vidéo : options de lecture (lecture auto, son coupé, boucle, début, image d'aperçu) ; document : aperçu du PDF et sa hauteur.
       - Style : alignement, format vidéo (16:9, 4:3, 1:1, vertical…), largeur maximale, arrondi, titre (couleur, typographie), boutons (couleurs, arrondi, typographie).
       - Avancé : marges, fond, bordure, animations, responsive, classes CSS (réglages Elementor habituels).
   - **🧩 Activité H5P suivie** (bloc et widget, si l'extension **H5P** est installée) : choisir une activité H5P (quiz, vidéo interactive, glisser-déposer, texte à trous…) et le **score minimum pour valider** (0 % = il suffit de la terminer). Le meilleur score est gardé ; l'apprenant peut réessayer. Bouton « + Créer une activité H5P » pour en créer une dans un nouvel onglet, puis « Actualiser la liste ».
   - Sans accès, l'apprenant voit la page mais ces éléments sont **verrouillés 🔒** ; le bouton « Commander » est en haut.
   - L'avancement compte chaque élément suivi, dans l'ordre de la page.
   - L'ancienne boîte « Contenus de la notion » a été retirée : les notions qui l'utilisaient ont été **converties automatiquement** en blocs (ajoutés en fin de page, avancement conservé).

## Installer H5P (une fois)
1. **Extensions → Ajouter** → chercher **H5P** → Installer → Activer.
2. **H5P Content → Add New** : H5P demande l'autorisation de communiquer avec H5P.org (« H5P Hub »). Accepter permet d'installer facilement les types d'activités ; refuser oblige à téléverser des fichiers .h5p.
3. **Réglages → H5P** : laisser cochée l'option qui enregistre les résultats des utilisateurs (« Log results for signed in users »).

## Valider une vidéo automatiquement
Dans le bloc **Vidéo suivie** (ou le widget Elementor), panneau **Validation de la vidéo** :
- **Automatiquement, quand elle est vue** : la vidéo compte dans l'avancement dès que l'apprenant en a regardé le pourcentage choisi (90 % par défaut).
- **Automatiquement + bouton « J'ai terminé »** (par défaut).
- **Seulement avec le bouton**.
- **Empêcher d'avancer dans la vidéo** : impossible de sauter plus loin que ce qui a déjà été regardé.
Fonctionne avec un fichier vidéo, YouTube et Vimeo.

## Imposer un ordre dans une notion
- Dans l'éditeur de la notion, encadré **Parcours** (colonne de droite) : cocher **Parcours dans l'ordre**.
- Chaque contenu suivi se débloque quand le précédent est terminé (vidéo vue, PDF « J'ai terminé », exercice envoyé, activité H5P réussie).
- Exception : sur un contenu, activer **Toujours accessible** (panneau « Élément suivi » du bloc, ou widget Elementor). Il reste ouvert et ne bloque pas les suivants.

## 4. Gérer les accès — Formation → Accès
- **Demandes en attente** : choisir les dates « du… au… » puis **Valider** (ou **Refuser**).
- **Donner un accès directement** : utilisateur + notion + dates.
- **Tous les accès** : modifier les dates, supprimer, voir le détail.
- Ajouter un accès pour une notion déjà attribuée met simplement ses dates à jour.

### Fiche apprenant — Formation → Apprenants
- Liste des apprenants (recherche par nom ou e-mail) → **Voir la fiche**.
- La fiche montre ses notions, l'état, les dates et l'avancement.
- **Ajouter une notion**, modifier les dates sur place (**Enregistrer**), **Retirer**, valider ou refuser ses demandes.
- Raccourci : **Utilisateurs** → survoler un compte → **Formation**.

### Inscrits d'une notion — Formation → Inscrits
- Choisir une notion → liste des apprenants inscrits, mêmes boutons (inscrire, modifier, retirer, valider).
- Raccourcis : colonne **Inscrits** et lien au survol dans **Toutes les notions**, encadré **Inscrits** dans l'éditeur de la notion.

## 5. Suivre et corriger
- **Formation → Suivi** : tableau apprenants × notions avec le % d'avancement. Clic = détail.
- **Formation → Corrections** : exercices rendus → **Corriger** → écrire la correction → **Envoyer**.

## Côté apprenant
- Créer un compte, parcourir le catalogue, cliquer **Commander cette notion**.
- Pendant la période d'accès : regarder les vidéos, ouvrir les PDF, cliquer **J'ai terminé**, répondre aux exercices.
- **Mon tableau de bord** : ses notions, ses dates, son avancement, ses corrections.

## E-mails envoyés automatiquement
| Événement | Destinataire |
|---|---|
| Nouvelle demande | Admin |
| Accès validé / refusé | Apprenant |
| Exercice rendu | Admin |
| Exercice corrigé | Apprenant |

Conseil : installer une extension SMTP gratuite (ex. WP Mail SMTP) pour que les e-mails arrivent bien.

## Limites à connaître
- **Avancement des vidéos** : compté quand l'apprenant clique « J'ai terminé » (pas de détection automatique du visionnage).
- **PDF** : le lien affiché vérifie l'accès. Mais l'adresse directe du fichier dans la médiathèque reste techniquement accessible si quelqu'un la connaît.
- **Vidéos YouTube/Vimeo** : mettre les vidéos en « non répertoriées » pour qu'elles ne soient pas trouvables ailleurs.
- **Vidéos téléversées** : limitées par la taille maximale autorisée par l'hébergeur ; elles occupent l'espace et la bande passante du site ; leur adresse directe reste accessible si on la connaît.
- **Fichiers joints aux exercices** : stockés dans la médiathèque.
