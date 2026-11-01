<?php
declare(strict_types=1);

final class TaskController
{
    private const STATUSES=['todo','in-progress','done'];
    private const PRIORITIES=['low','medium','high'];
    public function __construct(private Task $tasks) {}
    public function index(array $user): void
    {
        $status=$_GET['status'] ?? null;
        if ($status!==null && $status!=='' && !in_array($status,self::STATUSES,true)) Api::json(['error'=>'Invalid status filter.'],422);
        $page=filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT); $limit=filter_var($_GET['per_page'] ?? 10,FILTER_VALIDATE_INT);
        if (!$page || $page<1 || !$limit || $limit<1 || $limit>50) Api::json(['error'=>'Page must be positive and per_page must be between 1 and 50.'],422);
        Api::json($this->tasks->listing($user,$status ?: null,$page,$limit));
    }
    public function create(array $user): void
    {
        Api::csrf(); $data=$this->validated(Api::body()); $id=$this->tasks->save($data,(int)$user['id']); Api::json(['data'=>$this->tasks->find($id)],201);
    }
    public function update(array $user,int $id): void
    {
        Api::csrf(); $task=$this->visible($user,$id); $data=$this->validated(Api::body()); $this->tasks->save($data,(int)$task['owner_id'],$id); Api::json(['data'=>$this->tasks->find($id)]);
    }
    public function delete(array $user,int $id): void
    {
        Api::csrf(); $this->visible($user,$id); $this->tasks->delete($id); Api::json(['message'=>'Task deleted.']);
    }
    private function visible(array $user,int $id): array
    {
        $task=$this->tasks->find($id); if (!$task) Api::json(['error'=>'Task not found.'],404);
        if ($user['role']!=='admin' && (int)$task['owner_id'] !== (int)$user['id']) Api::json(['error'=>'You cannot access this task.'],403);
        return $task;
    }
    private function validated(array $body): array
    {
        $title=trim((string)($body['title'] ?? '')); $description=trim((string)($body['description'] ?? ''));
        $status=(string)($body['status'] ?? 'todo'); $priority=(string)($body['priority'] ?? 'medium'); $due=$body['due_date'] ?? null;
        $errors=[];
        if ($title==='' || mb_strlen($title)>160) $errors['title']='Title is required and must be at most 160 characters.';
        if (mb_strlen($description)>5000) $errors['description']='Description must be at most 5000 characters.';
        if (!in_array($status,self::STATUSES,true)) $errors['status']='Choose a valid task status.';
        if (!in_array($priority,self::PRIORITIES,true)) $errors['priority']='Choose a valid priority.';
        if ($due!==null && $due!=='' && (!is_string($due) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$due) || !checkdate((int)substr($due,5,2),(int)substr($due,8,2),(int)substr($due,0,4)))) $errors['due_date']='Enter a valid date.';
        if ($errors) Api::json(['error'=>'Please correct the highlighted fields.','fields'=>$errors],422);
        return ['title'=>$title,'description'=>$description ?: null,'status'=>$status,'priority'=>$priority,'due_date'=>($due ?: null)];
    }
}
