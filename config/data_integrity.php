<?php
declare(strict_types=1);

/**
 * Application-owned data-integrity rules.
 *
 * Family -> orphan is a mandatory domain relationship. A family may not be
 * created without an orphan and may not be edited through the family-update
 * path while it has zero orphan records. Sponsorships remain orphan-level.
 */
if (!function_exists('ak_family_orphan_count')) {
    function ak_family_orphan_count(int $familyId): int
    {
        if ($familyId <= 0) return 0;
        $row = dbFetchOne("SELECT COUNT(*) AS c FROM family_children WHERE family_id = ?", [$familyId]);
        return (int)($row['c'] ?? 0);
    }
}
if (!function_exists('ak_family_has_orphan')) {
    function ak_family_has_orphan(int $familyId): bool
    {
        return ak_family_orphan_count($familyId) > 0;
    }
}
if (!function_exists('ak_sync_family_children_count')) {
    function ak_sync_family_children_count(int $familyId): void
    {
        if ($familyId <= 0) return;
        dbExecute("UPDATE families SET children_count = (SELECT COUNT(*) FROM family_children WHERE family_id = ?) WHERE id = ?", [$familyId, $familyId]);
    }
}
if (!function_exists('ak_sync_family_children_count_for_child')) {
    function ak_sync_family_children_count_for_child(int $childId): void
    {
        if ($childId <= 0) return;
        $row = dbFetchOne("SELECT family_id FROM family_children WHERE id = ?", [$childId]);
        if ($row) ak_sync_family_children_count((int)$row['family_id']);
    }
}
if (!function_exists('ak_sync_all_family_children_counts')) {
    function ak_sync_all_family_children_counts(): void
    {
        dbExecute("UPDATE families f SET f.children_count = (SELECT COUNT(*) FROM family_children fc WHERE fc.family_id = f.id)");
    }
}
if (!function_exists('ak_supervisor_gender_normalize')) {
    function ak_supervisor_gender_normalize(?string $raw): string
    {
        $v = strtolower(trim((string)$raw));
        if (in_array($v, ['female','f','أنثى','انثى'], true)) return 'female';
        if (in_array($v, ['male','m','ذكر'], true)) return 'male';
        return '';
    }
}
if (!function_exists('ak_get_supervisor_matrix')) {
    function ak_get_supervisor_matrix(): array
    {
        $matrix=[];
        foreach (dbFetchAll("SELECT sl.letter_id, sl.supervisor_id, sl.gender FROM supervisor_letters sl WHERE sl.supervisor_id IS NOT NULL") as $row) {
            $letterId=(int)$row['letter_id']; $supervisorId=(int)$row['supervisor_id']; if($letterId<=0||$supervisorId<=0)continue;
            $gender=ak_supervisor_gender_normalize($row['gender']??null); if($gender==='')$matrix[$letterId]['legacy']=$supervisorId;else $matrix[$letterId][$gender]=$supervisorId;
        }
        return $matrix;
    }
}
if (!function_exists('ak_resolve_sponsor_supervisor')) {
    function ak_resolve_sponsor_supervisor(?int $letterId, ?string $gender, array $matrix): ?int
    {
        if(!$letterId||!isset($matrix[$letterId]))return null; $g=ak_supervisor_gender_normalize($gender);
        if($g!==''){if(isset($matrix[$letterId][$g]))return(int)$matrix[$letterId][$g];if(isset($matrix[$letterId]['legacy']))return(int)$matrix[$letterId]['legacy'];return null;}
        foreach(['legacy','male','female'] as $candidate)if(isset($matrix[$letterId][$candidate]))return(int)$matrix[$letterId][$candidate]; return null;
    }
}
if (!function_exists('ak_sync_non_manual_sponsor_supervisors')) {
    function ak_sync_non_manual_sponsor_supervisors(?int $sponsorId=null): void
    {
        $params=[];$where="COALESCE(s.is_manual_override,0)=0"; if($sponsorId!==null&&$sponsorId>0){$where.=" AND s.id = ?";$params[]=$sponsorId;}
        $sponsors=dbFetchAll("SELECT s.id,s.first_letter_id,s.gender FROM sponsors s WHERE {$where} ORDER BY s.id",$params); if(!$sponsors)return; $matrix=ak_get_supervisor_matrix();
        foreach($sponsors as $sponsor){$resolved=ak_resolve_sponsor_supervisor(isset($sponsor['first_letter_id'])?(int)$sponsor['first_letter_id']:null,$sponsor['gender']??null,$matrix);dbExecute("UPDATE sponsors SET supervisor_id=? WHERE id=? AND COALESCE(is_manual_override,0)=0",[$resolved,(int)$sponsor['id']]);}
    }
}
if (!function_exists('ak_register_data_integrity_hooks')) {
    function ak_register_data_integrity_hooks(): void
    {
        $path=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:''; $method=$_SERVER['REQUEST_METHOD']??'GET';
        /* Block family edits that would operate on an invalid orphanless family.
         * Adding a child uses the separate add_child action and is therefore not blocked. */
        if($method==='POST' && str_ends_with($path,'/modules/families/edit.php') && isset($_POST['update_family'])){
            $familyId=(int)($_POST['id']??$_GET['id']??0);
            if($familyId>0 && !ak_family_has_orphan($familyId)){
                flash('error','لا يمكن حفظ بيانات الأسرة قبل تسجيل يتيم واحد على الأقل. أضف اليتيم أولاً ثم احفظ بيانات الأسرة.');
                header('Location: '.APP_URL.'modules/families/edit.php?id='.$familyId); exit();
            }
        }
        if(str_ends_with($path,'/modules/families/edit.php')){
            $familyId=(int)($_GET['id']??$_POST['id']??0); if($familyId>0)register_shutdown_function(static function()use($familyId):void{try{ak_sync_family_children_count($familyId);}catch(Throwable $e){error_log('Family children count sync: '.$e->getMessage());}});
        }
        if(str_ends_with($path,'/modules/families/orphan_form.php')){
            $childId=(int)($_GET['child']??$_GET['id']??$_POST['child_id']??$_POST['id']??0); if($childId>0)register_shutdown_function(static function()use($childId):void{try{ak_sync_family_children_count_for_child($childId);}catch(Throwable $e){error_log('Orphan-form family children count sync: '.$e->getMessage());}});
        }
        if($method==='POST'&&str_ends_with($path,'/modules/supervisors/assign-letters.php'))register_shutdown_function(static function():void{try{ak_sync_non_manual_sponsor_supervisors();}catch(Throwable $e){error_log('Supervisor sponsor sync: '.$e->getMessage());}});
        if($method==='POST'&&(str_ends_with($path,'/modules/sponsors/create.php')||str_ends_with($path,'/modules/sponsors/edit.php')))register_shutdown_function(static function():void{try{ak_sync_non_manual_sponsor_supervisors();}catch(Throwable $e){error_log('Sponsor supervisor sync: '.$e->getMessage());}});
    }
}
