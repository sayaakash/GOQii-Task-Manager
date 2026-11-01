# GOQii Tasks

A beginner-friendly task manager built for the GOQii PHP/MySQL/React developer assessment. PHP 8+ with PDO serves a same-origin JSON API, MySQL stores the normalized application data, and React renders the browser UI without a frontend build step. Run it with Apache and MySQL from XAMPP.

## Implementation plan and status

1. Inspect the scaffold and choose the runtime layout: **complete**.
2. Create normalized schema, indexes, and PDO connection configuration: **complete**.
3. Implement session authentication, CSRF checks, role gates, and task APIs: **complete**.
4. Implement React authentication, dashboard, task CRUD, filters, pagination, and admin view: **complete**.
5. Document setup, API behavior, schema decisions, and verification notes: **complete**.

## Requirements covered

- **CRUD:** create, list, update, and delete tasks with title, description, status, priority, due date, and owner. Status filtering and page-based pagination are supported. The current member owns tasks they create; administrators can work with tasks across all owners.
- **Validation and API behavior:** input checks run in the browser and API; the API returns JSON and standard status codes including 201, 400, 401, 403, 404, 405, 409, 422, and 500. The UI has loading, empty, and error states.
- **Authentication:** user registration and login, `password_hash()`/`password_verify()`, regenerated PHP sessions, HTTP-only SameSite cookies, 30-minute inactivity expiry, logout, CSRF protection on mutations, and role-based admin endpoints. New registrations always receive the `user` role.
- **Admin:** admin users can see all tasks, overall counts, and the member list with task totals. Admin promotion is an intentional database operation; public registration cannot choose a role.
- **Database:** separate roles, users, and tasks tables; foreign keys, unique email, and indexes for task ownership/status/date access.

## Requirements

- XAMPP with Apache and MySQL/MariaDB running.
- PHP 8+ with `pdo_mysql`, `json`, and `mbstring` extensions enabled (these are included/enabled in a standard XAMPP PHP installation).
- No external runtime service is required. React 18 and Babel standalone browser builds are included under `frontend/vendor` with their MIT license texts. There is no Node.js server, npm install, Vite, Docker, or PostgreSQL requirement.

## Install in XAMPP

1. Place this project at `C:\xampp\htdocs\GOQii-Task-Manager` (or update the frontend API path if you use another directory name; see below).
2. Start Apache and MySQL in XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, choose **Import**, and import `database/schema.sql`. This creates `goqii_task_manager` and its tables/roles.
4. Copy `.env.example` to `.env`. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` for your local MySQL. A default XAMPP root account commonly uses `DB_USER=root` and an empty `DB_PASSWORD`; use a dedicated password-protected database user for any shared environment.
5. Visit `http://localhost/GOQii-Task-Manager/frontend/` and register an account.
6. To enable the admin dashboard, promote a registered account from phpMyAdmin SQL tab:

   ```sql
   USE goqii_task_manager;
   UPDATE users
   SET role_id = (SELECT id FROM roles WHERE name = 'admin')
   WHERE email = 'admin@example.com';
   ```

   Sign out and back in to refresh the role stored in the session.

The frontend derives the API path relative to its own URL, so the project folder can be renamed. Keep the frontend and API on the same origin so the session cookie is sent.

## Architecture

```text
frontend/index.html       React 18 browser entry point and local vendor scripts
frontend/js/App.js        UI, fetch helper, auth state, dashboard and forms
frontend/css/styles.css   Responsive dashboard and authentication styles
frontend/vendor/          React, ReactDOM and Babel browser builds plus licenses
backend/index.php         API bootstrap and route dispatch
backend/config/           PDO connection, JSON response and request helpers
backend/middleware/Auth.php Session authentication and admin role checks
backend/controllers/      Authentication and task validation/operations
backend/models/           PDO user and task data access
database/schema.sql       Tables, constraints, indexes and EXPLAIN notes
```

`Database` caches one PDO connection per request, uses exception mode, native prepared statements, and `utf8mb4`. Controllers return JSON; authorization is enforced in PHP before model operations. A normal user sees their own rows and cannot update/delete another user's task even if they guess its ID. Administrators can list and mutate tasks for every owner. The session stores the authenticated identity and role, and PHP session IDs rotate on successful login/registration.

## API

Base path in this XAMPP installation: `/GOQii-Task-Manager/backend/index.php`. Send JSON with `Content-Type: application/json`. Browser mutations must include `X-CSRF-Token`, obtained from `GET /auth/csrf`; session cookies are included automatically by same-origin fetch.

| Method | Path | Access | Behavior |
|---|---|---|---|
| GET | `/auth/csrf` | Public | Returns a token for the current session |
| GET | `/auth/me` | Public | Current user or `{ "user": null }` |
| POST | `/auth/register` | CSRF | `{name,email,password}`; creates a user and signs in |
| POST | `/auth/login` | CSRF | `{email,password}`; verifies the stored password hash |
| POST | `/auth/logout` | Signed in + CSRF | Invalidates the session |
| GET | `/dashboard` | Signed in | Status counts scoped to the user, or all tasks for admin |
| GET | `/tasks?page=1&per_page=10&status=todo` | Signed in | Paginated task list; status is optional (`todo`, `in-progress`, `done`) |
| POST | `/tasks` | Signed in + CSRF | Creates a task owned by the current account |
| PUT | `/tasks/{id}` | Owner/admin + CSRF | Updates title, description, status, priority and due date |
| DELETE | `/tasks/{id}` | Owner/admin + CSRF | Deletes a task |
| GET | `/admin/users` | Admin | Members, role, join date and task count |
| GET | `/admin/summary` | Admin | Global counts and user/task totals |

Task body example:

```json
{
  "title": "Prepare assessment demo",
  "description": "Walk through task CRUD and role checks.",
  "status": "in-progress",
  "priority": "high",
  "due_date": "2026-12-15"
}
```

Task lists return `{ "data": [...], "pagination": {"page":1,"per_page":10,"total":1,"pages":1} }`. Validation errors return 422 and may include a `fields` map. Unauthenticated protected requests return 401; authenticated users forbidden from an operation receive 403.

## Database and query notes

- `roles` is a small lookup table. `users.role_id` references it; unique `users.email` prevents duplicate accounts.
- Each task references one `users.id` through `owner_id`. Deleting an account cascades to its tasks. The API never accepts a role from registration input.
- `idx_tasks_owner_status_due (owner_id,status,due_date)` supports a member's owner-scoped list and status-filtered list. `idx_tasks_due_date` supports due-date-oriented cross-owner access. Unique email and role reference indexes support joins and account lookups.
- The filtered task list selects task rows and owner name via an indexed owner join, with a separate count query for pagination. Bound integer LIMIT/OFFSET values and prepared filters avoid SQL injection.
- `database/schema.sql` includes an `EXPLAIN` example and expected access path. The exact optimizer plan and row estimates depend on MySQL version and table size; run that statement in phpMyAdmin after adding representative data. A broad admin status query may scan tasks; only add a `(status,due_date)` index if measured plans or slow-query logs justify its write/storage cost.

## Verification

All PHP files pass `php -l`. Registration, login rejection, task create/list/update/status/filter/pagination/delete, invalid-input rejection, owner isolation, CSRF rejection, 401/403 responses, and administrator cross-owner access and statistics were exercised against the local XAMPP database. Chrome headless mounted the sign-in screen with the local React/Babel files; a temporary browser error collector observed no page-level JavaScript errors. The expiry branch was simulated with a session timestamp older than 30 minutes and returned 401; the actual 30-minute wall-clock interval was not waited out.

This application does not attempt optional assessment tasks.
