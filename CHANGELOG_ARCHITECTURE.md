# CHANGELOG — Refonte architecture Hodo_Api

Ce document liste **tout ce qui a changé** par rapport à ta version d'origine,
pour que tu puisses vérifier / ajuster si un comportement ne te convient pas.

## 🐛 Bugs corrigés

1. **Middleware `admin` qui laissait passer les managers.**
   Avant : `'admin' => AdminMiddleware::class` et `'is_manager' => AdminMiddleware::class`
   pointaient vers la **même classe**, qui ne testait que `isManager()`. Résultat :
   un manager pouvait appeler les routes CRUD départements/rôles/managers, le
   dashboard global, l'export des présences, etc.
   Maintenant : `admin` → `EnsureUserIsAdmin` (teste `isAdmin()` uniquement),
   `manager` → `EnsureUserIsManagerOrAdmin` (teste `isManager()`, donc admin OU manager,
   comme avant pour les routes qui doivent rester partagées).

2. **`GET /employees` accessible à n'importe quel employé.**
   Avant : la route était déclarée *en dehors* des groupes de rôle, donc un simple
   employé connecté pouvait lister tous les employés (nom, email, **salaire**...).
   Le filtrage par département ne s'appliquait qu'aux managers ; un employé "normal"
   tombait dans aucun des deux cas et recevait la liste complète.
   Maintenant : cette route est sous le middleware `manager` (manager ou admin uniquement).
   → Si ton frontend employé a besoin d'une liste de collègues (ex. pour un
   sélecteur), dis-le-moi, on ajoute une route dédiée qui ne renvoie que
   les noms (pas le salaire).

3. **`Presence::status` n'était pas dans `$fillable`.**
   Le check-in créait la présence avec `'status' => 'present'`, mais ce champ
   n'étant pas fillable, Laravel l'ignorait silencieusement (pas d'erreur,
   juste une colonne `status` toujours `null` pour les pointages du jour).
   Corrigé : `status` est maintenant fillable et casté en enum `PresenceStatus`.

4. **Code mort supprimé** : `AdminLeaveRequestController` (déclaré mais jamais
   routé dans `api.php`) et `EmailController` (route jamais déclarée, logique
   redondante avec l'envoi automatique dans `EmployeeService::create()`).

5. **`EmployeeRoleController` existait mais n'avait aucune route.** Je l'ai câblé
   sous `/admin/employee-roles` puisque la logique semblait terminée et utile
   (association employé ↔ rôle). Supprime la section correspondante dans
   `routes/api/admin.php` si tu n'en as pas besoin.

## 🏗️ Nouvelle architecture

```
app/
├── Enums/                      UserRole, TaskStatus, LeaveRequestStatus,
│                                EmployeeStatus, PresenceStatus
├── Http/
│   ├── Controllers/Api/
│   │   ├── Auth/                AuthController
│   │   ├── Employee/            self-service : Profile, Dashboard, Task,
│   │   │                        Presence, LeaveRequest, Announcement
│   │   ├── Manager/              gestion d'équipe : Employee, Task,
│   │   │                        Announcement, Presence, LeaveRequest
│   │   └── Admin/                config système : Employee, Department,
│   │                             Role, Manager, EmployeeRole, Task,
│   │                             Presence, LeaveRequest, Dashboard
│   ├── Middleware/               EnsureUserIsAdmin, EnsureUserIsManagerOrAdmin
│   └── Requests/                 1 FormRequest par action d'écriture,
│                                 rangée par domaine (Auth/, Task/, ...)
├── Services/                     TOUTE la logique métier (avant dans les
│                                 contrôleurs) : EmployeeService, TaskService,
│                                 PresenceService, LeaveRequestService,
│                                 AnnouncementService, DepartmentService,
│                                 RoleService, ManagerService,
│                                 EmployeeRoleService, DashboardService,
│                                 AuthService
└── Models/                       inchangés dans leurs relations, avec des
                                  casts enum ajoutés sur les colonnes status

routes/
├── api.php                      agrégateur (10 lignes, juste les groupes
│                                 de middleware + les require)
└── api/
    ├── auth.php                 routes publiques
    ├── employee.php              auth:sanctum (tout utilisateur connecté)
    ├── manager.php               + middleware 'manager'
    └── admin.php                 + middleware 'admin'
```

### Le principe (pourquoi c'est plus rapide à lire)

- **Un contrôleur = un seul niveau d'accès.** Plus besoin de lire chaque
  méthode pour savoir qui a le droit d'appeler quoi : le dossier (`Employee/`,
  `Manager/`, `Admin/`) te le dit déjà.
