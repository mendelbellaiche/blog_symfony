# Déploiement

Ce document décrit comment mettre en ligne une nouvelle version du blog sur le serveur de production.

---

## Environnement de production

| Élément | Valeur |
|---|---|
| Serveur | VPS Ubuntu 26.04 LTS |
| Utilisateur | `deploy` |
| Dossier du projet | `/var/www/blog` |
| Nom de domaine | `NOM_DE_DOMAINE` (HTTPS via Let's Encrypt) |
| Serveur web | Nginx + PHP 8.5-FPM |
| Base de données | MySQL 8.4 |
| Worker du Scheduler | Supervisor (`blog-scheduler`) |

---

## Installation initiale du serveur

Ces étapes ne sont à faire **qu'une seule fois**, sur un VPS Ubuntu neuf. Elles sont exécutées en `root` (ou avec `sudo`), sauf indication contraire.

### 1. Mettre à jour le système

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git unzip curl acl
```

### 2. Installer PHP 8.5

```bash
sudo apt install -y php8.5-cli php8.5-fpm php8.5-mysql php8.5-intl \
    php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip
```

> Depuis PHP 8.5, OPcache est intégré au cœur de PHP : il n'y a plus de paquet
> `php8.5-opcache` séparé. Vérifier qu'il est actif avec `php -m | grep -i opcache`.

Vérifier la version installée :

```bash
php -v
```

### 3. Installer Composer

```bash
cd /tmp
EXPECTED_CHECKSUM="$(curl -s https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
[ "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM" ] && echo "Installateur OK" || echo "ERREUR : checksum invalide"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

Vérifier :

```bash
composer --version
```

### 4. Installer le Symfony CLI

```bash
curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash
sudo apt install -y symfony-cli
```

Vérifier que le serveur remplit les prérequis de Symfony :

```bash
symfony check:requirements
```

### 5. Créer l'utilisateur `deploy`

Créer l'utilisateur et l'ajouter aux groupes `sudo` et `www-data` (le groupe de PHP-FPM et Nginx) :

```bash
sudo adduser deploy
sudo usermod -aG sudo,www-data deploy
```

Autoriser la connexion SSH par clé. Les commandes suivantes sont à lancer **depuis le poste de développement**.

Si le poste n'a pas encore de clé SSH (`ssh-copy-id` affiche alors `ERROR: No identities found`), en générer une :

```bash
ls ~/.ssh/id_ed25519.pub   # vérifier si une clé existe déjà
ssh-keygen -t ed25519 -C "EMAIL_DU_DEVELOPPEUR"
```

Garder l'emplacement par défaut (`~/.ssh/id_ed25519`) et choisir une passphrase. Sur macOS, l'enregistrer dans le trousseau pour ne pas la retaper à chaque connexion :

```bash
ssh-add --apple-use-keychain ~/.ssh/id_ed25519
```

Copier la clé publique sur le serveur (le mot de passe de `deploy` est demandé une seule fois) :

```bash
ssh-copy-id -i ~/.ssh/id_ed25519.pub deploy@ADRESSE_IP_DU_VPS
```

Vérifier que la connexion fonctionne sans mot de passe :

```bash
ssh deploy@ADRESSE_IP_DU_VPS
```

> Si `ssh-copy-id` échoue (connexion par mot de passe désactivée, par exemple), ajouter la clé à la main
> **en `root` sur le serveur**, en collant le contenu de `~/.ssh/id_ed25519.pub` du poste de développement :
>
> ```bash
> sudo mkdir -p /home/deploy/.ssh
> echo "CLE_PUBLIQUE" | sudo tee -a /home/deploy/.ssh/authorized_keys
> sudo chown -R deploy:deploy /home/deploy/.ssh
> sudo chmod 700 /home/deploy/.ssh && sudo chmod 600 /home/deploy/.ssh/authorized_keys
> ```

Autoriser `deploy` à redémarrer le worker sans mot de passe (nécessaire pour que `deploy.sh` puisse s'exécuter via SSH sans interaction) :

```bash
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart blog-scheduler' | sudo tee /etc/sudoers.d/deploy
sudo chmod 440 /etc/sudoers.d/deploy
sudo visudo -c
```

### 6. Donner accès au dépôt GitHub

Se connecter en tant que `deploy` et générer une clé SSH dédiée au serveur :

```bash
sudo -iu deploy
ssh-keygen -t ed25519 -C "deploy@blog" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
```

Sur GitHub, ouvrir le dépôt → **Settings → Deploy keys → Add deploy key**, coller la clé publique affichée et laisser **Allow write access** décoché (le serveur n'a besoin que de lire).

Tester la connexion :

```bash
ssh -T git@github.com
```

### 7. Installer MySQL et créer la base de données

Installer le serveur MySQL et le démarrer automatiquement au boot :

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
```

Vérifier la version installée (elle doit correspondre au `serverVersion` du `DATABASE_URL`, ici `8.4`) :

```bash
mysql --version
sudo systemctl status mysql
```

Sécuriser l'installation (supprimer les utilisateurs anonymes et la base de test, interdire la connexion distante de `root`) :

```bash
sudo mysql_secure_installation
```

> Sur Ubuntu, `root` se connecte à MySQL via le socket Unix (`auth_socket`) : pas de mot de passe,
> il suffit de lancer `sudo mysql`. MySQL n'écoute que sur `127.0.0.1` par défaut, ce qui suffit ici.

Créer la base et l'utilisateur de l'application :

```bash
sudo mysql -e "CREATE DATABASE blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'blog'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_MYSQL';"
sudo mysql -e "GRANT ALL PRIVILEGES ON blog.* TO 'blog'@'localhost'; FLUSH PRIVILEGES;"
```

### 8. Installer le projet depuis GitHub

Créer le dossier du projet et le donner à `deploy` :

```bash
sudo mkdir -p /var/www/blog
sudo chown deploy:www-data /var/www/blog
```

Puis, **en tant que `deploy`** :

```bash
cd /var/www/blog
git clone git@github.com:mendelbellaiche/blog_symfony.git .
```

Créer le fichier `.env.local` avec les secrets de production :

```bash
cat > .env.local <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(openssl rand -hex 32)
DATABASE_URL="mysql://blog:MOT_DE_PASSE_MYSQL@127.0.0.1:3306/blog?serverVersion=8.4&charset=utf8mb4"
EOF
chgrp www-data .env.local
chmod 640 .env.local
```

> PHP-FPM tourne sous `www-data` : il doit pouvoir lire `.env.local`. Avec un simple `chmod 600`,
> toutes les requêtes échouent en erreur 500 (`Unable to read the "/var/www/blog/.env.local" environment file`).

Donner à PHP-FPM (`www-data`) les droits d'écriture sur `var/` :

```bash
mkdir -p var
sudo setfacl -dR -m u:www-data:rwX -m u:deploy:rwX var
sudo setfacl -R -m u:www-data:rwX -m u:deploy:rwX var
```

Créer le dossier des images des articles et donner à PHP-FPM les droits d'écriture dessus. Les images sont envoyées depuis EasyAdmin dans `public/uploads/articles`, qui n'est pas versionné (`.gitignore`) :

```bash
mkdir -p public/uploads/articles
sudo setfacl -dR -m u:www-data:rwX -m u:deploy:rwX public/uploads
sudo setfacl -R -m u:www-data:rwX -m u:deploy:rwX public/uploads
```

Installer les dépendances, créer le schéma de la base et compiler les assets :

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports
php bin/console importmap:install
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
```

Rendre le script de déploiement exécutable :

```bash
chmod +x deploy.sh
```

### 9. Installer Supervisor et le worker du Scheduler

Le Scheduler Symfony (`src/Schedule.php`) a besoin d'un worker `messenger:consume` qui tourne en permanence. Supervisor le lance au démarrage et le relance s'il s'arrête.

Installer Supervisor et le démarrer automatiquement au boot :

```bash
sudo apt install -y supervisor
sudo systemctl enable --now supervisor
```

Créer la configuration du worker `blog-scheduler`. Le schedule est déclaré avec `#[AsSchedule]` sans nom, donc son transport s'appelle `scheduler_default` :

```bash
sudo tee /etc/supervisor/conf.d/blog-scheduler.conf > /dev/null <<'EOF'
[program:blog-scheduler]
command=php /var/www/blog/bin/console messenger:consume scheduler_default --time-limit=3600 --memory-limit=128M --env=prod
user=deploy
directory=/var/www/blog
autostart=true
autorestart=true
startsecs=0
stopwaitsecs=20
stdout_logfile=/var/log/supervisor/blog-scheduler.log
redirect_stderr=true
EOF
```

> `--time-limit` et `--memory-limit` font s'arrêter le worker proprement au bout d'une heure ou de 128 Mo ;
> Supervisor le relance aussitôt (`autorestart=true`), ce qui évite les fuites de mémoire.

Charger la configuration et vérifier que le worker tourne :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

La sortie doit afficher `blog-scheduler   RUNNING`. En cas de problème, consulter `/var/log/supervisor/blog-scheduler.log`.

### 10. Installer Nginx et le brancher sur le nom de domaine

Dans les commandes ci-dessous, remplacer `NOM_DE_DOMAINE` par le vrai domaine (par exemple `monblog.fr`).

#### Faire pointer le domaine vers le VPS

Chez le registrar du domaine, dans la zone DNS, créer deux enregistrements :

| Type | Nom | Valeur |
|---|---|---|
| `A` | `@` | `ADRESSE_IP_DU_VPS` |
| `A` | `www` | `ADRESSE_IP_DU_VPS` |

La propagation peut prendre de quelques minutes à quelques heures. Vérifier depuis le poste de développement :

```bash
dig +short NOM_DE_DOMAINE
dig +short www.NOM_DE_DOMAINE
```

Les deux commandes doivent afficher l'adresse IP du VPS.

#### Installer Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

Si le pare-feu `ufw` est actif (`sudo ufw status`), ouvrir les ports HTTP et HTTPS :

```bash
sudo ufw allow 'Nginx Full'
```

#### Configurer le site

Créer la configuration du site (d'après la [configuration Nginx recommandée par Symfony](https://symfony.com/doc/current/setup/web_server_configuration.html#nginx)) :

```bash
sudo tee /etc/nginx/sites-available/blog > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name NOM_DE_DOMAINE www.NOM_DE_DOMAINE;
    root /var/www/blog/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    # Assets compilés par AssetMapper : noms versionnés, donc cache long
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    # Interdire l'exécution de tout autre fichier PHP
    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/blog_error.log;
    access_log /var/log/nginx/blog_access.log;
}
EOF
```

> Le heredoc est entre guillemets (`<<'EOF'`) pour que le shell ne remplace pas les variables Nginx
> (`$uri`, `$realpath_root`…). Penser à remplacer `NOM_DE_DOMAINE` dans le fichier.

Activer le site, désactiver le site par défaut, vérifier la configuration et recharger Nginx :

```bash
sudo ln -s /etc/nginx/sites-available/blog /etc/nginx/sites-enabled/blog
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

Le blog doit maintenant répondre sur `http://NOM_DE_DOMAINE`.

#### Activer le HTTPS avec Let's Encrypt

Installer Certbot et générer le certificat. Certbot modifie automatiquement la configuration Nginx pour écouter en HTTPS et rediriger le HTTP vers le HTTPS :

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d NOM_DE_DOMAINE -d www.NOM_DE_DOMAINE
```

Le renouvellement est automatique (timer systemd installé avec Certbot). Vérifier qu'il fonctionne :

```bash
sudo certbot renew --dry-run
```

#### Indiquer le domaine à Symfony

`DEFAULT_URI` sert à générer les URL absolues hors requête HTTP (commandes, Scheduler, e-mails). En tant que `deploy`, l'ajouter à `.env.local` puis vider le cache et relancer le worker :

```bash
cd /var/www/blog
echo 'DEFAULT_URI=https://NOM_DE_DOMAINE' >> .env.local
php bin/console cache:clear
sudo supervisorctl restart blog-scheduler
```

Le projet est installé et accessible sur `https://NOM_DE_DOMAINE`. Les déploiements suivants se font avec `deploy.sh` (voir ci-dessous).

---

## Déployer une nouvelle version

### 1. Enregistrer les modifications (sur le poste de développement)

Vérifier les fichiers modifiés :

```bash
git status
```

Ajouter les modifications et créer un commit :

```bash
git add .
git commit -m "Description de la modification"
```

Envoyer le code sur GitHub :

```bash
git push
```

### 2. Lancer le déploiement sur le serveur

```bash
ssh deploy@ADRESSE_IP_DU_VPS /var/www/blog/deploy.sh
```

Cette commande se connecte au serveur et exécute le script `deploy.sh`. Chaque étape s'affiche dans le terminal, jusqu'au message `✓ Déploiement terminé`.

---

## Ce que fait le script `deploy.sh`

1. **Récupère le code** depuis GitHub (`git pull --ff-only`)
2. **Installe les dépendances** de production (`composer install --no-dev --optimize-autoloader`)
3. **Applique les migrations** de base de données
4. **Compile les assets** : bibliothèques JavaScript, CSS Tailwind minifié, AssetMapper
5. **Vide le cache** Symfony
6. **Redémarre le worker** du Scheduler pour qu'il charge le nouveau code

Le script s'arrête à la première erreur : si une étape échoue, les suivantes ne sont pas exécutées.

---

## Règles importantes

- **Ne jamais modifier le code directement sur le serveur.** Toutes les modifications passent par Git. Le script refuse de se lancer si le code du serveur a été modifié sur place.
- **Toujours créer une migration** pour chaque modification d'entité (`symfony console make:migration`), et la tester en local avant de déployer.
- **Le fichier `.env.local` du serveur** contient les secrets de production (`APP_SECRET`, mot de passe MySQL). Il n'est pas versionné et ne doit jamais l'être.

---

## Commandes utiles sur le serveur

Se connecter au serveur :

```bash
ssh deploy@ADRESSE_IP_DU_VPS
cd /var/www/blog
```

### Logs

```bash
# Erreurs de l'application Symfony
tail -n 50 var/log/prod.log

# Erreurs Nginx
sudo tail -n 50 /var/log/nginx/blog_error.log

# Worker du Scheduler
sudo tail -n 50 /var/log/supervisor/blog-scheduler.log
```

### Worker du Scheduler

```bash
# État du worker
sudo supervisorctl status

# Redémarrer le worker
sudo supervisorctl restart blog-scheduler

# Lister les tâches planifiées
php bin/console debug:scheduler

# Calculer les statistiques de visites du jour (par défaut : la veille)
php bin/console app:compute-daily-stats --date=today
```

### Donner le rôle administrateur à un utilisateur

L'utilisateur doit d'abord créer son compte via la page d'inscription du blog :

```bash
sudo mysql blog -e "UPDATE \`user\` SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'EMAIL_DE_L_UTILISATEUR'"
```

Pour le rôle auteur, remplacer `ROLE_ADMIN` par `ROLE_AUTHOR`. L'utilisateur doit se déconnecter puis se reconnecter pour que le changement soit pris en compte.

---

## À faire

- [ ] Acheter un nom de domaine et le faire pointer vers l'adresse IP du VPS
- [ ] Renseigner le domaine dans `server_name` de la configuration Nginx (`/etc/nginx/sites-available/blog`)
- [ ] Activer le HTTPS avec Certbot (Let's Encrypt)
