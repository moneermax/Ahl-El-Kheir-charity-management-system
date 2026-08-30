<?php
$flashes = get_flashes();

foreach ($flashes as $flash) {
    $type = $flash['type'] ?? 'info';

    if ($type === 'error') {
        $type = 'danger';
    }

    if (!in_array($type, ['success', 'danger', 'warning', 'info'], true)) {
        $type = 'info';
    }
?>
    <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show" role="alert">
        <?php echo e($flash['message'] ?? ''); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php
}
?>