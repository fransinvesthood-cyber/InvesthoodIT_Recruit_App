<?php
function cm_label(?string $v): string {
    $v = trim((string)$v);
    return $v === '' ? 'Unknown' : ucwords(str_replace('_',' ',$v));
}
function cm_status_class(?string $v): string {
    return match(strtolower(trim((string)$v))) {
        'generated','ready','active','verified' => 'success',
        'draft','pending' => 'warning',
        'suppressed','restricted' => 'purple',
        'failed','expired','inactive' => 'danger',
        default => 'info'
    };
}
function cm_date(?string $v, string $fallback='Not set'): string {
    if (!$v) return $fallback;
    $t = strtotime($v);
    return $t ? date('d M Y',$t) : $fallback;
}
function cm_datetime(?string $v, string $fallback='Not available'): string {
    if (!$v) return $fallback;
    $t = strtotime($v);
    return $t ? date('d M Y, H:i',$t) : $fallback;
}
function cm_initials(string $f,string $l): string {
    $i = ($f !== '' ? strtoupper(substr($f,0,1)) : '') . ($l !== '' ? strtoupper(substr($l,0,1)) : '');
    return $i ?: 'CM';
}
function cm_table(mysqli $c,string $t): bool {
    static $cache=[];
    if (array_key_exists($t,$cache)) return $cache[$t];
    $safe=$c->real_escape_string($t);
    $r=$c->query("SHOW TABLES LIKE '{$safe}'");
    return $cache[$t]=(bool)($r && $r->num_rows);
}
function cm_csrf(): string {
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['cm_csrf'])) $_SESSION['cm_csrf']=bin2hex(random_bytes(32));
    return $_SESSION['cm_csrf'];
}
function cm_verify_csrf(): void {
    $a=(string)($_SESSION['cm_csrf']??''); $b=(string)($_POST['csrf_token']??'');
    if ($a==='' || $b==='' || !hash_equals($a,$b)) { http_response_code(419); exit('Invalid security token.'); }
}
function cm_flash(string $type,string $message): void {
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    $_SESSION['cm_flash']=['type'=>$type,'message'=>$message];
}
function cm_local_flash(): string {
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    $f=$_SESSION['cm_flash']??null; unset($_SESSION['cm_flash']);
    if (!$f) return '';
    $t=htmlspecialchars($f['type']??'info',ENT_QUOTES,'UTF-8');
    $m=htmlspecialchars($f['message']??'',ENT_QUOTES,'UTF-8');
    return "<div class=\"cm-alert cm-alert--{$t}\"><i class=\"fas fa-circle-info\"></i><div>{$m}</div></div>";
}
function cm_threshold(mysqli $c): int {
    if (!cm_table($c,'commercial_privacy_rules')) return 5;
    $r=$c->query("SELECT small_group_threshold FROM commercial_privacy_rules WHERE is_active=1 ORDER BY id DESC LIMIT 1");
    $row=$r?$r->fetch_assoc():null;
    return max(1,(int)($row['small_group_threshold']??5));
}
function cm_currency(?string $d): string {
    if (!$d || !($t=strtotime($d))) return 'Unknown';
    $days=(int)floor((time()-$t)/86400);
    return $days<=30?'Current':($days<=90?'Recent':($days<=180?'Aging':'Stale'));
}
function cm_json(?string $j): array {
    $d=json_decode((string)$j,true); return is_array($d)?$d:[];
}
function cm_activity(mysqli $c,int $id,string $action,string $desc,?string $type=null,?int $entity=null): void {
    if (!cm_table($c,'commercial_manager_activity_log')) return;
    $ip=$_SERVER['REMOTE_ADDR']??null; $ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255);
    $s=$c->prepare("INSERT INTO commercial_manager_activity_log(commercial_manager_id,action,description,entity_type,entity_id,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
    if(!$s)return;
    $s->bind_param('isssiss',$id,$action,$desc,$type,$entity,$ip,$ua); $s->execute(); $s->close();
}
?>