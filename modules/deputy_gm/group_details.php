<?php
/**
 * Dedicated VGM group workspace.
 *
 * The group list/create screen remains in groups.php. This route reuses the
 * existing, permission-checked group workflow and renders only the selected
 * group's information, caregiver controls and member management.
 */
$vgm_group_details_only = true;
require __DIR__ . '/groups.php';
