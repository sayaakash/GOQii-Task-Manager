<?php
declare(strict_types=1);

require_once __DIR__ . '/config/Api.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Task.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/TaskController.php';

ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Strict');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));

try {
    $db=Database::connection(); $users=new User($db); $tasks=new Task($db);
    $authController=new AuthController($db,$users); $taskController=new TaskController($tasks);
    $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
    $path=preg_replace('#^.*?/backend/index\.php#','',$path) ?: '/';
    $path='/' . trim($path,'/'); if ($path==='//') $path='/';
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($path==='/auth/csrf' && $method==='GET') $authController->csrf();
    if ($path==='/auth/me' && $method==='GET') $authController->me();
    if ($path==='/auth/register' && $method==='POST') $authController->register();
    if ($path==='/auth/login' && $method==='POST') $authController->login();
    if ($path==='/auth/logout' && $method==='POST') $authController->logout();

    if (str_starts_with($path,'/tasks')) {
        $user=Auth::user();
        if ($path==='/tasks' && $method==='GET') $taskController->index($user);
        if ($path==='/tasks' && $method==='POST') $taskController->create($user);
        if (preg_match('#^/tasks/(\d+)$#',$path,$m)) {
            $id=(int)$m[1];
            if ($method==='PUT') $taskController->update($user,$id);
            if ($method==='DELETE') $taskController->delete($user,$id);
        }
        Api::json(['error'=>'Method not allowed.'],405);
    }
    if ($path==='/dashboard' && $method==='GET') { $user=Auth::user(); Api::json(['counts'=>$tasks->summary($user),'user'=>$user]); }
    if ($path==='/admin/users' && $method==='GET') { $user=Auth::user(); Auth::admin($user); Api::json(['data'=>$users->all()]); }
    if ($path==='/admin/summary' && $method==='GET') {
        $user=Auth::user(); Auth::admin($user);
        $counts=$tasks->summary($user); $totalUsers=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        Api::json(['counts'=>$counts,'total_users'=>$totalUsers,'total_tasks'=>array_sum($counts)]);
    }
    Api::json(['error'=>'Endpoint not found.'],404);
} catch (Throwable $e) {
    error_log('[GOQii Task Manager] ' . $e->getMessage());
    Api::json(['error'=>'The server could not complete the request. Check PHP and database configuration.'],500);
}
