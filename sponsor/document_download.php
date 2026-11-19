<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_role('sponsor');
$conn=Database::getConnection();$sponsorId=(int)(current_user()['id']??0);$documentId=(int)($_GET['id']??0);
if($documentId<1){http_response_code(400);exit('Invalid document request.');}
$sql="SELECT v.storage_path,v.original_filename,v.mime_type,v.file_size FROM sponsor_document_versions v JOIN sponsor_documents d ON d.id=v.document_id JOIN sponsor_document_programmes dp ON dp.document_id=d.id JOIN sponsor_programme_access a ON a.programme_id=dp.programme_id AND a.sponsor_user_id=? AND a.access_status='active' WHERE d.id=? AND d.status='published' AND v.status='published' AND v.version_number=(SELECT MAX(v2.version_number) FROM sponsor_document_versions v2 WHERE v2.document_id=d.id AND v2.status='published') AND (d.available_from IS NULL OR d.available_from<=NOW()) AND (d.available_until IS NULL OR d.available_until>=NOW()) LIMIT 1";
$st=$conn->prepare($sql);if(!$st){http_response_code(500);exit('Unable to retrieve document.');}$st->bind_param('ii',$sponsorId,$documentId);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();
if(!$row){http_response_code(404);exit('Document not found or access is not authorised.');}
$base=realpath(__DIR__.'/../storage/sponsor_documents');$path=$base?realpath($base.DIRECTORY_SEPARATOR.$row['storage_path']):false;
if(!$base||!$path||strpos($path,$base.DIRECTORY_SEPARATOR)!==0||!is_file($path)){http_response_code(404);exit('Document file is unavailable.');}
$name=preg_replace('/[^A-Za-z0-9._ -]/','_',basename((string)$row['original_filename']));$mime=(string)($row['mime_type']??'application/octet-stream');if(!preg_match('#^[a-zA-Z0-9.+-]+/[a-zA-Z0-9.+-]+$#',$mime))$mime='application/octet-stream';
header('X-Content-Type-Options: nosniff');header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.str_replace('"','',$name).'"');readfile($path);exit;
