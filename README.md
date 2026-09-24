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

# Compiler le CSS une seule fois (sans watcher)
symfony console tailwind:build
```

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
