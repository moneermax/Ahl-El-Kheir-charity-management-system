<?php
/**
 * modules/accounting/group_disbursements.php
 *
 * DEPRECATED (retired): This page previously duplicated the group disbursement
 * workflow using broken queries (referenced a non-existent group_children.family_id
 * column) and bypassed financial manager visibility. All group disbursement logic
 * — batch creation, transfer, nanny confirmation, partial closure/return — now
 * lives exclusively in disbursements.php.
 *
 * This file is kept only as a redirect so any existing bookmarks, links, or
 * sidebar entries pointing here don't break.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
header('Location: ' . APP_URL . 'modules/accounting/disbursements.php');
exit();