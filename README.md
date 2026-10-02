# Le Carnet PHP

Blog technique consacré à PHP et à son écosystème, développé avec **Symfony 8**.

Le projet propose une partie publique (articles, catégories, tags, recherche, commentaires), un espace membre, un back-office complet et des tâches automatisées (publication programmée, nettoyage des fichiers).

---

## Fonctionnalités

### Partie publique
- Liste des articles publiés, paginée et triée du plus récent au plus ancien
- Filtrage par catégorie et par tag
- Page de détail avec image de couverture, tags et commentaires approuvés
- Recherche plein texte (index `FULLTEXT` MySQL), triée par pertinence, avec recherche par préfixe

### Utilisateurs
- Inscription, connexion et option « Se souvenir de moi »
- Commentaires réservés aux utilisateurs connectés, soumis à modération
- Trois niveaux de droits : lecteur, auteur, administrateur

### Back-office (EasyAdmin)
- Gestion des articles, catégories et tags
- Upload d'images de couverture (JPG, PNG, WebP, 2 Mo maximum)
- Modération des commentaires en un clic
- Un auteur ne peut modifier et supprimer que ses propres articles (`ArticleVoter`)

### Tâches automatisées (Symfony Scheduler)
| Tâche | Fréquence | Commande |
|---|---|---|
| Publication des articles programmés | Toutes les minutes | `app:publish-scheduled-articles` |
| Suppression des images orphelines | Tous les jours à 3h00 | `app:clean-orphan-images` |

---

## Stack technique

| Élément | Technologie |
|---|---|
| Langage | PHP 8.5 |
| Framework | Symfony 8.1 |
| Base de données | MySQL 8.4+ / Doctrine ORM |
| Templates | Twig |
| Style | Tailwind CSS 4 (symfonycasts/tailwind-bundle) |
| Assets | AssetMapper, Symfony UX Turbo |
| Back-office | EasyAdmin 5 |
| Tâches planifiées | Symfony Scheduler + Messenger |
| Tests | PHPUnit 13 |

---

## Prérequis

