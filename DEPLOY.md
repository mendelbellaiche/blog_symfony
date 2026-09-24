# Déploiement

Ce document décrit comment mettre en ligne une nouvelle version du blog sur le serveur de production.

---

## Environnement de production

| Élément | Valeur |
|---|---|
| Serveur | VPS Ubuntu 26.04 LTS |
| Utilisateur | `deploy` |
| Dossier du projet | `/var/www/blog` |
| Serveur web | Nginx + PHP 8.5-FPM |
| Base de données | MySQL 8.4 |
| Worker du Scheduler | Supervisor (`blog-scheduler`) |

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
sudo tail -n 50 /var/log/blog-scheduler.log
```

### Worker du Scheduler

```bash
# État du worker
sudo supervisorctl status

# Redémarrer le worker
sudo supervisorctl restart blog-scheduler

# Lister les tâches planifiées
php bin/console debug:scheduler
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
