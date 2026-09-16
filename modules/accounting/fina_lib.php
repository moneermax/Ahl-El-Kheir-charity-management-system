<?php
// Shared standalone Fina Al-Khair data and accounting helpers.
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once __DIR__.'/lib.php';

function fina_ensure_tables(): void{
    dbExecute("CREATE TABLE IF NOT EXISTS fina_sources (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_type ENUM('sponsor','person','organization','other') NOT NULL DEFAULT 'person',
        sponsor_id INT UNSIGNED NULL,
        source_name VARCHAR(255) NULL,
        source_phone VARCHAR(100) NULL,
        source_alt_phone VARCHAR(100) NULL,
        source_email VARCHAR(255) NULL,
        source_address TEXT NULL,
        source_id_number VARCHAR(150) NULL,
        source_reference VARCHAR(150) NULL,
        contact_person VARCHAR(255) NULL,
        source_details TEXT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_fina_sources_sponsor (sponsor_id),
        KEY idx_fina_sources_type (source_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    dbExecute("CREATE TABLE IF NOT EXISTS fina_collections (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        fina_source_id INT UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        currency_code VARCHAR(10) NOT NULL,
        payment_method VARCHAR(32) NOT NULL,
        collection_date DATE NOT NULL,
        receipt_path VARCHAR(500) NULL,
        purpose_note TEXT NULL,
        description TEXT NULL,
        status ENUM('pending','approved','returned') NOT NULL DEFAULT 'pending',
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        return_note TEXT NULL,
        accounting_journal_id BIGINT UNSIGNED NULL,
        PRIMARY KEY (id),
        KEY idx_fina_collections_source (fina_source_id),
        KEY idx_fina_collections_status (status),
        KEY idx_fina_collections_date (collection_date),
        KEY idx_fina_collections_journal (accounting_journal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
    if($existing)return (int)$existing['id'];
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
    return $entryId;
}
