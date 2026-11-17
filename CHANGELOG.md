# Journal des versions

## 2.12.3
- Lecteur PDF : sur grand écran, le lecteur prend toute la largeur ; nom du fichier et bouton Télécharger en dessous.

## 2.12.2
- Lecteur PDF : la barre d'outils garde sa taille compacte quel que soit le thème (Astra, Elementor…).

## 2.12.1
- Aperçu « voir comme un apprenant » : le bandeau propose des liens vers le Catalogue et le Tableau de bord.

## 2.12.0
- **Lecteur PDF intégré** (PDF.js, inclus dans l'extension, sans CDN) pour les documents suivis (bloc Gutenberg et widget Elementor) : le PDF s'affiche de la même façon sur ordinateur, téléphone et tablette.
- Pages l'une sous l'autre, dessinées au fil du défilement, à la largeur du cadre ; barre d'outils : « Page x / n », page précédente / suivante, zoom − / +, « Ajuster », « Plein écran ». Boutons utilisables au clavier, libellés en français.
- Si le lecteur ne peut pas se charger : lien « Ouvrir le PDF ».
- **Formation → Réglages → Lecteur PDF** : « Lecteur intégré » (par défaut) ou « Lecteur du navigateur », et « Autoriser le téléchargement et l'impression » (coché par défaut). Décoché : boutons de téléchargement masqués aux apprenants (l'accès au document reste protégé comme avant).

## 2.11.6
- Mises à jour : plus de fausse « nouvelle version disponible » juste après une mise à jour (la version installée est relue).

## 2.11.5
- Tableau de bord apprenant : « Mes exercices » n'affiche plus qu'une ligne par exercice (la réponse la plus récente).

## 2.11.4
- « Vérifier à nouveau » (Tableau de bord > Mises à jour) interroge GitHub immédiatement, sans attendre le cache de 6 h.

## 2.11.3
- Extensions : le lien « Activer les mises à jour auto » apparaît aussi pour Catalogue de formations (mises à jour depuis GitHub).

## 2.11.2
- Exercices : « Déposé le … à … » pour le premier envoi et « Modifié le … à … » si l'apprenant renvoie une réponse (comme Moodle). Visible par l'apprenant et dans la page de correction.