- PHP 8.4 ou supérieur, avec les extensions `pdo_mysql`, `intl`, `mbstring`, `xml`, `curl`, `zip`
- Composer
- MySQL 8.4 ou supérieur
- [Symfony CLI](https://symfony.com/download)

Vérifier l'environnement :

```bash
symfony check:requirements
```

---

## Installation en local

### 1. Récupérer le projet

```bash
git clone git@github.com:TON_PSEUDO/blog_tech.git
cd blog_tech
composer install
```

### 2. Configurer la base de données

Créer un fichier `.env.local` à la racine du projet :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/blog?serverVersion=9.7.1&charset=utf8mb4"
```

Adapter l'utilisateur, le mot de passe et `serverVersion` à votre installation de MySQL.

### 3. Créer la base et charger les données de test

```bash
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate -n
symfony console doctrine:fixtures:load -n
mkdir -p public/uploads/articles
```

### 4. Lancer le serveur

```bash
symfony serve -d
```

Le blog est accessible sur http://127.0.0.1:8000.

Grâce au fichier `.symfony.local.yaml`, le serveur lance automatiquement deux workers :
- le **watcher Tailwind**, qui recompile le CSS à chaque modification de template ;
- le **worker du Scheduler**, qui exécute les tâches planifiées.

Vérifier que tout tourne :

```bash
symfony server:status
```

### Comptes de test

Tous les comptes créés par les fixtures ont le mot de passe `password`.

| Email | Rôle |
|---|---|
| `admin@blog.fr` | Administrateur |
| `user1@blog.fr`, `user2@blog.fr` | Auteur |
| `user3@blog.fr` à `user5@blog.fr` | Lecteur |

Le back-office est accessible sur http://127.0.0.1:8000/admin.

---

## Tests

Les tests utilisent une base de données dédiée (`blog_test`). Créer un fichier `.env.test.local` avec la même connexion que `.env.local` :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/blog?serverVersion=9.7.1&charset=utf8mb4"
```

Doctrine ajoute automatiquement le suffixe `_test` au nom de la base. Préparer la base de test :

```bash
symfony console doctrine:database:create --env=test
symfony console doctrine:migrations:migrate --env=test -n
symfony console doctrine:fixtures:load --env=test -n
```

Lancer les tests :

```bash
vendor/bin/phpunit
```

La suite comprend :
- des **tests unitaires** du `ArticleVoter` (`tests/Security/Voter/`) ;
- des **tests fonctionnels** des pages publiques et des accès au back-office (`tests/Controller/`).

---

## Commandes utiles

```bash
# Lister les tâches planifiées et leur prochaine exécution
symfony console debug:scheduler

# Publier manuellement les articles programmés arrivés à échéance
symfony console app:publish-scheduled-articles

# Simuler le nettoyage des images orphelines, sans rien supprimer
symfony console app:clean-orphan-images --dry-run --min-age=0

# Calculer les statistiques de visites du jour (par défaut : la veille)
symfony console app:compute-daily-stats --date=today

# Compiler le CSS une seule fois (sans watcher)
symfony console tailwind:build
```

---

## Consulter les logs du serveur

Nginx enregistre chaque requête reçue en production dans `/var/log/nginx/blog_access.log`, une ligne par requête. Les commandes ci-dessous se lancent **sur le serveur** et affichent les résultats en colonnes : date, adresse IP, méthode (`GET`, `POST`…), code de réponse et URL.

```
DATE                  IP             METHODE  CODE  URL
02/Oct/2026:15:40:12  203.0.113.42   GET      200   /article/les-voters-symfony
02/Oct/2026:15:41:03  198.51.100.7   POST     302   /login
```

Les commandes de comptage remplacent la date et l'heure par le jour seul, et ajoutent une colonne `NB` avec le nombre de requêtes identiques.

Dans les exemples, remplacer `/article/les-voters-symfony` par la page voulue, et `203.0.113.42` par l'adresse IP voulue. Si la commande `column` est introuvable : `sudo apt install -y bsdextrautils`.

### Suivre les requêtes en direct

Affiche chaque nouvelle requête au moment où elle arrive. `Ctrl+C` pour arrêter.

```bash
sudo tail -f /var/log/nginx/blog_access.log | awk 'BEGIN {printf "%-20s  %-39s  %-7s  %-4s  %s\n", "DATE", "IP", "METHODE", "CODE", "URL"} {printf "%-20s  %-39s  %-7s  %-4s  %s\n", substr($4, 2), $1, substr($6, 2), $9, $7; fflush()}'
```

### Les 50 dernières requêtes

```bash
sudo tail -n 50 /var/log/nginx/blog_access.log | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

### Les requêtes les plus fréquentes

Les 20 combinaisons jour, IP, méthode et URL les plus fréquentes. Utile pour repérer un robot ou une IP trop active.

```bash
sudo awk '{print substr($4, 2, 11), $1, substr($6, 2), $7}' /var/log/nginx/blog_access.log | sort | uniq -c | sort -rn | head -20 | awk 'BEGIN {print "NB JOUR IP METHODE URL"} {print}' | column -t
```

### Les visites d'une page précise

Le détail de chaque visite de la page :

```bash
sudo grep "/article/les-voters-symfony" /var/log/nginx/blog_access.log | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

Le nombre de visites de la page, par jour et par IP :

```bash
sudo grep "/article/les-voters-symfony" /var/log/nginx/blog_access.log | awk '{print substr($4, 2, 11), $1, substr($6, 2), $7}' | sort | uniq -c | sort -rn | awk 'BEGIN {print "NB JOUR IP METHODE URL"} {print}' | column -t
```

### Toutes les requêtes d'une IP

Le parcours complet d'une adresse IP sur le site :

```bash
sudo grep "^203.0.113.42 " /var/log/nginx/blog_access.log | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

### Les requêtes du jour

Le détail de toutes les requêtes reçues aujourd'hui :

```bash
sudo grep "$(date +%d/%b/%Y)" /var/log/nginx/blog_access.log | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

Le résumé du jour : les 20 requêtes les plus fréquentes, par IP, méthode et URL :

```bash
sudo grep "$(date +%d/%b/%Y)" /var/log/nginx/blog_access.log | awk '{print substr($4, 2, 11), $1, substr($6, 2), $7}' | sort | uniq -c | sort -rn | head -20 | awk 'BEGIN {print "NB JOUR IP METHODE URL"} {print}' | column -t
```

### Les tentatives de connexion

Chaque envoi du formulaire de connexion. Une même IP qui revient de nombreuses fois en quelques minutes signale probablement une tentative de deviner un mot de passe.

```bash
sudo grep '"POST /login' /var/log/nginx/blog_access.log | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

### Toutes les requêtes POST

Toutes les actions envoyées au site : connexions, inscriptions, commentaires, formulaires du back-office.

```bash
sudo grep '"POST ' /var/log/nginx/blog_access.log | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

### Les erreurs

Le détail des requêtes en erreur (code 400 et plus) : pages introuvables (404), accès refusés (403), erreurs du serveur (500). On y voit notamment les robots qui testent des adresses comme `/wp-login.php` ou `/.env`.

```bash
sudo awk 'BEGIN {print "DATE IP METHODE CODE URL"} $9 >= 400 {print substr($4, 2), $1, substr($6, 2), $9, $7}' /var/log/nginx/blog_access.log | column -t
```

Les 20 erreurs les plus fréquentes, par jour :

```bash
sudo awk '$9 >= 400 {print substr($4, 2, 11), $1, substr($6, 2), $7}' /var/log/nginx/blog_access.log | sort | uniq -c | sort -rn | head -20 | awk 'BEGIN {print "NB JOUR IP METHODE URL"} {print}' | column -t
```

### Les jours précédents

Ubuntu archive les logs chaque jour : la veille est dans `blog_access.log.1`, les jours plus anciens dans des fichiers compressés (`blog_access.log.2.gz`, etc.), conservés 14 jours. `zgrep` lit directement ces fichiers compressés.

Les tentatives de connexion des jours précédents :

```bash
sudo zgrep -h '"POST /login' /var/log/nginx/blog_access.log.*.gz | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

Toutes les requêtes d'une IP sur les deux dernières semaines :

```bash
{ sudo zgrep -h "^203.0.113.42 " /var/log/nginx/blog_access.log.*.gz; sudo grep -h "^203.0.113.42 " /var/log/nginx/blog_access.log /var/log/nginx/blog_access.log.1; } | awk 'BEGIN {print "DATE IP METHODE CODE URL"} {print substr($4, 2), $1, substr($6, 2), $9, $7}' | column -t
```

> Les adresses IP sont des données personnelles au sens du RGPD : ces logs servent uniquement à la sécurité et au diagnostic, et sont supprimés automatiquement après 14 jours.

---

## Structure du projet

```
src/
├── Command/            # Commandes console, exécutées par le Scheduler
├── Controller/
│   ├── Admin/          # Back-office EasyAdmin
│   └── ...             # Blog, sécurité, inscription
├── DataFixtures/       # Données de test
├── Doctrine/           # Fonction DQL MATCH_AGAINST (recherche plein texte)
├── Entity/             # Article, Category, Tag, Comment, User
├── Enum/               # ArticleStatus
├── Form/               # Formulaires (inscription, commentaire)
├── Repository/         # Requêtes Doctrine
└── Security/Voter/     # ArticleVoter

templates/
├── article/            # Liste et détail des articles
├── form/tailwind.html.twig   # Thème de formulaires Tailwind
└── _pagination.html.twig     # Pagination réutilisable
```

---

## Déploiement

La procédure de mise en production est décrite dans [DEPLOY.md](DEPLOY.md).
