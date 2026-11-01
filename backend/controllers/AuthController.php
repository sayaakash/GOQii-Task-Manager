<?php
declare(strict_types=1);

final class AuthController
{
    public function __construct(private PDO $db, private User $users) {}
    public function csrf(): void { Api::json(['csrfToken' => $_SESSION['csrf']]); }
    public function me(): void
    {
        if (!isset($_SESSION['user_id'])) Api::json(['user' => null]);
        $user = Auth::user(); Api::json(['user' => $user]);
    }
    public function register(): void
    {
        Api::csrf(); $body=Api::body();
        $name=trim((string)($body['name'] ?? '')); $email=strtolower(trim((string)($body['email'] ?? ''))); $password=(string)($body['password'] ?? '');
        $errors=[];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors['name']='Name must be between 2 and 100 characters.';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) $errors['email']='Enter a valid email address.';
        if (strlen($password)<10 || strlen($password)>200) $errors['password']='Password must be between 10 and 200 characters.';
        if ($errors) Api::json(['error'=>'Please correct the highlighted fields.','fields'=>$errors],422);
        if ($this->users->byEmail($email)) Api::json(['error'=>'An account with this email already exists.','fields'=>['email'=>'Email is already registered.']],409);
        try { $id=$this->users->create($name,$email,$password); }
        catch (PDOException $e) { if ($e->getCode()==='23000') Api::json(['error'=>'An account with this email already exists.'],409); throw $e; }
        $row=$this->users->byEmail($email); $this->startSession($row);
        Api::json(['user'=>$this->sessionUser()],201);
    }
    public function login(): void
    {
        Api::csrf(); $body=Api::body(); $email=strtolower(trim((string)($body['email'] ?? ''))); $password=(string)($body['password'] ?? '');
        $row=$this->users->byEmail($email);
        if (!$row || !password_verify($password,$row['password_hash'])) Api::json(['error'=>'Email or password is incorrect.'],401);
        if (password_needs_rehash($row['password_hash'],PASSWORD_DEFAULT)) { $s=$this->db->prepare('UPDATE users SET password_hash=:hash WHERE id=:id'); $s->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$row['id']]); }
        $this->startSession($row); Api::json(['user'=>$this->sessionUser()]);
    }
    public function logout(): void
    {
        Api::csrf(); $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
        session_destroy(); Api::json(['message'=>'Signed out.']);
    }
    private function startSession(array $row): void
    {
        session_regenerate_id(true); $_SESSION['user_id']=(int)$row['id']; $_SESSION['name']=$row['name']; $_SESSION['email']=$row['email']; $_SESSION['role']=$row['role']; $_SESSION['last_activity']=time(); $_SESSION['csrf']=bin2hex(random_bytes(32));
    }
    private function sessionUser(): array { return ['id'=>(int)$_SESSION['user_id'],'name'=>$_SESSION['name'],'email'=>$_SESSION['email'],'role'=>$_SESSION['role']]; }
}
