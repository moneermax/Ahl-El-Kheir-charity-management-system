<?php
// Shared standalone Fina Al-Khair data and accounting helpers.
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once __DIR__.'/lib.php';
require_once __DIR__.'/lib_transaction_review.php';

/**
 * Verify that the standalone Fina schema has been provisioned by the database
 * migration. Normal web requests must never create or alter application schema.
 */
function fina_ensure_tables(): void{
    $sources = dbFetchOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='fina_sources'");
    $collections = dbFetchOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='fina_collections'");
    if ((int)($sources['n'] ?? 0) !== 1 || (int)($collections['n'] ?? 0) !== 1) {
        throw new RuntimeException('جداول فينا الخير غير مهيأة. يجب تطبيق migration الخاصة بفينا الخير قبل استخدام هذه الصفحة.');
    }
}

function fina_source_from_sponsor(int $sponsorId): ?array{
    if($sponsorId<=0)return null;
    return dbFetchOne("SELECT id,sponsor_code,full_name,phone,alt_phone,email,address,sponsor_type,gender,status FROM sponsors WHERE id=? LIMIT 1",[$sponsorId]);
}

function fina_ensure_liability_account(): int{
    $row=dbFetchOne("SELECT id,account_type FROM accounts WHERE code='2300' LIMIT 1");
    if($row){
        if($row['account_type']!=='liability')throw new RuntimeException('الحساب 2300 موجود لكنه ليس حساب التزام.');
        return (int)$row['id'];
    }
    dbExecute("INSERT INTO accounts (code,name_ar,name_en,account_type,is_active,description) VALUES ('2300','التزام مستحق لفينا الخير','Fina Al-Khair Payable','liability',1,'100% of standalone Fina Al-Khair collections belongs to Fina Al-Khair')");
    return (int)dbLastInsertId();
}

function fina_post_collection_journal(array $collection,int $reviewerId): int{
    $collectionId=(int)$collection['id'];
    $existing=dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='fina_collection' AND reference_id=? AND status='posted' LIMIT 1",[$collectionId]);
    if($existing){
        ak_transaction_review_notify_user(
            (int)($collection['created_by'] ?? 0),
            'تم اعتماد تحصيل فينا الخير',
            'تم اعتماد تحصيل فينا الخير رقم #'.$collectionId.' بمبلغ '.number_format((float)$collection['amount'],2).' '.($collection['currency_code'] ?? APP_CURRENCY_CODE).' وترحيله كالتزام مستقل بنسبة 100% لصالح فينا الخير.',
            APP_URL.'modules/accounting/fina_payment_review.php'
        );
        return (int)$existing['id'];
    }
    $liabilityId=fina_ensure_liability_account();
    $assetCode=ak_cash_code((string)$collection['payment_method']);
    $assetId=ak_account_id($assetCode);
    if($assetId<=0)throw new RuntimeException('الحساب المالي لطريقة الدفع غير موجود: '.$assetCode);
    $amount=round((float)$collection['amount'],2);
    if($amount<=0)throw new RuntimeException('مبلغ تحصيل فينا الخير غير صالح.');
    $currency=(string)$collection['currency_code'];
    $entryNo=(int)(dbFetchOne("SELECT COALESCE(MAX(CASE WHEN entry_code REGEXP '^JE-[0-9]+$' THEN CAST(SUBSTRING(entry_code,4) AS UNSIGNED) ELSE 0 END),0) n FROM journal_entries")['n']??0)+1;
    $entryCode='JE-'.str_pad((string)$entryNo,6,'0',STR_PAD_LEFT);
    $desc='تحصيل فينا الخير — 100% التزام لصالح فينا الخير — '.$currency;
    dbExecute("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,?,?,'posted',?)",[$entryCode,$collection['collection_date'],$desc,'fina_collection',$collectionId,$reviewerId]);
    $entryId=(int)dbLastInsertId();
    if($entryId<=0)throw new RuntimeException('تعذر إنشاء رأس القيد المحاسبي لفينا الخير.');
    dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",[$entryId,$assetId,$amount,0,'استلام أموال مخصصة لفينا الخير']);
    dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",[$entryId,$liabilityId,0,$amount,'التزام مستحق لفينا الخير']);
    ak_transaction_review_notify_user(
        (int)($collection['created_by'] ?? 0),
        'تم اعتماد تحصيل فينا الخير',
        'تم اعتماد تحصيل فينا الخير رقم #'.$collectionId.' بمبلغ '.number_format($amount,2).' '.$currency.' وترحيله كالتزام مستقل بنسبة 100% لصالح فينا الخير.',
        APP_URL.'modules/accounting/fina_payment_review.php'
    );
    return $entryId;
}