- **Le contrôleur ne fait que 3 choses** : valider (FormRequest), appeler le
  Service, formatter la réponse JSON. Aucune requête Eloquent, aucun
  `Storage::`, aucune règle métier dans un contrôleur.
- **Le Service ne connaît pas HTTP.** Il reçoit des types PHP simples (User,
  Employee, tableaux validés, UploadedFile) et renvoie des modèles ou lève des
  exceptions (`AuthorizationException`, `RuntimeException`). Ça le rend
  testable sans faire de vraies requêtes HTTP, et réutilisable (ex. depuis une
  commande artisan ou un futur job en file d'attente).
- **Les enums remplacent les strings magiques** (`'pending'`, `'admin'`...).
  Ton IDE te propose maintenant les valeurs possibles et une faute de frappe
  devient une erreur de compilation plutôt qu'un bug silencieux en prod.

## ⚡ Sur la lenteur que tu observais

Le code avait déjà pas mal d'optimisations ponctuelles (colonnes `select()`
ciblées, cache d'instance sur `isManager()`/`isAdmin()`, index de perf
ajoutés en migration). La vraie cause de lenteur perçue vient plus
probablement de :
- **Pas de queue worker actif** : les emails (`Mail::queue()`) restent
  coincés dans la table `jobs` tant que `php artisan queue:work` ne tourne
  pas (vérifie `QUEUE_CONNECTION` dans `.env` — s'il est sur `sync`, ça
  bloque la requête HTTP le temps de l'envoi SMTP).
- **N+1 potentiels** restants sur certains écrans React qui appellent
  `show()` en boucle plutôt qu'un `index()` avec les relations déjà chargées.
Si tu veux, dans un second temps on profile une route précise (ex. avec
`DB::listen()` ou Laravel Debugbar) pour confirmer.

## Fichiers PHP supprimés

- `app/Http/Controllers/AuthController.php` → `Api/Auth/AuthController.php`
- `app/Http/Controllers/Api/EmployeeController.php` → éclaté en 3
- `app/Http/Controllers/Api/TaskController.php` → éclaté en 3
- `app/Http/Controllers/Api/PresenceController.php` → éclaté en 3
- `app/Http/Controllers/Api/LeaveRequestController.php` → éclaté en 3
- `app/Http/Controllers/Api/AnnouncementController.php` → éclaté en 2
- `app/Http/Controllers/Api/DepartmentController.php` → `Api/Admin/DepartmentController.php`
- `app/Http/Controllers/Api/RoleController.php` → `Api/Admin/RoleController.php`
- `app/Http/Controllers/Api/ManagerController.php` → `Api/Admin/ManagerController.php`
- `app/Http/Controllers/Api/EmployeeRoleController.php` → `Api/Admin/EmployeeRoleController.php`
- `app/Http/Controllers/Api/DashboardController.php` → éclaté en 2
- `app/Http/Controllers/Api/AdminLeaveRequestController.php` → supprimé (mort)
- `app/Http/Controllers/Api/EmailController.php` → supprimé (mort)
- `app/Http/Middleware/AdminMiddleware.php` → remplacé par 2 classes

## ⚠️ Impact potentiel côté frontend React à vérifier

- Un manager qui appelait `/departments`, `/roles`, `/managers` ou
  `/dashboard-summary` (à cause du bug #1) recevra maintenant un **403**.
  Vérifie que le menu manager de `Hodo_React_typescript` n'affiche pas ces
  écrans (probable, puisque ce n'était pas voulu).
- Un employé "simple" qui appelait `/employees` recevra un **403** (bug #2).