## 2.11.1
- Testé avec **Elementor 4.4** : les 4 widgets (vidéo, document, exercice, quiz), les restrictions, la correction et l'aperçu fonctionnent.
- Correctif Elementor : une condition laissée sur « Contenu terminé » (valeur par défaut) était perdue à l'enregistrement. Idem pour le délai laissé à 7 jours.
- Elementor : la liste « Contenu » des conditions se met à jour en direct (plus besoin d'enregistrer et recharger après avoir ajouté un widget).

## 2.11.0
- **Voir comme un apprenant** (barre d'outils en haut du site, menu « 👁 ») : un apprenant sans accès, un apprenant qui commence, ou un apprenant précis. Bandeau d'aperçu, actions désactivées, « Quitter l'aperçu ».
- Raccourcis : fiche apprenant (« Voir le site comme cet apprenant »), page Inscrits (« Voir comme »).
- Correctif : un contenu « masqué aux apprenants » ou caché par ses conditions n'apparaît plus (même avec un cadenas) pour les visiteurs sans accès.

## 2.10.0
- **Correction des exercices** : « Validé » ou « À refaire » (l'apprenant peut renvoyer une réponse), note facultative (« Noté sur… » dans le bloc).
- Réglage par exercice : terminé quand la formatrice valide (par défaut) ou dès l'envoi.
- Exercice noté : utilisable dans la condition « score minimum ».
- E-mail à l'apprenant : « Exercice validé » ou « Exercice à refaire », avec la note.

## 2.9.0
- **Dupliquer une notion** : lien « Dupliquer » au survol dans Toutes les notions, ou encadré « Dupliquer » dans l'éditeur. Copie en brouillon avec contenus (blocs ou Elementor), niveaux, matières, image et réglages ; sans les inscrits.

## 2.8.0
- **Quiz** intégré (bloc Gutenberg + widget Elementor, sans H5P) : QCM une ou plusieurs bonnes réponses, vrai/faux, réponse courte.
- Corrigé automatiquement ; score minimum pour valider ; nombre d'essais ; mélange ; correction affichée après chaque essai, une fois réussi, ou jamais.
- Meilleur score gardé : compte dans l'avancement et utilisable dans la condition « score minimum ».
- Démo Playground : un quiz dans « Les fractions simples ».

## 2.7.0
- Fichiers joints aux réponses protégés : dossier à part, nom aléatoire, ouverts seulement par l'apprenant et les formateurs, absents de la médiathèque. Les anciens fichiers sont déplacés automatiquement.
- Réglages → « À la suppression de l'extension » : tout garder (par défaut), supprimer les données des apprenants et les réglages, ou tout supprimer (y compris notions, niveaux, matières).

## 2.6.0
- Données personnelles (RGPD) :
  - compte supprimé → accès, avancement, réponses et fichiers joints supprimés ;
  - Outils → Exporter les données personnelles : accès, avancement, réponses et corrections ;
  - Outils → Effacer les données personnelles : effacement de ces données ;
  - Réglages → Confidentialité : texte proposé pour la politique de confidentialité.

## 2.5.1
- Avancement : un contenu caché par ses conditions ne compte pas tant qu'il est caché (un contenu bloqué avec cadenas compte toujours).

## 2.5.0
- **Restrictions d'accès** sur chaque contenu suivi (comme Moodle) : contenu terminé, score minimum, date (du… / jusqu'au…), délai après le début de l'accès.
- Combinaison : toutes les conditions ou au moins une ; si non rempli : cadenas + ce qu'il faut faire, ou caché.
- **Masqué aux apprenants** : préparer un contenu sans qu'il soit visible (hors avancement).
- Les formateurs voient tout, avec un bandeau rappelant les conditions.
- Bloc Gutenberg (panneau « Restrictions d'accès ») et widget Elementor.

## 2.4.0
- **Parcours dans l'ordre** (encadré « Parcours » de la notion) : chaque contenu suivi se débloque quand le précédent est terminé.
- Exception par contenu : « Toujours accessible » (bloc Gutenberg et widget Elementor).
- Contenu bloqué : cadenas + lien vers le contenu à terminer d'abord.
- Vidéo ou activité H5P validée : bouton « Continuer → » vers le contenu débloqué.

## 2.3.0
- Vidéo suivie : validation automatique quand la vidéo est vue (seuil réglable, 90 % par défaut) — fichier vidéo, YouTube et Vimeo.
- Au choix pour chaque vidéo : automatique seulement, automatique + bouton « J'ai terminé », ou bouton seulement.
- Option « Empêcher d'avancer dans la vidéo ».
- Disponible dans le bloc Gutenberg et le widget Elementor.

## 2.2.1
- Correctif : les listes de choix (Inscrits, Apprenants, Accès) fonctionnent aussi dans WordPress Playground.
- Filtres du catalogue : adresse explicite, compatible avec tous les réglages de liens.

## 2.2.0
- Nouvelle page **Apprenants** : liste + fiche apprenant (notions, dates, avancement ; ajouter, modifier, retirer, valider).
- Nouvelle page **Inscrits** : apprenants d'une notion (inscrire, modifier, retirer, valider).
- Raccourcis : colonne « Inscrits » dans la liste des notions, encadré dans l'éditeur, lien « Formation » dans la liste des comptes.
- Après une action, retour sur la page d'où l'on vient.
- Ajouter un accès déjà existant met à jour ses dates (plus de doublon).

## 2.1.1
- Mises à jour : lecture de la version directement sur la branche main si aucune release n'existe.
- Publication automatique des releases (GitHub Actions).

## 2.1.0
- Mises à jour automatiques depuis GitHub (releases).

## 2.0.0
- Bloc et widget « Activité H5P suivie » : score minimum, meilleur score gardé, avancement en direct.

## 1.9.0
- Blocs suivis = enveloppes de vrais blocs WordPress (Vidéo, Intégration, Fichier, tous blocs pour la consigne) + alignement, couleurs, marges, bordure, ombre.
- Widgets Elementor : onglet Style, options de lecture vidéo, aperçu PDF.

## 1.8.0
- Encadré « Ajouter un contenu suivi » (boutons rapides). Ancienne boîte retirée et convertie en blocs.

## 1.7.0
- Blocs WordPress et widgets Elementor : Vidéo suivie, Document suivi, Exercice.

## 1.5.0
- Groupes de niveaux / de matières (un élément peut être dans plusieurs groupes), filtres du catalogue réglables.

## 1.0.0 – 1.4.x
- Catalogue, accès datés validés par l'admin, avancement, exercices corrigés, alertes e-mail, niveaux prêts à cocher, ordre automatique.
