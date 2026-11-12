# Journal des versions

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
