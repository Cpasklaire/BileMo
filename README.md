#BileMo

OpenClassRoom - Dev PHP - Projet 7 - Créez un web service exposant une API

#Require

Symfony 6 MySQL 8 Composer

#Lancer le projet

Cloner le repertoir. Modifier le .env selon votre configuration

Dans votre console 

``composer install`` : pour récupérer l'ensemble des packages nécessaires

créer vos clefs publiques et privées pour JWT dans config/jwt :

créez le répertoire "jwt" dans le dossier config

pour créer la clef privée et publique
```bash
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem-pubout
```

créer un fichier .env

ce fichier doit contenir vos identifiants de connexion à la base de données

le chemin vers vos clefs privées et publiques

votre passphrase de création de clef

``php bin/console doctrine:database:create`` : pour créer la base de données

``php bin/console doctrine:schema:update --force`` : pour créer les tables

``php bin/console doctrine:fixtures:load`` : pour charger les fixtures

``symfony server:start``

#Documentation

https://127.0.0.1:8000/api/doc

(Selon votre localhost)

#Login

https://127.0.0.1:8000/api/login_check

les fixture vous donne accés à un compte test déja créé

{"email": "admin@mail.com","password": "password"}

#Memory perso

commande 

vider cache : ``php bin/console cache:clear``
