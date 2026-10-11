# Optimisations de performance appliquées

## Ce qui a été corrigé dans le code

1. **`app/Models/User.php`** — `isAdmin()` et `isManager()` mémorisent maintenant
   leur résultat sur l'instance (`$isAdminCache` / `$isManagerCache`). Comme le
   middleware ET le contrôleur appelaient chacun leur propre vérification,
   une requête pouvait déclencher 6 à 10 requêtes SQL rien que pour vérifier
   les droits. Maintenant, tout le monde utilise `$user->isAdmin()` /
   `$user->isManager()`, calculés une seule fois par requête HTTP.

2. **`app/Http/Middleware/AdminMiddleware.php`** — réécrit pour utiliser
   `$user->isManager()` au lieu de dupliquer 4 requêtes SQL + 3 logs à chaque
   passage.

3. **`EmployeeController`, `TaskController`, `AnnouncementController`** —
   toute la logique "est-ce un manager ?" dupliquée un peu partout a été
   remplacée par `$user->isManager()` / `$user->isAdmin()`. Les `Log::info`
   volumineux (tableaux de debug) sur les routes les plus utilisées
   (`index`, `uploadPhoto`, `managerTeamTasks`...) ont été retirés ; seuls les
   logs d'erreur utiles restent.

4. **Email de bienvenue** (`EmployeeController::store`) — passe de
   `Mail::send()` (bloquant, attend la connexion SMTP Gmail) à
   `Mail::queue()` (asynchrone). **Il faut un worker de queue actif** :
   ```bash
   php artisan queue:work
   ```
   (ou `queue:listen` en dev). Sans ça, les emails resteront en attente dans
   la table `jobs` sans être envoyés.

5. **Nouvelle migration `2026_07_11_143658_add_performance_indexes.php`** —
   ajoute les index manquants sur `employee_id`, `department_id`, `status`,
   `date`, `role`, etc. Avec SQLite, `foreignId()->constrained()` ne crée pas
   d'index automatiquement (contrairement à MySQL/InnoDB) : les filtres comme
   `where('employee_id', ...)` faisaient un scan complet de table. Testée avec
   succès (`php artisan migrate`).

6. **`.env`** — logs en rotation quotidienne (`LOG_STACK=daily` au lieu de
   `single`) pour éviter un fichier `laravel.log` unique qui grossit à l'infini
   et ralentit chaque écriture.

## À faire vous-même avant/pendant le déploiement en production

Ces points ne pouvaient pas être appliqués sans risquer de casser votre
environnement de dev local, mais ils sont **importants** :

- [ ] **`APP_ENV=production`** et **`APP_DEBUG=false`** dans le `.env` de
      production (actuellement `local` / `true`, ce qui désactive les caches
      Laravel et génère des traces d'erreurs coûteuses à chaque exception).
- [ ] **`LOG_LEVEL=error`** en production (au lieu de `debug`).
- [ ] Lancer après chaque déploiement :
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan event:cache
  php artisan migrate --force
  composer install --no-dev --optimize-autoloader
  ```
- [ ] Démarrer un worker de queue en permanence (supervisor, systemd, ou
      équivalent hébergeur) : `php artisan queue:work --daemon`.
- [ ] **Base de données** : SQLite convient pour le développement, mais pour
      la production d'un système RH avec plusieurs utilisateurs simultanés
      (pointages, tâches...), préférez MySQL/PostgreSQL — SQLite verrouille
      toute la base à chaque écriture, ce qui devient un goulot d'étranglement
      dès qu'il y a de la concurrence.
- [ ] Activer OPcache côté serveur PHP (souvent activé par défaut chez les
      hébergeurs, à vérifier).
