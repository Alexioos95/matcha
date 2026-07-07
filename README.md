Application Web responsive de rencontre en Server-Side Rendering avec PHP 

## Usage

Créer un fichier ```.env``` avec les variables nécessaires, et ```make``` afin de lancer les Dockers. Le serveur Apache tournera sur ```https://<DUMP>:8443```.

Une fois le container ```mysql``` correctement lancé, vous pouvez ```make populate``` afin de remplir la base de données de profils virtuels et activer les ```centres d'intérêts/tags``` pour les utilisateurs.

## Fonctionnalités
- Gestion de sessions utilisateurs (inscription, connexion, mot de passe oublié, modification d'informations, et cookies)
- Envoie de mails de confirmation d'inscription et réinitialisation de mot de passe
- Upload et suppression de photo
- Centres d'intérêts/tags réutilisables ```*```
- Galerie et recherche d'utilisateur en scroll infini, avec filtre et tri
- Simple algorithme de popularité prenant en compte les vues et likes de profil
- Algorithme de recommendation prenant en compte la distance, le nombre commun d'intérêts et la popularité des utilisateurs
- Profil d'utilisateur en fenêtre modale
- Gestion de status sociaux (aucun, 'like', 'liked', 'matched', bloqué)
- Possibilité de signaler un compte ```**```
- Accès à un chat privé en cas de 'match'
- Notifications en cas de vue de profil, 'like', 'match', 'unmatch' ou réception d'un message privé

```*``` : Stockés dans la table ```interests``` dans la base de données
```**``` : Ajouté à la table ```reports``` de la base de données

## Demo
<img src="https://i.imgur.com/z7PDDHF.gif" alt="Login" width="500"> <img src="https://i.imgur.com/Ed7dLYA.gif" alt="Search in infinite scroll" width="500"> <img src="https://i.imgur.com/tFNGggi.gif" alt="Filtres" width="500"> <img src="https://i.imgur.com/O8wK5tB.gif" alt="Connections and chat" width="500"> <img src="https://i.imgur.com/Ot3cWqi.gif" alt="Notifications" width="500">  <img src="https://i.imgur.com/h8YCzKw.gif" alt="Editing" width="500">

## Crédits
[Alexis Payen](https://github.com/Alexioos95/) - Designing du site et de la base de données, responsivité, gestion utilisateurs, création et édition de profil, recherche avec filtrage et tri, algorithmes, gestion status sociaux et notifications  
[Ralph Balazs](https://github.com/balazsralph) - Designing, responsivité et implémentation du chat privé avec ses notifications
